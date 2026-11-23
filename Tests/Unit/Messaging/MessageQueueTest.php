<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Messaging;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Webicorns\Pongback\Messaging\MessageQueue;
use Webicorns\Pongback\Sending\SendPingbacksMessage;

final class MessageQueueTest extends TestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $routingBackup = null;

    protected function setUp(): void
    {
        $this->routingBackup = $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'] = $this->routingBackup;
    }

    /**
     * @return array<string, array{mixed, list<string>}>
     */
    public static function routingProvider(): array
    {
        return [
            'not configured' => [null, ['doctrine']],
            'one transport' => ['amqp', ['amqp']],
            'several transports' => [['amqp', 'doctrine'], ['amqp', 'doctrine']],
            'invalid values are ignored' => [['', 42, 'amqp'], ['amqp']],
            'nothing valid' => [[''], ['doctrine']],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('routingProvider')]
    public function sendsToTheConfiguredTransportsOnly(mixed $routing, array $expected): void
    {
        // TYPO3 13 would also use the "*" route (synchronous) without the explicit transport names.
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'] = ['*' => 'default', SendPingbacksMessage::class => $routing];

        $envelope = $this->queue(new SendPingbacksMessage(5));

        self::assertSame($expected, $envelope->last(TransportNamesStamp::class)?->getTransportNames());
        self::assertNull($envelope->last(DelayStamp::class));
    }

    #[Test]
    public function delaysInSeconds(): void
    {
        $message = new SendPingbacksMessage(5, 1, 2);

        $envelope = $this->queue($message, 300);

        self::assertSame($message, $envelope->getMessage());
        self::assertSame(300_000, $envelope->last(DelayStamp::class)?->getDelay());
    }

    #[Test]
    public function retriesAfterFiveMinutesAndOneHour(): void
    {
        $dispatched = new \ArrayObject();
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message, array $stamps) use ($dispatched): Envelope {
            $envelope = new Envelope($message, $stamps);
            $dispatched->append($envelope);
            return $envelope;
        });
        $queue = new MessageQueue($bus);

        self::assertTrue($queue->retryLater(new SendPingbacksMessage(5, 1, 1)));
        self::assertTrue($queue->retryLater(new SendPingbacksMessage(5, 1, 2)));
        self::assertFalse($queue->retryLater(new SendPingbacksMessage(5, 1, MessageQueue::MAX_ATTEMPTS)));

        /** @var list<Envelope> $envelopes */
        $envelopes = $dispatched->getArrayCopy();
        self::assertCount(2, $envelopes);
        self::assertEquals(new SendPingbacksMessage(5, 1, 2), $envelopes[0]->getMessage());
        self::assertSame(300_000, $envelopes[0]->last(DelayStamp::class)?->getDelay());
        self::assertEquals(new SendPingbacksMessage(5, 1, 3), $envelopes[1]->getMessage());
        self::assertSame(3_600_000, $envelopes[1]->last(DelayStamp::class)?->getDelay());
    }

    #[Test]
    public function knowsTheLastAttempt(): void
    {
        self::assertFalse(MessageQueue::isLastAttempt(new SendPingbacksMessage(5, 0, MessageQueue::MAX_ATTEMPTS - 1)));
        self::assertTrue(MessageQueue::isLastAttempt(new SendPingbacksMessage(5, 0, MessageQueue::MAX_ATTEMPTS)));
        self::assertTrue(MessageQueue::isLastAttempt(new SendPingbacksMessage(5, 0, MessageQueue::MAX_ATTEMPTS + 1)));
    }

    private function queue(SendPingbacksMessage $message, int $delay = 0): Envelope
    {
        $dispatched = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $message, array $stamps) use (&$dispatched): Envelope {
                return $dispatched = new Envelope($message, $stamps);
            },
        );

        $delay === 0 ? (new MessageQueue($bus))->queue($message) : (new MessageQueue($bus))->queue($message, $delay);

        self::assertInstanceOf(Envelope::class, $dispatched);
        return $dispatched;
    }
}
