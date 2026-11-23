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

use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Webicorns\Pongback\Html\HtmlDocumentLoader;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\Source\SourceFetcher;

/**
 * Finds the Pingback endpoint of a linked page: "X-Pingback" header first, then <link rel="pingback">.
 *
 * @see https://www.hixie.ch/specs/pingback/pingback#TOC2.3
 */
readonly class EndpointDiscovery
{
    public function __construct(
        private SourceFetcher $fetcher,
    ) {}

    /**
     * @return string|null absolute endpoint URL, null if the page announces none
     *
     * @throws MentionRejected if the page cannot be retrieved
     */
    public function discover(string $targetUrl): ?string
    {
        $page = $this->fetcher->fetch($targetUrl);

        $endpoint = $page->pingbackHeader !== '' ? $page->pingbackHeader : $this->findLinkElement($page->html);
        if ($endpoint === null) {
            return null;
        }

        $absolute = (string) UriResolver::resolve(Utils::uriFor($page->url), Utils::uriFor($endpoint));
        $scheme = strtolower((string) parse_url($absolute, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $absolute : null;
    }

    private function findLinkElement(string $html): ?string
    {
        $document = HtmlDocumentLoader::load($html);
        if (! $document instanceof \DOMDocument) {
            return null;
        }

        foreach ($document->getElementsByTagName('link') as $link) {
            $relations = preg_split('/\s+/', strtolower(trim($link->getAttribute('rel')))) ?: [];
            $href = trim($link->getAttribute('href'));
            if ($href !== '' && in_array('pingback', $relations, true)) {
                return $href;
            }
        }

        return null;
    }
}
