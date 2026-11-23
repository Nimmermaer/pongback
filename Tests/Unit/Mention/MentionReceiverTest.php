<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Mention;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Site\Entity\Site;
use Webicorns\Pongback\Domain\Model\Pingback;
use Webicorns\Pongback\Event\AfterPingbackCreatedEvent;
use Webicorns\Pongback\Event\BeforePingbackCreateEvent;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Mention\MentionReceiver;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\MentionStorage;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Mention\ResolvedTarget;
use Webicorns\Pongback\Mention\Source\FetchedSource;
use Webicorns\Pongback\Mention\Source\SourceFetcher;
use Webicorns\Pongback\Mention\Source\SourceInspector;
use Webicorns\Pongback\Mention\SyncResult;
use Webicorns\Pongback\Mention\TargetResolver;
use Webicorns\Pongback\Tests\Unit\Fixtures\AssertRejectionTrait;
use Webicorns\Pongback\Tests\Unit\Fixtures\SetSettings;

final class MentionReceiverTest extends TestCase
{
    use AssertRejectionTrait;

    private const SOURCE = 'https://blog.example/post';
    private const TARGET = 'https://www.target.example/article';

    private TargetResolver&MockObject $targetResolver;
    private SourceFetcher&MockObject $sourceFetcher;
    private MentionStorage&MockObject $storage;

    /**
     * @var list<BeforePingbackCreateEvent>
     */
    private array $dispatchedEvents = [];

    /**
     * @var list<AfterPingbackCreatedEvent>
     */
    private array $createdEvents = [];

    /**
     * @var (\Closure(BeforePingbackCreateEvent): void)|null
     */
    private ?\Closure $listener = null;

    protected function setUp(): void
    {
        $this->targetResolver = $this->createMock(TargetResolver::class);
        $this->sourceFetcher = $this->createMock(SourceFetcher::class);
        $this->storage = $this->createMock(MentionStorage::class);
    }

    #[Test]
    public function storesVerifiedMentionsHiddenByDefault(): void
    {
        $site = $this->createSite([]);
        $this->targetResolver->method('resolve')->with(self::TARGET, $site)->willReturn(new ResolvedTarget($site, 42, 1));
        $this->sourceFetcher->method('fetch')->with(self::SOURCE)->willReturn(new FetchedSource(
            self::SOURCE,
            '<title>Source title</title><p>Read <a href="' . self::TARGET . '">this</a>!</p>',
        ));

        $stored = null;
        $this->storage->method('exists')->willReturn(false);
        $this->storage->expects(self::once())->method('add')->willReturnCallback(
            static function (Pingback $pingback, int $languageId) use (&$stored): int {
                $stored = [$pingback, $languageId];
                return 7;
            },
        );

        self::assertSame(7, $this->createSubject()->receive($this->mention(), $site));

        [$pingback, $languageId] = $stored;
        self::assertSame(1, $languageId);
        self::assertSame(42, $pingback->getPid());
        self::assertTrue($pingback->isHidden());
        self::assertSame(self::SOURCE, $pingback->getSourceLink());
        self::assertSame(self::TARGET, $pingback->getTargetLink());
        self::assertSame('Source title', $pingback->getSourceTitle());
        self::assertSame('Read this!', $pingback->getExcerpt());
        self::assertCount(1, $this->dispatchedEvents);
    }

    #[Test]
    public function publishesImmediatelyIfConfiguredForTheSite(): void
    {
        $site = $this->createSite(['pongback.receive.autoPublish' => true]);
        $this->prepareValidMention($site);

        $this->storage->expects(self::once())->method('add')
            ->with(self::callback(static fn(Pingback $pingback): bool => ! $pingback->isHidden()))
            ->willReturn(1);

        $this->createSubject()->receive($this->mention(), $site);
    }

    #[Test]
    public function rejectsDuplicatesWithoutFetchingTheSource(): void
    {
        $site = $this->createSite([]);
        $this->targetResolver->method('resolve')->willReturn(new ResolvedTarget($site, 42, 0));
        $this->storage->method('exists')->with(self::SOURCE, self::TARGET)->willReturn(true);
        $this->sourceFetcher->expects(self::never())->method('fetch');

        self::assertRejected(RejectionReason::AlreadyRegistered, fn() => $this->createSubject()->receive($this->mention(), $site));
    }

    #[Test]
    public function rejectsSourcesWithoutLinkToTheTarget(): void
    {
        $site = $this->createSite([]);
        $this->targetResolver->method('resolve')->willReturn(new ResolvedTarget($site, 42, 0));
        $this->sourceFetcher->method('fetch')->willReturn(new FetchedSource(self::SOURCE, '<p>No link</p>'));
        $this->storage->expects(self::never())->method('add');

        self::assertRejected(RejectionReason::NoLinkToTarget, fn() => $this->createSubject()->receive($this->mention(), $site));
    }

