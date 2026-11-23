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

use Webicorns\Pongback\Mention\Protocol;

final readonly class SendResult
{
    public function __construct(
        public SendStatus $status,
        public string $message = '',
        public int $faultCode = 0,
        public string $endpoint = '',
        public ?Protocol $protocol = null,
    ) {}

    public function withEndpoint(DiscoveredEndpoint $endpoint): self
    {
        return new self($this->status, $this->message, $this->faultCode, $endpoint->url, $endpoint->protocol);
    }
}
