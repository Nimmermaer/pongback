<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

call_user_func(
    static function ($extensionKey, $table): void {
        ExtensionUtility::registerPlugin(
            $extensionKey,
            'list',
            'LLL:EXT:pongback/Resources/Private/Language/locallang_be.xlf:plugin.tx_pongback.pongback_list.title',
            'tx-pongback-pong'
        );
        ExtensionUtility::registerPlugin(
            $extensionKey,
            'latest',
            'LLL:EXT:pongback/Resources/Private/Language/locallang_be.xlf:plugin.tx_pongback.pongback_latest.title',
            'tx-pongback-pong',
        );

        $GLOBALS['TCA'][$table]['types']['list']['subtypes_excludelist'] = array_replace_recursive(
            $GLOBALS['TCA'][$table]['types']['list']['subtypes_excludelist'],
            [
                'pongback_list' => 'pages,layout,select_key,recursive',
                'pongback_latest' => 'pages,layout,select_key,recursive',
            ]
        );
    },
    'pongback',
    basename(__FILE__, '.php')
);
