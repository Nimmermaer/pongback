<?php

declare(strict_types=1);

use Webicorns\Pongback\Middleware\PingbackDiscoveryMiddleware;
use Webicorns\Pongback\Middleware\PingbackReceiverMiddleware;

return [
    'frontend' => [
        'webicorns/pongback/pingback-discovery' => [
            'target' => PingbackDiscoveryMiddleware::class,
            'after' => ['typo3/cms-frontend/site'],
            'before' => ['typo3/cms-frontend/content-length-headers'],
        ],
        'webicorns/pongback/pingback-receiver' => [
            'target' => PingbackReceiverMiddleware::class,
            // needs the resolved page type ("routing") and the frontend TypoScript ("frontend.typoscript")
            'after' => ['typo3/cms-frontend/prepare-tsfe-rendering'],
            'before' => ['typo3/cms-frontend/content-length-headers'],
        ],
    ],
];
