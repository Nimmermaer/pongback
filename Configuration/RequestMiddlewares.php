<?php

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webicorns\Pongback\Middleware\PingbackMiddleware;

$extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);

if ($extensionConfiguration->get('pongback', 'includePingbackHTTPHeader')) {
    return [
        'frontend' => [
            'pongback-pingback-header' => [
                'target' => PingbackMiddleware::class,
                'before' => ['typo3/cms-frontend/content-length-headers'],
            ],
        ],
    ];
}
