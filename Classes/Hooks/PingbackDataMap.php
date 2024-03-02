<?php

declare(strict_types=1);

namespace Webicorns\Pongback\Hooks;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webicorns\Pongback\Utility\Constants;

class PingbackDataMap
{
    /**
     * @var string
     */
    final public const TABLE = 'tx_pongback_domain_model_pingback';

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
        $pasteUpdate
    ): void {
        if ($command === 'delete' || $command === 'move') {
            $this->clearPongbackCache($table, $id, $command);
        }
    }

    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        string $id,
        &$fieldArray,
        DataHandler $pObj
    ): void {
        $this->clearPongbackCache($table, $id, $status);
    }

    private function clearPongbackCache(string $table, string $id, string $status): void
    {
        if ($table === self::TABLE) {
            $this->cacheManager->flushByTags(['pageId_' . $id, 'pingback_latest_view', 'pingback_list_view']);
        }

        if ($table === self::TABLE && $status !== 'new') {
            $record = BackendUtility::getRecord($table, $id);
            $this->cacheManager->flushByTag('pageId_' . $record['pid']);
        }
    }
}
