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

use TYPO3\CMS\Core\Pagination\SlidingWindowPagination;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

trait PaginationTrait
{
    private function buildSlideWindowPagination(
        QueryResultInterface $allItems,
        $itemsPerPage = 10,
        $maximumLinks = 15
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
}
