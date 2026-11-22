<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die();

(static function (string $extensionKey): void {
    $flexForm = 'FILE:EXT:' . $extensionKey . '/Configuration/Flexforms/PluginPongback.xml';
    $isTypo3v14OrHigher = GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() >= 14;

    foreach (['list', 'latest'] as $pluginName) {
        $label = 'LLL:EXT:' . $extensionKey . '/Resources/Private/Language/locallang_be.xlf:plugin.tx_pongback.pongback_' . $pluginName . '.title';

        $contentType = ExtensionUtility::registerPlugin($extensionKey, $pluginName, $label, 'tx-pongback-pong');
        ExtensionManagementUtility::addToAllTCAtypes(
            'tt_content',
            '--div--;LLL:EXT:' . $extensionKey . '/Resources/Private/Language/locallang_be.xlf:plugin.pongback.configuration,pi_flexform',
            $contentType,
            'after:subheader',
        );

        if ($isTypo3v14OrHigher) {
            // TYPO3 v14+: data structure per content type (addPiFlexFormValue() is deprecated)
            $GLOBALS['TCA']['tt_content']['types'][$contentType]['columnsOverrides']['pi_flexform']['config']['ds'] = $flexForm;
        } else {
            // TYPO3 v13: data structure is matched by "<list_type>,<CType>"
            ExtensionManagementUtility::addPiFlexFormValue('*', $flexForm, $contentType);
        }
    }
})('pongback');
