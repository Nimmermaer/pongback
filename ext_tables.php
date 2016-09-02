<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}


\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    $_EXTKEY,
    'Pongbackfrontend',
    'LLL:EXT:pongback/Resources/Private/Language/locallang_be.xlf:tx_pongback_plugin.pongback_frontend'
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    $_EXTKEY,
    'Server',
    'LLL:EXT:pongback/Resources/Private/Language/locallang_be.xlf:tx_pongback_plugin.pongback_server'
);

if (TYPO3_MODE === 'BE') {

    /**
     * Registers a Backend Module
     */
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'PHTH.' . $_EXTKEY,
        'web',     // Make module a submodule of 'web'
        'pongbackbackend',    // Submodule key
        '',                        // Position
        array(
            'Pingback' => 'administer, show, delete, edit, publish, unpublish ',
        ),
        array(
            'access' => 'user,group',
            'icon' => 'EXT:' . $_EXTKEY . '/ext_icon.gif',
            'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_pongbackbackend.xlf',
        )
    );

}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'Pongback');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_pongback_domain_model_pingback',
    'EXT:pongback/Resources/Private/Language/locallang_csh_tx_pongback_domain_model_pingback.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_pongback_domain_model_pingback');
