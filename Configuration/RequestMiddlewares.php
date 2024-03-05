<?php

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webicorns\Pongback\Middleware\PingbackHeaderMiddleware;
use Webicorns\Pongback\Middleware\PingbackReceiverMiddleware;

$extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);

if ($extensionConfiguration->get('pongback', 'includePingbackHTTPHeader')) {
    $middleware['pongback-pingback-header'] = [
        'target' => PingbackHeaderMiddleware::class,
        'before' => ['typo3/cms-frontend/content-length-headers'],
    ];
}

$middleware['pongback-pingback-receiver'] = [
    'target' => PingbackReceiverMiddleware::class,
    'before' => ['typo3/cms-frontend/content-length-headers'],
];
return [
    'frontend' => $middleware,
];
