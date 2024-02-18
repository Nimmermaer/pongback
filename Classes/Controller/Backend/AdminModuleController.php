<?php

namespace Webicorns\Pongback\Controller\Backend;

use enshrined\svgSanitize\ElementReference\Usage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webicorns\Pongback\Domain\Repository\PingbackRepository;
use Webicorns\Pongback\Traits\PaginationTrait;

#[AsController]
readonly class AdminModuleController
{
    use PaginationTrait;

    protected ModuleTemplate $moduleTemplate;
    public function __construct(
        protected ModuleTemplateFactory $moduleTemplateFactory,
        protected IconFactory $iconFactory
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($request);

        /** @var LanguageService $languageService */
        $languageService = $this->getLanguageService();
        $this->addDocHeader('active');
        $this->addDocHeaderShortcutButton( $this->moduleTemplate, 'show', $request->getParsedBody()['id'] ?? 0);
        $title = $languageService->sL('LLL:EXT:pongback/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab');
        $this->moduleTemplate->setTitle($title);

        $pingbacks = GeneralUtility::makeInstance(PingbackRepository::class)->findAll();
        $this->buildSlideWindowPagination($pingbacks, 5, 5);

        return $this->moduleTemplate->renderResponse('Administration/Index');
    }

    private function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    private function addDocHeader(string $active = 'inactive'): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        $list = $buttonBar->makeLinkButton()
            ->setHref('ruri')
            ->setTitle('A Title')
            ->setShowLabelText('Link')
            ->setIcon($this->iconFactory->getIcon('actions-extension-import', Icon::SIZE_SMALL));
        $buttonBar->addButton($list, ButtonBar::BUTTON_POSITION_LEFT, 1);
    }

    protected function addDocHeaderShortcutButton(ModuleTemplate $moduleTemplate, string $name, string $action = '', int $taskUid = 0): void
    {
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $shortcutArguments = [];
        if ($action) {
            $shortcutArguments['action'] = $action;
        }
        if ($taskUid) {
            $shortcutArguments['uid'] = $taskUid;
        }
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier('pongback_admin')
            ->setDisplayName($name)
            ->setArguments($shortcutArguments);
        $buttonBar->addButton($shortcutButton);
    }
}
