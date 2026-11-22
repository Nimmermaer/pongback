<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Pingback;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Site\Entity\Site;
use Webicorns\Pongback\Configuration\PongbackSettings;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Mention\MentionRateLimiter;
use Webicorns\Pongback\Mention\MentionReceiverInterface;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Service\XmlResponseService;
use Webicorns\Pongback\XmlRpc\XmlRpcCodec;

/**
 * XML-RPC server for "pingback.ping".
 *
 * @see https://www.hixie.ch/specs/pingback/pingback#TOC3
 */
readonly class PingbackEndpoint
{
    public const METHOD = 'pingback.ping';

    /**
     * XML-RPC fault codes of the Pingback specification.
     */
    public const FAULT_GENERIC = 0;
    public const FAULT_SOURCE_NOT_FOUND = 16;
    public const FAULT_NO_LINK_TO_TARGET = 17;
    public const FAULT_TARGET_NOT_FOUND = 32;
    public const FAULT_TARGET_NOT_ALLOWED = 33;
    public const FAULT_ALREADY_REGISTERED = 48;
    public const FAULT_ACCESS_DENIED = 49;
    public const FAULT_UPSTREAM_ERROR = 50;

    /**
     * Fault codes of the XML-RPC interoperability specification.
     */
    public const FAULT_PARSE_ERROR = -32700;
    public const FAULT_METHOD_NOT_FOUND = -32601;
    public const FAULT_INVALID_PARAMS = -32602;

    private const MAX_BODY_BYTES = 65536;

    public function __construct(
        private XmlRpcCodec $codec,
        private MentionReceiverInterface $receiver,
        private MentionRateLimiter $rateLimiter,
        private LoggerInterface $logger,
    ) {}

    public function handle(ServerRequestInterface $request, Site $site): ResponseInterface
    {
        $body = (string) $request->getBody();
        if (strlen($body) > self::MAX_BODY_BYTES) {
            return XmlResponseService::xmlErrorResponse(self::FAULT_PARSE_ERROR, 'Request too large.');
        }

        try {
            $call = $this->codec->decodeMethodCall($body);
        } catch (\InvalidArgumentException) {
            return XmlResponseService::xmlErrorResponse(self::FAULT_PARSE_ERROR, 'Parse error, not well formed.');
        }

        if ($call['methodName'] !== self::METHOD) {
            return XmlResponseService::xmlErrorResponse(self::FAULT_METHOD_NOT_FOUND, 'Requested method not found.');
        }

        [$source, $target] = $call['params'] + [null, null];
        if (count($call['params']) !== 2 || ! is_string($source) || ! is_string($target)) {
            return XmlResponseService::xmlErrorResponse(
                self::FAULT_INVALID_PARAMS,
                'Invalid method parameters, expected pingback.ping(sourceURI, targetURI).',
            );
        }

        $mention = new IncomingMention(
            trim($source),
            trim($target),
            IncomingMention::PROTOCOL_PINGBACK,
            $this->getClientIp($request),
            substr($request->getHeaderLine('User-Agent'), 0, 255),
        );

        try {
            $this->rateLimiter->consume($mention->clientIp, PongbackSettings::fromSite($site)->receiveRateLimit);
            $this->receiver->receive($mention, $site);
        } catch (MentionRejected $rejected) {
            $this->logger->info('Pingback rejected', [
                'reason' => $rejected->reason->value,
                'source' => $mention->source,
                'target' => $mention->target,
            ]);

            return XmlResponseService::xmlErrorResponse(self::faultCode($rejected->reason), $rejected->getMessage());
        }

        return XmlResponseService::xmlSuccessResponse(
            sprintf('Pingback from %s to %s registered.', $mention->source, $mention->target),
        );
    }

    private static function faultCode(RejectionReason $reason): int
    {
        return match ($reason) {
            RejectionReason::InvalidRequest => self::FAULT_GENERIC,
            RejectionReason::SourceNotFound => self::FAULT_SOURCE_NOT_FOUND,
            RejectionReason::NoLinkToTarget => self::FAULT_NO_LINK_TO_TARGET,
            RejectionReason::TargetNotFound => self::FAULT_TARGET_NOT_FOUND,
            RejectionReason::TargetNotAllowed => self::FAULT_TARGET_NOT_ALLOWED,
            RejectionReason::AlreadyRegistered => self::FAULT_ALREADY_REGISTERED,
            RejectionReason::AccessDenied => self::FAULT_ACCESS_DENIED,
            RejectionReason::UpstreamError => self::FAULT_UPSTREAM_ERROR,
        };
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        // NormalizedParams respects [SYS][reverseProxyIP], unlike REMOTE_ADDR.
        $normalizedParams = $request->getAttribute('normalizedParams');
        if ($normalizedParams instanceof NormalizedParams) {
            return $normalizedParams->getRemoteAddress();
        }

        return (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');
    }
}
