<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Event;

use TYPO3\CMS\Core\Site\Entity\Site;
use Webicorns\Pongback\Sending\PingResult;

/**
 * Dispatched after sending the Pingbacks and Webmentions of a page, if linked pages could not be
 * notified for good: rejected, or failed after the last attempt. Only new problems are included,
 * a link that was already rejected before is not reported again.
 */
final readonly class SendingFailedEvent
{
    /**
     * @param array<string, PingResult> $problems result per linked URL
     */
    public function __construct(
        public Site $site,
        public int $pageId,
        public string $pageUrl,
        public array $problems,
    ) {}
}
