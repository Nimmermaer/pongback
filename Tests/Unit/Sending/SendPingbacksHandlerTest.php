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
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Settings\Settings;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webicorns\Pongback\Configuration\PongbackSettings;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Messaging\MessageQueue;
use Webicorns\Pongback\Sending\EndpointDiscovery;
use Webicorns\Pongback\Sending\LinkExtractor;
use Webicorns\Pongback\Sending\PageFetcher;
use Webicorns\Pongback\Sending\PingbackClient;
use Webicorns\Pongback\Sending\PingResult;
use Webicorns\Pongback\Sending\PingStatus;
use Webicorns\Pongback\Sending\SendPingbacksHandler;
use Webicorns\Pongback\Sending\SendPingbacksMessage;
use Webicorns\Pongback\Sending\SentPingbackLog;

final class SendPingbacksHandlerTest extends TestCase
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
    private SentPingbackLog&MockObject $log;
    private MessageBusInterface&MockObject $bus;
    private LoggerInterface&MockObject $logger;
    private RouterInterface&MockObject $router;

    /**
     * Flat site settings, as provided by the set.
     *
     * @var array<string, mixed>
     */
    private array $settings = PongbackSettings::DEFAULTS;

    /**
     * @var array<string, mixed>
     */
    private array $page = ['uid' => 5, 'doktype' => 1];

    /**
     * @var list<Envelope>
     */
    private array $dispatched = [];

    /**
     * @var list<array{int, string, string, PingResult}>
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
            static fn(string $target): string => preg_replace('#^(https://[^/]+)/.*$#', '$1/xmlrpc.php', $target),
        );

        $this->client = $this->createMock(PingbackClient::class);
        $this->client->method('ping')->willReturn(new PingResult(PingStatus::Sent, 'Thanks'));

        $this->log = $this->createMock(SentPingbackLog::class);
        $this->log->method('record')->willReturnCallback(function (int $pageId, string $source, string $target, PingResult $result): void {
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
        $this->client->method('ping')->willReturnCallback(function (string $endpoint, string $source, string $target) use (&$pinged): PingResult {
            $pinged[] = [$endpoint, $source, $target];
            return new PingResult(PingStatus::Sent, 'Thanks');
        });

        $this->handle(new SendPingbacksMessage(5, 1));

        self::assertSame([
            ['https://blog.example/xmlrpc.php', self::PAGE_URL, 'https://blog.example/article'],
            ['https://second.example/xmlrpc.php', self::PAGE_URL, 'https://second.example/'],
        ], $pinged);
        self::assertSame([5, self::PAGE_URL, 'https://blog.example/article'], array_slice($this->recorded[0], 0, 3));
        self::assertSame(PingStatus::Sent, $this->recorded[0][3]->status);
        self::assertSame('https://blog.example/xmlrpc.php', $this->recorded[0][3]->endpoint, 'The endpoint is logged');
        self::assertCount(2, $this->recorded);
        self::assertSame([], $this->dispatched, 'Nothing to retry');
    }

    #[Test]
    public function skipsLinksThatWereHandledForGood(): void
    {
        $this->log->method('isFinal')->willReturnCallback(
            static fn(int $pageId, string $source, string $target): bool => $pageId === 5 && $source === self::PAGE_URL && $target === 'https://blog.example/article',
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

        self::assertSame([PingStatus::NoEndpoint, PingStatus::NoEndpoint], array_map(static fn(array $entry): PingStatus => $entry[3]->status, $this->recorded));
    }

    /**
     * @return array<string, array{RejectionReason, PingStatus}>
     */
    public static function discoveryProblemProvider(): array
    {
        return [
            'server error is temporary' => [RejectionReason::UpstreamError, PingStatus::Failed],
            'unreachable is temporary' => [RejectionReason::SourceNotFound, PingStatus::Failed],
            'linked page gone is final' => [RejectionReason::SourceGone, PingStatus::Rejected],
            'internal address is final' => [RejectionReason::InvalidRequest, PingStatus::Rejected],
            'no html is final' => [RejectionReason::NoLinkToTarget, PingStatus::Rejected],
        ];
    }

    #[Test]
    #[DataProvider('discoveryProblemProvider')]
    public function logsProblemsDuringDiscovery(RejectionReason $reason, PingStatus $expected): void
    {
        $this->discovery = $this->createMock(EndpointDiscovery::class);
        $this->discovery->method('discover')->willThrowException(new MentionRejected($reason, 'Problem'));

        $this->handle(new SendPingbacksMessage(5));

        self::assertSame($expected, $this->recorded[0][3]->status);
        self::assertSame('Problem', $this->recorded[0][3]->message);
        self::assertCount($expected === PingStatus::Failed ? 1 : 0, $this->dispatched);
    }

    #[Test]
    public function retriesTemporaryFailuresLater(): void
    {
        $this->client = $this->createMock(PingbackClient::class);
        $this->client->method('ping')->willReturnOnConsecutiveCalls(
            new PingResult(PingStatus::Failed, 'Timeout'),
            new PingResult(PingStatus::Sent),
        );

        $this->handle(new SendPingbacksMessage(5, 1));

        self::assertCount(2, $this->recorded, 'The other link is still sent');
        $this->assertRetry(new SendPingbacksMessage(5, 1, 2), 300_000);
    }

    #[Test]
    public function waitsLongerBeforeTheLastAttempt(): void
    {
        $this->client = $this->createMock(PingbackClient::class);
        $this->client->method('ping')->willReturn(new PingResult(PingStatus::Failed));

        $this->handle(new SendPingbacksMessage(5, 0, 2));

        $this->assertRetry(new SendPingbacksMessage(5, 0, 3), 3_600_000);
    }

    #[Test]
    public function givesUpAfterTheLastAttempt(): void
    {
        $this->client = $this->createMock(PingbackClient::class);
        $this->client->method('ping')->willReturn(new PingResult(PingStatus::Failed));

        $this->handle(new SendPingbacksMessage(5, 0, SendPingbacksHandler::MAX_ATTEMPTS));

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

    private function handle(SendPingbacksMessage $message): void
    {
        $handler = new SendPingbacksHandler(
            $this->pageRepository,
            $this->siteFinder,
            $this->pageFetcher,
            new LinkExtractor(),
            $this->discovery,
            $this->client,
            $this->log,
            new MessageQueue($this->bus),
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
        $site->method('getSettings')->willReturn(SiteSettings::create(new Settings($this->settings)));
        if ($router !== null) {
            $site->method('getRouter')->willReturn($router);
        }

        return $site;
    }
}
