<?php

namespace Webicorns\Pongback\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Localization\LanguageService;

#[AsController]
class AdminModuleController extends AbstractModuleController
{
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($request);

        /** @var LanguageService $languageService */
        $languageService = $this->getLanguageService();
        $this->addDocHeader($this->getId($request), 'active');
        $this->addDocHeaderShortcutButton($this->moduleTemplate, 'show', $request->getParsedBody()['id'] ?? 0);
        $title = $languageService->sL('LLL:EXT:pongback/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab');
        $this->moduleTemplate->setTitle($title);

        return $this->moduleTemplate->renderResponse('Administration/Index');
    }
}
