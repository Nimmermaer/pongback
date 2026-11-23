<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Webmention;

use Webicorns\Pongback\Messaging\RetryableMessage;

/**
 * "Verify this Webmention", queued by the endpoint after the quick checks.
 */
final readonly class ReceiveWebmentionMessage implements RetryableMessage
{
    public function __construct(
        public string $source,
        public string $target,
        public string $siteIdentifier,
        public string $clientIp = '',
        public string $userAgent = '',
        public int $attempt = 1,
    ) {}

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    public function nextAttempt(): self
    {
        return new self($this->source, $this->target, $this->siteIdentifier, $this->clientIp, $this->userAgent, $this->attempt + 1);
    }
}
