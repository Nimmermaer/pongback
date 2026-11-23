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
        'default_sortby' => 'crdate DESC',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        // Moderated in the module "Web > Pongback"
        'hideTable' => true,
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
            'showitem' => 'hidden, protocol, source_link, source_title, target_link, excerpt, sys_language_uid,
                --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.access, starttime, endtime',
        ],
    ],
    'columns' => [
        // hidden, starttime, endtime and the language fields are added by TYPO3 from "ctrl" (TcaEnrichment, 13.4+)
        // Mapped for the date shown in the frontend, Extbase only maps fields with a column definition.
        'crdate' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'protocol' => [
            'label' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_db.xlf:tx_pongback_domain_model_pingback.protocol',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'readOnly' => true,
                'dbFieldLength' => 16,
                'items' => [
                    ['label' => 'Pingback', 'value' => 'pingback'],
                    ['label' => 'Webmention', 'value' => 'webmention'],
                ],
                'default' => 'pingback',
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
