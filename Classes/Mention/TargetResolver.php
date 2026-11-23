<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Mention;

use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use Webicorns\Pongback\Utility\Constants;

/**
 * Resolves the target URI of a mention to a page of the receiving site that is visible
 * to anonymous visitors, using the same routing as regular frontend requests.
 */
readonly class TargetResolver
{
    public function __construct(
        private SiteMatcher $siteMatcher,
        private PageRepository $pageRepository,
    ) {}

    /**
     * @throws MentionRejected
     */
    public function resolve(string $targetUrl, Site $receivingSite): ResolvedTarget
    {
        $request = $this->createRequest($targetUrl);
        $siteResult = $this->matchSite($request, $receivingSite);
        $pageId = $this->matchVisiblePage($request, $siteResult, $receivingSite);
        $language = $siteResult->getLanguage();

        return new ResolvedTarget(
            $receivingSite,
            $pageId,
            $language instanceof SiteLanguage ? $language->getLanguageId() : 0,
        );
    }

    private function createRequest(string $targetUrl): ServerRequest
    {
        $scheme = strtolower((string) parse_url($targetUrl, PHP_URL_SCHEME));
        if (strlen($targetUrl) > Constants::MAX_URL_LENGTH || ! in_array($scheme, ['http', 'https'], true)) {
            throw new MentionRejected(RejectionReason::TargetNotAllowed, 'The target URI cannot be used as a target.');
        }

        try {
            return new ServerRequest($targetUrl, 'GET');
        } catch (\InvalidArgumentException $exception) {
            throw new MentionRejected(RejectionReason::TargetNotAllowed, 'The target URI cannot be used as a target.', $exception);
        }
    }

    /**
     * Only accept mentions for pages of the site whose endpoint was called.
     */
    private function matchSite(ServerRequest $request, Site $receivingSite): SiteRouteResult
    {
        $siteResult = $this->siteMatcher->matchRequest($request);
        $site = $siteResult instanceof SiteRouteResult ? $siteResult->getSite() : null;
        if (! $site instanceof Site || $site->getIdentifier() !== $receivingSite->getIdentifier()) {
            throw new MentionRejected(RejectionReason::TargetNotAllowed, 'The target URI cannot be used as a target.');
        }

        return $siteResult;
    }

    private function matchVisiblePage(ServerRequest $request, SiteRouteResult $siteResult, Site $site): int
    {
        try {
            $pageArguments = $site->getRouter()->matchRequest($request, $siteResult);
        } catch (RouteNotFoundException $exception) {
            throw new MentionRejected(RejectionReason::TargetNotFound, 'The target URI does not exist.', $exception);
        }

        $pageId = $pageArguments instanceof PageArguments ? $pageArguments->getPageId() : 0;

        // Routing also resolves hidden, scheduled and access protected pages, only the rendering checks visibility.
        $page = $pageId > 0 ? $this->pageRepository->getPage($pageId) : [];
        if ($page === [] || (int) ($page['doktype'] ?? 0) >= PageRepository::DOKTYPE_SPACER) {
            throw new MentionRejected(RejectionReason::TargetNotFound, 'The target URI does not exist.');
        }

        return $pageId;
    }
}
