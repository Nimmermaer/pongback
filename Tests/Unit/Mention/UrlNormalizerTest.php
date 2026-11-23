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
use PHPUnit\Framework\TestCase;
use Webicorns\Pongback\Mention\UrlNormalizer;

final class UrlNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function urlProvider(): array
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
    #[DataProvider('urlProvider')]
    public function normalizesUrlsForComparison(string $url, string $expected): void
    {
        self::assertSame($expected, UrlNormalizer::normalize($url));
    }
}
