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

use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;

/**
 * Checks whether a source HTML document links to the target and extracts title and excerpt as plain text.
 */
readonly class SourceInspector
{
    public const MAX_TITLE_LENGTH = 255;
    public const MAX_EXCERPT_LENGTH = 300;

    private const BLOCK_ELEMENTS = ['p', 'li', 'blockquote', 'dd', 'td', 'figcaption', 'article', 'section', 'div', 'body'];

    /**
     * @return SourceDocument|null null if the document does not link to $targetUrl
     */
    public function inspect(string $html, string $sourceUrl, string $targetUrl): ?SourceDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            // The XML declaration makes libxml treat the input as UTF-8; LIBXML_NONET forbids network access.
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($loaded === false) {
            return null;
        }

        $this->removeNonContentElements($document);

        $link = $this->findLink($document, $sourceUrl, self::normalizeUrl($targetUrl));
        if (! $link instanceof \DOMElement) {
            return null;
        }

        return new SourceDocument(
            $this->extractTitle($document),
            $this->extractExcerpt($link),
        );
    }

    public static function normalizeUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! isset($parts['host'])) {
            return trim($url);
        }

        $normalized = strtolower($parts['scheme'] ?? 'http') . '://' . strtolower($parts['host']);
        if (isset($parts['port'])) {
            $normalized .= ':' . $parts['port'];
        }

        $normalized .= rtrim($parts['path'] ?? '', '/');
        if (($parts['query'] ?? '') !== '') {
            $normalized .= '?' . $parts['query'];
        }

        return $normalized;
    }

    private function findLink(\DOMDocument $document, string $sourceUrl, string $normalizedTarget): ?\DOMElement
    {
        $base = Utils::uriFor($sourceUrl);
        foreach ($document->getElementsByTagName('a') as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            $absolute = (string) UriResolver::resolve($base, Utils::uriFor($href));
            if (self::normalizeUrl($absolute) === $normalizedTarget) {
                return $anchor;
            }
        }

        return null;
    }

    private function removeNonContentElements(\DOMDocument $document): void
    {
        foreach (['script', 'style', 'noscript', 'template'] as $tagName) {
            // Copy the live list first, removing nodes while iterating would skip elements.
            foreach (iterator_to_array($document->getElementsByTagName($tagName)) as $element) {
                $element->parentNode?->removeChild($element);
            }
        }
    }

    private function extractTitle(\DOMDocument $document): string
    {
        $title = $document->getElementsByTagName('title')->item(0);
        // <title> is raw text in HTML5, depending on the libxml version markup inside stays literal text.
        $text = strip_tags($title instanceof \DOMNode ? $title->textContent : '');

        return $this->limit($this->plainText($text), self::MAX_TITLE_LENGTH);
    }

    private function extractExcerpt(\DOMElement $link): string
    {
        $block = $link;
        while ($block->parentNode instanceof \DOMElement && ! in_array(strtolower($block->nodeName), self::BLOCK_ELEMENTS, true)) {
            $block = $block->parentNode;
        }

        $text = $this->plainText($block->textContent);
        $length = mb_strlen($text);
        if ($length <= self::MAX_EXCERPT_LENGTH) {
            return $text;
        }

        // Center a window around the link text, keeping room for a leading and a trailing ellipsis.
        $window = self::MAX_EXCERPT_LENGTH - 2;
        $linkText = $this->plainText($link->textContent);
        $position = $linkText === '' ? false : mb_strpos($text, $linkText);
        $start = ($position === false ? 0 : $position) - intdiv($window - mb_strlen($linkText), 2);
        $start = max(0, min($start, $length - $window));

        return ($start > 0 ? '…' : '')
            . mb_substr($text, $start, $window)
            . ($start + $window < $length ? '…' : '');
    }

    private function plainText(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function limit(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
    }
}
