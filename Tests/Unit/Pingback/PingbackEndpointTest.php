<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Pingback;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Mention\MentionRateLimiter;
use Webicorns\Pongback\Mention\MentionReceiverInterface;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Pingback\PingbackEndpoint;
use Webicorns\Pongback\XmlRpc\XmlRpcCodec;

final class PingbackEndpointTest extends TestCase
{
    private const SOURCE = 'https://blog.example/post';
    private const TARGET = 'https://www.target.example/article';

    private MentionReceiverInterface&MockObject $receiver;

    protected function setUp(): void
    {
        $this->receiver = $this->createMock(MentionReceiverInterface::class);
    }

    #[Test]
    public function registersPingbacks(): void
    {
        $site = $this->createSite([]);
        $this->receiver->expects(self::once())->method('receive')
            ->with(
                self::callback(static fn(IncomingMention $mention): bool => $mention->source === self::SOURCE
                    && $mention->target === self::TARGET
                    && $mention->protocol === IncomingMention::PROTOCOL_PINGBACK
                    && $mention->clientIp === '203.0.113.5'
                    && $mention->userAgent === 'WordPress/6.9'),
                $site,
            )
            ->willReturn(1);

        $response = $this->createSubject()->handle($this->request($this->pingCall(self::SOURCE, self::TARGET)), $site);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
        // getContents() reads from the current position, so the body must be rewound.
        self::assertStringContainsString('<string>Pingback from', $response->getBody()->getContents());
        $response->getBody()->rewind();
        self::assertSame(
            'Pingback from ' . self::SOURCE . ' to ' . self::TARGET . ' registered.',
            $this->decodeResponse($response)['string'],
        );
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function invalidCallProvider(): array
    {
        return [
            'not xml' => ['nonsense', PingbackEndpoint::FAULT_PARSE_ERROR],
            'other method' => [self::methodCall('system.listMethods', []), PingbackEndpoint::FAULT_METHOD_NOT_FOUND],
            'missing target' => [self::methodCall('pingback.ping', ['<string>' . self::SOURCE . '</string>']), PingbackEndpoint::FAULT_INVALID_PARAMS],
            'struct param' => [
                self::methodCall('pingback.ping', ['<struct></struct>', '<string>' . self::TARGET . '</string>']),
                PingbackEndpoint::FAULT_INVALID_PARAMS,
            ],
            'too large' => [str_repeat(' ', 70000), PingbackEndpoint::FAULT_PARSE_ERROR],
        ];
    }

    #[Test]
    #[DataProvider('invalidCallProvider')]
    public function answersInvalidCallsWithFaults(string $body, int $expectedFault): void
    {
        $this->receiver->expects(self::never())->method('receive');

        $response = $this->createSubject()->handle($this->request($body), $this->createSite([]));

        self::assertSame(200, $response->getStatusCode(), 'XML-RPC faults are transported with HTTP 200');
        self::assertSame($expectedFault, $this->decodeResponse($response)['faultCode']);
    }

    /**
     * @return array<string, array{RejectionReason, int}>
     */
    public static function rejectionProvider(): array
    {
        return [
            'invalid request' => [RejectionReason::InvalidRequest, 0],
            'source not found' => [RejectionReason::SourceNotFound, 16],
            'source gone' => [RejectionReason::SourceGone, 16],
            'no link' => [RejectionReason::NoLinkToTarget, 17],
            'target not found' => [RejectionReason::TargetNotFound, 32],
            'target not allowed' => [RejectionReason::TargetNotAllowed, 33],
            'already registered' => [RejectionReason::AlreadyRegistered, 48],
            'access denied' => [RejectionReason::AccessDenied, 49],
            'upstream error' => [RejectionReason::UpstreamError, 50],
        ];
    }

    #[Test]
    #[DataProvider('rejectionProvider')]
    public function mapsRejectionsToPingbackFaultCodes(RejectionReason $reason, int $expectedFault): void
    {
        $this->receiver->method('receive')->willThrowException(new MentionRejected($reason, 'Message for the client'));

        $fault = $this->decodeResponse(
            $this->createSubject()->handle($this->request($this->pingCall(self::SOURCE, self::TARGET)), $this->createSite([])),
        );

        self::assertSame($expectedFault, $fault['faultCode']);
        self::assertSame('Message for the client', $fault['faultString']);
    }

    #[Test]
    public function limitsPingbacksPerClient(): void
    {
        $site = $this->createSite(['pongback' => ['receive' => ['rateLimit' => 2]]]);
        $this->receiver->expects(self::exactly(2))->method('receive')->willReturn(1);
        $subject = $this->createSubject();

        $subject->handle($this->request($this->pingCall(self::SOURCE, self::TARGET)), $site);
        $subject->handle($this->request($this->pingCall(self::SOURCE . '2', self::TARGET)), $site);
        $third = $subject->handle($this->request($this->pingCall(self::SOURCE . '3', self::TARGET)), $site);

        self::assertSame(PingbackEndpoint::FAULT_ACCESS_DENIED, $this->decodeResponse($third)['faultCode']);
    }

    private function createSubject(): PingbackEndpoint
    {
        return new PingbackEndpoint(
            new XmlRpcCodec(),
            $this->receiver,
            new MentionRateLimiter(new InMemoryStorage()),
            new NullLogger(),
        );
    }

    private function pingCall(string $source, string $target): string
    {
        return self::methodCall('pingback.ping', ['<string>' . $source . '</string>', '<string>' . $target . '</string>']);
    }

    /**
     * @param list<string> $values
     */
    private static function methodCall(string $method, array $values): string
    {
        $params = implode('', array_map(static fn(string $value): string => '<param><value>' . $value . '</value></param>', $values));
        return '<?xml version="1.0"?><methodCall><methodName>' . $method . '</methodName><params>' . $params . '</params></methodCall>';
    }

    private function request(string $body): ServerRequest
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();

        return (new ServerRequest('https://www.target.example/pingback.ping', 'POST', $stream, ['User-Agent' => 'WordPress/6.9'], ['REMOTE_ADDR' => '203.0.113.5']));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(ResponseInterface $response): array
    {
        $xml = simplexml_load_string((string) $response->getBody());
        self::assertNotFalse($xml);

        if (isset($xml->fault)) {
            $fault = [];
            foreach ($xml->fault->value->struct->member as $member) {
                $value = $member->value->children()[0];
                $fault[(string) $member->name] = $value->getName() === 'int' ? (int) $value : (string) $value;
            }

            return $fault;
        }

        return ['string' => (string) $xml->params->param->value->string];
    }

    /**
     * @param array<string, mixed> $settingsTree
     */
    private function createSite(array $settingsTree): Site&MockObject
    {
        $site = $this->createMock(Site::class);
        $site->method('getIdentifier')->willReturn('main');
        $site->method('getSettings')->willReturn(SiteSettings::createFromSettingsTree($settingsTree));

        return $site;
    }

    #[Test]
    public function trimsParametersAndLimitsTheUserAgent(): void
    {
        $this->receiver->expects(self::once())->method('receive')
            ->with(self::callback(static fn(IncomingMention $mention): bool => $mention->source === self::SOURCE
                && $mention->target === self::TARGET
                && $mention->userAgent === str_repeat('u', 255)))
            ->willReturn(1);

        $request = $this->request($this->pingCall(' ' . self::SOURCE . "\n", "\t" . self::TARGET . ' '))
            ->withHeader('User-Agent', str_repeat('u', 300));

        $this->createSubject()->handle($request, $this->createSite([]));
    }

    #[Test]
    public function determinesTheClientIpViaNormalizedParams(): void
    {
        // NormalizedParams knows about trusted reverse proxies, REMOTE_ADDR would be the proxy.
        $normalizedParams = $this->createMock(NormalizedParams::class);
        $normalizedParams->method('getRemoteAddress')->willReturn('198.51.100.7');

        $this->receiver->expects(self::once())->method('receive')
            ->with(self::callback(static fn(IncomingMention $mention): bool => $mention->clientIp === '198.51.100.7'))
            ->willReturn(1);

        $request = $this->request($this->pingCall(self::SOURCE, self::TARGET))->withAttribute('normalizedParams', $normalizedParams);
        $this->createSubject()->handle($request, $this->createSite([]));
    }

    #[Test]
    public function acceptsRequestBodiesUpToTheLimit(): void
    {
        $call = $this->pingCall(self::SOURCE, self::TARGET);
        $this->receiver->expects(self::once())->method('receive')->willReturn(1);

        $response = $this->createSubject()->handle($this->request(str_pad($call, 65536)), $this->createSite([]));

        self::assertArrayNotHasKey('faultCode', $this->decodeResponse($response));
    }

    #[Test]
    public function logsRejections(): void
    {
        $this->receiver->method('receive')->willThrowException(new MentionRejected(RejectionReason::NoLinkToTarget, 'No link'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Pingback rejected', [
            'reason' => 'no_link_to_target',
            'source' => self::SOURCE,
            'target' => self::TARGET,
        ]);

        $subject = new PingbackEndpoint(new XmlRpcCodec(), $this->receiver, new MentionRateLimiter(new InMemoryStorage()), $logger);
        $subject->handle($this->request($this->pingCall(self::SOURCE, self::TARGET)), $this->createSite([]));
    }
}
