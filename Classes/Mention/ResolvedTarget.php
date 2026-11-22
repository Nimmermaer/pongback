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

use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * The page of the own installation a mention points to.
 */
final readonly class ResolvedTarget
{
    public function __construct(
        public Site $site,
        public int $pageId,
        public int $languageId,
    ) {}
}
