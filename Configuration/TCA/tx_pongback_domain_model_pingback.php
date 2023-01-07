<?php

declare(strict_types=1);
if (! defined('TYPO3')) {
    die('Access denied.');
}

return [
    'ctrl' => [
        'title' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback',
        'label' => 'source_link',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
        'versioningWS' => true,
        'origUid' => 't3_origuid',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'source_link,ping_client,ping_ressource,target_link,serialized_information,',
        'iconfile' => 'EXT:pongback/Resources/Public/Icons/tx_pongback_domain_model_pingback.gif',
    ],
    'types' => [
        '1' => [
            'showitem' => 'sys_language_uid,--palette--,l10n_parent,l10n_diffsource,hidden,--palette--;;1,source_link,ping_client,ping_ressource,target_link,serialized_information,--div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.access,starttime,endtime',
        ],
    ],
    'palettes' => [
        '1' => [
            'showitem' => '',
        ],
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language',
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [['', 0]],
                'foreign_table' => 'tx_pongback_domain_model_pingback',
                'foreign_table_where' => 'AND tx_pongback_domain_model_pingback.pid=###CURRENT_PID### AND tx_pongback_domain_model_pingback.sys_language_uid IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        't3ver_label' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.versionLabel',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
            ],
        ],
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
            ],
        ],
        'starttime' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.starttime',
            'config' => [
                'type' => 'input',
                'size' => 13,
                'eval' => 'datetime',
                'checkbox' => 0,
                'default' => 0,
                'range' => [
                    'lower' => mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y')),
                ],
                'renderType' => 'inputDateTime',
                [
                    'behaviour' => [
                        'allowLanguageSynchronization' => true,
                    ],
                ],
            ],
        ],
        'endtime' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.endtime',
            'config' => [
                'type' => 'input',
                'size' => 13,
                'eval' => 'datetime',
                'checkbox' => 0,
                'default' => 0,
                'range' => [
                    'lower' => mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y')),
                ],
                'renderType' => 'inputDateTime',
                [
                    'behaviour' => [
                        'allowLanguageSynchronization' => true,
                    ],
                ],
            ],
        ],
        'source_link' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.source_link',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'ping_client' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.ping_client',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'ping_ressource' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.ping_ressource',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'target_link' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.target_link',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'serialized_information' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.serialized_information',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
    ],
];
