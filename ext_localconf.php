<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Webicorns\Pongback\Controller\PingbackController;
use Webicorns\Pongback\Hooks\PingbackDataMap;
use Webicorns\Pongback\Listener\PageModificationListener;
use Webicorns\Pongback\Message\PingbackMessage;
use Webicorns\Pongback\MetaTag\PingbackMetaTagGenerator;
use Webicorns\Pongback\Utility\Constants;

defined('TYPO3') || die();

call_user_func(
    static function ($extensionKey): void {
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        ExtensionUtility::configurePlugin(
            $extensionKey,
            'list',
            [
                PingbackController::class => 'list',
            ],
            [
                PingbackController::class => 'list',
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

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
            PageModificationListener::class;

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] =
            PingbackDataMap::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
            PingbackDataMap::class;

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][PingbackMessage::class] = 'doctrine';

        if ($extensionConfiguration->get($extensionKey, 'includePingbackHeader')) {
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['TYPO3\CMS\Frontend\Page\PageGenerator']['generateMetaTags'][1_709_405_732_751] =
                PingbackMetaTagGenerator::class . '->generate';
        }
    },
    'pongback',
);
