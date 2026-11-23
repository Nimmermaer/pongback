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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use Webicorns\Pongback\Mention\Source\UrlGuard;
use Webicorns\Pongback\Pingback\PingbackEndpoint;
use Webicorns\Pongback\Sending\PingbackClient;
use Webicorns\Pongback\Sending\PingStatus;
use Webicorns\Pongback\Utility\Constants;
use Webicorns\Pongback\XmlRpc\XmlRpcCodec;

final class PingbackClientTest extends TestCase
{
    private const ENDPOINT = 'https://blog.example/xmlrpc.php';
    private const SOURCE = 'https://my-site.example/post';
    private const TARGET = 'https://blog.example/article';

    /**
     * @var list<array{url: string, method: string, options: array<string, mixed>}>
     */
    private array $requests = [];

    #[Test]
    public function postsAnXmlRpcCallWithStrictOptions(): void
    {
        $result = $this->createSubject($this->xml((new XmlRpcCodec())->encodeResponse('Thanks!')))
            ->ping(self::ENDPOINT, self::SOURCE, self::TARGET);

        self::assertSame(PingStatus::Sent, $result->status);
        self::assertSame('Thanks!', $result->message);
        self::assertSame(0, $result->faultCode);

        self::assertCount(1, $this->requests);
        [$request] = $this->requests;
        self::assertSame(self::ENDPOINT, $request['url']);
        self::assertSame('POST', $request['method']);

        $options = $request['options'];
        self::assertFalse($options['allow_redirects']);
        self::assertFalse($options['http_errors']);
        self::assertSame(5, $options['connect_timeout']);
        self::assertSame(PingbackClient::TIMEOUT, $options['timeout']);
        self::assertSame('text/xml; charset=utf-8', $options['headers']['Content-Type']);
        self::assertSame(Constants::USER_AGENT, $options['headers']['User-Agent']);
        self::assertSame([\CURLOPT_RESOLVE => ['blog.example:443:93.184.215.14']], $options['curl']);

        $call = (new XmlRpcCodec())->decodeMethodCall($options['body']);
        self::assertSame(PingbackEndpoint::METHOD, $call->methodName);
        self::assertSame([self::SOURCE, self::TARGET], $call->params);
    }

    #[Test]
    public function ignoresNonScalarSuccessValues(): void
    {
        $body = '<?xml version="1.0"?><methodResponse><params><param><value><array><data/></array></value></param></params></methodResponse>';

        $result = $this->createSubject($this->xml($body))->ping(self::ENDPOINT, self::SOURCE, self::TARGET);

        self::assertSame(PingStatus::Sent, $result->status);
        self::assertSame('', $result->message);
    }

    #[Test]
    public function convertsScalarSuccessValuesToText(): void
    {
        $body = '<?xml version="1.0"?><methodResponse><params><param><value><int>1</int></value></param></params></methodResponse>';

        self::assertSame('1', $this->createSubject($this->xml($body))->ping(self::ENDPOINT, self::SOURCE, self::TARGET)->message);
    }

    #[Test]
    public function neverContactsInternalEndpoints(): void
    {
        $result = $this->createSubject()->ping('https://intranet.example/xmlrpc.php', self::SOURCE, self::TARGET);

        self::assertSame(PingStatus::Rejected, $result->status);
        self::assertMatchesRegularExpression('/^Endpoint not allowed: \S/', $result->message);
        self::assertSame([], $this->requests);
    }

