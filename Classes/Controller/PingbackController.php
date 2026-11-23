<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Frontend\Page\PageInformation;
use Webicorns\Pongback\Domain\Repository\PingbackRepository;
use Webicorns\Pongback\Utility\Constants;

/**
 * Plugins "Pingbacks of this page" (list) and "Latest Pingbacks" of the site (latest).
 *
 * The output is cached with the page. Every page showing Pingbacks carries the cache tag of the
 * Pingback table, which the DataHandler (and MentionStorage) flush when Pingbacks change.
 */
class PingbackController extends ActionController
{
    public const DEFAULT_ITEMS = 5;

    /**
     * Page levels below the root page searched by the plugin "latest".
     */
    private const SITE_DEPTH = 99;

    public function __construct(
        private readonly PingbackRepository $pingbackRepository,
        private readonly PageRepository $pageRepository,
    ) {}

    public function listAction(int $currentPage = 1): ResponseInterface
    {
        $pingbacks = $this->pingbackRepository->findOnPage($this->pageId(), $this->languageId());
        $paginator = new QueryResultPaginator($pingbacks, max(1, $currentPage), $this->numberOfItems());

        $this->view->assignMultiple([
            'paginator' => $paginator,
            'pagination' => new SlidingWindowPagination($paginator, 10),
        ]);
        $this->tagCache();

        return $this->htmlResponse();
    }

    public function latestAction(): ResponseInterface
    {
        $site = $this->request->getAttribute('site');
        $pageIds = $site instanceof Site
            ? array_map('intval', $this->pageRepository->getPageIdsRecursive([$site->getRootPageId()], self::SITE_DEPTH))
            : [$this->pageId()];

        $this->view->assign('pingbacks', $this->pingbackRepository->findOnPages($pageIds, $this->languageId(), $this->numberOfItems()));
        $this->tagCache();

        return $this->htmlResponse();
    }

    private function numberOfItems(): int
    {
        $configured = (int) ($this->settings['listItem'] ?? 0);

        return $configured > 0 ? $configured : self::DEFAULT_ITEMS;
    }

    private function pageId(): int
    {
        $pageInformation = $this->request->getAttribute('frontend.page.information');

        return $pageInformation instanceof PageInformation ? $pageInformation->getId() : 0;
    }

    private function languageId(): int
    {
        $language = $this->request->getAttribute('language');

        return $language instanceof SiteLanguage ? $language->getLanguageId() : 0;
    }

    private function tagCache(): void
    {
        $collector = $this->request->getAttribute('frontend.cache.collector');
        if ($collector instanceof CacheDataCollector) {
            $collector->addCacheTags(new CacheTag(Constants::PINGBACK_TABLE));
        }
    }
}
