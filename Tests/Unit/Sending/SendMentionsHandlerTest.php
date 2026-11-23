<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Sending;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webicorns\Pongback\Event\SendingFailedEvent;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\Protocol;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Messaging\MessageQueue;
use Webicorns\Pongback\Sending\DiscoveredEndpoint;
use Webicorns\Pongback\Sending\EndpointDiscovery;
use Webicorns\Pongback\Sending\LinkExtractor;
use Webicorns\Pongback\Sending\PageFetcher;
use Webicorns\Pongback\Sending\PingbackClient;
use Webicorns\Pongback\Sending\SendMentionsHandler;
use Webicorns\Pongback\Sending\SendPingbacksMessage;
use Webicorns\Pongback\Sending\SendResult;
use Webicorns\Pongback\Sending\SendStatus;
use Webicorns\Pongback\Sending\SentMentionLog;
use Webicorns\Pongback\Sending\SourcePageResolver;
use Webicorns\Pongback\Sending\WebmentionClient;
use Webicorns\Pongback\Tests\Unit\Fixtures\SetSettings;

final class SendMentionsHandlerTest extends TestCase
{
    private const PAGE_URL = 'https://main.example/blog/post';
    private const PAGE_HTML = '<main>'
        . '<a href="https://blog.example/article">external</a>'
        . '<a href="https://second.example/">external</a>'
        . '<a href="/contact">own page</a>'
        . '<a href="https://de.main.example/">own language</a>'
        . '<a href="https://other-site.example/">own other site</a>'
        . '</main>';

    private PageRepository&MockObject $pageRepository;
    private SiteFinder&MockObject $siteFinder;
    private PageFetcher&MockObject $pageFetcher;
    private EndpointDiscovery&MockObject $discovery;
    private PingbackClient&MockObject $client;
    private WebmentionClient&MockObject $webmentionClient;
    private SentMentionLog&MockObject $log;
    private MessageBusInterface&MockObject $bus;
    private LoggerInterface&MockObject $logger;
    private RouterInterface&MockObject $router;

    /**
     * Overrides of the defaults of the set (flat identifiers).
     *
     * @var array<string, mixed>
     */
    private array $settings = [];

    /**
     * @var array<string, mixed>
     */
    private array $page = ['uid' => 5, 'doktype' => 1];

    /**
     * @var list<object>
     */
    private array $events = [];

    /**
     * @var list<Envelope>
     */
    private array $dispatched = [];

    /**
     * @var list<array{int, string, string, SendResult}>
     */
    private array $recorded = [];

    protected function setUp(): void
    {
        $this->pageRepository = $this->createMock(PageRepository::class);
        $this->pageRepository->method('getPage')->willReturnCallback(fn(int $uid): array => $uid === 5 ? $this->page : []);

        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('generateUri')->willReturn(new Uri(self::PAGE_URL));

        $this->siteFinder = $this->createMock(SiteFinder::class);
        $this->siteFinder->method('getSiteByPageId')->willReturnCallback(
            fn(int $pageId): Site => $pageId === 5
                ? $this->site('https://main.example/', ['https://main.example/', 'https://de.main.example/'], $this->router)
                : throw new SiteNotFoundException('No site', 1732500300),
        );
        $this->siteFinder->method('getAllSites')->willReturnCallback(fn(): array => [
            $this->site('https://main.example/', ['https://main.example/', 'https://de.main.example/']),
            $this->site('https://other-site.example/', ['/']),
        ]);

        $this->pageFetcher = $this->createMock(PageFetcher::class);
        $this->pageFetcher->method('fetch')->willReturn(self::PAGE_HTML);

        $this->discovery = $this->createMock(EndpointDiscovery::class);
        $this->discovery->method('discover')->willReturnCallback(
            static fn(string $target): DiscoveredEndpoint => new DiscoveredEndpoint(Protocol::Pingback, (string) preg_replace('#^(https://[^/]+)/.*$#', '$1/xmlrpc.php', $target)),
        );
        $this->webmentionClient = $this->createMock(WebmentionClient::class);

        $this->client = $this->createMock(PingbackClient::class);
        $this->client->method('ping')->willReturn(new SendResult(SendStatus::Sent, 'Thanks'));

        $this->log = $this->createMock(SentMentionLog::class);
        $this->log->method('record')->willReturnCallback(function (int $pageId, string $source, string $target, SendResult $result): void {
            $this->recorded[] = [$pageId, $source, $target, $result];
        });

        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->bus->method('dispatch')->willReturnCallback(function (object $message, array $stamps = []): Envelope {
            $envelope = new Envelope($message, $stamps);
            $this->dispatched[] = $envelope;
            return $envelope;
        });

        $this->logger = $this->createMock(LoggerInterface::class);
    }

