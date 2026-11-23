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
use Webicorns\Pongback\Pingback\PingbackEndpoint;
use Webicorns\Pongback\XmlRpc\XmlRpcCodec;

/**
 * Sends pingback.ping(source, target) to the endpoint of a linked site.
 */
readonly class PingbackClient
{
    private const MAX_RESPONSE_BYTES = 65536;

    /**
     * Faults that will not change when trying again.
     */
    private const FINAL_FAULTS = [
        PingbackEndpoint::FAULT_SOURCE_NOT_FOUND,
        PingbackEndpoint::FAULT_NO_LINK_TO_TARGET,
        PingbackEndpoint::FAULT_TARGET_NOT_FOUND,
        PingbackEndpoint::FAULT_TARGET_NOT_ALLOWED,
        PingbackEndpoint::FAULT_ACCESS_DENIED,
        PingbackEndpoint::FAULT_METHOD_NOT_FOUND,
        PingbackEndpoint::FAULT_INVALID_PARAMS,
    ];

    public function __construct(
        private RequestFactory $requestFactory,
        private UrlGuard $urlGuard,
        private XmlRpcCodec $codec,
    ) {}

    public function ping(string $endpoint, string $source, string $target): SendResult
    {
        try {
            $address = $this->urlGuard->assertAllowed($endpoint);
        } catch (MentionRejected $rejected) {
            return new SendResult(SendStatus::Rejected, 'Endpoint not allowed: ' . $rejected->getMessage());
        }

        try {
            $response = $this->requestFactory->request($endpoint, 'POST', OutgoingRequest::options([
                RequestOptions::HEADERS => ['Content-Type' => 'text/xml; charset=utf-8'],
                RequestOptions::BODY => $this->codec->encodeMethodCall(PingbackEndpoint::METHOD, [$source, $target]),
            ], $address));
        } catch (GuzzleException $exception) {
            return new SendResult(SendStatus::Failed, 'Endpoint not reachable: ' . $exception->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            return new SendResult(SendStatus::Failed, sprintf('Endpoint responded with HTTP %d', $response->getStatusCode()));
        }

        try {
            $result = $this->codec->decodeMethodResponse(substr((string) $response->getBody(), 0, self::MAX_RESPONSE_BYTES));
        } catch (\InvalidArgumentException $exception) {
            return new SendResult(SendStatus::Failed, 'Invalid XML-RPC response: ' . $exception->getMessage());
        }

        if (! $result->isFault()) {
            return new SendResult(SendStatus::Sent, is_scalar($result->value) ? (string) $result->value : '');
        }

        return new SendResult($this->statusForFault((int) $result->faultCode), $result->faultString, (int) $result->faultCode);
    }

    private function statusForFault(int $faultCode): SendStatus
    {
        return match (true) {
            $faultCode === PingbackEndpoint::FAULT_ALREADY_REGISTERED => SendStatus::AlreadyRegistered,
            in_array($faultCode, self::FINAL_FAULTS, true) => SendStatus::Rejected,
            // 0 (generic), 50 (upstream error) and unknown codes may be temporary
            default => SendStatus::Failed,
        };
    }
}
