<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Mention\Source;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Mention\Source\SourceFetcher;
use Webicorns\Pongback\Mention\Source\UrlGuard;
use Webicorns\Pongback\Tests\Unit\Fixtures\AssertRejectionTrait;
use Webicorns\Pongback\Utility\Constants;

final class SourceFetcherTest extends TestCase
{
    use AssertRejectionTrait;

    private const DNS = [
        'blog.example' => ['93.184.215.14'],
        'www.blog.example' => ['93.184.215.15'],
        'intranet.example' => ['10.0.0.1'],
    ];

    /**
     * @var list<array{url: string, options: array<string, mixed>}>
     */
    private array $requests = [];

    #[Test]
    public function returnsHtmlAndDisablesAutomaticRedirects(): void
    {
        $result = $this->createSubject([$this->html('<p>Hello</p>')])->fetch('https://blog.example/post');

        self::assertSame('https://blog.example/post', $result->url);
        self::assertSame('<p>Hello</p>', $result->html);
        self::assertFalse($this->requests[0]['options']['allow_redirects']);
        self::assertSame(SourceFetcher::TIMEOUT, $this->requests[0]['options']['timeout']);
    }

    #[Test]
    public function followsRedirectsAndChecksEveryHop(): void
    {
        $subject = $this->createSubject([
            $this->redirect('https://www.blog.example/post'),
            $this->redirect('/final'),
            $this->html('<p>Hello</p>'),
        ]);

        self::assertSame('https://www.blog.example/final', $subject->fetch('http://blog.example/post')->url);
        self::assertSame(
            ['http://blog.example/post', 'https://www.blog.example/post', 'https://www.blog.example/final'],
            array_column($this->requests, 'url'),
        );
    }

    #[Test]
    public function rejectsRedirectsToInternalHosts(): void
    {
        $subject = $this->createSubject([$this->redirect('https://intranet.example/admin')]);

        self::assertRejected(RejectionReason::InvalidRequest, fn() => $subject->fetch('https://blog.example/post'));
        self::assertCount(1, $this->requests, 'The internal host must not be requested');
    }

    #[Test]
    public function rejectsEndlessRedirects(): void
    {
        $subject = $this->createSubject(array_fill(0, SourceFetcher::MAX_REDIRECTS + 1, $this->redirect('https://blog.example/loop')));

        self::assertRejected(RejectionReason::UpstreamError, fn() => $subject->fetch('https://blog.example/loop'));
    }

    #[Test]
    public function reportsMissingSources(): void
    {
        $subject = $this->createSubject([new Response(null, 404)]);

        self::assertRejected(RejectionReason::SourceNotFound, fn() => $subject->fetch('https://blog.example/gone'));
    }

    #[Test]
    public function reportsServerErrorsAsUpstreamError(): void
    {
        $subject = $this->createSubject([new Response(null, 503)]);

        self::assertRejected(RejectionReason::UpstreamError, fn() => $subject->fetch('https://blog.example/post'));
    }

    #[Test]
    public function reportsUnreachableSources(): void
    {
        $subject = $this->createSubject([new ConnectException('Connection refused', new Request('GET', 'https://blog.example/'))]);

        self::assertRejected(RejectionReason::SourceNotFound, fn() => $subject->fetch('https://blog.example/post'));
    }

    #[Test]
    public function rejectsNonHtmlDocuments(): void
    {
        $subject = $this->createSubject([(new Response(null, 200, ['Content-Type' => 'application/pdf']))]);

        self::assertRejected(RejectionReason::NoLinkToTarget, fn() => $subject->fetch('https://blog.example/file.pdf'));
    }

    #[Test]
    public function readsAtMostTheConfiguredAmountOfBytes(): void
    {
        $html = $this->createSubject([$this->html(str_repeat('a', SourceFetcher::MAX_BYTES + 100))])
            ->fetch('https://blog.example/huge')->html;

        self::assertSame(SourceFetcher::MAX_BYTES, strlen($html));
    }

    /**
     * @param list<ResponseInterface|\Throwable> $responses
     */
    private function createSubject(array $responses): SourceFetcher
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willReturnCallback(
            function (string $url, string $method, array $options) use (&$responses): ResponseInterface {
                $this->requests[] = ['url' => $url, 'options' => $options];
                $response = array_shift($responses);
                if ($response instanceof \Throwable) {
                    throw $response;
                }

                return $response ?? throw new \LogicException('Unexpected request to ' . $url, 1732400100);
            },
        );

