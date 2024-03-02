<?php

namespace Webicorns\Pongback\Controller\Backend;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItem;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webicorns\Pongback\Traits\PaginationTrait;

abstract class AbstractModuleController
{
    use PaginationTrait;

    protected int $id = 0;

    protected string $returnUrl = '';

    protected array $pageInfo = [];

    protected ModuleTemplate $moduleTemplate;

    public function __construct(
        protected ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly IconFactory $iconFactory,
        protected readonly UriBuilder $uriBuilder,
    ) {
    }

    public function getId(ServerRequestInterface $request): int
    {
        $parsedBody = $request->getParsedBody();
        $queryParams = $request->getQueryParams();
        return (int) ($parsedBody['id'] ?? $queryParams['id'] ?? 0);
    }

    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    protected function addDocHeader(int $id, string $active = 'inactive', string $label = 'Administration'): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()
            ->getButtonBar();

        $dropDownButton = $buttonBar->makeDropDownButton()
            ->setLabel($label)
            ->setTitle('Pongback')
            ->setIcon($this->iconFactory->getIcon('tx-pongback-pong', Icon::SIZE_SMALL))
            ->addItem(
                GeneralUtility::makeInstance(DropDownItem::class)
                    ->setLabel('Pongbacks')
                    ->setHref((string) $this->uriBuilder->buildUriFromRoute('pingback_list', [
                        'id' => $id,
                    ])),
            )->addItem(
                GeneralUtility::makeInstance(DropDownItem::class)
                    ->setLabel('Administration')
                    ->setHref((string) $this->uriBuilder->buildUriFromRoute('pongback_admin', [
                        'id' => $id,
                    ])),
            )
            ->setShowLabelText(true);
        $buttonBar->addButton($dropDownButton, buttonGroup: 2);
    }

    protected function addDocHeaderShortcutButton(
        ModuleTemplate $moduleTemplate,
        string $name,
        string $action = '',
        int $taskUid = 0
    ): void {
        $buttonBar = $moduleTemplate->getDocHeaderComponent()
            ->getButtonBar();
        $shortcutArguments = [];
        if ($action !== '' && $action !== '0') {
            $shortcutArguments['action'] = $action;
        }

        if ($taskUid !== 0) {
            $shortcutArguments['uid'] = $taskUid;
        }

        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier('pongback_admin')
            ->setDisplayName($name)
            ->setArguments($shortcutArguments);
        $buttonBar->addButton($shortcutButton);
    }
}
