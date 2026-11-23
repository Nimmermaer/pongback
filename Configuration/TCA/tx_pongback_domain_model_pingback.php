<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

if (! defined('TYPO3')) {
    die('Access denied.');
}

$tca = [
    'ctrl' => [
        'title' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback',
        'label' => 'source_link',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
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
        'typeicon_classes' => [
            'default' => 'tx-pongback-pong',
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'sys_language_uid,
            --palette--,l10n_parent,l10n_diffsource,hidden,
            --palette--;;1,
            source_link,
            source_title,
            target_link,
            excerpt,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.access,starttime,endtime',
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
                'items' => [
                    [
                        'label' => '',
                        'value' => 0,
                    ],
                ],
                'foreign_table' => 'tx_pongback_domain_model_pingback',
                'foreign_table_where' => 'AND tx_pongback_domain_model_pingback.pid=###CURRENT_PID### AND tx_pongback_domain_model_pingback.sys_language_uid IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
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
                'type' => 'datetime',
                'searchable' => false,
                'default' => 0,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'endtime' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.endtime',
            'config' => [
                'type' => 'datetime',
                'searchable' => false,
                'default' => 0,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'source_link' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.source_link',
            'description' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.source_link.description',
            'config' => [
                'type' => 'link',
                'size' => 50,
            ],
        ],
        'source_title' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.source_title',
            'description' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.source_title.description',
            'config' => [
                'type' => 'input',
                'size' => 50,
            ],
        ],
        'target_link' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.target_link',
            'description' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.target_link.description',
            'config' => [
                'type' => 'link',
                'size' => 50,
            ],
        ],
        'excerpt' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.excerpt',
            'description' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.excerpt.description',
            'config' => [
                'type' => 'text',
                'rows' => 5,
            ],
        ],
    ],
];

// TYPO3 v13 needs "searchFields", v14 uses the "searchable" field option instead and deprecates "searchFields".
if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 14) {
    $tca['ctrl']['searchFields'] = 'source_link,source_title,target_link,excerpt';
}

return $tca;
