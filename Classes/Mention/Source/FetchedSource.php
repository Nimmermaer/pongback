<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Mention\Source;

/**
 * HTML of a page and the URL it was finally retrieved from (after redirects).
 */
final readonly class FetchedSource
{
    /**
     * @param string $pingbackHeader value of the "X-Pingback" response header, if any
     */
    public function __construct(
        public string $url,
        public string $html,
        public string $pingbackHeader = '',
    ) {}
}
