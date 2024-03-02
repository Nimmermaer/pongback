<?php

use Webicorns\Pongback\Middleware\PingbackMiddleware;

return [
    'frontend' => [
        'pongback-pingback-header' => [
            'target' => PingbackMiddleware::class,
            'before' => ['typo3/cms-frontend/timetracker'],
        ],
    ],
];
