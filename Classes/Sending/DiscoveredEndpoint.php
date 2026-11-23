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

use Webicorns\Pongback\Mention\IncomingMention;

/**
 * The endpoint a linked page announces, with its protocol.
 */
final readonly class DiscoveredEndpoint
{
    public function __construct(
        public string $protocol,
        public string $url,
    ) {}

    public function isWebmention(): bool
    {
        return $this->protocol === IncomingMention::PROTOCOL_WEBMENTION;
    }
}
