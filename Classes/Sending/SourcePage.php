<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Sending;

use TYPO3\CMS\Core\Site\Entity\Site;
use Webicorns\Pongback\Configuration\PongbackSettings;

/**
 * A page of this installation whose links are notified.
 */
final readonly class SourcePage
{
    /**
     * @param list<string> $ownHosts hosts of all sites of this installation: links to them are not notified
     */
    public function __construct(
        public Site $site,
        public PongbackSettings $settings,
        public string $url,
        public array $ownHosts,
    ) {}
}
