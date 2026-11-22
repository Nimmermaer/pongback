<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Event;

use Psr\EventDispatcher\StoppableEventInterface;
use Webicorns\Pongback\Domain\Model\Pingback;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Mention\ResolvedTarget;

/**
 * Dispatched after a mention has been verified and before it is stored.
 *
 * Listeners may modify the record (e.g. set it hidden) or reject the mention,
 * which stops the propagation and sends the given reason to the remote party.
 */
final class BeforePingbackCreateEvent implements StoppableEventInterface
{
    private ?RejectionReason $rejectionReason = null;

    private string $rejectionMessage = '';

    public function __construct(
        private Pingback $pingback,
        private readonly IncomingMention $mention,
        private readonly ResolvedTarget $target,
    ) {}

    public function getPingback(): Pingback
    {
        return $this->pingback;
    }

    public function setPingback(Pingback $pingback): void
    {
        $this->pingback = $pingback;
    }

    public function getMention(): IncomingMention
    {
        return $this->mention;
    }

    public function getTarget(): ResolvedTarget
    {
        return $this->target;
    }

    /**
     * @param string $message sent to the remote party, do not expose internal details
     */
    public function reject(RejectionReason $reason, string $message): void
    {
        $this->rejectionReason = $reason;
        $this->rejectionMessage = $message;
    }

    public function isRejected(): bool
    {
        return $this->rejectionReason !== null;
    }

    public function getRejectionReason(): ?RejectionReason
    {
        return $this->rejectionReason;
    }

    public function getRejectionMessage(): string
    {
        return $this->rejectionMessage;
    }

    public function isPropagationStopped(): bool
    {
        return $this->isRejected();
    }
}
