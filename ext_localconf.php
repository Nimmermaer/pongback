<?php

use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Webicorns\Pongback\Controller\PingbackController;
use Webicorns\Pongback\Hooks\PongbackDataMap;
use Webicorns\Pongback\Utility\Constants;

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
        ExtensionUtility::configurePlugin(
            $extensionKey,
            'latest',
            [
                PingbackController::class => 'latest',
            ],
            [
                PingbackController::class => 'latest',
            ]
        );
        if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][Constants::PONGBACK_CACHE] ?? true) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][Constants::PONGBACK_CACHE] = [
                'backend' => Typo3DatabaseBackend::class,
                'frontend' => VariableFrontend::class,
                'options' => [
                    'defaultLifetime' => 0,
                ],
            ];
        }

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] =
            PongbackDataMap::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
            PongbackDataMap::class;
    },
    'pongback',
);
