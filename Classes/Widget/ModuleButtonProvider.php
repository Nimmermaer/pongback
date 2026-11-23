<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Widget;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;
use Webicorns\Pongback\Controller\Backend\ModerationController;

/**
 * Button of the widget: opens the module "Web > Pongback". The URL needs a token, so it is built at runtime.
 */
readonly class ModuleButtonProvider implements ButtonProviderInterface
{
    public function __construct(
        private UriBuilder $uriBuilder,
    ) {}

    public function getTitle(): string
    {
        return 'LLL:EXT:pongback/Resources/Private/Language/locallang_be.xlf:widgets.pongback.button';
    }

    public function getLink(): string
    {
        return (string) $this->uriBuilder->buildUriFromRoute(ModerationController::MODULE);
    }

    public function getTarget(): string
    {
        return '';
    }
}
