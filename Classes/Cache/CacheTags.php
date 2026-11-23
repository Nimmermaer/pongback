<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Cache;

/**
 * Tags of the rendered plugin output in the pongback cache (Constants::PONGBACK_CACHE).
 */
final class CacheTags
{
    public const LIST_VIEW = 'pingback_list_view';
    public const LATEST_VIEW = 'pingback_latest_view';

    public static function forPage(int $pageId): string
    {
        return 'pageId_' . $pageId;
    }

    public static function forView(string $view): string
    {
        return 'pingback_' . $view . '_view';
    }

    /**
     * Everything that may show Pingbacks of the given page.
     *
     * @return list<string>
     */
    public static function affectedByPage(int $pageId): array
    {
        return [self::forPage($pageId), self::LIST_VIEW, self::LATEST_VIEW];
    }
}
