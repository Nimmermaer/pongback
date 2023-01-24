<?php

declare(strict_types=1);

namespace PHTH\Pongback\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Site\Entity\Site;

class PingbackHeader implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Site $site */
        $site = $request->getAttribute('site');
        $response = $handler->handle($request);

        try {
            $uri = $site->getRouter()->generateUri($site->getRootPageId(), ['type' => '1392814100']);
            return $response->withHeader('X-Pingback', $uri->__toString());
        } catch (\Exception) {
            $this->logger->notice('site url was not found', [
                'url' => $uri->__toString(),
            ]);
        }

        return $response;
    }
}
