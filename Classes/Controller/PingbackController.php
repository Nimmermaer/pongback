<?php

namespace Webicorns\Pongback\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Webicorns\Pongback\Domain\Repository\PingbackRepository;
use Webicorns\Pongback\Traits\PaginationTrait;

class PingbackController extends ActionController
{
    use PaginationTrait;

    public function __construct(
        private readonly PingbackRepository $pingbackRepository
    ) {
    }

    public function listAction(): ResponseInterface
    {
        $this->buildSlideWindowPagination($this->pingbackRepository->findAll());
        return $this->htmlResponse();
    }
}
