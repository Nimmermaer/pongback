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

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webicorns\Pongback\Configuration\PongbackSettings;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Mention\MentionReceiverInterface;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Messaging\MessageQueue;

/**
 * Verifies a received Webmention: creates, updates or removes the stored mention.
 */
#[AsMessageHandler]
readonly class ReceiveWebmentionHandler
{
    public const MAX_ATTEMPTS = 3;

    /**
     * Delay before attempt 2, 3 in seconds.
     */
    private const RETRY_DELAYS = [2 => 300, 3 => 3600];

    public function __construct(
        private SiteFinder $siteFinder,
        private MentionReceiverInterface $receiver,
        private MessageQueue $queue,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ReceiveWebmentionMessage $message): void
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($message->siteIdentifier);
        } catch (SiteNotFoundException) {
            return;
        }

        if (! PongbackSettings::fromSite($site)->receiveWebmention) {
            return;
        }

        $mention = new IncomingMention(
            $message->source,
            $message->target,
            IncomingMention::PROTOCOL_WEBMENTION,
            $message->clientIp,
            $message->userAgent,
        );

        try {
            $this->receiver->synchronize($mention, $site);
        } catch (MentionRejected $rejected) {
            $this->logger->info('Webmention rejected', [
                'reason' => $rejected->reason->value,
                'source' => $message->source,
                'target' => $message->target,
                'attempt' => $message->attempt,
            ]);

            $temporary = in_array($rejected->reason, [RejectionReason::UpstreamError, RejectionReason::SourceNotFound], true);
            if ($temporary && $message->attempt < self::MAX_ATTEMPTS) {
                $next = $message->nextAttempt();
                $this->queue->queue($next, self::RETRY_DELAYS[$next->attempt]);
            }
        }
    }
}
