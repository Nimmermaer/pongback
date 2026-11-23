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
 * Receives a mention: verify target and source, then store it.
 * Shared by all protocol endpoints (Pingback, Webmention).
 */
interface MentionReceiverInterface
{
    /**
     * @return int uid of the stored record
     *
     * @throws MentionRejected
     */
    public function receive(IncomingMention $mention, Site $site): int;

    /**
     * Brings the stored mention in line with the source (Webmention): creates it, updates title and
     * excerpt of a known one, or removes it if the source is gone or no longer links to the target.
     *
     * @throws MentionRejected if nothing is stored for a source that cannot be verified
     */
    public function synchronize(IncomingMention $mention, Site $site): SyncResult;
}
