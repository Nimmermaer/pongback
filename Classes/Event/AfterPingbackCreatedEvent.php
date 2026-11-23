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

use Webicorns\Pongback\Domain\Model\Pingback;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Mention\ResolvedTarget;

/**
 * Dispatched after a verified Pingback has been stored, e.g. to notify editors.
 */
final readonly class AfterPingbackCreatedEvent
{
    public function __construct(
        public int $uid,
        public Pingback $pingback,
        public IncomingMention $mention,
        public ResolvedTarget $target,
    ) {}
}
