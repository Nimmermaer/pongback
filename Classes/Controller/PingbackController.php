<?php

namespace Webicorns\Pongback\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class PingbackController extends ActionController
{
    public function listAction(): ResponseInterface
    {
        return $this->htmlResponse();
    }
}
