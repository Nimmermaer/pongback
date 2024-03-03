<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Webicorns\Pongback\Reaction\PingbackType;

defined('TYPO3') || die();

ExtensionManagementUtility::addTcaSelectItem(
    'sys_reaction',
    'reaction_type',
    [
        'label' => PingbackType::getDescription(),
        'value' => PingbackType::getType(),
        'icon' => PingbackType::getIconIdentifier(),
    ]
);
