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

final readonly class PingResult
{
    public function __construct(
        public PingStatus $status,
        public string $message = '',
        public int $faultCode = 0,
        public string $endpoint = '',
    ) {}

    public function withEndpoint(string $endpoint): self
    {
        return new self($this->status, $this->message, $this->faultCode, $endpoint);
    }
}
