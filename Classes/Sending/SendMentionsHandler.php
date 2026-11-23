<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Sending;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webicorns\Pongback\Event\SendingFailedEvent;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\Protocol;
use Webicorns\Pongback\Messaging\MessageQueue;

/**
 * Sends the Pingbacks of one page: every external link of the rendered page is pinged once.
 */
#[AsMessageHandler]
readonly class SendMentionsHandler
{
    public function __construct(
        private SourcePageResolver $sourcePages,
        private PageFetcher $pageFetcher,
        private LinkExtractor $linkExtractor,
        private EndpointDiscovery $endpointDiscovery,
        private PingbackClient $client,
        private WebmentionClient $webmentionClient,
        private SentMentionLog $log,
        private MessageQueue $queue,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SendPingbacksMessage $message): void
    {
        $page = $this->sourcePages->resolve($message->pageId, $message->languageId);
        if ($page === null) {
            return;
        }

        $sourceUrl = $page->url;
        $settings = $page->settings;

        try {
            $html = $this->pageFetcher->fetch($sourceUrl);
        } catch (\RuntimeException $exception) {
            $this->logger->warning($exception->getMessage(), ['pageId' => $message->pageId]);
            $this->queue->retryLater($message);
            return;
        }

        $links = $this->linkExtractor->extract($html, $sourceUrl, $page->ownHosts);
        // Webmention receivers remove a mention when the source no longer links to them, so they are told once more.
        $removed = $settings->sendWebmention
            ? array_values(array_diff($this->log->webmentionTargets($message->pageId, $sourceUrl), $links))
            : [];

        $retry = false;
        $problems = [];
        foreach ([...$links, ...$removed] as $target) {
            $last = $this->log->lastResult($message->pageId, $sourceUrl, $target);
            if (! $this->needsSending($message, $last)) {
                continue;
            }

            $isRemoved = in_array($target, $removed, true);
            $result = $this->send($sourceUrl, $target, $settings->sendWebmention, onlyWebmention: $isRemoved);
            if ($isRemoved && $result->status->isFinal()) {
                $this->log->forget($message->pageId, $sourceUrl, $target);
                continue;
            }

            $this->log->record($message->pageId, $sourceUrl, $target, $result);
            $retry = $retry || ! $result->status->isFinal();
            if ($this->isNewProblem($message, $last, $result)) {
                $problems[$target] = $result;
            }
        }

        if ($retry) {
            $this->queue->retryLater($message);
        }

        if ($problems !== []) {
            $this->eventDispatcher->dispatch(new SendingFailedEvent($page->site, $message->pageId, $sourceUrl, $problems));
        }
    }

    /**
     * Pingbacks are sent once. Webmentions are sent again whenever the page changed, so the receiver
     * can update the mention. Retries only repeat what failed.
     */
    private function needsSending(SendPingbacksMessage $message, ?SendResult $last): bool
    {
        if ($last === null || ! $last->status->isFinal()) {
            return true;
        }

        return $message->attempt === 1 && $last->protocol === Protocol::Webmention;
    }

    /**
     * Rejected for the first time, or still failing after the last attempt.
     */
    private function isNewProblem(SendPingbacksMessage $message, ?SendResult $last, SendResult $result): bool
    {
        return match ($result->status) {
            SendStatus::Rejected => $last?->status !== SendStatus::Rejected,
            SendStatus::Failed => MessageQueue::isLastAttempt($message),
            default => false,
        };
    }

    private function send(string $source, string $target, bool $webmention, bool $onlyWebmention): SendResult
    {
        try {
            $endpoint = $this->endpointDiscovery->discover($target, $webmention);
        } catch (MentionRejected $rejected) {
            return new SendResult($rejected->reason->isTemporary() ? SendStatus::Failed : SendStatus::Rejected, $rejected->getMessage());
        }

        if ($endpoint === null || ($onlyWebmention && ! $endpoint->isWebmention())) {
            return new SendResult(SendStatus::NoEndpoint);
        }

        $result = $endpoint->isWebmention()
            ? $this->webmentionClient->send($endpoint->url, $source, $target)
            : $this->client->ping($endpoint->url, $source, $target);

        return $result->withEndpoint($endpoint);
    }




}
