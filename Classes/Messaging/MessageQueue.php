<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Messaging;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Queues the messages of the extension (sending Pingbacks, verifying Webmentions).
 *
 * The transport is set explicitly: TYPO3 13 routes a message to every matching route,
 * so the default route "*" => "default" would also send it synchronously while saving in the backend.
 */
readonly class MessageQueue
{
    public const DEFAULT_TRANSPORT = 'doctrine';

    /**
     * Attempts of a RetryableMessage, and the delay before attempt 2, 3 in seconds.
     */
    public const MAX_ATTEMPTS = 3;
    public const RETRY_DELAYS = [2 => 300, 3 => 3600];

    public function __construct(
        private MessageBusInterface $bus,
    ) {}

    public function queue(object $message, int $delayInSeconds = 0): void
    {
        $stamps = [new TransportNamesStamp($this->transports($message::class))];
        if ($delayInSeconds > 0) {
            $stamps[] = new DelayStamp($delayInSeconds * 1000);
        }

        $this->bus->dispatch($message, $stamps);
    }

    /**
     * Queues the next attempt after a temporary problem.
     *
     * @return bool false after the last attempt
     */
    public function retryLater(RetryableMessage $message): bool
    {
        if (self::isLastAttempt($message)) {
            return false;
        }

        $next = $message->nextAttempt();
        $this->queue($next, self::RETRY_DELAYS[$next->getAttempt()]);

        return true;
    }

    public static function isLastAttempt(RetryableMessage $message): bool
    {
        return $message->getAttempt() >= self::MAX_ATTEMPTS;
    }

    /**
     * The transport(s) configured for the message, "doctrine" unless an integrator changed the routing.
     *
     * @return list<string>
     */
    private function transports(string $messageClass): array
    {
        $configured = $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][$messageClass] ?? null;
        $transports = array_values(array_filter(
            (array) $configured,
            static fn(mixed $transport): bool => is_string($transport) && $transport !== '',
        ));

        return $transports !== [] ? $transports : [self::DEFAULT_TRANSPORT];
    }
}
