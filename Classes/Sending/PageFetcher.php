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
use Webicorns\Pongback\Mention\Source\SourceFetcher;

/**
 * Downloads a page of the own installation as anonymous visitors see it.
 *
 * Unlike SourceFetcher there is no UrlGuard: the URL is generated from the site configuration,
 * and the own site may well run on an internal address.
 */
readonly class PageFetcher
{
    public function __construct(
        private RequestFactory $requestFactory,
    ) {}

    /**
     * @throws \RuntimeException if the page cannot be retrieved
     */
    public function fetch(string $url): string
    {
        try {
            // Our own site: redirects are fine, and it may run on an internal address (no UrlGuard).
            $response = $this->requestFactory->request($url, 'GET', OutgoingRequest::options([
                RequestOptions::ALLOW_REDIRECTS => ['max' => 3],
                RequestOptions::HEADERS => ['Accept' => 'text/html'],
            ]));
        } catch (GuzzleException $exception) {
            throw new \RuntimeException('Page ' . $url . ' could not be retrieved: ' . $exception->getMessage(), 1732500100, $exception);
        }

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Page %s responded with HTTP %d', $url, $response->getStatusCode()), 1732500101);
        }

        return substr((string) $response->getBody(), 0, SourceFetcher::MAX_BYTES);
    }
}
