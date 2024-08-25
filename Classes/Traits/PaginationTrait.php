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

namespace Webicorns\Pongback\Traits;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

trait PaginationTrait
{
    protected function buildSlideWindowPagination(
        QueryResultInterface $allItems,
        int $itemsPerPage = 10,
        int $maximumLinks = 15,
    ): void {
        $currentPage = $this->request->hasArgument('currentPage')
            ? (int) $this->request->getArgument('currentPage')
            : 1;

        $paginator = new QueryResultPaginator($allItems, $currentPage, $itemsPerPage);
        $pagination = new SlidingWindowPagination($paginator, $maximumLinks);
        $this->view->assign('pagination', [
            'pagination' => $pagination,
            'paginator' => $paginator,
        ]);
    }

    protected function buildSlideWindowPaginationForBackend(
        QueryResultInterface $allItems,
        int $itemsPerPage = 10,
        int $maximumLinks = 15,
        ServerRequestInterface $request = null
    ): void {
        $currentPage = ($request->getQueryParams()['currentPageNumber'] ?? false)
            ? (int) $request->getQueryParams()['currentPageNumber']
            : 1;

        $paginator = new QueryResultPaginator($allItems, $currentPage, $itemsPerPage);
        $pagination = new SlidingWindowPagination($paginator, $maximumLinks);
        $this->moduleTemplate->assign('pagination', [
            'pagination' => $pagination,
            'paginator' => $paginator,
        ]);
    }
}
