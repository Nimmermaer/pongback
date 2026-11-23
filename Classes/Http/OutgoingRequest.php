<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Http;

use GuzzleHttp\RequestOptions;
use Webicorns\Pongback\Mention\Source\CheckedAddress;
use Webicorns\Pongback\Utility\Constants;

/**
 * Options of every request the extension sends to other servers: short timeouts, no exceptions
 * for HTTP errors, no automatic redirects, our User-Agent, and pinned to the checked IP address.
 */
final class OutgoingRequest
{
    public const CONNECT_TIMEOUT = 5;
    public const TIMEOUT = 10;

    /**
     * @param array<string, mixed> $options added to or replacing the defaults; headers are merged
     * @param CheckedAddress|null $pinnedTo connect to the IP address the UrlGuard checked
     * @return array<string, mixed>
     */
    public static function options(array $options = [], ?CheckedAddress $pinnedTo = null): array
    {
        $headers = ['User-Agent' => Constants::USER_AGENT, ...($options[RequestOptions::HEADERS] ?? [])];

        return [
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
            RequestOptions::TIMEOUT => self::TIMEOUT,
            RequestOptions::HTTP_ERRORS => false,
            ...$options,
            RequestOptions::HEADERS => $headers,
        ] + ($pinnedTo?->pinningRequestOptions() ?? []);
    }
}
