<?php

declare(strict_types=1);
use PHTH\Pongback\Controller\PingbackController;
use PHTH\Pongback\Domain\Validator\PingbackValidator;
use PHTH\Pongback\Hook\Tcemain;
use PHTH\Pongback\Service\PingbackClient;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

if (! defined('TYPO3')) {
    die('Access denied.');
}

ExtensionUtility::configurePlugin(
    'Pongback',
    'Pongbackfrontend',
    [
        PingbackController::class => 'list, show',
    ],
    // non-cacheable actions
    [
        PingbackController::class => '',
    ]
);
ExtensionUtility::configurePlugin(
    'Pongback',
    'Pongbackbackend',
    [
        PingbackController::class => 'list, show, delete, edit, publish, unpublish',
    ],
    // non-cacheable actions
    [
        PingbackController::class => '',
    ]
);

ExtensionUtility::configurePlugin(
    'Pongback',
    'server',
    [
        PingbackController::class => 'server',
    ],
    // non-cacheable actions
    [
        PingbackController::class => 'server',
    ]
);

$GLOBALS['TYPO3_CONF_VARS']['MAIL']['substituteOldMailAPI'] = '0';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pongback']['validatePingback'][] = PingbackValidator::class . '->validateTargetUri';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pongback']['validatePingback'][] = PingbackValidator::class . '->getInformationFromOtherWebsite';
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pongback']['validatePingback'][] = PingbackClient::class . '->mailPingbackArrived';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = Tcemain::class;
