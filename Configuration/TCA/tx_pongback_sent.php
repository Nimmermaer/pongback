<?php

declare(strict_types=1);

defined('TYPO3') || die();

$ll = 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_sent';
// "max" also sets the column type: URLs get a text column, the message a varchar(255).
$readOnlyInput = static fn(string $field, int $max = 2048): array => [
    'label' => $ll . '.' . $field,
    'config' => [
        'type' => 'input',
        'max' => $max,
        'readOnly' => true,
    ],
];

return [
    'ctrl' => [
        'title' => $ll,
        'label' => 'target_link',
        'label_alt' => 'status',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'tstamp DESC',
        'delete' => 'deleted',
        'typeicon_classes' => [
            'default' => 'tx-pongback-pong',
        ],
        // Written by the extension only
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'status, protocol, target_link, source_link, endpoint, fault_code, message, attempts',
        ],
    ],
    'columns' => [
        'source_link' => $readOnlyInput('source_link'),
        'target_link' => $readOnlyInput('target_link'),
        'endpoint' => $readOnlyInput('endpoint'),
        'protocol' => [
            'label' => $ll . '.protocol',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'readOnly' => true,
                'dbFieldLength' => 16,
                'items' => [
                    ['label' => '', 'value' => ''],
                    ['label' => 'Pingback', 'value' => 'pingback'],
                    ['label' => 'Webmention', 'value' => 'webmention'],
                ],
            ],
        ],
        'status' => [
            'label' => $ll . '.status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'readOnly' => true,
                'items' => [
                    ['label' => $ll . '.status.sent', 'value' => 'sent'],
                    ['label' => $ll . '.status.already_registered', 'value' => 'already_registered'],
                    ['label' => $ll . '.status.no_endpoint', 'value' => 'no_endpoint'],
                    ['label' => $ll . '.status.rejected', 'value' => 'rejected'],
                    ['label' => $ll . '.status.failed', 'value' => 'failed'],
                ],
            ],
        ],
        'fault_code' => [
            'label' => $ll . '.fault_code',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'message' => $readOnlyInput('message', 255),
        'attempts' => [
            'label' => $ll . '.attempts',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
    ],
];
