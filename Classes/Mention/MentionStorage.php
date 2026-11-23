<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Mention;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use Webicorns\Pongback\Domain\Model\Pingback;
use Webicorns\Pongback\Utility\Constants;

/**
 * Persistence of received mentions. Uses plain DBAL as it runs in a middleware,
 * outside of an Extbase request.
 */
readonly class MentionStorage
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private CacheManager $cacheManager,
    ) {}

    /**
     * Also finds hidden (not yet approved) records, so a pending mention cannot be sent twice.
     */
    public function exists(string $source, string $target): bool
    {
        return $this->find($source, $target) !== null;
    }

    /**
     * @return int|null uid of the (also hidden) record of this source and target
     */
    public function find(string $source, string $target): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Constants::PINGBACK_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        $uid = $queryBuilder
            ->select('uid')
            ->from(Constants::PINGBACK_TABLE)
            ->where(
                $queryBuilder->expr()->eq('source_link', $queryBuilder->createNamedParameter($source)),
                $queryBuilder->expr()->eq('target_link', $queryBuilder->createNamedParameter($target)),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return $uid === false ? null : (int) $uid;
    }

    /**
     * The source changed: new title and excerpt, the moderation state stays.
     */
    public function update(int $uid, string $sourceTitle, string $excerpt): void
    {
        $this->connectionPool->getConnectionForTable(Constants::PINGBACK_TABLE)->update(
            Constants::PINGBACK_TABLE,
            ['source_title' => $sourceTitle, 'excerpt' => $excerpt, 'tstamp' => time()],
            ['uid' => $uid],
            ['tstamp' => Connection::PARAM_INT],
        );
        $this->flushPages();
    }

    /**
     * The source was deleted or no longer links to the target.
     */
    public function remove(int $uid): void
    {
        $this->connectionPool->getConnectionForTable(Constants::PINGBACK_TABLE)->update(
            Constants::PINGBACK_TABLE,
            ['deleted' => 1, 'tstamp' => time()],
            ['uid' => $uid],
            ['deleted' => Connection::PARAM_INT, 'tstamp' => Connection::PARAM_INT],
        );
        $this->flushPages();
    }

    public function add(Pingback $pingback, int $languageId): int
    {
        $now = time();
        $connection = $this->connectionPool->getConnectionForTable(Constants::PINGBACK_TABLE);
        $connection->insert(
            Constants::PINGBACK_TABLE,
            [
                'pid' => (int) $pingback->getPid(),
                'sys_language_uid' => $languageId,
                'hidden' => (int) $pingback->isHidden(),
                'crdate' => $now,
                'tstamp' => $now,
                'protocol' => $pingback->getProtocol()->value,
                'source_link' => $pingback->getSourceLink(),
                'target_link' => $pingback->getTargetLink(),
                'source_title' => $pingback->getSourceTitle(),
                'excerpt' => $pingback->getExcerpt(),
            ],
            [
                'pid' => Connection::PARAM_INT,
                'sys_language_uid' => Connection::PARAM_INT,
                'hidden' => Connection::PARAM_INT,
                'crdate' => Connection::PARAM_INT,
                'tstamp' => Connection::PARAM_INT,
            ],
        );
        $uid = (int) $connection->lastInsertId();

        if (! $pingback->isHidden()) {
            $this->flushPages();
        }

        return $uid;
    }

    /**
     * Pages showing Pingbacks carry this tag, as for changes made with the DataHandler.
     */
    private function flushPages(): void
    {
        $this->cacheManager->flushCachesInGroupByTags('pages', [Constants::PINGBACK_TABLE]);
    }
}
