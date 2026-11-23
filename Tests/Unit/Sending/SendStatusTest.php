<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Sending;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webicorns\Pongback\Mention\Protocol;
use Webicorns\Pongback\Sending\DiscoveredEndpoint;
use Webicorns\Pongback\Sending\SendPingbacksMessage;
use Webicorns\Pongback\Sending\SendResult;
use Webicorns\Pongback\Sending\SendStatus;

final class SendStatusTest extends TestCase
{
    #[Test]
    public function onlyFailedPingbacksAreSentAgain(): void
    {
        foreach (SendStatus::cases() as $status) {
            self::assertSame($status !== SendStatus::Failed, $status->isFinal(), $status->name);
        }
    }

    #[Test]
    public function statusValuesMatchTheTcaItems(): void
    {
        // TCA files stop without the constant that TYPO3 defines during bootstrap.
        defined('TYPO3') || define('TYPO3', 'BE');
        $tca = require __DIR__ . '/../../../Configuration/TCA/tx_pongback_sent.php';
        $items = array_map(
            static fn(array $item): string => (string) ($item['value'] ?? $item[1] ?? ''),
            $tca['columns']['status']['config']['items'],
        );

        self::assertEqualsCanonicalizing(array_column(SendStatus::cases(), 'value'), $items);
    }

    #[Test]
    public function resultsKeepEverythingButTheEndpointWhenAddingIt(): void
    {
        $result = (new SendResult(SendStatus::Rejected, 'Nope', 17))->withEndpoint(new DiscoveredEndpoint(Protocol::Pingback, 'https://blog.example/xmlrpc.php'));

        self::assertSame(SendStatus::Rejected, $result->status);
        self::assertSame('Nope', $result->message);
        self::assertSame(17, $result->faultCode);
        self::assertSame('https://blog.example/xmlrpc.php', $result->endpoint);
        self::assertSame(Protocol::Pingback, $result->protocol);
    }

    #[Test]
    public function messagesCountTheirAttempts(): void
    {
        $message = new SendPingbacksMessage(42, 1);
        $next = $message->nextAttempt();

        self::assertSame(1, $message->attempt);
        self::assertSame([42, 1, 2], [$next->pageId, $next->languageId, $next->attempt]);
    }
}
