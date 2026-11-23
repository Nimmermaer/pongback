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

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use Webicorns\Pongback\Sending\PingStatus;
use Webicorns\Pongback\Utility\Constants;

/**
 * Pingbacks for the backend: approved and pending ones, limited to the pages the user may see.
 */
readonly class ModerationRepository
{
    /**
     * Levels of subpages taken into account below the selected page.
     */
    public const MAX_DEPTH = 20;

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * Pingbacks on the page and its subpages, pending ones first, then newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function findOnPageTree(int $pageId, BackendUserAuthentication $user): array
    {
        $pageIds = $this->pageTree($pageId);
        $queryBuilder = $this->createQueryBuilder($user);
        $queryBuilder
            ->andWhere($queryBuilder->expr()->in(
                'pingback.pid',
                $queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY),
            ))
            ->orderBy('pingback.hidden', 'DESC')
            ->addOrderBy('pingback.crdate', 'DESC')
            ->addOrderBy('pingback.uid', 'DESC');

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * Newest Pingbacks of all pages the user may see.
     *
     * @return list<array<string, mixed>>
     */
    public function findLatest(int $limit, BackendUserAuthentication $user): array
    {
        return $this->createQueryBuilder($user)
            ->orderBy('pingback.crdate', 'DESC')
            ->addOrderBy('pingback.uid', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function countPending(BackendUserAuthentication $user): int
    {
        $queryBuilder = $this->createQueryBuilder($user);

        return (int) $queryBuilder
            ->count('pingback.uid')
            ->andWhere($queryBuilder->expr()->eq('pingback.hidden', 1))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Sent Pingbacks and Webmentions of the page tree that did not reach the linked site:
     * rejected, or failed after the last attempt. Newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function findSendingProblemsOnPageTree(int $pageId, BackendUserAuthentication $user): array
    {
        $queryBuilder = $this->createSentQueryBuilder($user);
        $queryBuilder
            ->andWhere($queryBuilder->expr()->in(
                'sent.pid',
                $queryBuilder->createNamedParameter($this->pageTree($pageId), Connection::PARAM_INT_ARRAY),
            ))
            ->orderBy('sent.tstamp', 'DESC')
            ->addOrderBy('sent.uid', 'DESC');

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    public function countSendingProblems(BackendUserAuthentication $user): int
    {
        return (int) $this->createSentQueryBuilder($user)
            ->count('sent.uid')
            ->executeQuery()
            ->fetchOne();
    }

    private function createSentQueryBuilder(BackendUserAuthentication $user): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Constants::SENT_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $queryBuilder
            ->select(
                'sent.uid',
                'sent.pid',
                'sent.tstamp',
                'sent.source_link',
                'sent.target_link',
                'sent.protocol',
                'sent.status',
                'sent.message',
                'sent.attempts',
            )
            ->addSelect('pages.title AS page_title')
            ->from(Constants::SENT_TABLE, 'sent')
            ->where($queryBuilder->expr()->in(
                'sent.status',
                $queryBuilder->createNamedParameter([PingStatus::Rejected->value, PingStatus::Failed->value], Connection::PARAM_STR_ARRAY),
            ));
        $this->restrictToVisiblePages($queryBuilder, 'sent', $user);

        return $queryBuilder;
    }

    private function createQueryBuilder(BackendUserAuthentication $user): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Constants::PINGBACK_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $queryBuilder
            ->select(
                'pingback.uid',
                'pingback.pid',
                'pingback.hidden',
                'pingback.protocol',
                'pingback.crdate',
                'pingback.source_link',
                'pingback.source_title',
                'pingback.target_link',
                'pingback.excerpt',
            )
            ->addSelect('pages.title AS page_title')
            ->from(Constants::PINGBACK_TABLE, 'pingback');
        $this->restrictToVisiblePages($queryBuilder, 'pingback', $user);

        return $queryBuilder;
    }

    /**
     * Only records on pages the user may see.
     */
    private function restrictToVisiblePages(QueryBuilder $queryBuilder, string $alias, BackendUserAuthentication $user): void
    {
        $queryBuilder
            ->join($alias, 'pages', 'pages', $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier($alias . '.pid')))
            ->andWhere($queryBuilder->expr()->eq('pages.deleted', 0));

        // The permission clause refers to the table "pages", hence the alias.
        $permissions = QueryHelper::stripLogicalOperatorPrefix($user->getPagePermsClause(Permission::PAGE_SHOW));
        if ($permissions !== '') {
            $queryBuilder->andWhere($permissions);
        }
    }

    /**
     * @return list<int> the page and its subpages
     */
    private function pageTree(int $pageId): array
    {
        $pageIds = [$pageId];
        $parents = [$pageId];
        for ($depth = 0; $depth < self::MAX_DEPTH && $parents !== []; $depth++) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
            $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
            $parents = array_map(intval(...), $queryBuilder
                ->select('uid')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($parents, Connection::PARAM_INT_ARRAY)),
                    $queryBuilder->expr()->eq('sys_language_uid', 0),
                )
                ->executeQuery()
                ->fetchFirstColumn());
            $pageIds = [...$pageIds, ...$parents];
        }

        return $pageIds;
    }
}
