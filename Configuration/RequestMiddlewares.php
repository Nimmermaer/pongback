<?php

declare(strict_types=1);

use PHTH\Pongback\Middlewares\PingbackHeader;

return [
    'frontend' => [
        'php/pongback/pingback-header' => [
            'target' => PingbackHeader::class,
            'after' => [
                'typo3/cms-frontend/tsfe',
            ],
        ],
    ],
];
