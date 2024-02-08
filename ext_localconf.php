<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Webicorns\Pongback\Controller\PingbackController;

defined('TYPO3') || die();

call_user_func(
    static function ($extensionKey): void {
        ExtensionUtility::configurePlugin(
            $extensionKey,
            'list',
            [
                PingbackController::class => 'list',
            ],
            [
                PingbackController::class => '',
            ]
        );
    },
    'pongback',
);
