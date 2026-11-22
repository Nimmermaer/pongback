<?php

$EM_CONF['pongback'] = [
    'title' => 'Pongback',
    'description' => 'TYPO3 extension for sending and receiving Pingbacks',
    'category' => 'fe',
    'state' => 'beta',
    'version' => '0.9.0',
    'author' => 'Michael Blunck',
    'author_email' => 'michael.blunck@webicorns.com',
    'author_company' => 'Webicorns',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.4.0-14.99.99',
            'install' => '13.4.0-14.99.99',
            'reactions' => '13.4.0-14.99.99',
            'webhooks' => '13.4.0-14.99.99',
        ],
        'suggests' => [
            'dashboard' => '13.4.0-14.99.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Webicorns\\Pongback\\' => 'Classes/',
        ],
    ],
];
