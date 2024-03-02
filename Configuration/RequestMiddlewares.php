<?php

use Webicorns\Pongback\Middleware\PingbackMiddleware;

$extensionConfiguration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class);

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
