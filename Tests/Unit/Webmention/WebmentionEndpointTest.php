<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Webmention;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use Webicorns\Pongback\Mention\MentionRateLimiter;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Mention\ResolvedTarget;
use Webicorns\Pongback\Mention\TargetResolver;
use Webicorns\Pongback\Messaging\MessageQueue;
use Webicorns\Pongback\Webmention\ReceiveWebmentionMessage;
use Webicorns\Pongback\Webmention\WebmentionEndpoint;

final class WebmentionEndpointTest extends TestCase
{
    private const SOURCE = 'https://blog.example/post';
    private const TARGET = 'https://main.example/article';

    private TargetResolver&MockObject $targetResolver;
    private MentionRateLimiter&MockObject $rateLimiter;
    private Site&MockObject $site;

    private ?LoggerInterface $logger = null;

    /**
     * @var list<object>
     */
    private array $queued = [];

    protected function setUp(): void
    {
        $this->site = $this->createMock(Site::class);
        $this->site->method('getIdentifier')->willReturn('main');
        $this->site->method('getSettings')->willReturn(SiteSettings::createFromSettingsTree(['pongback' => ['receive' => ['rateLimit' => 7]]]));
        $this->targetResolver = $this->createMock(TargetResolver::class);
        $this->targetResolver->method('resolve')->willReturn(new ResolvedTarget($this->site, 2, 0));
        $this->rateLimiter = $this->createMock(MentionRateLimiter::class);
    }

    #[Test]
    public function queuesValidWebmentionsAndAnswersAccepted(): void
    {
        $this->targetResolver = $this->createMock(TargetResolver::class);
        $this->targetResolver->expects(self::once())->method('resolve')->with(self::TARGET, $this->site)->willReturn(new ResolvedTarget($this->site, 2, 0));
        $this->rateLimiter->expects(self::once())->method('consume')->with('203.0.113.5', 7);

        $response = $this->handle(['source' => ' ' . self::SOURCE . ' ', 'target' => ' ' . self::TARGET . ' ']);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('Accepted, the Webmention will be verified.', (string) $response->getBody());
        self::assertEquals(
            [new ReceiveWebmentionMessage(self::SOURCE, self::TARGET, 'main', '203.0.113.5', 'Test agent', 1)],
            $this->queued,
        );
        self::assertSame(1, $this->queued[0]->attempt);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validUrlProvider(): array
    {
        return [
            'http' => ['http://blog.example/post'],
            'upper case scheme' => ['HTTPS://blog.example/post'],
            'longest allowed' => ['https://blog.example/' . str_repeat('a', 255 - 21)],
        ];
    }

    #[Test]
    #[DataProvider('validUrlProvider')]
    public function acceptsHttpSources(string $source): void
    {
        self::assertSame(202, $this->handle(['source' => $source, 'target' => self::TARGET])->getStatusCode());
    }

    #[Test]
    public function shortensTheUserAgent(): void
    {
        $this->handle(['source' => self::SOURCE, 'target' => self::TARGET], str_repeat('u', 300));

        self::assertInstanceOf(ReceiveWebmentionMessage::class, $this->queued[0]);
        self::assertSame(str_repeat('u', 255), $this->queued[0]->userAgent);
    }

    #[Test]
    public function logsRejections(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects(self::once())->method('info')->with('Webmention rejected', [
            'reason' => 'invalid_request',
            'source' => self::SOURCE,
            'target' => 'nope',
        ]);

        $this->handle(['source' => self::SOURCE, 'target' => 'nope']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidRequestProvider(): array
    {
        return [
            'nothing' => [[]],
            'no array' => [null],
            'source missing' => [['target' => self::TARGET]],
            'target missing' => [['source' => self::SOURCE]],
            'source is an array' => [['source' => [self::SOURCE], 'target' => self::TARGET]],
            'source no URL' => [['source' => 'not a url', 'target' => self::TARGET]],
            'target not http' => [['source' => self::SOURCE, 'target' => 'ftp://main.example/article']],
            'source not http' => [['source' => 'javascript:alert(1)', 'target' => self::TARGET]],
            'source too long' => [['source' => 'https://blog.example/' . str_repeat('a', 255 - 20), 'target' => self::TARGET]],
            'http, but no valid URL' => [['source' => 'http://blog example/post', 'target' => self::TARGET]],
            'same URL' => [['source' => self::TARGET . '#comments', 'target' => self::TARGET]],
        ];
    }

    #[Test]
    #[DataProvider('invalidRequestProvider')]
    public function rejectsInvalidRequests(mixed $body): void
    {
        $this->rateLimiter->expects(self::never())->method('consume');

        $response = $this->handle($body);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame([], $this->queued);
    }

    #[Test]
    public function namesTheInvalidParameter(): void
    {
        self::assertSame('Parameter "target" must be an http(s) URL.', (string) $this->handle(['source' => self::SOURCE, 'target' => 'nope'])->getBody());
    }

    #[Test]
    public function rejectsTargetsThatAreNotPagesOfTheSite(): void
    {
        $this->targetResolver = $this->createMock(TargetResolver::class);
        $this->targetResolver->method('resolve')->willThrowException(new MentionRejected(RejectionReason::TargetNotAllowed, 'Not our page'));

        $response = $this->handle(['source' => self::SOURCE, 'target' => 'https://other.example/']);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Not our page', (string) $response->getBody());
        self::assertSame([], $this->queued);
    }

    #[Test]
    public function limitsTheRequestsPerClient(): void
    {
        $this->rateLimiter->method('consume')->willThrowException(new MentionRejected(RejectionReason::AccessDenied, 'Too many'));
        $this->targetResolver->expects(self::never())->method('resolve');

        self::assertSame(429, $this->handle(['source' => self::SOURCE, 'target' => self::TARGET])->getStatusCode());
        self::assertSame([], $this->queued);
    }

    #[Test]
    public function usesTheRemoteAddressWithoutNormalizedParams(): void
    {
        $this->rateLimiter->expects(self::once())->method('consume')->with('198.51.100.1', 7);

        $request = (new ServerRequest('https://main.example/webmention', 'POST', 'php://input', [], ['REMOTE_ADDR' => '198.51.100.1']))
            ->withParsedBody(['source' => self::SOURCE, 'target' => self::TARGET]);

        self::assertSame(202, $this->createSubject()->handle($request, $this->site)->getStatusCode());
    }

    private function handle(mixed $body, string $userAgent = 'Test agent'): ResponseInterface
    {
        $normalizedParams = $this->createMock(NormalizedParams::class);
        $normalizedParams->method('getRemoteAddress')->willReturn('203.0.113.5');
        $request = (new ServerRequest('https://main.example/webmention', 'POST'))
            ->withHeader('User-Agent', $userAgent)
            ->withAttribute('normalizedParams', $normalizedParams);
        if (is_array($body) || $body === null) {
            $request = $request->withParsedBody($body);
        }

        return $this->createSubject()->handle($request, $this->site);
    }

    private function createSubject(): WebmentionEndpoint
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->queued[] = $message;
            return new Envelope($message);
        });

        return new WebmentionEndpoint($this->targetResolver, $this->rateLimiter, new MessageQueue($bus), $this->logger ?? new NullLogger());
    }
}
