<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use Webicorns\Pongback\Http\ClientIp;
use Webicorns\Pongback\Http\OutgoingRequest;
use Webicorns\Pongback\Http\TextResponse;
use Webicorns\Pongback\Mention\Source\CheckedAddress;
use Webicorns\Pongback\Utility\Constants;

final class HttpHelpersTest extends TestCase
{
    #[Test]
    public function outgoingRequestsAreStrictByDefault(): void
    {
        self::assertSame([
            'allow_redirects' => false,
            'connect_timeout' => 5,
            'timeout' => 10,
            'http_errors' => false,
            'headers' => ['User-Agent' => Constants::USER_AGENT],
        ], OutgoingRequest::options());
    }

    #[Test]
    public function outgoingRequestsMergeHeadersAndPinTheAddress(): void
    {
        $options = OutgoingRequest::options(
            ['allow_redirects' => ['max' => 3], 'headers' => ['Accept' => 'text/html'], 'stream' => true],
            new CheckedAddress('blog.example', 443, ['93.184.215.14']),
        );

        self::assertSame(['max' => 3], $options['allow_redirects']);
        self::assertTrue($options['stream']);
        self::assertSame(['User-Agent' => Constants::USER_AGENT, 'Accept' => 'text/html'], $options['headers']);
        self::assertSame([\CURLOPT_RESOLVE => ['blog.example:443:93.184.215.14']], $options['curl']);
    }

    #[Test]
    public function textResponsesCarryTheirContentType(): void
    {
        $plain = new TextResponse('Hello', 405);
        $xml = new TextResponse('<x/>', 200, 'text/xml');

        self::assertSame(405, $plain->getStatusCode());
        self::assertSame('Hello', (string) $plain->getBody());
        self::assertSame('text/plain; charset=utf-8', $plain->getHeaderLine('Content-Type'));
        self::assertSame(200, $xml->getStatusCode());
        self::assertSame('<x/>', (string) $xml->getBody());
        self::assertSame('text/xml; charset=utf-8', $xml->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function clientIpPrefersNormalizedParams(): void
    {
        $normalizedParams = $this->createMock(NormalizedParams::class);
        $normalizedParams->method('getRemoteAddress')->willReturn('203.0.113.5');
        $request = new ServerRequest('https://main.example/', 'POST', 'php://input', [], ['REMOTE_ADDR' => '10.0.0.1']);

        self::assertSame('203.0.113.5', ClientIp::of($request->withAttribute('normalizedParams', $normalizedParams)));
        self::assertSame('10.0.0.1', ClientIp::of($request));
        self::assertSame('', ClientIp::of(new ServerRequest('https://main.example/', 'POST', 'php://input', [], ['REMOTE_ADDR' => ['x']])));
        self::assertSame('', ClientIp::of(new ServerRequest('https://main.example/')));
    }
}
