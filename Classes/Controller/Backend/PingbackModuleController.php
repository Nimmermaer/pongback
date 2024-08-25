<?php

namespace Webicorns\Pongback\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use Webicorns\Pongback\Domain\Model\Pingback;
use Webicorns\Pongback\Domain\Repository\PingbackRepository;

#[AsController]
class PingbackModuleController extends AbstractModuleController
{
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($request);

        /** @var LanguageService $languageService */
        $languageService = $this->getLanguageService();
        $this->addDocHeader($this->getId($request), 'active', 'Pingback');
        $this->addDocHeaderShortcutButton($this->moduleTemplate, 'show', $request->getParsedBody()['id'] ?? 0);
        $title = $languageService->sL('LLL:EXT:pongback/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab');
        $this->moduleTemplate->setTitle($title);

        $pingbacks = $this->getPingbackRepositoryWithIgnoredFields()
            ->findAll();
        $this->buildSlideWindowPaginationForBackend($pingbacks, 10, 99, request: $request);

        return $this->moduleTemplate->renderResponse('Administration/Pingbacks');
    }

    public function enabledAction(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $uid = $queryParams['uid'] ?? null;
        $enabled = $queryParams['enabled'] ?? null;

        if ($uid === null || $enabled === null) {
            return new JsonResponse([
                'error' => 'Invalid parameters',
            ], 400);
        }

        /** @var Pingback $pongback */
        $pongback = $this->getPingbackRepositoryWithIgnoredFields()
            ->findOneBy([
                'uid' => $uid,
            ]);

        if (! $pongback instanceof Pingback) {
            return new JsonResponse([
                'error' => 'Pingback not found',
            ], 404);
        }

        $pongback->setHidden($enabled !== 'true');
        $this->getPingbackRepositoryWithIgnoredFields()
            ->update($pongback);
        $this->persistenceManager->persistAll();

        return new JsonResponse([
            'result' => 'success',
        ]);
    }

    public function getPingbackRepositoryWithIgnoredFields(): PingbackRepository
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings
            ->setRespectStoragePage(false)
            ->setIgnoreEnableFields(true)
            ->setIncludeDeleted(false);
        $pingbackRepository = $this->pingbackRepository;
        $pingbackRepository->setDefaultQuerySettings($querySettings);

        return $pingbackRepository;
    }
}
