<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

call_user_func(
    static function (string $extensionKey, $table): void {
        $plugins = [
            'list' => 'tx-pongback-pong',
            'latest' => 'tx-pongback-pong',
        ];

        foreach ($plugins as $pluginName => $pluginIcon) {
            $pluginSignature = ExtensionUtility::registerPlugin(
                $extensionKey,
                $pluginName,
                'LLL:EXT:' . $extensionKey . '/Resources/Private/Language/locallang_be.xlf:plugin.tx_pongback.pongback_' . $pluginName . '.title',
                $pluginIcon
            );
            $GLOBALS['TCA'][$table]['types']['list']['subtypes_excludelist'] = array_replace_recursive(
                $GLOBALS['TCA'][$table]['types']['list']['subtypes_excludelist'],
                [
                    'pongback_' . $pluginName => 'pages,layout,select_key,recursive',
                ]
            );
            $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature]
                = 'pi_flexform';
            ExtensionManagementUtility::addPiFlexFormValue(
                $pluginSignature,
                'FILE:EXT:' . $extensionKey . '/Configuration/Flexforms/PluginPongback.xml'
            );
        }
    },
    'pongback',
    basename(__FILE__, '.php')
);
