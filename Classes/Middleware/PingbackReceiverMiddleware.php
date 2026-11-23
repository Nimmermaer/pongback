<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use Webicorns\Pongback\Configuration\PongbackSettings;
use Webicorns\Pongback\Pingback\PingbackEndpoint;
use Webicorns\Pongback\Utility\Constants;

/**
 * Answers requests to the Pingback page type of a site, before any TypoScript rendering happens.
 */
final readonly class PingbackReceiverMiddleware implements MiddlewareInterface
{
    public function __construct(
        private PingbackEndpoint $endpoint,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routing = $request->getAttribute('routing');
        $site = $request->getAttribute('site');

        if (! $routing instanceof PageArguments
            || $routing->getPageType() !== (string) Constants::PINGBACK_PAGE_TYPE
            || ! $site instanceof Site
            || ! PongbackSettings::fromSite($site)->enabled
        ) {
            return $handler->handle($request);
        }

        if ($request->getMethod() !== 'POST') {
            return (new Response(null, 405))
                ->withHeader('Allow', 'POST')
                ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withBody($this->textStream('This is the Pingback XML-RPC endpoint, send a POST request.'));
        }

        return $this->endpoint->handle($request, $site);
    }

    private function textStream(string $text): StreamInterface
    {
        $body = new Stream('php://temp', 'rw');
        $body->write($text);
        $body->rewind();

        return $body;
    }
}
