<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Html;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webicorns\Pongback\Html\LinkHeader;

final class LinkHeaderTest extends TestCase
{
    /**
     * @return array<string, array{list<string>, ?string}>
     */
    public static function headerProvider(): array
    {
        return [
            'quoted' => [['<https://a.example/wm>; rel="webmention"'], 'https://a.example/wm'],
            'unquoted' => [['<https://a.example/wm>; rel=webmention'], 'https://a.example/wm'],
            'upper case parameter and value' => [['<https://a.example/wm>; REL="WebMention"'], 'https://a.example/wm'],
            'spaces everywhere' => [['< https://a.example/wm > ;  rel = " webmention  other "'], 'https://a.example/wm'],
            'other parameters first' => [['<https://a.example/wm>; title="Endpoint"; rel="webmention"'], 'https://a.example/wm'],
            'second link' => [['<https://a.example/style>; rel="stylesheet", <https://a.example/wm>; rel="webmention"'], 'https://a.example/wm'],
            'second header' => [['<https://a.example/feed>; rel="alternate"', '<https://a.example/wm>; rel="webmention"'], 'https://a.example/wm'],
            'first match wins' => [['<https://a.example/one>; rel="webmention", <https://a.example/two>; rel="webmention"'], 'https://a.example/one'],
            'relative' => [['</wm>; rel="webmention"'], '/wm'],
            'empty reference' => [['<>; rel="webmention"'], ''],
            'only similar relations' => [['<https://a.example/wm>; rel="webmentions not-webmention"'], null],
            'no rel parameter' => [['<https://a.example/wm>; title="webmention"'], null],
            'parameter ending in rel' => [['<https://a.example/wm>; prefixrel="webmention"'], null],
            'no headers' => [[], null],
            'garbage' => [['webmention'], null],
        ];
    }

    /**
     * @param list<string> $headers
     */
    #[Test]
    #[DataProvider('headerProvider')]
    public function findsTheFirstLinkWithTheRelation(array $headers, ?string $expected): void
    {
        self::assertSame($expected, LinkHeader::find($headers, 'webmention'));
    }

    #[Test]
    public function comparesTheRequestedRelationCaseInsensitive(): void
    {
        self::assertSame('/wm', LinkHeader::find(['</wm>; rel="webmention"'], 'Webmention'));
    }
}
