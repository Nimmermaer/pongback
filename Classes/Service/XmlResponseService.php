<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Service;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use Webicorns\Pongback\XmlRpc\XmlRpcCodec;

class XmlResponseService
{
    /**
     * XML-RPC faults are transported with HTTP 200, the error is part of the payload.
     */
    public static function xmlErrorResponse(int $faultCode, string $faultString): ResponseInterface
    {
        return self::xmlResponse((new XmlRpcCodec())->encodeFault($faultCode, $faultString));
    }

    public static function xmlSuccessResponse(string $value): ResponseInterface
    {
        return self::xmlResponse((new XmlRpcCodec())->encodeResponse($value));
    }

    private static function xmlResponse(string $xml): ResponseInterface
    {
        $body = new Stream('php://temp', 'rw');
        $body->write($xml);
        $body->rewind();

        return (new Response())
            ->withHeader('Content-Type', 'text/xml; charset=utf-8')
            ->withBody($body);
    }
}
