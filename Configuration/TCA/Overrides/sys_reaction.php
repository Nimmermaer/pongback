<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Schema\Struct\SelectItem;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Webicorns\Pongback\Reaction\PingbackType;

defined('TYPO3') || die();

ExtensionManagementUtility::addTcaSelectItem(
    'sys_reaction',
    'reaction_type',
    new SelectItem(
        type: 'select',
        label: PingbackType::getDescription(),
        value: PingbackType::getType(),
        icon: PingbackType::getIconIdentifier(),
    ),
);
