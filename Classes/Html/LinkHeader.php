<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Html;

/**
 * Reads HTTP "Link" headers (RFC 8288): Link: <https://example.com/webmention>; rel="webmention", <…>; rel=next
 */
final class LinkHeader
{
    /**
     * @param list<string> $headerValues
     * @return string|null URI reference of the first link with the relation, as given (may be relative)
     */
    public static function find(array $headerValues, string $relation): ?string
    {
        foreach ($headerValues as $headerValue) {
            // A link is "<uri>" followed by parameters up to the next "<"; the URI itself cannot contain ">".
            preg_match_all('/<([^>]*)>([^<]*)/', $headerValue, $links, PREG_SET_ORDER);
            foreach ($links as [, $uri, $parameters]) {
                if (in_array(strtolower($relation), self::relations($parameters), true)) {
                    return trim($uri);
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function relations(string $parameters): array
    {
        if (preg_match('/(?:^|;)\s*rel\s*=\s*(?:"([^"]*)"|([^\s;,"]+))/i', $parameters, $match) !== 1) {
            return [];
        }

        $value = $match[1] !== '' ? $match[1] : ($match[2] ?? '');

        return preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
