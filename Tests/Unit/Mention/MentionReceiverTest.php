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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use Webicorns\Pongback\Domain\Model\Pingback;
use Webicorns\Pongback\Event\BeforePingbackCreateEvent;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Mention\MentionReceiver;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\MentionStorage;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Mention\ResolvedTarget;
use Webicorns\Pongback\Mention\Source\SourceFetcher;
use Webicorns\Pongback\Mention\Source\SourceInspector;
use Webicorns\Pongback\Mention\TargetResolver;
use Webicorns\Pongback\Tests\Unit\Fixtures\AssertRejectionTrait;

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
        $this->sourceFetcher->method('fetch')->with(self::SOURCE)->willReturn([
            'url' => self::SOURCE,
            'html' => '<title>Source title</title><p>Read <a href="' . self::TARGET . '">this</a>!</p>',
        ]);

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
        self::assertSame('Source title', $pingback->getPingClient());
        self::assertSame('Read this!', $pingback->getSerializedInformation());
        self::assertCount(1, $this->dispatchedEvents);
    }

    #[Test]
    public function publishesImmediatelyIfConfiguredForTheSite(): void
    {
        $site = $this->createSite(['pongback' => ['receive' => ['autoPublish' => true]]]);
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
        $this->sourceFetcher->method('fetch')->willReturn(['url' => self::SOURCE, 'html' => '<p>No link</p>']);
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
        $site = $this->createSite(['pongback' => ['receive' => ['autoPublish' => true]]]);
        $this->prepareValidMention($site);
        $this->listener = static fn(BeforePingbackCreateEvent $event) => $event->getPingback()->setHidden(true);

        $this->storage->expects(self::once())->method('add')
            ->with(self::callback(static fn(Pingback $pingback): bool => $pingback->isHidden()))
            ->willReturn(1);

        $this->createSubject()->receive($this->mention(), $site);
    }

    private function prepareValidMention(Site $site): void
    {
        $this->targetResolver->method('resolve')->willReturn(new ResolvedTarget($site, 42, 0));
        $this->sourceFetcher->method('fetch')->willReturn([
            'url' => self::SOURCE,
            'html' => '<p><a href="' . self::TARGET . '">link</a></p>',
        ]);
        $this->storage->method('exists')->willReturn(false);
    }

    private function createSubject(?LoggerInterface $logger = null): MentionReceiver
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(function (object $event): object {
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
     * @param array<string, mixed> $settingsTree
     */
    private function createSite(array $settingsTree): Site&MockObject
    {
        $site = $this->createMock(Site::class);
        $site->method('getIdentifier')->willReturn('main');
        $site->method('getSettings')->willReturn(SiteSettings::createFromSettingsTree($settingsTree));

        return $site;
    }

    #[Test]
    public function storesTheTargetPageAndLogsTheRegistration(): void
    {
        $site = $this->createSite([]);
        $this->prepareValidMention($site);
        $this->storage->method('add')
            ->with(self::callback(static fn(Pingback $pingback): bool => $pingback->getPingResource() === '42'))
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
        $this->sourceFetcher->method('fetch')->willReturn([
            'url' => 'https://www.target.example/blog/answer',
            'html' => '<p><a href="/article">relative</a></p>',
        ]);
        $this->storage->expects(self::once())->method('add')->willReturn(1);

        $this->createSubject()->receive($this->mention(), $site);
    }
}
