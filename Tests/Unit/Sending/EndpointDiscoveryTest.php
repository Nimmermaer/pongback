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
use Webicorns\Pongback\Mention\Protocol;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Mention\Source\FetchedSource;
use Webicorns\Pongback\Mention\Source\SourceFetcher;
use Webicorns\Pongback\Sending\DiscoveredEndpoint;
use Webicorns\Pongback\Sending\EndpointDiscovery;

final class EndpointDiscoveryTest extends TestCase
{
    private const TARGET = 'https://blog.example/2026/post';

    /**
     * The page was redirected to www.blog.example, relative endpoints refer to that URL.
     */
    private const FINAL_URL = 'https://www.blog.example/2026/post';

    #[Test]
    public function prefersThePingbackHttpHeader(): void
    {
        $page = new FetchedSource(self::TARGET, '<link rel="pingback" href="https://blog.example/link-endpoint">', 'https://blog.example/xmlrpc.php');

        self::assertEquals(new DiscoveredEndpoint(Protocol::Pingback, 'https://blog.example/xmlrpc.php'), $this->discover($page));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function pingbackElementProvider(): array
    {
        return [
            'absolute' => ['<link rel="pingback" href="https://blog.example/xmlrpc.php">', 'https://blog.example/xmlrpc.php'],
            'relative to the final URL' => ['<link rel="pingback" href="/xmlrpc.php">', 'https://www.blog.example/xmlrpc.php'],
            'several relations, upper case' => ['<link rel="Pingback  alternate" href="https://blog.example/rpc">', 'https://blog.example/rpc'],
            'in the body' => ['<body><link rel="pingback" href="https://blog.example/rpc"></body>', 'https://blog.example/rpc'],
            'first one wins' => ['<link rel="pingback" href="/one"><link rel="pingback" href="/two">', 'https://www.blog.example/one'],
            'only <link> elements' => ['<a rel="pingback" href="/a">x</a>', null],
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
    #[DataProvider('pingbackElementProvider')]
    public function findsThePingbackLinkElement(string $html, ?string $expected): void
    {
        $endpoint = $this->discover(new FetchedSource(self::FINAL_URL, $html));

        self::assertEquals($expected === null ? null : new DiscoveredEndpoint(Protocol::Pingback, $expected), $endpoint);
    }

    /**
     * @return array<string, array{list<string>, string, ?string}>
     */
    public static function webmentionProvider(): array
    {
        return [
            'header, absolute' => [['<https://blog.example/webmention>; rel="webmention"'], '', 'https://blog.example/webmention'],
            'header, relative' => [['</webmention>; rel="webmention"'], '', 'https://www.blog.example/webmention'],
            'header, unquoted' => [['</webmention>; rel=webmention'], '', 'https://www.blog.example/webmention'],
            'header, several relations' => [['</webmention>; rel="webmention somethingelse"'], '', 'https://www.blog.example/webmention'],
            'header, several links' => [['</style.css>; rel="stylesheet", </webmention>; rel="webmention"'], '', 'https://www.blog.example/webmention'],
            'header, several headers' => [['</feed>; rel="alternate"', '</webmention>; rel="webmention"'], '', 'https://www.blog.example/webmention'],
            'header, similar relation only' => [['</webmentions>; rel="not-webmention"'], '', null],
            'header wins over HTML' => [['</from-header>; rel="webmention"'], '<link rel="webmention" href="/from-html">', 'https://www.blog.example/from-header'],
            '<link>' => [[], '<link rel="webmention" href="/webmention">', 'https://www.blog.example/webmention'],
            '<a>' => [[], '<a rel="webmention" href="/webmention">endpoint</a>', 'https://www.blog.example/webmention'],
            'first in document order' => [[], '<a rel="webmention" href="/a">a</a><link rel="webmention" href="/link">', 'https://www.blog.example/a'],
            'relation among others' => [[], '<link rel="nofollow Webmention" href="/webmention">', 'https://www.blog.example/webmention'],
            'empty href is the page itself' => [[], '<link rel="webmention" href="">', self::FINAL_URL],
            'query string is kept' => [[], '<link rel="webmention" href="/webmention?version=1&amp;id=2">', 'https://www.blog.example/webmention?version=1&id=2'],
            'element without href is ignored' => [[], '<link rel="webmention"><link rel="webmention" href="/second">', 'https://www.blog.example/second'],
            'comments are ignored' => [[], '<!-- <link rel="webmention" href="/commented"> --><link rel="webmention" href="/real">', 'https://www.blog.example/real'],
            'non http endpoint' => [[], '<link rel="webmention" href="mailto:me@blog.example">', null],
            'upper case scheme' => [[], '<link rel="webmention" href="HTTP://blog.example/wm">', 'http://blog.example/wm'],
            'whitespace in rel' => [[], '<a rel=" webmention " href="/webmention">x</a>', 'https://www.blog.example/webmention'],
        ];
    }

    /**
     * @param list<string> $linkHeaders
     */
    #[Test]
    #[DataProvider('webmentionProvider')]
    public function findsTheWebmentionEndpoint(array $linkHeaders, string $html, ?string $expected): void
    {
        $endpoint = $this->discover(new FetchedSource(self::FINAL_URL, $html, '', $linkHeaders));

        self::assertEquals($expected === null ? null : new DiscoveredEndpoint(Protocol::Webmention, $expected), $endpoint);
    }

    #[Test]
    public function fallsBackToPingback(): void
    {
        $page = new FetchedSource(self::FINAL_URL, '<link rel="webmention" href="mailto:x@blog.example"><link rel="pingback" href="/xmlrpc.php">');

        self::assertEquals(new DiscoveredEndpoint(Protocol::Pingback, 'https://www.blog.example/xmlrpc.php'), $this->discover($page));
    }

    #[Test]
    public function ignoresWebmentionIfSwitchedOff(): void
    {
        $page = new FetchedSource(self::FINAL_URL, '<link rel="webmention" href="/webmention">', 'https://blog.example/xmlrpc.php', ['</webmention>; rel="webmention"']);

        self::assertEquals(new DiscoveredEndpoint(Protocol::Pingback, 'https://blog.example/xmlrpc.php'), $this->discover($page, false));
        self::assertNull($this->discover(new FetchedSource(self::FINAL_URL, '<link rel="webmention" href="/webmention">'), false));
    }

    #[Test]
    public function knowsWebmentionEndpoints(): void
    {
        self::assertTrue((new DiscoveredEndpoint(Protocol::Webmention, 'https://blog.example/'))->isWebmention());
        self::assertFalse((new DiscoveredEndpoint(Protocol::Pingback, 'https://blog.example/'))->isWebmention());
    }

    #[Test]
    public function passesRejectionsOfTheFetcherOn(): void
    {
        $fetcher = $this->createMock(SourceFetcher::class);
        $fetcher->method('fetch')->willThrowException(new MentionRejected(RejectionReason::SourceGone, 'gone'));

        $this->expectException(MentionRejected::class);
        (new EndpointDiscovery($fetcher))->discover(self::TARGET);
    }

    #[Test]
    public function prefersWebmentionByDefault(): void
    {
        $fetcher = $this->createMock(SourceFetcher::class);
        $fetcher->method('fetch')->willReturn(new FetchedSource(self::FINAL_URL, '<link rel="webmention" href="/wm">', 'https://blog.example/xmlrpc.php'));

        self::assertEquals(new DiscoveredEndpoint(Protocol::Webmention, 'https://www.blog.example/wm'), (new EndpointDiscovery($fetcher))->discover(self::TARGET));
    }

    private function discover(FetchedSource $page, bool $webmention = true): ?DiscoveredEndpoint
    {
        $fetcher = $this->createMock(SourceFetcher::class);
        $fetcher->method('fetch')->with(self::TARGET)->willReturn($page);

        return (new EndpointDiscovery($fetcher))->discover(self::TARGET, $webmention);
    }
}
