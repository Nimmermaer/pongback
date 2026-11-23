<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;
use Webicorns\Pongback\Domain\Model\Pingback;

/**
 * Approved Pingbacks for the frontend. Hidden ones are excluded by the enable fields.
 *
 * @extends Repository<Pingback>
 */
class PingbackRepository extends Repository
{
    protected $defaultOrderings = [
        'crdate' => QueryInterface::ORDER_DESCENDING,
        'uid' => QueryInterface::ORDER_DESCENDING,
    ];

    public function __construct(
        private readonly Typo3QuerySettings $querySettings,
    ) {
        parent::__construct();
    }

    public function initializeObject(): void
    {
        // Pingbacks are stored on the page they link to, in the language of that page, and are not translated.
        $this->setDefaultQuerySettings(
            $this->querySettings
                ->setRespectStoragePage(false)
                ->setRespectSysLanguage(false),
        );
    }

    /**
     * @return QueryResultInterface<int, Pingback>
     */
    public function findOnPage(int $pageId, int $languageId): QueryResultInterface
    {
        return $this->findOnPages([$pageId], $languageId);
    }

    /**
     * @param list<int> $pageIds
     * @return QueryResultInterface<int, Pingback>
     */
    public function findOnPages(array $pageIds, int $languageId, int $limit = 0): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching($query->logicalAnd(
            $query->in('pid', $pageIds === [] ? [0] : $pageIds),
            $query->in('sysLanguageUid', [$languageId, -1]),
        ));
        if ($limit > 0) {
            $query->setLimit($limit);
        }

        return $query->execute();
    }
}
