<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Functional\Pingback;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use Webicorns\Pongback\Mention\MentionStorage;
use Webicorns\Pongback\Pingback\PingbackEndpoint;
use Webicorns\Pongback\Tests\Functional\AbstractPongbackTestCase;

/**
 * Real frontend requests through the TYPO3 middleware stack: routing, site resolution,
 * dependency injection and database, without outgoing HTTP requests.
 */
final class PingbackEndpointTest extends AbstractPongbackTestCase
{
    private const ENDPOINT = 'https://main.example/pingback.ping';

    #[Test]
    public function announcesTheEndpointOnRegularPages(): void
    {
        $this->setUpFrontendRootPage(1, ['EXT:pongback/Tests/Functional/Fixtures/page.typoscript']);

        $response = $this->executeFrontendSubRequest(new InternalRequest('https://main.example/article'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(self::ENDPOINT, $response->getHeaderLine('X-Pingback'));
        self::assertStringContainsString('<link rel="pingback" href="' . self::ENDPOINT . '">', (string) $response->getBody());
    }

    #[Test]
    public function endpointRequiresPost(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest(self::ENDPOINT));

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('POST', $response->getHeaderLine('Allow'));
    }

    #[Test]
    public function endpointAnswersWithoutTypoScriptPageObject(): void
    {
        // No sys_template at all: the endpoint must answer before TypoScript is required.
        $response = $this->post('<methodCall>');

        self::assertSame(PingbackEndpoint::FAULT_PARSE_ERROR, $this->faultCode($response));
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function rejectedPingbackProvider(): array
    {
        return [
            'target page does not exist' => ['https://blog.example/post', 'https://main.example/does-not-exist', PingbackEndpoint::FAULT_TARGET_NOT_FOUND],
            'target page is hidden' => ['https://blog.example/post', 'https://main.example/hidden', PingbackEndpoint::FAULT_TARGET_NOT_FOUND],
            'target belongs to another site' => ['https://blog.example/post', 'https://other.example/article', PingbackEndpoint::FAULT_TARGET_NOT_ALLOWED],
            'target on a foreign host' => ['https://blog.example/post', 'https://somewhere.example/article', PingbackEndpoint::FAULT_TARGET_NOT_ALLOWED],
            'target with other scheme' => ['https://blog.example/post', 'ftp://main.example/article', PingbackEndpoint::FAULT_TARGET_NOT_ALLOWED],
            // The target is valid, so this proves the request got as far as the source check without any outgoing request.
            'source is an internal address' => ['http://127.0.0.1/post', 'https://main.example/article', PingbackEndpoint::FAULT_GENERIC],
            'source and target identical' => ['https://main.example/article', 'https://main.example/article/', PingbackEndpoint::FAULT_GENERIC],
        ];
    }

    #[Test]
    #[DataProvider('rejectedPingbackProvider')]
    public function rejectsInvalidPingbacksWithTheSpecifiedFaultCode(string $source, string $target, int $expectedFault): void
    {
        self::assertSame($expectedFault, $this->faultCode($this->ping($source, $target)));
        self::assertSame(0, $this->countPingbacks(), 'Rejected pingbacks must not be stored');
    }

    #[Test]
    public function rejectsPingbacksThatAreAlreadyRegistered(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pingbacks.csv');

        $response = $this->ping('https://blog.example/already-sent', 'https://main.example/article');

        self::assertSame(PingbackEndpoint::FAULT_ALREADY_REGISTERED, $this->faultCode($response));
        self::assertSame(1, $this->countPingbacks());
    }

    private function ping(string $source, string $target): ResponseInterface
    {
        return $this->post(
            '<?xml version="1.0"?><methodCall><methodName>pingback.ping</methodName><params>'
            . '<param><value><string>' . htmlspecialchars($source) . '</string></value></param>'
            . '<param><value><string>' . htmlspecialchars($target) . '</string></value></param>'
            . '</params></methodCall>',
        );
    }

    private function post(string $body): ResponseInterface
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();

        $request = (new InternalRequest(self::ENDPOINT))
            ->withMethod('POST')
            ->withHeader('Content-Type', 'text/xml')
            ->withBody($stream);

        return $this->executeFrontendSubRequest($request);
    }

    private function faultCode(ResponseInterface $response): ?int
    {
        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('text/xml', $response->getHeaderLine('Content-Type'));

        $xml = simplexml_load_string((string) $response->getBody());
        self::assertNotFalse($xml, (string) $response->getBody());

        foreach ($xml->fault->value->struct->member ?? [] as $member) {
            if ((string) $member->name === 'faultCode') {
                return (int) $member->value->int;
            }
        }

        return null;
    }

    private function countPingbacks(): int
    {
        // Without restrictions: new pingbacks are hidden by default.
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(MentionStorage::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return (int) $queryBuilder->count('uid')->from(MentionStorage::TABLE)->executeQuery()->fetchOne();
    }
}
