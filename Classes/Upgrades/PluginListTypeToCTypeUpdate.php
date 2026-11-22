<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Upgrades;

use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate;

/**
 * Migrates existing Pongback plugins from "list_type" to their own content types (CType).
 *
 * The EXT:install namespace is used on purpose: it exists in TYPO3 v13 and is kept as alias in v14.
 * Switch to the EXT:core classes once TYPO3 v13 support is dropped.
 */
#[UpgradeWizard('pongbackPluginListTypeToCTypeUpdate')]
final class PluginListTypeToCTypeUpdate extends AbstractListTypeToCTypeUpdate
{
    public function getTitle(): string
    {
        return 'Pongback: Migrate plugins to content elements';
    }

    public function getDescription(): string
    {
        return 'Migrates existing Pongback plugins ("list_type") to dedicated content types ("CType").';
    }

    /**
     * @return array<string, string>
     */
    protected function getListTypeToCTypeMapping(): array
    {
        return [
            'pongback_list' => 'pongback_list',
            'pongback_latest' => 'pongback_latest',
        ];
    }
}
