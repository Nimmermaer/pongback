<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Http;

use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;

/**
 * A response with a text body: plain text by default, XML for XML-RPC.
 */
final class TextResponse extends Response
{
    public function __construct(string $text, int $status = 200, string $contentType = 'text/plain')
    {
        $body = new Stream('php://temp', 'rw');
        $body->write($text);
        $body->rewind();

        parent::__construct($body, $status, ['Content-Type' => $contentType . '; charset=utf-8']);
    }
}
