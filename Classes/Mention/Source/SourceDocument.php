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
 * What we keep from a source page. Plain text only, never remote HTML.
 */
final readonly class SourceDocument
{
    public function __construct(
        public string $title,
        public string $excerpt,
    ) {}
}