        $dns = self::DNS;
        return new SourceFetcher($requestFactory, new UrlGuard(static fn(string $host): array => $dns[$host] ?? []));
    }

    private function html(string $html, string $contentType = 'text/html; charset=utf-8'): Response
    {
        $body = new Stream('php://temp', 'rw');
        $body->write($html);
        $body->rewind();

        return new Response($body, 200, ['Content-Type' => $contentType]);
    }

    private function redirect(string $location): Response
    {
        return new Response(null, 301, ['Location' => $location]);
    }

    #[Test]
    public function sendsStrictRequestOptionsAndPinsTheCheckedIpAddress(): void
    {
        $this->createSubject([$this->html('<p>Hello</p>')])->fetch('https://blog.example/post');

        $options = $this->requests[0]['options'];
        self::assertFalse($options['allow_redirects']);
        self::assertFalse($options['http_errors']);
        self::assertTrue($options['stream']);
        self::assertSame(5, $options['connect_timeout']);
        self::assertSame(SourceFetcher::TIMEOUT, $options['timeout']);
        self::assertSame('text/html,application/xhtml+xml;q=0.9', $options['headers']['Accept']);
        self::assertSame(Constants::USER_AGENT, $options['headers']['User-Agent']);
        self::assertSame([\CURLOPT_RESOLVE => ['blog.example:443:93.184.215.14']], $options['curl']);
    }

    #[Test]
    public function followsAtMostTheConfiguredNumberOfRedirects(): void
    {
        $responses = array_fill(0, SourceFetcher::MAX_REDIRECTS, $this->redirect('https://blog.example/next'));
        $responses[] = $this->html('<p>Hello</p>');

        self::assertSame('https://blog.example/next', $this->createSubject($responses)->fetch('https://blog.example/start')->url);
        self::assertCount(SourceFetcher::MAX_REDIRECTS + 1, $this->requests);
    }

    /**
     * @return array<string, array{int, ?RejectionReason}>
     */
    public static function statusProvider(): array
    {
        return [
            '199 informational' => [199, RejectionReason::UpstreamError],
            '200 ok' => [200, null],
            '299 last success' => [299, null],
            '300 without location' => [300, RejectionReason::UpstreamError],
            '400 bad request' => [400, RejectionReason::UpstreamError],
            '404 not found' => [404, RejectionReason::SourceNotFound],
            '409 conflict' => [409, RejectionReason::UpstreamError],
            '410 gone' => [410, RejectionReason::SourceNotFound],
            '411 length required' => [411, RejectionReason::UpstreamError],
        ];
    }

    #[Test]
    #[DataProvider('statusProvider')]
    public function mapsHttpStatusCodes(int $status, ?RejectionReason $expected): void
    {
        // TYPO3's Response only accepts registered status codes, the Guzzle one accepts any.
        $subject = $this->createSubject([new Psr7Response($status, ['Content-Type' => 'text/html'])]);

        if ($expected === null) {
            self::assertSame('', $subject->fetch('https://blog.example/post')->html);
            return;
        }

        self::assertRejected($expected, fn() => $subject->fetch('https://blog.example/post'));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function redirectStatusProvider(): array
    {
        return ['status 300' => [300], 'status 302' => [302], 'status 399' => [399]];
    }

    #[Test]
    #[DataProvider('redirectStatusProvider')]
    public function treatsAll3xxResponsesWithLocationAsRedirect(int $status): void
    {
        $subject = $this->createSubject([
            new Psr7Response($status, ['Location' => 'https://blog.example/final']),
            $this->html('<p>Hello</p>'),
        ]);

        self::assertSame('https://blog.example/final', $subject->fetch('https://blog.example/post')->url);
    }

    #[Test]
    public function doesNotFollowLocationHeadersOfNonRedirects(): void
    {
        $subject = $this->createSubject([new Response(null, 400, ['Location' => 'https://blog.example/final'])]);

        self::assertRejected(RejectionReason::UpstreamError, fn() => $subject->fetch('https://blog.example/post'));
        self::assertCount(1, $this->requests);
    }

    #[Test]
    public function reportsOtherTransferErrorsAsUpstreamError(): void
    {
        // Any Guzzle exception besides connection errors, independent of the Guzzle major version.
        $exception = new class ('Too many requests') extends \RuntimeException implements GuzzleException {};
        $subject = $this->createSubject([$exception]);

        self::assertRejected(RejectionReason::UpstreamError, fn() => $subject->fetch('https://blog.example/post'));
    }

    #[Test]
    public function comparesTheContentTypeCaseInsensitive(): void
    {
        self::assertSame(
            '<p>Hello</p>',
            $this->createSubject([$this->html('<p>Hello</p>', 'TEXT/HTML')])->fetch('https://blog.example/post')->html,
        );
        self::assertRejected(
            RejectionReason::NoLinkToTarget,
            fn() => $this->createSubject([$this->html('%PDF', 'Application/PDF')])->fetch('https://blog.example/post'),
        );
    }

    #[Test]
    public function acceptsResponsesWithoutContentType(): void
    {
        $response = $this->html('<p>Hello</p>')->withoutHeader('Content-Type');

        self::assertSame('<p>Hello</p>', $this->createSubject([$response])->fetch('https://blog.example/post')->html);
    }

    #[Test]
    public function closesTheResponseStream(): void
    {
        $response = $this->html('<p>Hello</p>');
        $this->createSubject([$response])->fetch('https://blog.example/post');

        self::assertNull($response->getBody()->detach());
    }
}
