<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Webicorns\Pongback\Controller\PingbackController;

defined('TYPO3') || die();

ExtensionUtility::configurePlugin(
    'Pongback',
    'list',
    [
        PingbackController::class => 'list',
    ],
    [
        PingbackController::class => '',
    ]
);
