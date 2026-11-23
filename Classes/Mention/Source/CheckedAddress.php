<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Mention\Source;

/**
 * Host of a source URL that passed the UrlGuard, with the public IP addresses it resolved to.
 */
final readonly class CheckedAddress
{
    /**
     * @param non-empty-list<string> $ips
     */
    public function __construct(
        public string $host,
        public int $port,
        public array $ips,
    ) {}

    /**
     * Entry for CURLOPT_RESOLVE, pins the connection to the checked address.
     */
    public function toCurlResolveEntry(): string
    {
        return sprintf('%s:%d:%s', $this->host, $this->port, $this->ips[0]);
    }

    /**
     * Guzzle request options that pin the connection to the checked IP address
     * (no DNS rebinding between check and request). Only effective with the cURL handler.
     *
     * @return array<string, mixed>
     */
    public function pinningRequestOptions(): array
    {
        if (! \defined('CURLOPT_RESOLVE')) {
            return [];
        }

        return ['curl' => [\CURLOPT_RESOLVE => [$this->toCurlResolveEntry()]]];
    }
}
