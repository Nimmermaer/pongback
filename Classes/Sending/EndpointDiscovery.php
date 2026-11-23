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
use Webicorns\Pongback\Html\LinkHeader;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\Source\FetchedSource;
use Webicorns\Pongback\Mention\Source\SourceFetcher;

/**
 * Finds the endpoint of a linked page. Webmention is preferred:
 * "Link" header, then the first <link> or <a> with rel="webmention".
 * Pingback otherwise: "X-Pingback" header, then <link rel="pingback">.
 *
 * @see https://www.w3.org/TR/webmention/#sender-discovers-receiver-webmention-endpoint
 * @see https://www.hixie.ch/specs/pingback/pingback#TOC2.3
 */
readonly class EndpointDiscovery
{
    public function __construct(
        private SourceFetcher $fetcher,
    ) {}

    /**
     * @throws MentionRejected if the page cannot be retrieved
     */
    public function discover(string $targetUrl, bool $webmention = true): ?DiscoveredEndpoint
    {
        $page = $this->fetcher->fetch($targetUrl);
        $document = HtmlDocumentLoader::load($page->html);

        if ($webmention) {
            $endpoint = LinkHeader::find($page->linkHeaders, 'webmention') ?? $this->findElement($document, ['link', 'a'], 'webmention');
            $absolute = $endpoint === null ? null : $this->absolute($page, $endpoint);
            if ($absolute !== null) {
                return new DiscoveredEndpoint(IncomingMention::PROTOCOL_WEBMENTION, $absolute);
            }
        }

        $endpoint = $page->pingbackHeader !== '' ? $page->pingbackHeader : $this->findElement($document, ['link'], 'pingback');
        // The Pingback endpoint must not be the page itself.
        $absolute = $endpoint === null || $endpoint === '' ? null : $this->absolute($page, $endpoint);

        return $absolute === null ? null : new DiscoveredEndpoint(IncomingMention::PROTOCOL_PINGBACK, $absolute);
    }

    /**
     * Relative to the URL the page was finally retrieved from; an empty reference is the page itself.
     */
    private function absolute(FetchedSource $page, string $reference): ?string
    {
        $absolute = (string) UriResolver::resolve(Utils::uriFor($page->url), Utils::uriFor($reference));
        $scheme = strtolower((string) parse_url($absolute, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $absolute : null;
    }

    /**
     * @param list<string> $tagNames
     */
    private function findElement(?\DOMDocument $document, array $tagNames, string $relation): ?string
    {
        if (! $document instanceof \DOMDocument) {
            return null;
        }

        $xpath = new \DOMXPath($document);
        $elements = $xpath->query(implode('|', array_map(static fn(string $tagName): string => '//' . $tagName . '[@href]', $tagNames)));
        foreach ($elements === false ? [] : $elements as $element) {
            if (! $element instanceof \DOMElement) {
                continue;
            }

            $relations = preg_split('/\s+/', strtolower(trim($element->getAttribute('rel'))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (in_array($relation, $relations, true)) {
                return trim($element->getAttribute('href'));
            }
        }

        return null;
    }
}
