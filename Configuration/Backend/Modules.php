<?php

declare(strict_types=1);

use Webicorns\Pongback\Controller\Backend\AdminModuleController;
use Webicorns\Pongback\Controller\Backend\PingbackModuleController;

return [
    'pongback_admin' => [
        'parent' => 'web',
        'position' => ['top'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/pongback/administration',
        'labels' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'tx-pongback-pong',
        'navigationComponent' => '@typo3/backend/page-tree/page-tree-element',
        'routes' => [
            '_default' => [
                'target' => AdminModuleController::class . '::handleRequest',
            ],
        ],
    ],
    'pingback_list' => [
        'parent' => 'web',
        'position' => ['top'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/pongback/list/pingback',
        'labels' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'tx-pongback-pong',
        'navigationComponent' => '@typo3/backend/page-tree/page-tree-element',
        'routes' => [
            '_default' => [
                'target' => PingbackModuleController::class . '::handleRequest',
            ],
            'edit' => [
                'target' => PingbackModuleController::class . '::editAction',
                'methods' => ['POST'],
            ],
        ],
    ],
];
