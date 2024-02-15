<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

call_user_func(
    static function (string $extensionKey, $table): void {
        ExtensionManagementUtility::addStaticFile($extensionKey, 'Configuration/TypoScript', 'EXT:' . $extensionKey);
    },
    'pongback',
    basename(__FILE__, '.php')
);
