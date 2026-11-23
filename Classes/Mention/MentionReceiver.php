<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Mention;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use Webicorns\Pongback\Configuration\PongbackSettings;
use Webicorns\Pongback\Domain\Model\Pingback;
use Webicorns\Pongback\Event\AfterPingbackCreatedEvent;
use Webicorns\Pongback\Event\BeforePingbackCreateEvent;
use Webicorns\Pongback\Mention\Source\SourceDocument;
use Webicorns\Pongback\Mention\Source\SourceFetcher;
use Webicorns\Pongback\Mention\Source\SourceInspector;

/**
 * Protocol neutral processing of a received mention:
 * resolve the target, reject duplicates, verify the source links to the target, store it.
 */
readonly class MentionReceiver implements MentionReceiverInterface
{
    public function __construct(
        private TargetResolver $targetResolver,
        private SourceFetcher $sourceFetcher,
        private SourceInspector $sourceInspector,
        private MentionStorage $storage,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {}

    public function receive(IncomingMention $mention, Site $site): int
    {
        // Cheap checks first: the target is local, the source requires an outgoing request.
        $target = $this->resolveTarget($mention, $site);

        if ($this->storage->exists($mention->source, $mention->target)) {
            throw new MentionRejected(RejectionReason::AlreadyRegistered, 'The pingback has already been registered.');
        }

        return $this->create($mention, $target, $this->verify($mention));
    }

    public function synchronize(IncomingMention $mention, Site $site): SyncResult
    {
        $target = $this->resolveTarget($mention, $site);
        $existing = $this->storage->find($mention->source, $mention->target);

        try {
            $document = $this->verify($mention);
        } catch (MentionRejected $rejected) {
            $gone = in_array($rejected->reason, [RejectionReason::SourceGone, RejectionReason::NoLinkToTarget], true);
            if ($existing === null || ! $gone) {
                throw $rejected;
            }

            $this->storage->remove($existing);
            $this->log('Mention removed', $mention, $existing);
            return SyncResult::Removed;
        }

        if ($existing !== null) {
            $this->storage->update($existing, $document->title, $document->excerpt);
            $this->log('Mention updated', $mention, $existing);
            return SyncResult::Updated;
        }

        $this->create($mention, $target, $document);
        return SyncResult::Created;
    }

    private function resolveTarget(IncomingMention $mention, Site $site): ResolvedTarget
    {
        if (UrlNormalizer::normalize($mention->source) === UrlNormalizer::normalize($mention->target)) {
            throw new MentionRejected(RejectionReason::InvalidRequest, 'Source and target URI must differ.');
        }

        return $this->targetResolver->resolve($mention->target, $site);
    }

    private function verify(IncomingMention $mention): SourceDocument
    {
        $source = $this->sourceFetcher->fetch($mention->source);
        $document = $this->sourceInspector->inspect($source->html, $source->url, $mention->target);
        if ($document === null) {
            throw new MentionRejected(RejectionReason::NoLinkToTarget, 'The source URI does not contain a link to the target URI.');
        }

        return $document;
    }

    private function create(IncomingMention $mention, ResolvedTarget $target, SourceDocument $document): int
    {
        $pingback = new Pingback();
        $pingback->setProtocol($mention->protocol);
        $pingback->setPid($target->pageId);
        $pingback->setSourceLink($mention->source);
        $pingback->setTargetLink($mention->target);
        $pingback->setSourceTitle($document->title);
        $pingback->setExcerpt($document->excerpt);
        $pingback->setHidden(! PongbackSettings::fromSite($target->site)->receiveAutoPublish);

        $event = $this->eventDispatcher->dispatch(new BeforePingbackCreateEvent($pingback, $mention, $target));
        if ($event->isRejected()) {
            throw new MentionRejected(
                $event->getRejectionReason() ?? RejectionReason::AccessDenied,
                $event->getRejectionMessage(),
            );
        }

        $uid = $this->storage->add($pingback, $target->languageId);
        $this->log('Mention registered', $mention, $uid);
        $this->eventDispatcher->dispatch(new AfterPingbackCreatedEvent($uid, $pingback, $mention, $target));

        return $uid;
    }

    private function log(string $message, IncomingMention $mention, int $uid): void
    {
        $this->logger->info($message, [
            'protocol' => $mention->protocol->value,
            'source' => $mention->source,
            'target' => $mention->target,
            'uid' => $uid,
        ]);
    }
}
