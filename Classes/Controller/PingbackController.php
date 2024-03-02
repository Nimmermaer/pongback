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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use Webicorns\Pongback\Domain\Repository\PingbackRepository;
use Webicorns\Pongback\Traits\PaginationTrait;
use Webicorns\Pongback\Utility\Constants;

class PingbackController extends ActionController
{
    use PaginationTrait;

    public function __construct(
        private readonly PingbackRepository $pingbackRepository
    ) {
    }

    public function listAction(): ResponseInterface
    {
        $data = $this->getCachedData((int)($this->settings['listItem'] ?? 3));
        return $this->htmlResponse($data);
    }

    public function latestAction(): ResponseInterface
    {
        $data = $this->getCachedData((int)($this->settings['listItem'] ?? 3), 'latest');
        return $this->htmlResponse($data);
    }

    /**
     * @return mixed|string
     */
    public function getCachedData(int $limit = 0, string $cacheTag = 'list'): mixed
    {
        $pageId = ($this->request->getAttribute('currentContentObject')
                ->data['pid'] !== 0)
            ? $this->request->getAttribute('currentContentObject')
                ->data['pid']
            : $this->request->getAttribute('frontend.controller')
                ->id;
        $pluginId = ($this->request->getAttribute('currentContentObject')
                ->data['pid'] !== 0)
            ? $this->request->getAttribute('currentContentObject')
                ->data['pid']
            : $this->request->getAttribute('language')
                ->getLanguageId();

        $languageId = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('language', 'id');
        $cacheKey = 'pingback-latest-' . $pageId . '-' . $pluginId . '-' . $languageId;

        $cache = GeneralUtility::makeInstance(CacheManager::class)
            ->getCache(Constants::PONGBACK_CACHE);
        if (($data = $cache->get($cacheKey)) === false) {
            $this->buildSlideWindowPagination($this->pingbackRepository->findBy(
                [
                    'pid' => $pageId,
                ],
                [
                    'crdate' => QueryInterface::ORDER_DESCENDING,
                ],
                $limit
            ));

            $data = $this->view->render();
            $tags = ['pingback_' . $cacheTag . '_view'];
            $tags[] = 'pageId_' . $pageId;
            $cache->set($cacheKey, $data, $tags, 0);
        }

        return $data;
    }
}
