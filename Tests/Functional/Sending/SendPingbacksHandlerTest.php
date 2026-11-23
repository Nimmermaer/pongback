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
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
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
use Webicorns\Pongback\Tests\Functional\AbstractPongbackTestCase;
use Webicorns\Pongback\Utility\Constants;

/**
 * Sending with real sites, routing, page visibility, log table and queue.
 * Only the HTTP requests (own page, discovery, ping) are replaced.
 */
final class SendPingbacksHandlerTest extends AbstractPongbackTestCase
{
    /**
     * @var list<string>
     */
    private array $fetchedPages = [];

    /**
     * @var list<array{string, string, string}>
     */
    private array $pings = [];

    #[Test]
    public function pingsTheLinksOfThePublishedPage(): void
    {
        $this->handle(new SendPingbacksMessage(2));

        self::assertSame(['https://main.example/article'], $this->fetchedPages);
        self::assertSame([
            ['https://blog.example/xmlrpc.php', 'https://main.example/article', 'https://blog.example/post'],
        ], $this->pings);

        $rows = $this->getConnectionPool()->getConnectionForTable(Constants::SENT_TABLE)
            ->select(['pid', 'source_link', 'target_link', 'endpoint', 'status'], Constants::SENT_TABLE)
            ->fetchAllAssociative();
        self::assertEquals([[
            'pid' => 2,
            'source_link' => 'https://main.example/article',
            'target_link' => 'https://blog.example/post',
            'endpoint' => 'https://blog.example/xmlrpc.php',
            'status' => 'sent',
        ]], $rows);
    }

    #[Test]
    public function sendsEveryPingbackOnlyOnce(): void
    {
        $this->handle(new SendPingbacksMessage(2));
        $this->handle(new SendPingbacksMessage(2));

        self::assertCount(1, $this->pings);
        self::assertCount(2, $this->fetchedPages, 'The page itself is checked again, it might link to new pages');
    }

    #[Test]
    public function ignoresHiddenPagesAndSitesWithoutTheSet(): void
    {
        $this->handle(new SendPingbacksMessage(3));
        $this->handle(new SendPingbacksMessage(11));

        self::assertSame([], $this->fetchedPages);
    }

    private function handle(SendPingbacksMessage $message): void
    {
        $pageFetcher = $this->createMock(PageFetcher::class);
        $pageFetcher->method('fetch')->willReturnCallback(function (string $url): string {
            $this->fetchedPages[] = $url;
            return '<main><a href="https://blog.example/post">Post</a><a href="/">Home</a></main>';
        });

        $discovery = $this->createMock(EndpointDiscovery::class);
        $discovery->method('discover')->willReturn('https://blog.example/xmlrpc.php');

        $client = $this->createMock(PingbackClient::class);
        $client->method('ping')->willReturnCallback(function (string $endpoint, string $source, string $target): PingResult {
            $this->pings[] = [$endpoint, $source, $target];
            return new PingResult(PingStatus::Sent);
        });

        $handler = new SendPingbacksHandler(
            $this->get(PageRepository::class),
            $this->get(SiteFinder::class),
            $pageFetcher,
            new LinkExtractor(),
            $discovery,
            $client,
            $this->get(SentPingbackLog::class),
            $this->get(MessageQueue::class),
            new NullLogger(),
        );

        $handler($message);
    }
}
