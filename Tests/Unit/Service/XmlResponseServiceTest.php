<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webicorns\Pongback\Service\XmlResponseService;

final class XmlResponseServiceTest extends TestCase
{
    #[Test]
    public function createsReadableXmlResponses(): void
    {
        $response = XmlResponseService::xmlSuccessResponse('registered');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
        // getContents() reads from the current position, so the body must be rewound.
        self::assertStringContainsString('<string>registered</string>', $response->getBody()->getContents());
    }

    #[Test]
    public function transportsFaultsWithHttp200(): void
    {
        $response = XmlResponseService::xmlErrorResponse(17, 'No link');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<int>17</int>', $response->getBody()->getContents());
    }
}
