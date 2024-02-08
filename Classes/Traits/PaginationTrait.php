<?php

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
