<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use Webicorns\Pongback\Moderation\ModerationRepository;
use Webicorns\Pongback\Moderation\PingbackModerator;

/**
 * Module "Web > Pongback": the Pingbacks of the selected page and its subpages.
 */
#[AsController]
final readonly class ModerationController
{
    public const MODULE = 'web_pongback';
    public const ITEMS_PER_PAGE = 25;

    private const LABELS = 'LLL:EXT:pongback/Resources/Private/Language/locallang_mod.xlf:';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private UriBuilder $uriBuilder,
        private ModerationRepository $repository,
        private PingbackModerator $moderator,
        private FlashMessageService $flashMessageService,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $pageId = (int) ($request->getQueryParams()['id'] ?? 0);
        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle($this->getLanguageService()->sL(self::LABELS . 'mlang_tabs_tab'));

        $page = $pageId > 0 ? BackendUtility::readPageAccess($pageId, $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)) : false;
        if (is_array($page)) {
            $paginator = new ArrayPaginator(
                $this->repository->findOnPageTree($pageId, $this->getBackendUser()),
                max(1, (int) ($request->getQueryParams()['page'] ?? 1)),
                self::ITEMS_PER_PAGE,
            );
            $view->assignMultiple([
                'paginator' => $paginator,
                'pagination' => new SlidingWindowPagination($paginator, 10),
                'moderateUrl' => (string) $this->uriBuilder->buildUriFromRoute(self::MODULE . '.moderate'),
                'sendingProblems' => $this->repository->findSendingProblemsOnPageTree($pageId, $this->getBackendUser()),
            ]);
        }

        $view->assignMultiple([
            'pageId' => $pageId,
            'page' => $page,
            'highlight' => (int) ($request->getQueryParams()['pingback'] ?? 0),
        ]);

        return $view->renderResponse('Moderation/Index');
    }

    public function moderateAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $pageId = (int) ($body['id'] ?? 0);
        $operation = (string) ($body['operation'] ?? '');
        $uid = (int) ($body['pingback'] ?? 0);

        if ($uid > 0 && in_array($operation, PingbackModerator::OPERATIONS, true)) {
            $errors = $this->moderator->moderate($operation, $uid);
            $this->addFlashMessage(
                $errors === [] ? $this->getLanguageService()->sL(self::LABELS . 'moderation.' . $operation . '.done') : implode("\n", $errors),
                $errors === [] ? ContextualFeedbackSeverity::OK : ContextualFeedbackSeverity::ERROR,
            );
        }

        // Post/redirect/get: reloading the list does not repeat the operation.
        return new RedirectResponse((string) $this->uriBuilder->buildUriFromRoute(self::MODULE, ['id' => $pageId]), 303);
    }

    private function addFlashMessage(string $message, ContextualFeedbackSeverity $severity): void
    {
        $this->flashMessageService->getMessageQueueByIdentifier()
            ->enqueue(new FlashMessage($message, '', $severity, true));
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
