<?php

return [
    'frontend' => [
        'phth/pongback/pingback-header' => [
            'target' => \PHTH\Pongback\Middlewares\PingbackHeader::class,
            'before' => [
                'typo3/cms-frontend/timetracker',
            ],
        ],
    ],
];
