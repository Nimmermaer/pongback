<?php

$EM_CONF['pongback'] = [
    'title' => 'pongback',
    'description' => 'TYPO3 extension for sending and receiving Pingbacks',
    'constraints' => [
        'depends' => [
            'typo3' => '12.0.0-12.4.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Webicorns\\Pongback\\' => 'Classes/',
        ],
    ],
];
