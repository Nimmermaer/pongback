<?php

declare(strict_types=1);

use Webicorns\Pongback\Controller\Backend\ModerationController;

return [
    ModerationController::MODULE => [
        'parent' => 'web',
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/web/pongback',
        'labels' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'tx-pongback-pong',
        'navigationComponent' => '@typo3/backend/tree/page-tree-element',
        'routes' => [
            '_default' => [
                'target' => ModerationController::class . '::indexAction',
            ],
            'moderate' => [
                'target' => ModerationController::class . '::moderateAction',
                'methods' => ['POST'],
            ],
        ],
    ],
];
