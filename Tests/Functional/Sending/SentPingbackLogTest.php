<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Functional\Sending;

use PHPUnit\Framework\Attributes\Test;
use Webicorns\Pongback\Sending\PingResult;
use Webicorns\Pongback\Sending\PingStatus;
use Webicorns\Pongback\Sending\SentPingbackLog;
use Webicorns\Pongback\Tests\Functional\AbstractPongbackTestCase;
use Webicorns\Pongback\Utility\Constants;

/**
 * SentPingbackLog against a real database.
 */
final class SentPingbackLogTest extends AbstractPongbackTestCase
{
    private const SOURCE = 'https://main.example/article';
    private const TARGET = 'https://blog.example/post';

    #[Test]
    public function recordsTheFirstAttempt(): void
    {
        $this->log()->record(2, self::SOURCE, self::TARGET, new PingResult(PingStatus::Sent, 'Thanks', 0, 'https://blog.example/xmlrpc.php'));

        $rows = $this->rows();
        self::assertCount(1, $rows);
        self::assertSame(2, (int) $rows[0]['pid']);
        self::assertSame(self::SOURCE, $rows[0]['source_link']);
        self::assertSame(self::TARGET, $rows[0]['target_link']);
        self::assertSame('https://blog.example/xmlrpc.php', $rows[0]['endpoint']);
        self::assertSame('sent', $rows[0]['status']);
        self::assertSame('Thanks', $rows[0]['message']);
        self::assertSame(1, (int) $rows[0]['attempts']);
        self::assertGreaterThan(0, (int) $rows[0]['crdate']);
    }

    #[Test]
    public function updatesTheRecordOnFurtherAttempts(): void
    {
        $log = $this->log();
        $log->record(2, self::SOURCE, self::TARGET, new PingResult(PingStatus::Failed, 'Timeout'));
        $log->record(2, self::SOURCE, self::TARGET, new PingResult(PingStatus::Rejected, 'No link', 17));

        $rows = $this->rows();
        self::assertCount(1, $rows);
        self::assertSame('rejected', $rows[0]['status']);
        self::assertSame(17, (int) $rows[0]['fault_code']);
        self::assertSame(2, (int) $rows[0]['attempts']);
    }

    #[Test]
    public function onlyFailedPingbacksAreNotFinal(): void
    {
        $log = $this->log();
        self::assertFalse($log->isFinal(2, self::SOURCE, self::TARGET), 'Unknown Pingbacks are not final');

        $log->record(2, self::SOURCE, self::TARGET, new PingResult(PingStatus::Failed));
        self::assertFalse($log->isFinal(2, self::SOURCE, self::TARGET));

        $log->record(2, self::SOURCE, self::TARGET, new PingResult(PingStatus::NoEndpoint));
        self::assertTrue($log->isFinal(2, self::SOURCE, self::TARGET));
        self::assertFalse($log->isFinal(2, self::SOURCE, 'https://blog.example/other'));
        self::assertFalse($log->isFinal(1, self::SOURCE, self::TARGET), 'Entries belong to their page');
    }

    #[Test]
    public function shortensLongMessages(): void
    {
        $this->log()->record(2, self::SOURCE, self::TARGET, new PingResult(PingStatus::Failed, str_repeat('m', 300)));

        self::assertSame(str_repeat('m', 255), $this->rows()[0]['message']);
    }

    #[Test]
    public function remembersTheLastResultWithProtocol(): void
    {
        $log = $this->log();
        self::assertNull($log->lastResult(2, self::SOURCE, self::TARGET));

        $log->record(2, self::SOURCE, self::TARGET, new PingResult(PingStatus::Rejected, 'HTTP 400', 0, 'https://blog.example/webmention', 'webmention'));

        self::assertEquals(
            new PingResult(PingStatus::Rejected, 'HTTP 400', 0, 'https://blog.example/webmention', 'webmention'),
            $log->lastResult(2, self::SOURCE, self::TARGET),
        );
    }

    #[Test]
    public function listsWebmentionTargetsOfAPageAndForgetsThem(): void
    {
        $log = $this->log();
        $log->record(2, self::SOURCE, 'https://blog.example/one', new PingResult(PingStatus::Sent, protocol: 'webmention'));
        $log->record(2, self::SOURCE, 'https://blog.example/pingback', new PingResult(PingStatus::Sent, protocol: 'pingback'));
        $log->record(2, self::SOURCE, 'https://blog.example/two', new PingResult(PingStatus::Failed, protocol: 'webmention'));
        $log->record(2, 'https://main.example/other', 'https://blog.example/three', new PingResult(PingStatus::Sent, protocol: 'webmention'));
        $log->record(3, self::SOURCE, 'https://blog.example/four', new PingResult(PingStatus::Sent, protocol: 'webmention'));

        self::assertSame(['https://blog.example/one', 'https://blog.example/two'], $log->webmentionTargets(2, self::SOURCE));

        $log->forget(2, self::SOURCE, 'https://blog.example/one');

        self::assertSame(['https://blog.example/two'], $log->webmentionTargets(2, self::SOURCE));
        self::assertNull($log->lastResult(2, self::SOURCE, 'https://blog.example/one'));
        self::assertCount(4, $this->rows(), 'Only this entry is removed');
    }

    private function log(): SentPingbackLog
    {
        return $this->get(SentPingbackLog::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return $this->getConnectionPool()->getConnectionForTable(Constants::SENT_TABLE)
            ->select(['*'], Constants::SENT_TABLE)
            ->fetchAllAssociative();
    }
}
