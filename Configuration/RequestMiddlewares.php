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
            // The page type is known after routing; answer before TypoScript is built, so no PAGE object is needed.
            'after' => ['typo3/cms-frontend/page-argument-validator'],
            'before' => ['typo3/cms-frontend/prepare-tsfe-rendering'],
        ],
    ],
];
