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

namespace Webicorns\Pongback\Reaction;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reactions\Model\ReactionInstruction;
use TYPO3\CMS\Reactions\Reaction\ReactionInterface;

class PingbackType implements ReactionInterface
{
    /**
     * @var string
     */
    private const REGISTRY_KEY = 'pingback';

    public function __construct(
        private readonly Registry $registry,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public static function getType(): string
    {
        return 'pingback-reaction-type';
    }

    public static function getDescription(): string
    {
        return 'Pingback reaction type';
    }

    public static function getIconIdentifier(): string
    {
        return 'tx-pongback-pong';
    }

    public function react(
        ServerRequestInterface $request,
        array $payload,
        ReactionInstruction $reaction
    ): ResponseInterface {
        $response = [];
        $xmlObject = new \SimpleXMLElement($request->getBody()->getContents());
        if ((string) $xmlObject->children()->methodName !== 'pingback.ping') {
            $data = [
                'success' => false,
                'error' => 'id not given',
            ];

            return $this->xmlResponse($data, 400);
        }

        foreach ($xmlObject->children()->params->param as $param) {
            $response[] = $param->value->string;
        }

        $this->updateRegistryEntry(trim(implode('-', $response)));

        return $this->xmlResponse([
            'string' => 'success',
        ]);
    }

    private function updateRegistryEntry(string $id): void
    {
        $ids = $this->registry->get('tx_pongback', self::REGISTRY_KEY) ?? [];
        $ids[] = $id;
        $ids = array_unique($ids);
        $this->registry->set('tx_pongback', self::REGISTRY_KEY, $ids);
    }

    private function xmlResponse(array $data, int $statusCode = 201): MessageInterface|ResponseInterface
    {
        $transformedXml = GeneralUtility::array2xml([
            'params' => [
                'param' => [
                    'value' => $data,
                ],
            ],
        ], docTag: 'methodResponse');
        $xml = new \SimpleXMLElement($transformedXml);

        return $this->responseFactory
            ->createResponse()
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'text/xml; charset=utf-8')
            ->withBody($this->streamFactory->createStream($xml->asXML()));
    }
}
