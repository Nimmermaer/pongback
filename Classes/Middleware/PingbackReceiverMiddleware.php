<?php

declare(strict_types=1);

namespace Webicorns\Pongback\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reactions\Authentication\ReactionUserAuthentication;
use TYPO3\CMS\Reactions\Exception\ReactionNotFoundException;
use TYPO3\CMS\Reactions\Model\ReactionInstruction;
use TYPO3\CMS\Reactions\Repository\ReactionRepository;
use Webicorns\Pongback\Http\ReactionHandler;
use Webicorns\Pongback\Service\XmlResponseService;
use Webicorns\Pongback\Utility\Constants;

class PingbackReceiverMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ReactionHandler $reactionHandler,
        private readonly ReactionRepository $reactionRepository,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. We only listen to the "pingback" endpoint
        $routeResult = $request->getAttribute('routing');

        if (! ($routeResult instanceof PageArguments) || $routeResult->getPageType() !== (string) Constants::PINGBACK_PAGE_TYPE) {
            return $handler->handle($request);
        }

        $reactionIdentifier = $request->getAttribute('site')
            ->getConfiguration()['pingback_identifier'] ?? '';
        $reaction = $this->reactionRepository->getReactionRecordByIdentifier($reactionIdentifier);
        if (! $reaction instanceof ReactionInstruction) {
            return XmlResponseService::xmlErrorResponse(401, 'Invalid reaction identifier given');
        }

        // 3. Handle reaction user authentication
        /** @var ReactionUserAuthentication $user */
        $user = GeneralUtility::makeInstance(ReactionUserAuthentication::class);
        $user->setReactionInstruction($reaction);
        $user->start($request);

        try {
            return $this->reactionHandler->handleReaction($request, $reaction, $user);
        } catch (ReactionNotFoundException $reactionNotFoundException) {
            return XmlResponseService::xmlErrorResponse(404, $reactionNotFoundException->getMessage());
        }
    }
}
