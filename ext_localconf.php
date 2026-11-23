<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Webicorns\Pongback\Controller\PingbackController;
use Webicorns\Pongback\Hooks\PageChangeHook;
use Webicorns\Pongback\Hooks\PingbackDataMap;
use Webicorns\Pongback\Sending\SendPingbacksMessage;
use Webicorns\Pongback\Utility\Constants;

defined('TYPO3') || die();

(static function (string $extensionKey): void {
    foreach (['list', 'latest'] as $pluginName) {
        ExtensionUtility::configurePlugin(
            $extensionKey,
            $pluginName,
            [
                PingbackController::class => $pluginName,
            ],
            [
                PingbackController::class => $pluginName,
            ],
            ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
        );
    }

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][Constants::PONGBACK_CACHE] ??= [
        'backend' => Typo3DatabaseBackend::class,
        'frontend' => VariableFrontend::class,
        'options' => [
            'defaultLifetime' => 0,
        ],
    ];

    // Queue sending Pingbacks when pages or their content change
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
        PageChangeHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] =
        PageChangeHook::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] =
        PingbackDataMap::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
        PingbackDataMap::class;

    // Sending runs asynchronously: "vendor/bin/typo3 messenger:consume doctrine"
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][SendPingbacksMessage::class] = 'doctrine';
})('pongback');
