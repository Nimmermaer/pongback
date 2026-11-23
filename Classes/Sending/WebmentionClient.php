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
use Webicorns\Pongback\Http\OutgoingRequest;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\Source\UrlGuard;

/**
 * Sends a Webmention: POST source and target, form encoded, to the endpoint of the linked page.
 *
 * @see https://www.w3.org/TR/webmention/#sender-notifies-receiver
 */
readonly class WebmentionClient
{
    private const MAX_MESSAGE_BYTES = 255;

    public function __construct(
        private RequestFactory $requestFactory,
        private UrlGuard $urlGuard,
    ) {}

    public function send(string $endpoint, string $source, string $target): SendResult
    {
        try {
            $address = $this->urlGuard->assertAllowed($endpoint);
        } catch (MentionRejected $rejected) {
            return new SendResult(SendStatus::Rejected, 'Endpoint not allowed: ' . $rejected->getMessage());
        }

        try {
            $response = $this->requestFactory->request($endpoint, 'POST', OutgoingRequest::options([
                RequestOptions::FORM_PARAMS => [
                    'source' => $source,
                    'target' => $target,
                ],
            ], $address));
        } catch (GuzzleException $exception) {
            return new SendResult(SendStatus::Failed, 'Endpoint not reachable: ' . $exception->getMessage());
        }

        $status = $response->getStatusCode();
        $message = sprintf('HTTP %d %s', $status, substr(trim((string) $response->getBody()), 0, self::MAX_MESSAGE_BYTES));

        return new SendResult(self::statusFor($status), trim($message));
    }

    /**
     * Any 2xx is a success (201 created, 202 accepted). Client errors are final, except "too many requests".
     */
    private static function statusFor(int $httpStatus): SendStatus
    {
        return match (true) {
            $httpStatus >= 200 && $httpStatus < 300 => SendStatus::Sent,
            $httpStatus >= 400 && $httpStatus < 500 && $httpStatus !== 429 => SendStatus::Rejected,
            default => SendStatus::Failed,
        };
    }
}
