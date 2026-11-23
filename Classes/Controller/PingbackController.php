<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Webicorns\Pongback\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Frontend\Page\PageInformation;
use Webicorns\Pongback\Cache\CacheTags;
use Webicorns\Pongback\Domain\Repository\PingbackRepository;
use Webicorns\Pongback\Traits\PaginationTrait;
use Webicorns\Pongback\Utility\Constants;

class PingbackController extends ActionController
{
    use PaginationTrait;

    public function __construct(
        private readonly PingbackRepository $pingbackRepository,
        private readonly Context $context,
        private readonly CacheManager $cacheManager,
    ) {}

    public function listAction(): ResponseInterface
    {
        $data = $this->getCachedData((int) ($this->settings['listItem'] ?? 3));
        return $this->htmlResponse($data);
    }

    public function latestAction(): ResponseInterface
    {
        $data = $this->getCachedData((int) ($this->settings['listItem'] ?? 3), 'latest');
        return $this->htmlResponse($data);
    }

    public function getCachedData(int $limit = 0, string $cacheTag = 'list'): mixed
    {
        /** @var PageInformation $pageInformation */
        $pageInformation = $this->request->getAttribute('frontend.page.information');
        $pageId = ($this->request->getAttribute('currentContentObject')
            ->data['uid'] !== 0)
            ? $this->request->getAttribute('currentContentObject')
                ->data['uid']
            : $pageInformation->getId();
        $pluginId = ($this->request->getAttribute('currentContentObject')
            ->data['uid'] !== 0)
            ? $this->request->getAttribute('currentContentObject')
                ->data['uid']
            : $this->request->getAttribute('language')
                ->getLanguageId();

        $languageId = $this->context->getPropertyFromAspect('language', 'id');
        $cacheKey = 'pingback-latest-' . $pageId . '-' . $pluginId . '-' . $languageId;

        $cache = $this->cacheManager
            ->getCache(Constants::PONGBACK_CACHE);
        if (($data = $cache->get($cacheKey)) === false) {
            $this->buildSlideWindowPagination($this->pingbackRepository->findBy(
                [
                    'pid' => $pageId,
                ],
                [
                    'crdate' => QueryInterface::ORDER_DESCENDING,
                ],
                $limit,
            ));

            $data = $this->view->render();
            $cache->set($cacheKey, $data, [CacheTags::forView($cacheTag), CacheTags::forPage((int) $pageId)], 0);
        }

        return $data;
    }

}
