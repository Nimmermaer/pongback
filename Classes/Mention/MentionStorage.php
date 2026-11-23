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
use Webicorns\Pongback\Cache\CacheTags;
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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Constants::PINGBACK_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        return (int) $queryBuilder
            ->count('uid')
            ->from(Constants::PINGBACK_TABLE)
            ->where(
                $queryBuilder->expr()->eq('source_link', $queryBuilder->createNamedParameter($source)),
                $queryBuilder->expr()->eq('target_link', $queryBuilder->createNamedParameter($target)),
            )
            ->executeQuery()
            ->fetchOne() > 0;
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

        $this->cacheManager->getCache(Constants::PONGBACK_CACHE)
            ->flushByTags(CacheTags::affectedByPage((int) $pingback->getPid()));

        return $uid;
    }
}
