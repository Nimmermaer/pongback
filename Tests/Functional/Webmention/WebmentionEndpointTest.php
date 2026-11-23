<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Functional\Webmention;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use TYPO3\CMS\Core\Error\Http\InternalServerErrorException;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use Webicorns\Pongback\Tests\Functional\AbstractPongbackTestCase;
use Webicorns\Pongback\Webmention\ReceiveWebmentionMessage;

/**
 * Real frontend requests: routing of /webmention, site settings, queue.
 */
final class WebmentionEndpointTest extends AbstractPongbackTestCase
{
    private const ENDPOINT = 'https://main.example/webmention';

    #[Test]
    public function announcesTheEndpoint(): void
    {
        $this->setUpFrontendRootPage(1, ['EXT:pongback/Tests/Functional/Fixtures/page.typoscript']);

        $response = $this->executeFrontendSubRequest(new InternalRequest('https://main.example/article'));

        self::assertSame('<' . self::ENDPOINT . '>; rel="webmention"', $response->getHeaderLine('Link'));
        self::assertStringContainsString('<link rel="webmention" href="' . self::ENDPOINT . '">', (string) $response->getBody());
        self::assertSame('https://main.example/pingback.ping', $response->getHeaderLine('X-Pingback'));
    }

    #[Test]
    public function queuesTheVerification(): void
    {
        $response = $this->post(['source' => 'https://blog.example/post', 'target' => 'https://main.example/article']);

        self::assertSame(202, $response->getStatusCode());
        $messages = $this->queuedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(ReceiveWebmentionMessage::class, $messages[0]);
        self::assertSame(['https://blog.example/post', 'https://main.example/article', 'main'], [$messages[0]->source, $messages[0]->target, $messages[0]->siteIdentifier]);
    }

    #[Test]
    public function rejectsTargetsOfOtherSitesAndHiddenPages(): void
    {
        self::assertSame(400, $this->post(['source' => 'https://blog.example/post', 'target' => 'https://other.example/article'])->getStatusCode());
        self::assertSame(400, $this->post(['source' => 'https://blog.example/post', 'target' => 'https://main.example/hidden'])->getStatusCode());
        self::assertSame([], $this->queuedMessages());
    }

    #[Test]
    public function requiresPost(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest(self::ENDPOINT));

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('POST', $response->getHeaderLine('Allow'));
    }

    #[Test]
    public function canBeSwitchedOff(): void
    {
        $this->writeSiteSettings('main', ['pongback' => ['receive' => ['webmention' => false]]]);
        $this->setUpFrontendRootPage(1, ['EXT:pongback/Tests/Functional/Fixtures/page.typoscript']);

        $page = $this->executeFrontendSubRequest(new InternalRequest('https://main.example/article'));
        self::assertSame('', $page->getHeaderLine('Link'));
        self::assertStringNotContainsString('rel="webmention"', (string) $page->getBody());

        // TYPO3 handles the request itself: "No page configured for type=…", thrown in v13, a response in v14.
        try {
            $body = (string) $this->post(['source' => 'https://blog.example/post', 'target' => 'https://main.example/article'])->getBody();
        } catch (InternalServerErrorException $exception) {
            $body = $exception->getMessage();
        }
        self::assertStringNotContainsString('Accepted', $body);
        self::assertSame([], $this->queuedMessages());
    }

    /**
     * @param array<string, string> $fields
     */
    private function post(array $fields): ResponseInterface
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write(http_build_query($fields));
        $stream->rewind();

        $request = (new InternalRequest(self::ENDPOINT))
            ->withMethod('POST')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($stream);

        return $this->executeFrontendSubRequest($request);
    }

    /**
     * @return list<object>
     */
    private function queuedMessages(): array
    {
        $rows = $this->getConnectionPool()->getConnectionForTable('sys_messenger_messages')
            ->select(['body'], 'sys_messenger_messages')
            ->fetchAllAssociative();
        $serializer = new PhpSerializer();

        return array_map(static fn(array $row): object => $serializer->decode(['body' => $row['body']])->getMessage(), $rows);
    }
}
