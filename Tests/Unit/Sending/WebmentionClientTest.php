<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Sending;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use Webicorns\Pongback\Mention\Source\UrlGuard;
use Webicorns\Pongback\Sending\PingStatus;
use Webicorns\Pongback\Sending\WebmentionClient;
use Webicorns\Pongback\Utility\Constants;

final class WebmentionClientTest extends TestCase
{
    private const ENDPOINT = 'https://blog.example/webmention?token=abc';
    private const SOURCE = 'https://my-site.example/post';
    private const TARGET = 'https://blog.example/article';

    /**
     * @var list<array{url: string, method: string, options: array<string, mixed>}>
     */
    private array $requests = [];

    #[Test]
    public function postsSourceAndTargetFormEncoded(): void
    {
        $result = $this->createSubject($this->response(202, 'Accepted'))->send(self::ENDPOINT, self::SOURCE, self::TARGET);

        self::assertSame(PingStatus::Sent, $result->status);
        self::assertSame('HTTP 202 Accepted', $result->message);

        self::assertCount(1, $this->requests);
        [$request] = $this->requests;
        self::assertSame(self::ENDPOINT, $request['url'], 'The query string of the endpoint is kept');
        self::assertSame('POST', $request['method']);
        $options = $request['options'];
        self::assertSame(['source' => self::SOURCE, 'target' => self::TARGET], $options['form_params']);
        self::assertFalse($options['allow_redirects']);
        self::assertFalse($options['http_errors']);
        self::assertSame(5, $options['connect_timeout']);
        self::assertSame(WebmentionClient::TIMEOUT, $options['timeout']);
        self::assertSame(Constants::USER_AGENT, $options['headers']['User-Agent']);
        self::assertSame([\CURLOPT_RESOLVE => ['blog.example:443:93.184.215.14']], $options['curl']);
    }

    /**
     * @return array<string, array{int, PingStatus}>
     */
    public static function statusProvider(): array
    {
        return [
            'HTTP 200' => [200, PingStatus::Sent],
            'HTTP 201 created' => [201, PingStatus::Sent],
            'HTTP 299' => [299, PingStatus::Sent],
            'HTTP 300' => [300, PingStatus::Failed],
            'HTTP 301 redirect' => [301, PingStatus::Failed],
            'HTTP 199' => [199, PingStatus::Failed],
            'HTTP 400 bad request' => [400, PingStatus::Rejected],
            'HTTP 404' => [404, PingStatus::Rejected],
            'HTTP 499' => [499, PingStatus::Rejected],
            'HTTP 429 too many requests' => [429, PingStatus::Failed],
            'HTTP 500' => [500, PingStatus::Failed],
            'HTTP 503' => [503, PingStatus::Failed],
        ];
    }

    #[Test]
    #[DataProvider('statusProvider')]
    public function mapsHttpStatusCodes(int $httpStatus, PingStatus $expected): void
    {
        self::assertSame($expected, $this->createSubject($this->response($httpStatus, ''))->send(self::ENDPOINT, self::SOURCE, self::TARGET)->status);
    }

    #[Test]
    public function keepsAShortMessage(): void
    {
        $result = $this->createSubject($this->response(400, '  ' . str_repeat('x', 300) . '  '))->send(self::ENDPOINT, self::SOURCE, self::TARGET);

        self::assertSame('HTTP 400 ' . str_repeat('x', 255), $result->message);
        self::assertSame('HTTP 201', $this->createSubject($this->response(201, ''))->send(self::ENDPOINT, self::SOURCE, self::TARGET)->message);
    }

    #[Test]
    public function neverContactsInternalEndpoints(): void
    {
        $result = $this->createSubject()->send('https://intranet.example/webmention', self::SOURCE, self::TARGET);

        self::assertSame(PingStatus::Rejected, $result->status);
        self::assertMatchesRegularExpression('/^Endpoint not allowed: \S/', $result->message);
        self::assertSame([], $this->requests);
    }

    #[Test]
    public function reportsUnreachableEndpointsAsTemporaryFailure(): void
    {
        $result = $this->createSubject(new ConnectException('Connection refused', new Request('POST', self::ENDPOINT)))
            ->send(self::ENDPOINT, self::SOURCE, self::TARGET);

        self::assertSame(PingStatus::Failed, $result->status);
        self::assertSame('Endpoint not reachable: Connection refused', $result->message);
    }

    private function createSubject(ResponseInterface|\Throwable|null $response = null): WebmentionClient
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willReturnCallback(
            function (string $url, string $method, array $options) use ($response): ResponseInterface {
                $this->requests[] = ['url' => $url, 'method' => $method, 'options' => $options];
                if ($response instanceof \Throwable) {
                    throw $response;
                }

                return $response ?? throw new \LogicException('Unexpected request to ' . $url, 1732700100);
            },
        );

        $dns = ['blog.example' => ['93.184.215.14'], 'intranet.example' => ['10.0.0.1']];

        return new WebmentionClient($requestFactory, new UrlGuard(static fn(string $host): array => $dns[$host] ?? []));
    }

    private function response(int $status, string $body): Response
    {
        return new Response($status, [], $body);
    }
}
