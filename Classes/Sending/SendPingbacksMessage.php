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

/**
 * "Send the Pingbacks of this page", queued when a page or its content changes.
 * Holds IDs only, the handler reads the current state when it runs.
 */
final readonly class SendPingbacksMessage
{
    public function __construct(
        public int $pageId,
        public int $languageId = 0,
        public int $attempt = 1,
    ) {}

    public function nextAttempt(): self
    {
        return new self($this->pageId, $this->languageId, $this->attempt + 1);
    }
}