    #[Test]
    public function pingsEveryExternalLinkOfTheRenderedPageOnce(): void
    {
        $this->router->expects(self::once())->method('generateUri')
            ->with(5, ['_language' => 1], '', RouterInterface::ABSOLUTE_URL);
        $this->pageFetcher->expects(self::once())->method('fetch')->with(self::PAGE_URL);
        $pinged = [];
        $this->client->method('ping')->willReturnCallback(function (string $endpoint, string $source, string $target) use (&$pinged): SendResult {
            $pinged[] = [$endpoint, $source, $target];
            return new SendResult(SendStatus::Sent, 'Thanks');
        });

        $this->handle(new SendPingbacksMessage(5, 1));

        self::assertSame([
            ['https://blog.example/xmlrpc.php', self::PAGE_URL, 'https://blog.example/article'],
            ['https://second.example/xmlrpc.php', self::PAGE_URL, 'https://second.example/'],
        ], $pinged);
        self::assertSame([5, self::PAGE_URL, 'https://blog.example/article'], array_slice($this->recorded[0], 0, 3));
        self::assertSame(SendStatus::Sent, $this->recorded[0][3]->status);
        self::assertSame('https://blog.example/xmlrpc.php', $this->recorded[0][3]->endpoint, 'The endpoint is logged');
        self::assertCount(2, $this->recorded);
        self::assertSame([], $this->dispatched, 'Nothing to retry');
    }

    #[Test]
    public function skipsLinksThatWereHandledForGood(): void
    {
        $this->log->method('lastResult')->willReturnCallback(
            static fn(int $pageId, string $source, string $target): ?SendResult => $pageId === 5 && $source === self::PAGE_URL && $target === 'https://blog.example/article'
                ? new SendResult(SendStatus::Sent, protocol: Protocol::Pingback)
                : null,
        );

        $this->handle(new SendPingbacksMessage(5));

        self::assertSame(['https://second.example/'], array_column($this->recorded, 2));
    }

    #[Test]
    public function logsLinksWithoutEndpointWithoutPinging(): void
    {
        $this->discovery = $this->createMock(EndpointDiscovery::class);
        $this->discovery->method('discover')->willReturn(null);
        $this->client->expects(self::never())->method('ping');

        $this->handle(new SendPingbacksMessage(5));

        self::assertSame([SendStatus::NoEndpoint, SendStatus::NoEndpoint], array_map(static fn(array $entry): SendStatus => $entry[3]->status, $this->recorded));
    }

    /**
     * @return array<string, array{RejectionReason, SendStatus}>
     */
    public static function discoveryProblemProvider(): array
    {
        return [
            'server error is temporary' => [RejectionReason::UpstreamError, SendStatus::Failed],
            'unreachable is temporary' => [RejectionReason::SourceNotFound, SendStatus::Failed],
            'linked page gone is final' => [RejectionReason::SourceGone, SendStatus::Rejected],
            'internal address is final' => [RejectionReason::InvalidRequest, SendStatus::Rejected],
            'no html is final' => [RejectionReason::NoLinkToTarget, SendStatus::Rejected],
        ];
    }

