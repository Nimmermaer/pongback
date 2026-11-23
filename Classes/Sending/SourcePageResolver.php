<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Sending;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webicorns\Pongback\Configuration\PongbackSettings;

/**
 * Decides whether a page sends Pingbacks and Webmentions, and finds its public URL.
 */
readonly class SourcePageResolver
{
    public function __construct(
        private PageRepository $pageRepository,
        private SiteFinder $siteFinder,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return SourcePage|null null if the site does not send, or the page is not public
     */
    public function resolve(int $pageId, int $languageId): ?SourcePage
    {
        $site = $this->findSite($pageId);
        $settings = PongbackSettings::fromSite($site);
        if ($site === null || ! $settings->sendEnabled || ! $this->isVisible($pageId)) {
            return null;
        }

        $url = $this->pageUrl($site, $pageId, $languageId);

        return $url === null ? null : new SourcePage($site, $settings, $url, $this->ownHosts());
    }

    private function findSite(int $pageId): ?Site
    {
        try {
            return $this->siteFinder->getSiteByPageId($pageId);
        } catch (SiteNotFoundException) {
            return null;
        }
    }

    /**
     * Only pages that anonymous visitors can see: Pingbacks to unpublished pages would point to nothing.
     */
    private function isVisible(int $pageId): bool
    {
        $page = $this->pageRepository->getPage($pageId);

        return $page !== [] && (int) ($page['doktype'] ?? 0) < PageRepository::DOKTYPE_SPACER;
    }

    private function pageUrl(Site $site, int $pageId, int $languageId): ?string
    {
        try {
            return (string) $site->getRouter()->generateUri(
                $pageId,
                ['_language' => $languageId],
                '',
                RouterInterface::ABSOLUTE_URL,
            );
        } catch (\Throwable $exception) {
            // e.g. a disabled language
            $this->logger->info('No URL for page, no Pingbacks sent', [
                'pageId' => $pageId,
                'languageId' => $languageId,
                'reason' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function ownHosts(): array
    {
        $hosts = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            $hosts[] = $site->getBase()->getHost();
            foreach ($site->getAllLanguages() as $language) {
                $hosts[] = $language->getBase()->getHost();
            }
        }

        return array_values(array_unique(array_filter($hosts)));
    }
}
