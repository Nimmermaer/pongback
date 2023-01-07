<?php

declare(strict_types=1);
use PHTH\Pongback\Controller\PingbackController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

if (! defined('TYPO3')) {
    die('Access denied.');
}


ExtensionUtility::registerPlugin(
    'pongback',
    'Pongbackfrontend',
    'LLL:EXT:pongback/Resources/Private/Language/locallang_be.xlf:tx_pongback_plugin.pongback_frontend'
);

ExtensionUtility::registerPlugin(
    'pongback',
    'Server',
    'LLL:EXT:pongback/Resources/Private/Language/locallang_be.xlf:tx_pongback_plugin.pongback_server'
);


/**
 * Registers a Backend Module
 */
ExtensionUtility::registerModule(
    'Pongback',
    'web',     // Make module a submodule of 'web'
    'pongbackbackend',    // Submodule key
    '',                        // Position
    [
        PingbackController::class => 'administer, show, delete, edit, publish, unpublish ',
    ],
    [
        'access' => 'user,group',
        'icon' => 'EXT:pongback/ext_icon.gif',
        'labels' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_pongbackbackend.xlf',
    ]
);


ExtensionManagementUtility::addStaticFile('pongback', 'Configuration/TypoScript', 'Pongback');

ExtensionManagementUtility::addLLrefForTCAdescr(
    'tx_pongback_domain_model_pingback',
    'EXT:pongback/Resources/Private/Language/locallang_csh_tx_pongback_domain_model_pingback.xlf'
);
ExtensionManagementUtility::allowTableOnStandardPages('tx_pongback_domain_model_pingback');
