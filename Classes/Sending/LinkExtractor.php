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
use Psr\Http\Message\UriInterface;
use Webicorns\Pongback\Html\HtmlDocumentLoader;
use Webicorns\Pongback\Mention\UrlNormalizer;

/**
 * Finds the external links of a rendered page that are worth a Pingback.
 *
 * Only the main content is considered when the page marks it up with <main> (or <article>),
 * so navigation and footer links are not pinged from every page.
 */
readonly class LinkExtractor
{
    public const MAX_LINKS = 50;

    private const CONTENT_ELEMENTS = ['main', 'article', 'body'];

    /**
     * @param list<string> $ownHosts hosts of the own installation, links to them are skipped
     * @return list<string> absolute http(s) URLs without fragment, in document order
     */
    public function extract(string $html, string $pageUrl, array $ownHosts): array
    {
        $document = HtmlDocumentLoader::load($html);
        if (! $document instanceof \DOMDocument) {
            return [];
        }

        $ownHosts = array_map(strtolower(...), $ownHosts);
        $base = Utils::uriFor($pageUrl);
        $links = [];
        foreach ($this->contentRoot($document)->getElementsByTagName('a') as $anchor) {
            $link = $this->toExternalUrl(trim($anchor->getAttribute('href')), $base, $ownHosts);
            if ($link !== null) {
                $links[UrlNormalizer::normalize($link)] ??= $link;
            }

            if (count($links) >= self::MAX_LINKS) {
                break;
            }
        }

        return array_values($links);
    }

    private function contentRoot(\DOMDocument $document): \DOMDocument|\DOMElement
    {
        foreach (self::CONTENT_ELEMENTS as $tagName) {
            $element = $document->getElementsByTagName($tagName)->item(0);
            if ($element instanceof \DOMElement) {
                return $element;
            }
        }

        return $document;
    }

    /**
     * @param list<string> $ownHosts
     */
    private function toExternalUrl(string $href, UriInterface $base, array $ownHosts): ?string
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        $uri = UriResolver::resolve($base, Utils::uriFor($href))->withFragment('');
        $scheme = strtolower($uri->getScheme());
        $host = strtolower(rtrim($uri->getHost(), '.'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || in_array($host, $ownHosts, true)) {
            return null;
        }

        return (string) $uri;
    }
}
