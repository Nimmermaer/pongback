<?php

declare(strict_types=1);

use Webicorns\Pongback\Middleware\DiscoveryMiddleware;
use Webicorns\Pongback\Middleware\PingbackReceiverMiddleware;
use Webicorns\Pongback\Middleware\WebmentionReceiverMiddleware;

return [
    'frontend' => [
        'webicorns/pongback/discovery' => [
            'target' => DiscoveryMiddleware::class,
            'after' => ['typo3/cms-frontend/site'],
            'before' => ['typo3/cms-frontend/content-length-headers'],
        ],
        'webicorns/pongback/pingback-receiver' => [
            'target' => PingbackReceiverMiddleware::class,
            // The page type is known after routing; answer before TypoScript is built, so no PAGE object is needed.
            'after' => ['typo3/cms-frontend/page-argument-validator'],
            'before' => ['typo3/cms-frontend/prepare-tsfe-rendering'],
        ],
        'webicorns/pongback/webmention-receiver' => [
            'target' => WebmentionReceiverMiddleware::class,
            'after' => ['typo3/cms-frontend/page-argument-validator'],
            'before' => ['typo3/cms-frontend/prepare-tsfe-rendering'],
        ],
    ],
];
