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
use Webicorns\Pongback\Html\HtmlDocumentLoader;
use Webicorns\Pongback\Mention\UrlNormalizer;

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
        $document = HtmlDocumentLoader::load($html);
        if (! $document instanceof \DOMDocument) {
            return null;
        }

        $link = $this->findLink($document, $sourceUrl, UrlNormalizer::normalize($targetUrl));
        if (! $link instanceof \DOMElement) {
            return null;
        }

        return new SourceDocument(
            $this->extractTitle($document),
            $this->extractExcerpt($link),
        );
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
            if (UrlNormalizer::normalize($absolute) === $normalizedTarget) {
                return $anchor;
            }
        }

        return null;
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
