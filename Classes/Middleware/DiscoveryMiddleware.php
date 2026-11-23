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
 * Announces the endpoints of the current site:
 * Pingback via "X-Pingback" header and <link rel="pingback">,
 * Webmention via "Link: <…>; rel="webmention"" header and <link rel="webmention">.
 *
 * @see https://www.hixie.ch/specs/pingback/pingback#TOC2
 * @see https://www.w3.org/TR/webmention/#sender-discovers-receiver-webmention-endpoint
 */
final readonly class DiscoveryMiddleware implements MiddlewareInterface
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

        $pingback = $this->buildEndpointUri($site, Constants::PINGBACK_PAGE_TYPE);
        $webmention = $settings->receiveWebmention ? $this->buildEndpointUri($site, Constants::WEBMENTION_PAGE_TYPE) : null;

        if ($settings->discoveryMetaTag) {
            // Must happen before the page is rendered by the frontend request handler.
            $this->pageRenderer->addHeaderData($this->linkTag('pingback', $pingback));
            if ($webmention !== null) {
                $this->pageRenderer->addHeaderData($this->linkTag('webmention', $webmention));
            }
        }

        $response = $handler->handle($request);

        if ($settings->discoveryHttpHeader) {
            $response = $response->withHeader('X-Pingback', $pingback);
            if ($webmention !== null) {
                $response = $response->withAddedHeader('Link', '<' . $webmention . '>; rel="webmention"');
            }
        }

        return $response;
    }

    private function linkTag(string $relation, string $endpoint): string
    {
        return '<link rel="' . $relation . '" href="' . htmlspecialchars($endpoint, ENT_QUOTES | ENT_HTML5) . '">';
    }

    private function buildEndpointUri(Site $site, int $pageType): string
    {
        return (string) $site->getRouter()->generateUri(
            $site->getRootPageId(),
            [
                'type' => $pageType,
            ],
            '',
            RouterInterface::ABSOLUTE_URL,
        );
    }
}
