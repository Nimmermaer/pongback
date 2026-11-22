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
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Frontend\Page\PageInformation;
use Webicorns\Pongback\Domain\Model\Pingback;
use Webicorns\Pongback\Domain\Repository\PingbackRepository;
use Webicorns\Pongback\Event\BeforePingbackCreateEvent;
use Webicorns\Pongback\Service\XmlResponseService;
use Webicorns\Pongback\Traits\PaginationTrait;
use Webicorns\Pongback\Utility\Constants;
use Webicorns\Pongback\XmlRpc\XmlRpcCodec;

class PingbackController extends ActionController
{
    use PaginationTrait;

    public function __construct(
        private readonly PingbackRepository $pingbackRepository,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly XmlRpcCodec $xmlRpcCodec,
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

    public function serverAction(): ResponseInterface
    {
        try {
            $call = $this->xmlRpcCodec->decodeMethodCall((string) $this->request->getBody());
        } catch (\InvalidArgumentException $invalidArgumentException) {
            // -32700: parse error (XML-RPC fault code interoperability specification)
            return XmlResponseService::xmlErrorResponse(-32700, $invalidArgumentException->getMessage());
        }

        if ($call['methodName'] !== 'pingback.ping') {
            // -32601: requested method not found
            return XmlResponseService::xmlErrorResponse(-32601, 'Unknown method ' . $call['methodName']);
        }

        $result = $this->pingback($call['methodName'], $call['params']);
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        return XmlResponseService::xmlSuccessResponse((string) $result);
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
            $tags = ['pingback_' . $cacheTag . '_view'];
            $tags[] = 'pageId_' . $pageId;
            $cache->set($cacheKey, $data, $tags, 0);
        }

        return $data;
    }

    public function pingback(string $methodName, array $xmlRpcParams, ?array $userData = null)
    {
        /** @var Pingback $pingback */
        $pingback = GeneralUtility::makeInstance(Pingback::class);

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pongback']['validatePingback'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pongback']['validatePingback'] as $validation) {
                if (! $pingback->hasValidationErrors()) {
                    $params = [
                        'pingback' => $pingback,
                        'params' => $xmlRpcParams,
                    ];

                    GeneralUtility::callUserFunction($validation, $params, $this);
                } else {
                    break;
                }
            }
        }

        if ($pingback->hasValidationErrors()) {
            return $pingback->getValidationError()[0];
        }

        if (! $this->extensionConfiguration->get('pongback', 'enableAutoPublishing')) {
            $pingback->setHidden(true);
        }

        /** @var BeforePingbackCreateEvent $event */
        $event = $this->eventDispatcher->dispatch(new BeforePingbackCreateEvent($pingback));
        $this->pingbackRepository->add($event->getPingback());

        return 'success';
    }
}
