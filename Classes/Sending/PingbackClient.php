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
use Webicorns\Pongback\Pingback\PingbackEndpoint;
use Webicorns\Pongback\Utility\Constants;
use Webicorns\Pongback\XmlRpc\XmlRpcCodec;

/**
 * Sends pingback.ping(source, target) to the endpoint of a linked site.
 */
readonly class PingbackClient
{
    public const TIMEOUT = 10;
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

    public function ping(string $endpoint, string $source, string $target): PingResult
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
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'User-Agent' => Constants::USER_AGENT,
                ],
                RequestOptions::BODY => $this->codec->encodeMethodCall(PingbackEndpoint::METHOD, [$source, $target]),
            ] + $address->pinningRequestOptions());
        } catch (GuzzleException $exception) {
            return new PingResult(PingStatus::Failed, 'Endpoint not reachable: ' . $exception->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            return new PingResult(PingStatus::Failed, sprintf('Endpoint responded with HTTP %d', $response->getStatusCode()));
        }

        try {
            $result = $this->codec->decodeMethodResponse(substr((string) $response->getBody(), 0, self::MAX_RESPONSE_BYTES));
        } catch (\InvalidArgumentException $exception) {
            return new PingResult(PingStatus::Failed, 'Invalid XML-RPC response: ' . $exception->getMessage());
        }

        if (! $result->isFault()) {
            return new PingResult(PingStatus::Sent, is_scalar($result->value) ? (string) $result->value : '');
        }

        return new PingResult($this->statusForFault((int) $result->faultCode), $result->faultString, (int) $result->faultCode);
    }

    private function statusForFault(int $faultCode): PingStatus
    {
        return match (true) {
            $faultCode === PingbackEndpoint::FAULT_ALREADY_REGISTERED => PingStatus::AlreadyRegistered,
            in_array($faultCode, self::FINAL_FAULTS, true) => PingStatus::Rejected,
            // 0 (generic), 50 (upstream error) and unknown codes may be temporary
            default => PingStatus::Failed,
        };
    }
}
