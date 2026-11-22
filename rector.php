<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

/*
 * The extension supports TYPO3 v13 and v14 at the same time.
 * Only migrate up to the lowest supported version, otherwise Rector would
 * introduce v14-only APIs. Raise the level set when v13 support is dropped.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/Classes',
        __DIR__ . '/Configuration',
        __DIR__ . '/Tests',
        __DIR__ . '/ext_emconf.php',
        __DIR__ . '/ext_localconf.php',
    ])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withPhpSets(php82: true)
    ->withSets([
        Typo3LevelSetList::UP_TO_TYPO3_13,
        Typo3SetList::CODE_QUALITY,
        Typo3SetList::GENERAL,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