    #[Test]
    public function rejectsIdenticalSourceAndTarget(): void
    {
        $this->targetResolver->expects(self::never())->method('resolve');

        self::assertRejected(
            RejectionReason::InvalidRequest,
            fn() => $this->createSubject()->receive(
                new IncomingMention(self::TARGET . '/', self::TARGET, IncomingMention::PROTOCOL_PINGBACK),
                $this->createSite([]),
            ),
        );
    }

    #[Test]
    public function listenersCanRejectMentions(): void
    {
        $site = $this->createSite([]);
        $this->prepareValidMention($site);
        $this->listener = static fn(BeforePingbackCreateEvent $event) => $event->reject(RejectionReason::AccessDenied, 'Spam');
        $this->storage->expects(self::never())->method('add');

        self::assertRejected(RejectionReason::AccessDenied, fn() => $this->createSubject()->receive($this->mention(), $site));
    }

    #[Test]
    public function listenersCanModifyTheRecord(): void
    {
        $site = $this->createSite(['pongback.receive.autoPublish' => true]);
        $this->prepareValidMention($site);
        $this->listener = static fn(BeforePingbackCreateEvent $event) => $event->getPingback()->setHidden(true);

        $this->storage->expects(self::once())->method('add')
            ->with(self::callback(static fn(Pingback $pingback): bool => $pingback->isHidden()))
            ->willReturn(1);

        $this->createSubject()->receive($this->mention(), $site);
    }

    #[Test]
    public function synchronizeCreatesUnknownWebmentions(): void
    {
        $site = $this->createSite([]);
        $this->prepareValidMention($site);
        $this->storage->method('find')->with(self::SOURCE, self::TARGET)->willReturn(null);
        $this->storage->expects(self::once())->method('add')
            ->with(self::callback(static fn(Pingback $pingback): bool => $pingback->getProtocol() === IncomingMention::PROTOCOL_WEBMENTION))
            ->willReturn(3);

        self::assertSame(SyncResult::Created, $this->createSubject()->synchronize($this->webmention(), $site));
        self::assertCount(1, $this->dispatchedEvents, 'Listeners can reject new Webmentions as well');
        self::assertCount(1, $this->createdEvents);
        self::assertSame(3, $this->createdEvents[0]->uid);
        self::assertSame(IncomingMention::PROTOCOL_WEBMENTION, $this->createdEvents[0]->pingback->getProtocol());
    }

    #[Test]
    public function synchronizeUpdatesKnownMentions(): void
    {
        $site = $this->createSite([]);
        $this->targetResolver->method('resolve')->willReturn(new ResolvedTarget($site, 42, 0));
        $this->sourceFetcher->method('fetch')->willReturn(new FetchedSource(
            self::SOURCE,
            '<title>New title</title><p>Updated: <a href="' . self::TARGET . '">link</a></p>',
        ));
        $this->storage->method('find')->willReturn(5);
        $this->storage->expects(self::once())->method('update')->with(5, 'New title', 'Updated: link');
        $this->storage->expects(self::never())->method('add');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Mention updated', self::anything());

        self::assertSame(SyncResult::Updated, $this->createSubject($logger)->synchronize($this->webmention(), $site));
        self::assertSame([], $this->dispatchedEvents);
    }

    /**
     * @return array<string, array{FetchedSource|MentionRejected}>
     */
    public static function goneSourceProvider(): array
    {
        return [
            'deleted (404/410)' => [new MentionRejected(RejectionReason::SourceGone, 'Gone')],
            'link removed' => [new FetchedSource(self::SOURCE, '<p>No link any more</p>')],
        ];
    }

