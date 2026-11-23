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

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use TYPO3\CMS\Core\Http\RequestFactory;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\Source\UrlGuard;
use Webicorns\Pongback\Utility\Constants;

/**
 * Sends a Webmention: POST source and target, form encoded, to the endpoint of the linked page.
 *
 * @see https://www.w3.org/TR/webmention/#sender-notifies-receiver
 */
readonly class WebmentionClient
{
    public const TIMEOUT = 10;
    private const MAX_MESSAGE_BYTES = 255;

    public function __construct(
        private RequestFactory $requestFactory,
        private UrlGuard $urlGuard,
    ) {}

    public function send(string $endpoint, string $source, string $target): PingResult
    {
        try {
            $address = $this->urlGuard->assertAllowed($endpoint);
        } catch (MentionRejected $rejected) {
            return new PingResult(PingStatus::Rejected, 'Endpoint not allowed: ' . $rejected->getMessage());
        }

        try {
            $response = $this->requestFactory->request($endpoint, 'POST', [
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::CONNECT_TIMEOUT => 5,
                RequestOptions::TIMEOUT => self::TIMEOUT,
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::HEADERS => [
                    'User-Agent' => Constants::USER_AGENT,
                ],
                RequestOptions::FORM_PARAMS => [
                    'source' => $source,
                    'target' => $target,
                ],
            ] + $address->pinningRequestOptions());
        } catch (GuzzleException $exception) {
            return new PingResult(PingStatus::Failed, 'Endpoint not reachable: ' . $exception->getMessage());
        }

        $status = $response->getStatusCode();
        $message = sprintf('HTTP %d %s', $status, substr(trim((string) $response->getBody()), 0, self::MAX_MESSAGE_BYTES));

        return new PingResult(self::statusFor($status), trim($message));
    }

    /**
     * Any 2xx is a success (201 created, 202 accepted). Client errors are final, except "too many requests".
     */
    private static function statusFor(int $httpStatus): PingStatus
    {
        return match (true) {
            $httpStatus >= 200 && $httpStatus < 300 => PingStatus::Sent,
            $httpStatus >= 400 && $httpStatus < 500 && $httpStatus !== 429 => PingStatus::Rejected,
            default => PingStatus::Failed,
        };
    }
}
