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
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webicorns\Pongback\Configuration\PongbackSettings;
use Webicorns\Pongback\Event\SendingFailedEvent;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Messaging\MessageQueue;

/**
 * Sends the Pingbacks of one page: every external link of the rendered page is pinged once.
 */
#[AsMessageHandler]
readonly class SendPingbacksHandler
{
    public const MAX_ATTEMPTS = 3;

    /**
     * Delay before attempt 2, 3 … in seconds.
     */
    private const RETRY_DELAYS = [2 => 300, 3 => 3600];

    public function __construct(
        private PageRepository $pageRepository,
        private SiteFinder $siteFinder,
        private PageFetcher $pageFetcher,
        private LinkExtractor $linkExtractor,
        private EndpointDiscovery $endpointDiscovery,
        private PingbackClient $client,
        private WebmentionClient $webmentionClient,
        private SentPingbackLog $log,
        private MessageQueue $queue,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SendPingbacksMessage $message): void
    {
        $site = $this->findSite($message->pageId);
        $settings = PongbackSettings::fromSite($site);
        if ($site === null || ! $settings->sendEnabled || ! $this->isVisible($message->pageId)) {
            return;
        }

        $sourceUrl = $this->pageUrl($site, $message);
        if ($sourceUrl === null) {
            return;
        }

        try {
            $html = $this->pageFetcher->fetch($sourceUrl);
        } catch (\RuntimeException $exception) {
            $this->logger->warning($exception->getMessage(), ['pageId' => $message->pageId]);
            $this->retryLater($message);
            return;
        }

        $links = $this->linkExtractor->extract($html, $sourceUrl, $this->ownHosts());
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
            $this->retryLater($message);
        }

        if ($problems !== []) {
            $this->eventDispatcher->dispatch(new SendingFailedEvent($site, $message->pageId, $sourceUrl, $problems));
        }
    }

    /**
     * Pingbacks are sent once. Webmentions are sent again whenever the page changed, so the receiver
     * can update the mention. Retries only repeat what failed.
     */
    private function needsSending(SendPingbacksMessage $message, ?PingResult $last): bool
    {
        if ($last === null || ! $last->status->isFinal()) {
            return true;
        }

        return $message->attempt === 1 && $last->protocol === IncomingMention::PROTOCOL_WEBMENTION;
    }

    /**
     * Rejected for the first time, or still failing after the last attempt.
     */
    private function isNewProblem(SendPingbacksMessage $message, ?PingResult $last, PingResult $result): bool
    {
        return match ($result->status) {
            PingStatus::Rejected => $last?->status !== PingStatus::Rejected,
            PingStatus::Failed => $message->attempt >= self::MAX_ATTEMPTS,
            default => false,
        };
    }

    private function send(string $source, string $target, bool $webmention, bool $onlyWebmention): PingResult
    {
        try {
            $endpoint = $this->endpointDiscovery->discover($target, $webmention);
        } catch (MentionRejected $rejected) {
            // Network and server problems are temporary, invalid or internal URLs are not.
            $temporary = in_array($rejected->reason, [RejectionReason::UpstreamError, RejectionReason::SourceNotFound], true);

            return new PingResult($temporary ? PingStatus::Failed : PingStatus::Rejected, $rejected->getMessage());
        }

        if ($endpoint === null || ($onlyWebmention && ! $endpoint->isWebmention())) {
            return new PingResult(PingStatus::NoEndpoint);
        }

        $result = $endpoint->isWebmention()
            ? $this->webmentionClient->send($endpoint->url, $source, $target)
            : $this->client->ping($endpoint->url, $source, $target);

        return $result->withEndpoint($endpoint);
    }

    private function retryLater(SendPingbacksMessage $message): void
    {
        if ($message->attempt >= self::MAX_ATTEMPTS) {
            return;
        }

        $next = $message->nextAttempt();
        $this->queue->queue($next, self::RETRY_DELAYS[$next->attempt]);
    }

    private function findSite(int $pageId): ?Site
    {
        try {
            return $this->siteFinder->getSiteByPageId($pageId);
        } catch (SiteNotFoundException) {
            return null;
        }
    }

    /**
     * Only pages that anonymous visitors can see: Pingbacks to unpublished pages would point to nothing.
     */
    private function isVisible(int $pageId): bool
    {
        $page = $this->pageRepository->getPage($pageId);

        return $page !== [] && (int) ($page['doktype'] ?? 0) < PageRepository::DOKTYPE_SPACER;
    }

    private function pageUrl(Site $site, SendPingbacksMessage $message): ?string
    {
        try {
            return (string) $site->getRouter()->generateUri(
                $message->pageId,
                ['_language' => $message->languageId],
                '',
                RouterInterface::ABSOLUTE_URL,
            );
        } catch (\Throwable $exception) {
            // e.g. a disabled language
            $this->logger->info('No URL for page, no Pingbacks sent', [
                'pageId' => $message->pageId,
                'languageId' => $message->languageId,
                'reason' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function ownHosts(): array
    {
        $hosts = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            $hosts[] = $site->getBase()->getHost();
            foreach ($site->getAllLanguages() as $language) {
                $hosts[] = $language->getBase()->getHost();
            }
        }

        return array_values(array_unique(array_filter($hosts)));
    }
}
