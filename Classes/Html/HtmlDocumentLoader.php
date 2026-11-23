<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Html;

/**
 * Parses untrusted HTML of remote or own pages without network access and without libxml noise.
 */
final class HtmlDocumentLoader
{
    private const NON_CONTENT_ELEMENTS = ['script', 'style', 'noscript', 'template'];

    /**
     * @return \DOMDocument|null null if the HTML cannot be parsed at all
     */
    public static function load(string $html): ?\DOMDocument
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

        // Markup in scripts and styles is no content, e.g. links written by JavaScript.
        foreach (self::NON_CONTENT_ELEMENTS as $tagName) {
            // Copy the live list first, removing nodes while iterating would skip elements.
            foreach (iterator_to_array($document->getElementsByTagName($tagName)) as $element) {
                $element->parentNode?->removeChild($element);
            }
        }

        return $document;
    }
}
