<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Functional\Mention;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Mention\MentionReceiver;
use Webicorns\Pongback\Mention\MentionReceiverInterface;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\MentionStorage;
use Webicorns\Pongback\Mention\Protocol;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Mention\Source\FetchedSource;
use Webicorns\Pongback\Mention\Source\SourceFetcher;
use Webicorns\Pongback\Mention\Source\SourceInspector;
use Webicorns\Pongback\Mention\TargetResolver;
use Webicorns\Pongback\Tests\Functional\AbstractPongbackTestCase;
use Webicorns\Pongback\Utility\Constants;

/**
 * Receiving with real routing, site settings, event dispatcher and database.
 * Only the outgoing HTTP request for the source is replaced.
 */
final class MentionReceiverTest extends AbstractPongbackTestCase
{
    private const SOURCE = 'https://blog.example/post';
    private const TARGET = 'https://main.example/article';

    #[Test]
    public function receiverIsWiredInTheContainer(): void
    {
        self::assertInstanceOf(MentionReceiver::class, $this->get(MentionReceiverInterface::class));
    }

    #[Test]
    public function storesVerifiedMentionsHiddenOnTheTargetPage(): void
    {
        $uid = $this->createReceiver()->receive($this->mention(), $this->site());

        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(Constants::PINGBACK_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('*')->from(Constants::PINGBACK_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame(2, (int) $row['pid'], 'stored on the target page');
        self::assertSame(1, (int) $row['hidden'], 'moderation is the default of the site set');
        self::assertSame(0, (int) $row['sys_language_uid']);
        self::assertSame(self::SOURCE, $row['source_link']);
        self::assertSame(self::TARGET, $row['target_link']);
        self::assertSame('A blog post', $row['source_title']);
        self::assertSame('I liked this article a lot.', $row['excerpt']);
        self::assertGreaterThan(0, (int) $row['crdate']);
    }

    #[Test]
    public function rejectsTheSameMentionTwice(): void
    {
        $receiver = $this->createReceiver();
        $receiver->receive($this->mention(), $this->site());

        try {
            $receiver->receive($this->mention(), $this->site());
            self::fail('The second mention must be rejected');
        } catch (MentionRejected $rejected) {
            self::assertSame(RejectionReason::AlreadyRegistered, $rejected->reason);
        }
    }

    private function createReceiver(): MentionReceiver
    {
        $sourceFetcher = $this->createMock(SourceFetcher::class);
        $sourceFetcher->method('fetch')->with(self::SOURCE)->willReturn(new FetchedSource(
            self::SOURCE,
            '<html><head><title>A blog post</title></head><body><p>I liked <a href="' . self::TARGET . '">this article</a> a lot.</p></body></html>',
        ));

        return new MentionReceiver(
            $this->get(TargetResolver::class),
            $sourceFetcher,
            new SourceInspector(),
            $this->get(MentionStorage::class),
            $this->get(EventDispatcherInterface::class),
            new NullLogger(),
        );
    }

    private function mention(): IncomingMention
    {
        return new IncomingMention(self::SOURCE, self::TARGET, Protocol::Pingback, '203.0.113.5');
    }

    private function site(): Site
    {
        $site = $this->get(SiteFinder::class)->getSiteByIdentifier('main');
        self::assertInstanceOf(Site::class, $site);

        return $site;
    }
}
