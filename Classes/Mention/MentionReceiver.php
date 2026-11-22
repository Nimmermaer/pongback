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
use Webicorns\Pongback\Event\BeforePingbackCreateEvent;
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
        if (SourceInspector::normalizeUrl($mention->source) === SourceInspector::normalizeUrl($mention->target)) {
            throw new MentionRejected(RejectionReason::InvalidRequest, 'Source and target URI must differ.');
        }

        // Cheap checks first: the target is local, the source requires an outgoing request.
        $target = $this->targetResolver->resolve($mention->target, $site);

        if ($this->storage->exists($mention->source, $mention->target)) {
            throw new MentionRejected(RejectionReason::AlreadyRegistered, 'The pingback has already been registered.');
        }

        $source = $this->sourceFetcher->fetch($mention->source);
        $document = $this->sourceInspector->inspect($source['html'], $source['url'], $mention->target);
        if ($document === null) {
            throw new MentionRejected(RejectionReason::NoLinkToTarget, 'The source URI does not contain a link to the target URI.');
        }

        $pingback = new Pingback();
        $pingback->setPid($target->pageId);
        $pingback->setSourceLink($mention->source);
        $pingback->setTargetLink($mention->target);
        $pingback->setPingClient($document->title);
        $pingback->setPingResource((string) $target->pageId);
        $pingback->setSerializedInformation($document->excerpt);
        $pingback->setHidden(! PongbackSettings::fromSite($target->site)->receiveAutoPublish);

        $event = $this->eventDispatcher->dispatch(new BeforePingbackCreateEvent($pingback, $mention, $target));
        if ($event->isRejected()) {
            throw new MentionRejected(
                $event->getRejectionReason() ?? RejectionReason::AccessDenied,
                $event->getRejectionMessage(),
            );
        }

        $uid = $this->storage->add($event->getPingback(), $target->languageId);
        $this->logger->info('Mention registered', [
            'protocol' => $mention->protocol,
            'source' => $mention->source,
            'target' => $mention->target,
            'uid' => $uid,
        ]);

        return $uid;
    }
}
