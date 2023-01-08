<?php

declare(strict_types=1);

namespace PHTH\Pongback\Middlewares;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class PingbackHeader implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;


    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $siteUrl = '';
        try {
            $siteUrl = $request->getAttributes()['normalizedParams']->getSiteUrl();
        } catch (\Exception) {
            $this->logger->notice('site url was not found', $siteUrl);
        }

        if ($siteUrl !== '') {
            return $response->withHeader('X-Pingback', (string) $siteUrl . '?type=1392814100');
        }
        return $response;
    }
}
