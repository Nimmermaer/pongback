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


namespace Webicorns\Pongback\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Reactions\Authentication\ReactionUserAuthentication;
use TYPO3\CMS\Reactions\Exception\ReactionNotFoundException;
use TYPO3\CMS\Reactions\Model\ReactionInstruction;
use TYPO3\CMS\Reactions\Reaction\ReactionInterface;
use TYPO3\CMS\Reactions\ReactionRegistry;

readonly class ReactionHandler
{
    public function __construct(
        private ReactionRegistry $reactionRegistry,
        private LoggerInterface $logger,
        private LanguageServiceFactory $languageServiceFactory
    ) {
    }

    public function handleReaction(
        ServerRequestInterface $request,
        ?ReactionInstruction $reactionInstruction,
        ReactionUserAuthentication $user
    ): ResponseInterface {
        if (! $reactionInstruction instanceof ReactionInstruction) {
            $this->logger->warning('No reaction given', [
                'request' => $request,
            ]);
            throw new ReactionNotFoundException('No reaction given', 1_669_757_255);
        }

        $reaction = $this->reactionRegistry->getReactionByType($reactionInstruction->getType());
        if (! $reaction instanceof ReactionInterface) {
            throw new ReactionNotFoundException('No reaction found for given reaction type', 1_662_458_842);
        }

        // Prepare the user and language object before calling the reaction execution process
        $GLOBALS['LANG'] = $this->languageServiceFactory->createFromUserPreferences($user);
        $GLOBALS['BE_USER'] = $user;
        $payload = $this->getPayload($request);

        $response = $reaction->react($request, $payload, $reactionInstruction);
        $this->logger->info('Reaction was handled successfully', [
            'request' => $request,
        ]);
        return $this->buildReactionResponse($response);
    }

    protected function getPayload(ServerRequestInterface $request): array
    {
        $headersList = [];
        /** @var FrontendTypoScript $frontendTyposcript */
        $frontendTyposcript = $request->getAttribute('frontend.typoscript');

        $headersList['application-json.'] = [
            'type' => 'json',
            'header' => 'application/json',
        ];
        if ($frontendTyposcript->getSetupArray()['plugin.']['tx_pongback.']['settings.']['applicationList.'] ?? false) {
            $headersList = $frontendTyposcript->getSetupArray()['plugin.']['tx_pongback.']['settings.']['applicationList.'];
        }

        $type = null;
        foreach ($headersList as $value) {
            $header = $request->getHeader('content-type');
            if (isset($header[0]) && $header[0] === $value['header']) {
                $type = $value['type'];
                break;
            }
        }

        $body = (string) $request->getBody();
        try {
            $payload = match ($type) {
                'xml' => json_decode(
                    json_encode(new \SimpleXMLElement($body), JSON_THROW_ON_ERROR),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ),
                'json' => json_decode($body, true, 512, JSON_THROW_ON_ERROR),
                default => throw new \Exception(sprintf('Unsupported type: %s', $type))
            };

            return is_array($payload) ? $payload : [];
        } catch (\JsonException $e) {
            throw new \JsonException('JSON parsing error: ' . $e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            throw new \Exception('Error processing request: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function buildReactionResponse(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader(
                'X-Pongback-Reaction-Success',
                $response->getStatusCode() >= 200 && $response->getStatusCode() < 300 ? 'true' : 'false'
            );
    }
}
