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

/**
 * A notification that the page at $source links to the page at $target.
 *
 * Protocol neutral: created by the Pingback endpoint today, by a Webmention endpoint later.
 */
final readonly class IncomingMention
{
    public const PROTOCOL_PINGBACK = 'pingback';
    public const PROTOCOL_WEBMENTION = 'webmention';

    public function __construct(
        public string $source,
        public string $target,
        public string $protocol,
        public string $clientIp = '',
        public string $userAgent = '',
    ) {}
}