    #[Test]
    #[DataProvider('discoveryProblemProvider')]
    public function logsProblemsDuringDiscovery(RejectionReason $reason, SendStatus $expected): void
    {
        $this->discovery = $this->createMock(EndpointDiscovery::class);
        $this->discovery->method('discover')->willThrowException(new MentionRejected($reason, 'Problem'));

        $this->handle(new SendPingbacksMessage(5));

        self::assertSame($expected, $this->recorded[0][3]->status);
        self::assertSame('Problem', $this->recorded[0][3]->message);
        self::assertCount($expected === SendStatus::Failed ? 1 : 0, $this->dispatched);
    }

    #[Test]
    public function retriesTemporaryFailuresLater(): void
    {
        $this->client = $this->createMock(PingbackClient::class);
        $this->client->method('ping')->willReturnOnConsecutiveCalls(
            new SendResult(SendStatus::Failed, 'Timeout'),
            new SendResult(SendStatus::Sent),
        );

        $this->handle(new SendPingbacksMessage(5, 1));

        self::assertCount(2, $this->recorded, 'The other link is still sent');
        $this->assertRetry(new SendPingbacksMessage(5, 1, 2), 300_000);
    }

    #[Test]
    public function waitsLongerBeforeTheLastAttempt(): void
    {
        $this->client = $this->createMock(PingbackClient::class);
        $this->client->method('ping')->willReturn(new SendResult(SendStatus::Failed));

        $this->handle(new SendPingbacksMessage(5, 0, 2));

        $this->assertRetry(new SendPingbacksMessage(5, 0, 3), 3_600_000);
    }

    #[Test]
    public function givesUpAfterTheLastAttempt(): void
    {
        $this->client = $this->createMock(PingbackClient::class);
        $this->client->method('ping')->willReturn(new SendResult(SendStatus::Failed));

        $this->handle(new SendPingbacksMessage(5, 0, MessageQueue::MAX_ATTEMPTS));

        self::assertCount(2, $this->recorded);
        self::assertSame([], $this->dispatched);
    }

    #[Test]
    public function retriesIfThePageCannotBeRetrieved(): void
    {
        $this->pageFetcher = $this->createMock(PageFetcher::class);
        $this->pageFetcher->method('fetch')->willThrowException(new \RuntimeException('HTTP 503', 1732500101));
        $this->logger->expects(self::once())->method('warning')->with('HTTP 503', ['pageId' => 5]);

        $this->handle(new SendPingbacksMessage(5));

        self::assertSame([], $this->recorded);
        $this->assertRetry(new SendPingbacksMessage(5, 0, 2), 300_000);
    }

    #[Test]
    public function doesNothingForPagesWithoutSite(): void
    {
        $this->pageFetcher->expects(self::never())->method('fetch');

        $this->handle(new SendPingbacksMessage(99));

        self::assertSame([], $this->recorded);
    }

