<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

call_user_func(
    static function ($extensionKey): void {
        ExtensionUtility::registerPlugin(
            $extensionKey,
            'list',
            'LLL:EXT:pongback/Resources/Private/Language/locallang_be.xlf:tx_pongback_plugin.pongback_frontend'
        );
    },
    'pongpack',
    basename(__FILE__, '.php')
);