    #[Test]
    public function reportsUnreachableEndpointsAsTemporaryFailure(): void
    {
        $result = $this->createSubject(new ConnectException('Connection refused', new Request('POST', self::ENDPOINT)))
            ->ping(self::ENDPOINT, self::SOURCE, self::TARGET);

        self::assertSame(PingStatus::Failed, $result->status);
        self::assertSame('Endpoint not reachable: Connection refused', $result->message);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function httpErrorProvider(): array
    {
        return [
            'redirect' => [301],
            'not found' => [404],
            'server error' => [503],
            'created' => [201],
        ];
    }

    #[Test]
    #[DataProvider('httpErrorProvider')]
    public function reportsOtherHttpStatusCodesAsTemporaryFailure(int $status): void
    {
        $result = $this->createSubject(new Response(null, $status))->ping(self::ENDPOINT, self::SOURCE, self::TARGET);

        self::assertSame(PingStatus::Failed, $result->status);
        self::assertSame(sprintf('Endpoint responded with HTTP %d', $status), $result->message);
    }

    #[Test]
    public function reportsInvalidResponsesAsTemporaryFailure(): void
    {
        $result = $this->createSubject($this->xml('<html>Not XML-RPC</html>'))->ping(self::ENDPOINT, self::SOURCE, self::TARGET);

        self::assertSame(PingStatus::Failed, $result->status);
        self::assertMatchesRegularExpression('/^Invalid XML-RPC response: \S/', $result->message);
    }

    #[Test]
    public function readsAtMostSixtyFourKibibytesOfTheResponse(): void
    {
        // A valid response padded with whitespace after the root element: cut off, it is no longer valid.
        $body = (new XmlRpcCodec())->encodeResponse('ok');
        $padded = str_replace('<methodResponse>', '<methodResponse>' . str_repeat(' ', 65536), $body);

        self::assertSame(PingStatus::Sent, $this->createSubject($this->xml($body))->ping(self::ENDPOINT, self::SOURCE, self::TARGET)->status);
        self::assertSame(PingStatus::Failed, $this->createSubject($this->xml($padded))->ping(self::ENDPOINT, self::SOURCE, self::TARGET)->status);
    }

    /**
     * @return array<string, array{int, PingStatus}>
     */
    public static function faultProvider(): array
    {
        return [
            'generic' => [PingbackEndpoint::FAULT_GENERIC, PingStatus::Failed],
            'source not found' => [PingbackEndpoint::FAULT_SOURCE_NOT_FOUND, PingStatus::Rejected],
            'no link to target' => [PingbackEndpoint::FAULT_NO_LINK_TO_TARGET, PingStatus::Rejected],
            'target not found' => [PingbackEndpoint::FAULT_TARGET_NOT_FOUND, PingStatus::Rejected],
            'target not allowed' => [PingbackEndpoint::FAULT_TARGET_NOT_ALLOWED, PingStatus::Rejected],
            'already registered' => [PingbackEndpoint::FAULT_ALREADY_REGISTERED, PingStatus::AlreadyRegistered],
            'access denied' => [PingbackEndpoint::FAULT_ACCESS_DENIED, PingStatus::Rejected],
            'upstream error' => [PingbackEndpoint::FAULT_UPSTREAM_ERROR, PingStatus::Failed],
            'parse error' => [PingbackEndpoint::FAULT_PARSE_ERROR, PingStatus::Failed],
            'method not found' => [PingbackEndpoint::FAULT_METHOD_NOT_FOUND, PingStatus::Rejected],
            'invalid params' => [PingbackEndpoint::FAULT_INVALID_PARAMS, PingStatus::Rejected],
            'unknown' => [4711, PingStatus::Failed],
        ];
    }

    #[Test]
    #[DataProvider('faultProvider')]
    public function mapsFaultCodes(int $faultCode, PingStatus $expected): void
    {
        $result = $this->createSubject($this->xml((new XmlRpcCodec())->encodeFault($faultCode, 'Nope')))
            ->ping(self::ENDPOINT, self::SOURCE, self::TARGET);

        self::assertSame($expected, $result->status);
        self::assertSame('Nope', $result->message);
        self::assertSame($faultCode, $result->faultCode);
    }

    private function createSubject(ResponseInterface|\Throwable|null $response = null): PingbackClient
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willReturnCallback(
            function (string $url, string $method, array $options) use ($response): ResponseInterface {
                $this->requests[] = ['url' => $url, 'method' => $method, 'options' => $options];
                if ($response instanceof \Throwable) {
                    throw $response;
                }

                return $response ?? throw new \LogicException('Unexpected request to ' . $url, 1732500200);
            },
        );

        $dns = ['blog.example' => ['93.184.215.14'], 'intranet.example' => ['10.0.0.1']];

        return new PingbackClient(
            $requestFactory,
            new UrlGuard(static fn(string $host): array => $dns[$host] ?? []),
            new XmlRpcCodec(),
        );
    }

    private function xml(string $xml): Response
    {
        $body = new Stream('php://temp', 'rw');
        $body->write($xml);
        $body->rewind();

        return new Response($body, 200, ['Content-Type' => 'text/xml']);
    }
}
