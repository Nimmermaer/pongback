<?php

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
        $data = $this->getCachedData(3);
        return $this->htmlResponse($data);
    }

    public function latestAction(): ResponseInterface
    {
        $data = $this->getCachedData(15, 'latest');
        return $this->htmlResponse($data);
    }

    /**
     * @return mixed|string
     */
    public function getCachedData(int $limit = null, string $cacheTag = 'list'): mixed
    {
        $pageId = $this->request->getAttribute('currentContentObject')
            ->data['pid'];
        $pluginId = $this->request->getAttribute('currentContentObject')
            ->data['uid'];
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
