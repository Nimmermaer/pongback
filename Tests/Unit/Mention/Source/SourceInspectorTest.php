<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Mention\Source;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webicorns\Pongback\Mention\Source\SourceInspector;

final class SourceInspectorTest extends TestCase
{
    private const SOURCE = 'https://blog.example/2026/my-post';
    private const TARGET = 'https://www.target.example/articles/pingback';

    #[Test]
    public function extractsTitleAndPlainTextExcerpt(): void
    {
        $html = '<html><head><title> My   <b>Post</b> </title><script>var x = "<a href=\'' . self::TARGET . '\'>";</script></head>'
            . '<body><nav>Menu</nav><p>I read <a href="' . self::TARGET . '"><strong>this article</strong></a> about pingbacks.'
            . '<script>alert("xss")</script><img src=x onerror=alert(1)></p></body></html>';

        $document = (new SourceInspector())->inspect($html, self::SOURCE, self::TARGET);

        self::assertNotNull($document);
        self::assertSame('My Post', $document->title);
        self::assertSame('I read this article about pingbacks.', $document->excerpt);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function equivalentHrefProvider(): array
    {
        return [
            'identical' => [self::TARGET],
            'trailing slash' => [self::TARGET . '/'],
            'fragment' => [self::TARGET . '#comments'],
            'upper case host' => ['https://WWW.Target.Example/articles/pingback'],
        ];
    }

    #[Test]
    #[DataProvider('equivalentHrefProvider')]
    public function detectsEquivalentLinks(string $href): void
    {
        $html = '<p>See <a href="' . htmlspecialchars($href) . '">here</a>.</p>';

        self::assertNotNull((new SourceInspector())->inspect($html, self::SOURCE, self::TARGET));
    }

    #[Test]
    public function resolvesRelativeLinksAgainstTheSourceUrl(): void
    {
        $html = '<p><a href="/other">relative</a></p>';

        self::assertNotNull((new SourceInspector())->inspect($html, self::SOURCE, 'https://blog.example/other'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingLinkProvider(): array
    {
        return [
            'no link' => ['<p>Nothing here mentions ' . self::TARGET . '</p>'],
            'other path' => ['<p><a href="https://www.target.example/articles/pingback-2">x</a></p>'],
            'other query' => ['<p><a href="https://www.target.example/articles/pingback?page=2">x</a></p>'],
            'only inside script' => ['<script>document.write("<a href=\"' . self::TARGET . '\">x</a>")</script>'],
            'only in comment' => ['<!-- <a href="' . self::TARGET . '">x</a> -->'],
            'empty document' => [''],
        ];
    }

    #[Test]
    #[DataProvider('missingLinkProvider')]
    public function returnsNullWithoutLinkToTheTarget(string $html): void
    {
        self::assertNull((new SourceInspector())->inspect($html, self::SOURCE, self::TARGET));
    }

    #[Test]
    public function shortensLongExcerptsAroundTheLink(): void
    {
        $before = str_repeat('Lorem ipsum dolor sit amet. ', 40);
        $after = str_repeat('Consetetur sadipscing elitr. ', 40);
        $html = '<p>' . $before . '<a href="' . self::TARGET . '">the linked article</a>' . $after . '</p>';

        $document = (new SourceInspector())->inspect($html, self::SOURCE, self::TARGET);

        self::assertNotNull($document);
        self::assertLessThanOrEqual(SourceInspector::MAX_EXCERPT_LENGTH, mb_strlen($document->excerpt));
        self::assertStringContainsString('the linked article', $document->excerpt);
        self::assertStringStartsWith('…', $document->excerpt);
        self::assertStringEndsWith('…', $document->excerpt);
    }

    #[Test]
    public function keepsUtf8Characters(): void
    {
        $html = '<html><head><title>Grüße aus Köln</title></head><body><p>Schöner <a href="' . self::TARGET . '">Artikel</a> über Pingbacks</p></body></html>';

        $document = (new SourceInspector())->inspect($html, self::SOURCE, self::TARGET);

        self::assertNotNull($document);
        self::assertSame('Grüße aus Köln', $document->title);
        self::assertSame('Schöner Artikel über Pingbacks', $document->excerpt);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function excerptProvider(): array
    {
        $a = str_repeat('a', 500);
        $b = str_repeat('b', 500);
        $umlautA = str_repeat('ä', 500);
        $umlautO = str_repeat('ö', 500);

        return [
            // window = 298 characters, link text "LINK" centered: 147 characters on each side
            'link in the middle' => [$a . '<a href="' . self::TARGET . '">LINK</a>' . $b, '…' . str_repeat('a', 147) . 'LINK' . str_repeat('b', 147) . '…'],
            'link at the start' => ['<a href="' . self::TARGET . '">LINK</a>' . $b, 'LINK' . str_repeat('b', 294) . '…'],
            'link at the end' => [$a . '<a href="' . self::TARGET . '">LINK</a>', '…' . str_repeat('a', 294) . 'LINK'],
            'multibyte characters' => [$umlautA . '<a href="' . self::TARGET . '">LINK</a>' . $umlautO, '…' . str_repeat('ä', 147) . 'LINK' . str_repeat('ö', 147) . '…'],
            'image link without text' => ['<a href="' . self::TARGET . '"><img src="x.png"></a>' . $b, str_repeat('b', 298) . '…'],
        ];
    }

    #[Test]
    #[DataProvider('excerptProvider')]
    public function cutsLongExcerptsToAWindowAroundTheLink(string $paragraph, string $expected): void
    {
        $document = (new SourceInspector())->inspect('<p>' . $paragraph . '</p>', self::SOURCE, self::TARGET);

        self::assertNotNull($document);
        self::assertSame($expected, $document->excerpt);
        self::assertLessThanOrEqual(SourceInspector::MAX_EXCERPT_LENGTH, mb_strlen($document->excerpt));
    }

    #[Test]
    public function keepsExcerptsUpToTheMaximumLengthUnchanged(): void
    {
        $text = str_repeat('x', SourceInspector::MAX_EXCERPT_LENGTH - 4);
        $html = '<p>' . $text . '<a href="' . self::TARGET . '">LINK</a></p>';

        self::assertSame($text . 'LINK', (new SourceInspector())->inspect($html, self::SOURCE, self::TARGET)?->excerpt);
    }

    #[Test]
    public function usesTheClosestBlockElementAsContext(): void
    {
        $html = '<div><p>Other paragraph</p><ul><li>Item with <span>a <a href="' . self::TARGET . '">link</a></span> inside</li><li>Next</li></ul></div>';

        self::assertSame('Item with a link inside', (new SourceInspector())->inspect($html, self::SOURCE, self::TARGET)?->excerpt);
    }

    #[Test]
    public function limitsTheTitleLength(): void
    {
        $inspect = static fn(string $title): string => (string) (new SourceInspector())->inspect(
            '<title>' . $title . '</title><p><a href="' . self::TARGET . '">x</a></p>',
            self::SOURCE,
            self::TARGET,
        )?->title;

        self::assertSame(str_repeat('T', 255), $inspect(str_repeat('T', 255)));
        self::assertSame(str_repeat('T', 254) . '…', $inspect(str_repeat('T', 256)));
        self::assertSame(str_repeat('Ü', 254) . '…', $inspect(str_repeat('Ü', 300)));
        self::assertSame('', $inspect(''));
    }

    #[Test]
    public function ignoresWhitespaceAroundHrefValues(): void
    {
        $html = '<p><a href="  ' . self::TARGET . "\n" . '">x</a></p>';

        self::assertNotNull((new SourceInspector())->inspect($html, self::SOURCE, self::TARGET));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function normalizeUrlProvider(): array
    {
        return [
            'lower case scheme and host' => ['HTTPS://WWW.Example.COM/Path', 'https://www.example.com/Path'],
            'port kept' => ['http://example.com:8080/a', 'http://example.com:8080/a'],
            'trailing slash removed' => ['https://example.com/a/', 'https://example.com/a'],
            'root' => ['https://example.com/', 'https://example.com'],
            'query kept' => ['https://example.com/a/?b=1&c=2', 'https://example.com/a?b=1&c=2'],
            'empty query dropped' => ['https://example.com/a?', 'https://example.com/a'],
            'fragment dropped' => ['https://example.com/a#top', 'https://example.com/a'],
            'surrounding whitespace' => ["  https://example.com/a \n", 'https://example.com/a'],
            'protocol relative' => ['//example.com/a', 'http://example.com/a'],
            'no host' => [' /relative/path ', '/relative/path'],
        ];
    }

    #[Test]
    #[DataProvider('normalizeUrlProvider')]
    public function normalizesUrlsForComparison(string $url, string $expected): void
    {
        self::assertSame($expected, SourceInspector::normalizeUrl($url));
    }

    #[Test]
    public function restoresTheLibxmlErrorHandlingState(): void
    {
        $previous = libxml_use_internal_errors(false);
        try {
            (new SourceInspector())->inspect('<p><a href="' . self::TARGET . '">x</p></div></span>', self::SOURCE, self::TARGET);

            self::assertFalse(libxml_use_internal_errors(true), 'internal error handling must be reset');
            self::assertSame([], libxml_get_errors(), 'libxml errors must be cleared');
        } finally {
            libxml_use_internal_errors($previous);
        }
    }
}
