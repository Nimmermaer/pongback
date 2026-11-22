<?php

declare(strict_types=1);

use Webicorns\Pongback\Controller\Backend\PingbackModuleController;

return [
    'pongback_enabled_pingback' => [
        'path' => '/pongback/enabled-pingback',
        'target' => PingbackModuleController::class . '::enabledAction',
    ],
];
