<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Webicorns\Pongback\Hooks;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webicorns\Pongback\Cache\CacheTags;
use Webicorns\Pongback\Utility\Constants;

class PingbackDataMap
{
    protected mixed $cacheManager = null;

    public function __construct()
    {
        $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class)->getCache(Constants::PONGBACK_CACHE);
    }

    public function processCmdmap(
        $command,
        $table,
        $id,
        $value,
        $commandIsProcessed,
        DataHandler $pObj,
        $pasteUpdate,
    ): void {
        if ($command === 'delete' || $command === 'move') {
            $this->clearPongbackCache($table, (string) $id, $command);
        }
    }

    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        string $id,
        &$fieldArray,
        DataHandler $pObj,
    ): void {
        $this->clearPongbackCache($table, $id, $status);
    }

    private function clearPongbackCache(string $table, string $id, string $status): void
    {
        if ($table === Constants::PINGBACK_TABLE) {
            $this->cacheManager->flushByTags(CacheTags::affectedByPage((int) $id));
        }

        if ($table === Constants::PINGBACK_TABLE && $status !== 'new') {
            $record = BackendUtility::getRecord($table, $id);
            $this->cacheManager->flushByTag(CacheTags::forPage((int) $record['pid']));
        }
    }
}
