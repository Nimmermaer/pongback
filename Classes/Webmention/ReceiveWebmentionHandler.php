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
use Webicorns\Pongback\Mention\Protocol;
use Webicorns\Pongback\Messaging\MessageQueue;

/**
 * Verifies a received Webmention: creates, updates or removes the stored mention.
 */
#[AsMessageHandler]
readonly class ReceiveWebmentionHandler
{
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
            Protocol::Webmention,
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

            if ($rejected->reason->isTemporary()) {
                $this->queue->retryLater($message);
            }
        }
    }
}
