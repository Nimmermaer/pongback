<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Mention;

/**
 * Normalizes URLs for comparison: case insensitive scheme and host, no fragment, no trailing slash.
 */
final class UrlNormalizer
{
    /**
     * Normalize a URL for comparison only, the result is not meant to be requested.
     */
    public static function normalize(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! isset($parts['host'])) {
            return trim($url);
        }

        $normalized = strtolower($parts['scheme'] ?? 'http') . '://' . strtolower($parts['host']);
        if (isset($parts['port'])) {
            $normalized .= ':' . $parts['port'];
        }

        $normalized .= rtrim($parts['path'] ?? '', '/');
        if (($parts['query'] ?? '') !== '') {
            $normalized .= '?' . $parts['query'];
        }

        return $normalized;
    }
}
