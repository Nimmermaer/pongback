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

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Utility\Constants;

/**
 * Downloads the HTML of a mention source with strict limits.
 *
 * Redirects are followed manually so that every hop is checked by the UrlGuard and the
 * connection is pinned to the checked IP address (no DNS rebinding between check and request).
 */
readonly class SourceFetcher
{
    public const MAX_BYTES = 1048576;
    public const MAX_REDIRECTS = 3;
    public const TIMEOUT = 10;

    public function __construct(
        private RequestFactory $requestFactory,
        private UrlGuard $urlGuard,
    ) {}

    /**
     * @throws MentionRejected
     */
    public function fetch(string $url): FetchedSource
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $response = $this->send($url);

            $location = $this->getRedirectLocation($url, $response);
            if ($location !== null) {
                $url = $location;
                continue;
            }

            $this->assertHtmlDocument($response);

            return new FetchedSource(
                $url,
                $this->readLimited($response->getBody()),
                trim($response->getHeaderLine('X-Pingback')),
            );
        }

        throw new MentionRejected(RejectionReason::UpstreamError, 'The source URI redirects too often.');
    }

    private function send(string $url): ResponseInterface
    {
        $address = $this->urlGuard->assertAllowed($url);

        try {
            return $this->requestFactory->request($url, 'GET', $this->buildOptions($address));
        } catch (ConnectException $exception) {
            throw new MentionRejected(RejectionReason::SourceNotFound, 'The source URI could not be reached.', $exception);
        } catch (GuzzleException $exception) {
            throw new MentionRejected(RejectionReason::UpstreamError, 'The source URI could not be retrieved.', $exception);
        }
    }

    private function getRedirectLocation(string $url, ResponseInterface $response): ?string
    {
        $status = $response->getStatusCode();
        if ($status < 300 || $status >= 400 || ! $response->hasHeader('Location')) {
            return null;
        }

        return (string) UriResolver::resolve(Utils::uriFor($url), Utils::uriFor($response->getHeaderLine('Location')));
    }

    private function assertHtmlDocument(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status === 404 || $status === 410) {
            throw new MentionRejected(RejectionReason::SourceNotFound, 'The source URI does not exist.');
        }

        if ($status < 200 || $status >= 300) {
            throw new MentionRejected(RejectionReason::UpstreamError, sprintf('The source URI responded with HTTP %d.', $status));
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'html')) {
            throw new MentionRejected(RejectionReason::NoLinkToTarget, 'The source URI is no HTML document.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOptions(CheckedAddress $address): array
    {
        $options = [
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::TIMEOUT => self::TIMEOUT,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::STREAM => true,
            RequestOptions::HEADERS => [
                'Accept' => 'text/html,application/xhtml+xml;q=0.9',
                'User-Agent' => Constants::USER_AGENT,
            ],
        ];

        return $options + $address->pinningRequestOptions();
    }

    private function readLimited(StreamInterface $body): string
    {
        $html = '';
        while (! $body->eof() && strlen($html) < self::MAX_BYTES) {
            $html .= $body->read(min(8192, self::MAX_BYTES - strlen($html)));
        }

        $body->close();
        return $html;
    }
}
