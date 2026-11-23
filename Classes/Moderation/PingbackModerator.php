<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Moderation;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webicorns\Pongback\Utility\Constants;

/**
 * Approves, hides and deletes Pingbacks as the current backend user.
 *
 * The DataHandler checks the permissions (table, page), writes the history and
 * flushes the caches of all pages showing Pingbacks.
 */
class PingbackModerator
{
    public const APPROVE = 'approve';
    public const HIDE = 'hide';
    public const DELETE = 'delete';

    public const OPERATIONS = [self::APPROVE, self::HIDE, self::DELETE];

    /**
     * @return list<string> errors, empty on success
     */
    public function moderate(string $operation, int $uid): array
    {
        $data = [];
        $commands = [];
        match ($operation) {
            self::APPROVE => $data[Constants::PINGBACK_TABLE][$uid]['hidden'] = 0,
            self::HIDE => $data[Constants::PINGBACK_TABLE][$uid]['hidden'] = 1,
            self::DELETE => $commands[Constants::PINGBACK_TABLE][$uid]['delete'] = 1,
            default => throw new \InvalidArgumentException('Unknown operation "' . $operation . '"', 1732600001),
        };

        $dataHandler = $this->createDataHandler();
        $dataHandler->start($data, $commands);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();

        return $dataHandler->errorLog;
    }

    protected function createDataHandler(): DataHandler
    {
        return GeneralUtility::makeInstance(DataHandler::class);
    }
}
