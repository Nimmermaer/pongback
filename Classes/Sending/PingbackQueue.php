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

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Queues sending the Pingbacks of a page.
 *
 * The transport is set explicitly: TYPO3 13 routes a message to every matching route,
 * so the default route "*" => "default" would also send it synchronously while saving in the backend.
 */
readonly class PingbackQueue
{
    public const DEFAULT_TRANSPORT = 'doctrine';

    public function __construct(
        private MessageBusInterface $bus,
    ) {}

    public function queue(SendPingbacksMessage $message, int $delayInSeconds = 0): void
    {
        $stamps = [new TransportNamesStamp($this->transports())];
        if ($delayInSeconds > 0) {
            $stamps[] = new DelayStamp($delayInSeconds * 1000);
        }

        $this->bus->dispatch($message, $stamps);
    }

    /**
     * The transport(s) configured for the message, "doctrine" unless an integrator changed the routing.
     *
     * @return list<string>
     */
    private function transports(): array
    {
        $configured = $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][SendPingbacksMessage::class] ?? null;
        $transports = array_values(array_filter(
            (array) $configured,
            static fn(mixed $transport): bool => is_string($transport) && $transport !== '',
        ));

        return $transports !== [] ? $transports : [self::DEFAULT_TRANSPORT];
    }
}
