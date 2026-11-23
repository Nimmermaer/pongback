<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Webmention;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use Webicorns\Pongback\Configuration\PongbackSettings;
use Webicorns\Pongback\Http\ClientIp;
use Webicorns\Pongback\Http\TextResponse;
use Webicorns\Pongback\Mention\MentionRateLimiter;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Mention\TargetResolver;
use Webicorns\Pongback\Mention\UrlNormalizer;
use Webicorns\Pongback\Messaging\MessageQueue;
use Webicorns\Pongback\Utility\Constants;

/**
 * Webmention receiver: checks the request and queues the verification of the source (202 Accepted).
 *
 * @see https://www.w3.org/TR/webmention/#receiving-webmentions
 */
readonly class WebmentionEndpoint
{
    public function __construct(
        private TargetResolver $targetResolver,
        private MentionRateLimiter $rateLimiter,
        private MessageQueue $queue,
        private LoggerInterface $logger,
    ) {}

    public function handle(ServerRequestInterface $request, Site $site): ResponseInterface
    {
        $body = $request->getParsedBody();
        $source = is_array($body) && is_string($body['source'] ?? null) ? trim($body['source']) : '';
        $target = is_array($body) && is_string($body['target'] ?? null) ? trim($body['target']) : '';
        $clientIp = ClientIp::of($request);

        try {
            $this->assertValidUrl($source, 'source');
            $this->assertValidUrl($target, 'target');
            if (UrlNormalizer::normalize($source) === UrlNormalizer::normalize($target)) {
                throw new MentionRejected(RejectionReason::InvalidRequest, 'Source and target must differ.');
            }

            $this->rateLimiter->consume($clientIp, PongbackSettings::fromSite($site)->receiveRateLimit);
            // Only the cheap, local check here, the source is fetched by the worker.
            $this->targetResolver->resolve($target, $site);
        } catch (MentionRejected $rejected) {
            $this->logger->info('Webmention rejected', [
                'reason' => $rejected->reason->value,
                'source' => $source,
                'target' => $target,
            ]);

            return $this->text(self::statusCode($rejected->reason), $rejected->getMessage());
        }

        $this->queue->queue(new ReceiveWebmentionMessage(
            $source,
            $target,
            $site->getIdentifier(),
            $clientIp,
            substr($request->getHeaderLine('User-Agent'), 0, 255),
        ));

        return $this->text(202, 'Accepted, the Webmention will be verified.');
    }

    private function assertValidUrl(string $url, string $name): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($url === ''
            || strlen($url) > Constants::MAX_URL_LENGTH
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! in_array($scheme, ['http', 'https'], true)
        ) {
            throw new MentionRejected(RejectionReason::InvalidRequest, sprintf('Parameter "%s" must be an http(s) URL.', $name));
        }
    }

    private static function statusCode(RejectionReason $reason): int
    {
        return $reason === RejectionReason::AccessDenied ? 429 : 400;
    }

    private function text(int $status, string $text): ResponseInterface
    {
        return new TextResponse($text, $status);
    }

}
