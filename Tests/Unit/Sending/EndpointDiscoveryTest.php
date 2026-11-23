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
use PHPUnit\Framework\TestCase;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Mention\Source\FetchedSource;
use Webicorns\Pongback\Mention\Source\SourceFetcher;
use Webicorns\Pongback\Sending\EndpointDiscovery;

final class EndpointDiscoveryTest extends TestCase
{
    private const TARGET = 'https://blog.example/2026/post';

    #[Test]
    public function prefersTheHttpHeader(): void
    {
        $page = new FetchedSource(self::TARGET, '<link rel="pingback" href="https://blog.example/link-endpoint">', 'https://blog.example/xmlrpc.php');

        self::assertSame('https://blog.example/xmlrpc.php', $this->createSubject($page)->discover(self::TARGET));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function linkElementProvider(): array
    {
        return [
            'absolute' => ['<link rel="pingback" href="https://blog.example/xmlrpc.php">', 'https://blog.example/xmlrpc.php'],
            'relative to the final URL' => ['<link rel="pingback" href="/xmlrpc.php">', 'https://www.blog.example/xmlrpc.php'],
            'several relations, upper case' => ['<link rel="Pingback  alternate" href="https://blog.example/rpc">', 'https://blog.example/rpc'],
            'in the body' => ['<body><link rel="pingback" href="https://blog.example/rpc"></body>', 'https://blog.example/rpc'],
            'first one wins' => ['<link rel="pingback" href="/one"><link rel="pingback" href="/two">', 'https://www.blog.example/one'],
            'other relation only' => ['<link rel="stylesheet" href="/style.css">', null],
            'empty href' => ['<link rel="pingback" href="">', null],
            'no endpoint at all' => ['<p>Hello</p>', null],
            'non http endpoint' => ['<link rel="pingback" href="javascript:alert(1)">', null],
            'http endpoint' => ['<link rel="pingback" href="http://blog.example/rpc">', 'http://blog.example/rpc'],
            'upper case scheme' => ['<link rel="pingback" href="HTTPS://blog.example/rpc">', 'https://blog.example/rpc'],
            'whitespace around values' => ['<link rel=" pingback " href=" https://blog.example/rpc ">', 'https://blog.example/rpc'],
        ];
    }

    #[Test]
    #[DataProvider('linkElementProvider')]
    public function findsTheLinkElement(string $html, ?string $expected): void
    {
        // The page was redirected to www.blog.example, relative endpoints refer to that URL.
        $page = new FetchedSource('https://www.blog.example/2026/post', $html);

        self::assertSame($expected, $this->createSubject($page)->discover(self::TARGET));
    }

    #[Test]
    public function passesRejectionsOfTheFetcherOn(): void
    {
        $fetcher = $this->createMock(SourceFetcher::class);
        $fetcher->method('fetch')->willThrowException(new MentionRejected(RejectionReason::SourceNotFound, 'gone'));

        $this->expectException(MentionRejected::class);
        (new EndpointDiscovery($fetcher))->discover(self::TARGET);
    }

    private function createSubject(FetchedSource $page): EndpointDiscovery
    {
        $fetcher = $this->createMock(SourceFetcher::class);
        $fetcher->method('fetch')->with(self::TARGET)->willReturn($page);

        return new EndpointDiscovery($fetcher);
    }
}
