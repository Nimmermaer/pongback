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
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use Webicorns\Pongback\Configuration\PongbackSettings;
use Webicorns\Pongback\Utility\Constants;

/**
 * Announces the Pingback endpoint of the current site
 * via "X-Pingback" HTTP header and/or <link rel="pingback"> tag.
 *
 * @see https://www.hixie.ch/specs/pingback/pingback#TOC2
 */
final readonly class PingbackDiscoveryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private PageRenderer $pageRenderer,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if (! $site instanceof Site) {
            return $handler->handle($request);
        }

        $settings = PongbackSettings::fromSite($site);
        if (! $settings->discoveryMetaTag && ! $settings->discoveryHttpHeader) {
            return $handler->handle($request);
        }

        $endpoint = $this->buildEndpointUri($site);

        if ($settings->discoveryMetaTag) {
            // Must happen before the page is rendered by the frontend request handler.
            $this->pageRenderer->addHeaderData(
                '<link rel="pingback" href="' . htmlspecialchars($endpoint, ENT_QUOTES | ENT_HTML5) . '">',
            );
        }

        $response = $handler->handle($request);

        if ($settings->discoveryHttpHeader) {
            $response = $response->withHeader('X-Pingback', $endpoint);
        }

        return $response;
    }

    private function buildEndpointUri(Site $site): string
    {
        return (string) $site->getRouter()->generateUri(
            $site->getRootPageId(),
            [
                'type' => Constants::PINGBACK_PAGE_TYPE,
            ],
            '',
            RouterInterface::ABSOLUTE_URL,
        );
    }

}
