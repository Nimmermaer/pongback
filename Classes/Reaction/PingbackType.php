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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reactions\Model\ReactionInstruction;
use TYPO3\CMS\Reactions\Reaction\ReactionInterface;
use Webicorns\Pongback\Controller\PingbackController;
use Webicorns\Pongback\Service\XmlResponseService;

class PingbackType implements ReactionInterface
{
    /**
     * @var string
     */
    private const REGISTRY_KEY = 'pingback';

    public function __construct(
        private readonly Registry $registry,
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
        $this->updateRegistryEntry(trim(implode('-', $response)));
        /** @var PingbackController $controller */
        $controller = GeneralUtility::makeInstance(PingbackController::class);

        $controller->pingback($payload['methodName'], $payload['params']);

        $transformedXml = GeneralUtility::array2xml([
            'params' => [
                'param' => [
                    'value' => [
                        'string' => 'success',
                    ],
                ],
            ],
        ], docTag: 'methodResponse');
        $xml = new \SimpleXMLElement($transformedXml);
        return XmlResponseService::xmlResponse($xml->asXML());
    }

    private function updateRegistryEntry(string $id): void
    {
        $ids = $this->registry->get('tx_pongback', self::REGISTRY_KEY) ?? [];
        $ids[] = $id;
        $ids = array_unique($ids);
        $this->registry->set('tx_pongback', self::REGISTRY_KEY, $ids);
    }
}
