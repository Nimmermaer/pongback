<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Webicorns\Pongback\Controller\PingbackController;
use Webicorns\Pongback\Hooks\PingbackDataMap;
use Webicorns\Pongback\Listener\PageModificationListener;
use Webicorns\Pongback\Message\PingbackMessage;
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

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
        PageModificationListener::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] =
        PingbackDataMap::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
        PingbackDataMap::class;

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][PingbackMessage::class] = 'doctrine';
})('pongback');
