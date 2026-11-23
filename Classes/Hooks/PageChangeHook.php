<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Hooks;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\MathUtility;
use Webicorns\Pongback\Configuration\PongbackSettings;
use Webicorns\Pongback\Messaging\MessageQueue;
use Webicorns\Pongback\Sending\SendPingbacksMessage;

/**
 * DataHandler hook: queues sending the Pingbacks of every live page whose record or content changed.
 *
 * Changes are collected per DataHandler run and dispatched once per page and language
 * when the run is finished. Workspace changes are queued when they are published.
 */
class PageChangeHook
{
    /**
     * @var array<string, SendPingbacksMessage>
     */
    private array $pending = [];

    public function __construct(
        private readonly MessageQueue $queue,
        private readonly SiteFinder $siteFinder,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        int|string $id,
        array $fieldArray,
        DataHandler $dataHandler,
    ): void {
        if ((int) $dataHandler->BE_USER->workspace !== 0) {
            return;
        }

        $uid = MathUtility::canBeInterpretedAsInteger($id) ? (int) $id : (int) ($dataHandler->substNEWwithIDs[$id] ?? 0);
        $this->collect($table, $uid);
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $this->dispatchPending();
    }

    /**
     * Publishing a workspace version makes the change visible.
     */
    public function processCmdmap_postProcess(
        string $command,
        string $table,
        int|string $id,
        mixed $value,
        DataHandler $dataHandler,
    ): void {
        if ($command === 'version' && is_array($value) && ($value['action'] ?? '') === 'publish') {
            $this->collect($table, (int) $id);
        }
    }

    public function processCmdmap_afterFinish(DataHandler $dataHandler): void
    {
        $this->dispatchPending();
    }

    private function collect(string $table, int $uid): void
    {
        if ($uid <= 0) {
            return;
        }

        $record = BackendUtility::getRecord($table, $uid, '*', '', false);
        if (! is_array($record)) {
            return;
        }

        [$pageId, $languageId] = $this->pageAndLanguage($table, $uid, $record);
        if ($pageId <= 0) {
            return;
        }

        try {
            $settings = PongbackSettings::fromSite($this->siteFinder->getSiteByPageId($pageId));
        } catch (SiteNotFoundException) {
            return;
        }

        if ($settings->sendEnabled && in_array($table, $settings->sendTables, true)) {
            $this->pending[$pageId . '-' . $languageId] = new SendPingbacksMessage($pageId, $languageId);
        }
    }

    /**
     * @param array<string, mixed> $record
     * @return array{int, int} page uid (default language) and language ID
     */
    private function pageAndLanguage(string $table, int $uid, array $record): array
    {
        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? null;
        $languageId = max(0, (int) ($languageField !== null ? ($record[$languageField] ?? 0) : 0));

        if ($table !== 'pages') {
            return [(int) ($record['pid'] ?? 0), $languageId];
        }

        // Page translations are records of their own, the router expects the default language page.
        $parent = (int) ($record['l10n_parent'] ?? 0);

        return [$languageId > 0 && $parent > 0 ? $parent : $uid, $languageId];
    }

    private function dispatchPending(): void
    {
        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $message) {
            try {
                $this->queue->queue($message);
            } catch (\Throwable $throwable) {
                // Never break saving in the backend because of the queue.
                $this->logger->error('Could not queue sending Pingbacks: ' . $throwable->getMessage(), [
                    'pageId' => $message->pageId,
                ]);
            }
        }
    }
}
