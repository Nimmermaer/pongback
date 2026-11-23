<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Widget;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webicorns\Pongback\Widget\PongbackWidget;

final class PongbackWidgetTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function sourceProvider(): array
    {
        return [
            'plain host' => ['https://blog.example/post', 'blog.example'],
            'www is dropped' => ['https://www.blog.example/post', 'blog.example'],
            'only a leading www' => ['https://news.www.example/post', 'news.www.example'],
            'no URL' => ['', ''],
        ];
    }

    #[Test]
    #[DataProvider('sourceProvider')]
    public function namesTheLinkingWebsite(string $sourceLink, string $expected): void
    {
        $row = PongbackWidget::withSourceHost(['uid' => 1, 'source_link' => $sourceLink]);

        self::assertSame($expected, $row['source_host']);
        self::assertSame(1, $row['uid']);
    }
}
