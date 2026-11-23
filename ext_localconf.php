<?php

declare(strict_types=1);

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Webicorns\Pongback\Controller\PingbackController;
use Webicorns\Pongback\Hooks\PageChangeHook;
use Webicorns\Pongback\Sending\SendPingbacksMessage;

defined('TYPO3') || die();

(static function (string $extensionKey): void {
    foreach (['list', 'latest'] as $pluginName) {
        ExtensionUtility::configurePlugin(
            $extensionKey,
            $pluginName,
            [
                PingbackController::class => $pluginName,
            ],
            // Cached: pages showing Pingbacks are flushed via the cache tag of the Pingback table
            [],
            ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
        );
    }

    // Queue sending Pingbacks when pages or their content change
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
        PageChangeHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] =
        PageChangeHook::class;

    // Email "PongbackPingbackReceived" (Notification\NotifyEditors)
    $GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][1732600000] = 'EXT:pongback/Resources/Private/Templates/Email/';

    // Sending runs asynchronously: "vendor/bin/typo3 messenger:consume doctrine"
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][SendPingbacksMessage::class] = 'doctrine';
})('pongback');
