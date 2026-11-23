<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Sending;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Utility\Constants;

/**
 * One record per source page and linked page: what happened when the Pingback was sent.
 */
readonly class SentPingbackLog
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * True if this Pingback must not be sent again (sent, refused or no endpoint).
     */
    public function isFinal(int $pageId, string $source, string $target): bool
    {
        return $this->lastResult($pageId, $source, $target)?->status->isFinal() ?? false;
    }

    public function lastResult(int $pageId, string $source, string $target): ?PingResult
    {
        $row = $this->find($pageId, $source, $target);
        $status = $row === null ? null : PingStatus::tryFrom((string) $row['status']);
        if ($row === null || $status === null) {
            return null;
        }

        return new PingResult($status, (string) $row['message'], (int) $row['fault_code'], (string) $row['endpoint'], (string) $row['protocol']);
    }

    /**
     * Targets this page sent Webmentions to: if the page no longer links to them, they are notified once more.
     *
     * @return list<string>
     */
    public function webmentionTargets(int $pageId, string $source): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Constants::SENT_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        return array_map(strval(...), $queryBuilder
            ->select('target_link')
            ->from(Constants::SENT_TABLE)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('source_link', $queryBuilder->createNamedParameter($source)),
                $queryBuilder->expr()->eq('protocol', $queryBuilder->createNamedParameter(IncomingMention::PROTOCOL_WEBMENTION)),
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchFirstColumn());
    }

    /**
     * The page no longer links to the target and the target was told.
     */
    public function forget(int $pageId, string $source, string $target): void
    {
        $this->connectionPool->getConnectionForTable(Constants::SENT_TABLE)->delete(
            Constants::SENT_TABLE,
            ['pid' => $pageId, 'source_link' => $source, 'target_link' => $target],
            ['pid' => Connection::PARAM_INT],
        );
    }

    public function record(int $pageId, string $source, string $target, PingResult $result): void
    {
        $now = time();
        $values = [
            'tstamp' => $now,
            'endpoint' => mb_substr($result->endpoint, 0, 2048),
            'protocol' => $result->protocol,
            'status' => $result->status->value,
            'fault_code' => $result->faultCode,
            'message' => mb_substr($result->message, 0, 255),
        ];
        $types = [
            'tstamp' => Connection::PARAM_INT,
            'fault_code' => Connection::PARAM_INT,
            'attempts' => Connection::PARAM_INT,
            'pid' => Connection::PARAM_INT,
            'crdate' => Connection::PARAM_INT,
        ];

        $connection = $this->connectionPool->getConnectionForTable(Constants::SENT_TABLE);
        $existing = $this->find($pageId, $source, $target);
        if ($existing !== null) {
            $connection->update(
                Constants::SENT_TABLE,
                $values + ['attempts' => (int) $existing['attempts'] + 1],
                ['uid' => (int) $existing['uid']],
                $types,
            );
            return;
        }

        $connection->insert(
            Constants::SENT_TABLE,
            $values + [
                'pid' => $pageId,
                'crdate' => $now,
                'source_link' => $source,
                'target_link' => $target,
                'attempts' => 1,
            ],
            $types,
        );
    }

    /**
     * The page ID narrows the lookup down via the "parent" index that TYPO3 creates for every TCA table.
     *
     * @return array<string, mixed>|null
     */
    private function find(int $pageId, string $source, string $target): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Constants::SENT_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        $row = $queryBuilder
            ->select('uid', 'status', 'attempts', 'protocol', 'endpoint', 'message', 'fault_code')
            ->from(Constants::SENT_TABLE)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('source_link', $queryBuilder->createNamedParameter($source)),
                $queryBuilder->expr()->eq('target_link', $queryBuilder->createNamedParameter($target)),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : $row;
    }
}