    #[Test]
    #[DataProvider('goneSourceProvider')]
    public function synchronizeRemovesKnownMentionsWhoseSourceNoLongerLinks(FetchedSource|MentionRejected $source): void
    {
        $site = $this->createSite([]);
        $this->targetResolver->method('resolve')->willReturn(new ResolvedTarget($site, 42, 0));
        $source instanceof MentionRejected
            ? $this->sourceFetcher->method('fetch')->willThrowException($source)
            : $this->sourceFetcher->method('fetch')->willReturn($source);
        $this->storage->method('find')->willReturn(5);
        $this->storage->expects(self::once())->method('remove')->with(5);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Mention removed', [
            'protocol' => IncomingMention::PROTOCOL_WEBMENTION,
            'source' => self::SOURCE,
            'target' => self::TARGET,
            'uid' => 5,
        ]);

        self::assertSame(SyncResult::Removed, $this->createSubject($logger)->synchronize($this->webmention(), $site));
    }

    #[Test]
    public function synchronizeRejectsUnknownMentionsWithoutLink(): void
    {
        $site = $this->createSite([]);
        $this->targetResolver->method('resolve')->willReturn(new ResolvedTarget($site, 42, 0));
        $this->sourceFetcher->method('fetch')->willReturn(new FetchedSource(self::SOURCE, '<p>No link</p>'));
        $this->storage->method('find')->willReturn(null);
        $this->storage->expects(self::never())->method('remove');

        self::assertRejected(RejectionReason::NoLinkToTarget, fn() => $this->createSubject()->synchronize($this->webmention(), $site));
    }

    #[Test]
    public function synchronizeKeepsKnownMentionsOnTemporaryProblems(): void
    {
        $site = $this->createSite([]);
        $this->targetResolver->method('resolve')->willReturn(new ResolvedTarget($site, 42, 0));
        $this->sourceFetcher->method('fetch')->willThrowException(new MentionRejected(RejectionReason::UpstreamError, 'HTTP 503'));
        $this->storage->method('find')->willReturn(5);
        $this->storage->expects(self::never())->method('remove');
        $this->storage->expects(self::never())->method('update');

        self::assertRejected(RejectionReason::UpstreamError, fn() => $this->createSubject()->synchronize($this->webmention(), $site));
    }

    #[Test]
    public function synchronizeChecksSourceAndTargetFirst(): void
    {
        $site = $this->createSite([]);
        $this->sourceFetcher->expects(self::never())->method('fetch');

        self::assertRejected(RejectionReason::InvalidRequest, fn() => $this->createSubject()->synchronize(
            new IncomingMention(self::TARGET, self::TARGET, IncomingMention::PROTOCOL_WEBMENTION),
            $site,
        ));
    }

    private function webmention(): IncomingMention
    {
        return new IncomingMention(self::SOURCE, self::TARGET, IncomingMention::PROTOCOL_WEBMENTION, '203.0.113.5');
    }

    private function prepareValidMention(Site $site): void
    {
        $this->targetResolver->method('resolve')->willReturn(new ResolvedTarget($site, 42, 0));
        $this->sourceFetcher->method('fetch')->willReturn(new FetchedSource(self::SOURCE, '<p><a href="' . self::TARGET . '">link</a></p>'));
        $this->storage->method('exists')->willReturn(false);
    }

    private function createSubject(?LoggerInterface $logger = null): MentionReceiver
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(function (object $event): object {
            if ($event instanceof AfterPingbackCreatedEvent) {
                $this->createdEvents[] = $event;
            }
            if ($event instanceof BeforePingbackCreateEvent) {
                $this->dispatchedEvents[] = $event;
                if ($this->listener !== null) {
                    ($this->listener)($event);
                }
            }

            return $event;
        });

        return new MentionReceiver(
            $this->targetResolver,
            $this->sourceFetcher,
            new SourceInspector(),
            $this->storage,
            $eventDispatcher,
            $logger ?? new NullLogger(),
        );
    }

    private function mention(): IncomingMention
    {
        return new IncomingMention(self::SOURCE, self::TARGET, IncomingMention::PROTOCOL_PINGBACK, '203.0.113.5');
    }

    /**
     * @param array<string, mixed> $overrides flat setting identifiers
     */
    private function createSite(array $overrides): Site&MockObject
    {
        $site = $this->createMock(Site::class);
        $site->method('getIdentifier')->willReturn('main');
        $site->method('getSettings')->willReturn(SetSettings::create($overrides));

        return $site;
    }

    #[Test]
    public function storesTheTargetPageAndLogsTheRegistration(): void
    {
        $site = $this->createSite([]);
        $this->prepareValidMention($site);
        $this->storage->method('add')
            ->with(self::callback(static fn(Pingback $pingback): bool => $pingback->getPid() === 42))
            ->willReturn(9);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Mention registered', [
            'protocol' => IncomingMention::PROTOCOL_PINGBACK,
            'source' => self::SOURCE,
            'target' => self::TARGET,
            'uid' => 9,
        ]);

        self::assertSame(9, $this->createSubject($logger)->receive($this->mention(), $site));
    }

    #[Test]
    public function passesTheReasonOfRejectingListenersOn(): void
    {
        $site = $this->createSite([]);
        $this->prepareValidMention($site);
        $this->listener = static fn(BeforePingbackCreateEvent $event) => $event->reject(RejectionReason::TargetNotAllowed, 'Closed for comments');

        try {
            $this->createSubject()->receive($this->mention(), $site);
            self::fail('The mention must be rejected');
        } catch (MentionRejected $rejected) {
            self::assertSame(RejectionReason::TargetNotAllowed, $rejected->reason);
            self::assertSame('Closed for comments', $rejected->getMessage());
        }
    }

    #[Test]
    public function inspectsTheHtmlOfTheFinalUrlAfterRedirects(): void
    {
        $site = $this->createSite([]);
        $this->targetResolver->method('resolve')->willReturn(new ResolvedTarget($site, 42, 0));
        $this->storage->method('exists')->willReturn(false);
        // The relative link only points to the target when resolved against the final URL.
        $this->sourceFetcher->method('fetch')->willReturn(new FetchedSource('https://www.target.example/blog/answer', '<p><a href="/article">relative</a></p>'));
        $this->storage->expects(self::once())->method('add')->willReturn(1);

        $this->createSubject()->receive($this->mention(), $site);
    }
}
