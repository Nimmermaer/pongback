<?php

namespace PHTH\Pongback\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PingbackHeader implements \Psr\Http\Server\MiddlewareInterface
{

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $siteUrl = $request->getAttribute('siteUrl');
        if ($siteUrl) {
            /** @var \TYPO3\CMS\Core\Http\Response $response */
            $response->withHeader('X-Pingback', $siteUrl . '?type=1392814100');
        }

        return $response;
    }
}