    #[Test]
    public function doesNothingIfSendingIsDisabledForTheSite(): void
    {
        $this->settings['pongback.send.enabled'] = false;
        $this->pageFetcher->expects(self::never())->method('fetch');

        $this->handle(new SendPingbacksMessage(5));
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function pageProvider(): array
    {
        return [
            'standard page' => [['uid' => 5, 'doktype' => 1], true],
            'last visible doktype' => [['uid' => 5, 'doktype' => PageRepository::DOKTYPE_SPACER - 1], true],
            'spacer' => [['uid' => 5, 'doktype' => PageRepository::DOKTYPE_SPACER], false],
            'folder' => [['uid' => 5, 'doktype' => PageRepository::DOKTYPE_SYSFOLDER], false],
            'hidden, deleted or not existing' => [[], false],
        ];
    }

    /**
     * @param array<string, mixed> $page
     */
    #[Test]
    #[DataProvider('pageProvider')]
    public function onlySendsForPagesVisitorsCanSee(array $page, bool $expected): void
    {
        $this->page = $page;
        $this->pageFetcher->expects($expected ? self::once() : self::never())->method('fetch');

        $this->handle(new SendPingbacksMessage(5));
    }

    #[Test]
    public function doesNothingIfNoUrlCanBeGenerated(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('generateUri')->willThrowException(new \InvalidArgumentException('Language disabled', 1732500301));
        $this->pageFetcher->expects(self::never())->method('fetch');
        $this->logger->expects(self::once())->method('info')->with('No URL for page, no Pingbacks sent', [
            'pageId' => 5,
            'languageId' => 2,
            'reason' => 'Language disabled',
        ]);

        $this->handle(new SendPingbacksMessage(5, 2));
    }

    #[Test]
    public function prefersWebmention(): void
    {
        $this->discovery = $this->createMock(EndpointDiscovery::class);
        $this->discovery->expects(self::exactly(2))->method('discover')
            ->with(self::anything(), true)
            ->willReturn(new DiscoveredEndpoint(Protocol::Webmention, 'https://blog.example/webmention'));
        $this->client->expects(self::never())->method('ping');
        $this->webmentionClient->expects(self::exactly(2))->method('send')
            ->with('https://blog.example/webmention', self::PAGE_URL, self::anything())
            ->willReturn(new SendResult(SendStatus::Sent, 'HTTP 202'));

        $this->handle(new SendPingbacksMessage(5));

        self::assertSame([Protocol::Webmention, Protocol::Webmention], array_map(static fn(array $entry): ?Protocol => $entry[3]->protocol, $this->recorded));
        self::assertSame('https://blog.example/webmention', $this->recorded[0][3]->endpoint);
    }

    #[Test]
    public function sendsOnlyPingbacksIfWebmentionIsSwitchedOff(): void
    {
        $this->settings['pongback.send.webmention'] = false;
        $this->discovery = $this->createMock(EndpointDiscovery::class);
        $this->discovery->expects(self::exactly(2))->method('discover')->with(self::anything(), false)->willReturn(null);
        $this->log->expects(self::never())->method('webmentionTargets');

        $this->handle(new SendPingbacksMessage(5));
    }

    /**
     * @return array<string, array{string, int, bool}>
     */
    public static function earlierResultProvider(): array
    {
        return [
            'webmention, page changed: again' => ['webmention', 1, true],
            'webmention, retry: not again' => ['webmention', 2, false],
            'pingback: once only' => ['pingback', 1, false],
        ];
    }

    #[Test]
    #[DataProvider('earlierResultProvider')]
    public function sendsWebmentionsAgainWhenThePageChanged(string $protocol, int $attempt, bool $expected): void
    {
        $this->log->method('lastResult')->willReturnCallback(
            static fn(int $pageId, string $source, string $target): SendResult => $target === 'https://blog.example/article'
                ? new SendResult(SendStatus::Sent, protocol: Protocol::from($protocol))
                : new SendResult(SendStatus::Failed, protocol: Protocol::from($protocol)),
        );

        $this->handle(new SendPingbacksMessage(5, 0, $attempt));

        $expectedTargets = $expected ? ['https://blog.example/article', 'https://second.example/'] : ['https://second.example/'];
        self::assertSame($expectedTargets, array_column($this->recorded, 2));
    }

    #[Test]
    public function tellsRemovedTargetsOnceMore(): void
    {
        $this->log->method('webmentionTargets')->with(5, self::PAGE_URL)->willReturn(['https://blog.example/article', 'https://removed.example/post', 'https://removed2.example/post']);
        $this->discovery = $this->createMock(EndpointDiscovery::class);
        $this->discovery->method('discover')->willReturnCallback(
            static fn(string $target): DiscoveredEndpoint => new DiscoveredEndpoint(Protocol::Webmention, 'https://' . parse_url($target, PHP_URL_HOST) . '/webmention'),
        );
        $sent = [];
        $this->webmentionClient->method('send')->willReturnCallback(function (string $endpoint, string $source, string $target) use (&$sent): SendResult {
            $sent[] = $target;
            return new SendResult(SendStatus::Rejected, 'HTTP 400 no link');
        });
        $forgotten = [];
        $this->log->expects(self::exactly(2))->method('forget')->willReturnCallback(function (int $pageId, string $source, string $target) use (&$forgotten): void {
            $forgotten[] = $target;
        });

        $this->handle(new SendPingbacksMessage(5));

        self::assertSame(['https://blog.example/article', 'https://second.example/', 'https://removed.example/post', 'https://removed2.example/post'], $sent);
        self::assertSame(['https://removed.example/post', 'https://removed2.example/post'], $forgotten);
        self::assertNotContains('https://removed.example/post', array_column($this->recorded, 2), 'Forgotten, not logged');
    }

    #[Test]
    public function retriesRemovedTargetsOnTemporaryProblems(): void
    {
        $this->log->method('webmentionTargets')->willReturn(['https://removed.example/post']);
        $this->discovery = $this->createMock(EndpointDiscovery::class);
        $this->discovery->method('discover')->willReturn(new DiscoveredEndpoint(Protocol::Webmention, 'https://removed.example/webmention'));
        $this->webmentionClient->method('send')->willReturnCallback(
            static fn(string $endpoint, string $source, string $target): SendResult => $target === 'https://removed.example/post'
                ? new SendResult(SendStatus::Failed, 'HTTP 503')
                : new SendResult(SendStatus::Sent),
        );
        $this->log->expects(self::never())->method('forget');

        $this->handle(new SendPingbacksMessage(5));

        self::assertContains('https://removed.example/post', array_column($this->recorded, 2));
        $this->assertRetry(new SendPingbacksMessage(5, 0, 2), 300_000);
    }

    #[Test]
    public function doesNotPingRemovedTargetsThatOnlyOfferPingback(): void
    {
        $this->log->method('webmentionTargets')->willReturn(['https://removed.example/post']);
        $this->client->expects(self::exactly(2))->method('ping')->willReturn(new SendResult(SendStatus::Sent));
        $this->log->expects(self::once())->method('forget')->with(5, self::PAGE_URL, 'https://removed.example/post');

        $this->handle(new SendPingbacksMessage(5));
    }

    #[Test]
    public function reportsNewRejections(): void
    {
        $this->client = $this->createMock(PingbackClient::class);
        $this->client->method('ping')->willReturnCallback(
            static fn(string $endpoint, string $source, string $target): SendResult => $target === 'https://blog.example/article'
                ? new SendResult(SendStatus::Rejected, 'No link', 17)
                : new SendResult(SendStatus::Sent),
        );

        $this->handle(new SendPingbacksMessage(5));

        self::assertCount(1, $this->events);
        $event = $this->events[0];
        self::assertInstanceOf(SendingFailedEvent::class, $event);
        self::assertSame(5, $event->pageId);
        self::assertSame(self::PAGE_URL, $event->pageUrl);
        self::assertSame(['https://blog.example/article'], array_keys($event->problems));
        self::assertSame('No link', $event->problems['https://blog.example/article']->message);
        self::assertSame('https://blog.example/xmlrpc.php', $event->problems['https://blog.example/article']->endpoint);
    }

    #[Test]
    public function doesNotReportTheSameRejectionAgain(): void
    {
        $this->webmentionClient->method('send')->willReturn(new SendResult(SendStatus::Rejected, 'HTTP 400'));
        $this->discovery = $this->createMock(EndpointDiscovery::class);
        $this->discovery->method('discover')->willReturn(new DiscoveredEndpoint(Protocol::Webmention, 'https://blog.example/webmention'));
        $this->log->method('lastResult')->willReturnCallback(
            static fn(int $pageId, string $source, string $target): SendResult => $target === 'https://blog.example/article'
                ? new SendResult(SendStatus::Rejected, protocol: Protocol::Webmention)
                : new SendResult(SendStatus::Sent, protocol: Protocol::Webmention),
        );

        $this->handle(new SendPingbacksMessage(5));

        self::assertCount(2, $this->recorded, 'Both Webmentions are sent again');
        self::assertInstanceOf(SendingFailedEvent::class, $this->events[0] ?? null);
        self::assertSame(['https://second.example/'], array_keys($this->events[0]->problems), 'Only the new rejection');
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function failureAttemptProvider(): array
    {
        return [
            'first attempt: retried, not reported' => [1, false],
            'second attempt: retried, not reported' => [2, false],
            'last attempt: reported' => [MessageQueue::MAX_ATTEMPTS, true],
        ];
    }

    #[Test]
    #[DataProvider('failureAttemptProvider')]
    public function reportsFailuresAfterTheLastAttempt(int $attempt, bool $expected): void
    {
        $this->client = $this->createMock(PingbackClient::class);
        $this->client->method('ping')->willReturn(new SendResult(SendStatus::Failed, 'Timeout'));

        $this->handle(new SendPingbacksMessage(5, 0, $attempt));

        self::assertCount($expected ? 1 : 0, $this->events);
    }

    #[Test]
    public function reportsNothingIfAllWent(): void
    {
        $this->handle(new SendPingbacksMessage(5));

        self::assertSame([], $this->events);
    }

    #[Test]
    public function doesNotReportRemovedTargets(): void
    {
        $this->log->method('webmentionTargets')->willReturn(['https://removed.example/post']);
        $this->discovery = $this->createMock(EndpointDiscovery::class);
        $this->discovery->method('discover')->willReturnCallback(
            static fn(string $target): DiscoveredEndpoint => new DiscoveredEndpoint(Protocol::Webmention, 'https://' . parse_url($target, PHP_URL_HOST) . '/webmention'),
        );
        $this->webmentionClient->method('send')->willReturnCallback(
            static fn(string $endpoint, string $source, string $target): SendResult => $target === 'https://removed.example/post'
                ? new SendResult(SendStatus::Rejected, 'HTTP 400 no link')
                : new SendResult(SendStatus::Sent),
        );

        $this->handle(new SendPingbacksMessage(5));

        self::assertSame([], $this->events);
    }

    private function handle(SendPingbacksMessage $message): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(function (object $event): object {
            $this->events[] = $event;
            return $event;
        });

        $handler = new SendMentionsHandler(
            new SourcePageResolver($this->pageRepository, $this->siteFinder, $this->logger),
            $this->pageFetcher,
            new LinkExtractor(),
            $this->discovery,
            $this->client,
            $this->webmentionClient,
            $this->log,
            new MessageQueue($this->bus),
            $eventDispatcher,
            $this->logger,
        );

        $handler($message);
    }

    private function assertRetry(SendPingbacksMessage $expected, int $delayInMilliseconds): void
    {
        self::assertCount(1, $this->dispatched);
        $envelope = $this->dispatched[0];
        self::assertEquals($expected, $envelope->getMessage());
        self::assertSame(['doctrine'], $envelope->last(TransportNamesStamp::class)?->getTransportNames());
        $stamp = $envelope->last(DelayStamp::class);
        self::assertInstanceOf(DelayStamp::class, $stamp);
        self::assertSame($delayInMilliseconds, $stamp->getDelay());
    }

    /**
     * @param list<string> $languageBases
     */
    private function site(string $base, array $languageBases, ?RouterInterface $router = null): Site
    {
        $site = $this->createMock(Site::class);
        $site->method('getBase')->willReturn(new Uri($base));
        $site->method('getAllLanguages')->willReturn(array_map(
            static fn(string $languageBase, int $languageId): SiteLanguage => new SiteLanguage($languageId, 'en_US.UTF-8', new Uri($languageBase), []),
            $languageBases,
            array_keys($languageBases),
        ));
        $site->method('getSettings')->willReturn(SetSettings::create($this->settings));
        if ($router !== null) {
            $site->method('getRouter')->willReturn($router);
        }

        return $site;
    }
}
