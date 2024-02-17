<?php


declare(strict_types=1);


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
                'target' => \Webicorns\Pongback\Controller\Backend\AdminModuleController::class . '::handleRequest',
            ],
        ],
    ],
];
