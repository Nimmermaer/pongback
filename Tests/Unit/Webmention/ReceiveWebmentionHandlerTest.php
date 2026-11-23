<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Webmention;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Mention\MentionReceiverInterface;
use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Mention\SyncResult;
use Webicorns\Pongback\Messaging\MessageQueue;
use Webicorns\Pongback\Tests\Unit\Fixtures\SetSettings;
use Webicorns\Pongback\Webmention\ReceiveWebmentionHandler;
use Webicorns\Pongback\Webmention\ReceiveWebmentionMessage;

final class ReceiveWebmentionHandlerTest extends TestCase
{
    private MentionReceiverInterface&MockObject $receiver;
    private Site&MockObject $site;

    /**
     * @var list<Envelope>
     */
    private array $queued = [];

    /**
     * @var array<string, mixed>
     */
    private array $settings = [];

    protected function setUp(): void
    {
        $this->receiver = $this->createMock(MentionReceiverInterface::class);
        $this->site = $this->createMock(Site::class);
        $this->site->method('getSettings')->willReturnCallback(fn(): SiteSettings => SetSettings::create($this->settings));
    }

    #[Test]
    public function synchronizesTheMentionAsWebmention(): void
    {
        $this->receiver->expects(self::once())->method('synchronize')
            ->with(
                new IncomingMention('https://blog.example/post', 'https://main.example/article', IncomingMention::PROTOCOL_WEBMENTION, '203.0.113.5', 'Agent'),
                $this->site,
            )
            ->willReturn(SyncResult::Created);

        $this->handle(new ReceiveWebmentionMessage('https://blog.example/post', 'https://main.example/article', 'main', '203.0.113.5', 'Agent'));

        self::assertSame([], $this->queued);
    }

    #[Test]
    public function ignoresRemovedSites(): void
    {
        $this->receiver->expects(self::never())->method('synchronize');

        $this->handle($this->message(1), 'removed');
    }

    #[Test]
    public function ignoresSitesThatDisabledWebmentions(): void
    {
        $this->settings['pongback.receive.webmention'] = false;
        $this->receiver->expects(self::never())->method('synchronize');

        $this->handle($this->message(1));
    }

    /**
     * @return array<string, array{RejectionReason, int, ?int}>
     */
    public static function rejectionProvider(): array
    {
        return [
            'server error: again in 5 minutes' => [RejectionReason::UpstreamError, 1, 300_000],
            'unreachable: again in 1 hour' => [RejectionReason::SourceNotFound, 2, 3_600_000],
            'last attempt' => [RejectionReason::UpstreamError, ReceiveWebmentionHandler::MAX_ATTEMPTS, null],
            'no link: final' => [RejectionReason::NoLinkToTarget, 1, null],
            'gone: final' => [RejectionReason::SourceGone, 1, null],
            'rejected by a listener: final' => [RejectionReason::AccessDenied, 1, null],
        ];
    }

    #[Test]
    #[DataProvider('rejectionProvider')]
    public function retriesTemporaryProblems(RejectionReason $reason, int $attempt, ?int $expectedDelay): void
    {
        $this->receiver->method('synchronize')->willThrowException(new MentionRejected($reason, 'Problem'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Webmention rejected', [
            'reason' => $reason->value,
            'source' => 'https://blog.example/post',
            'target' => 'https://main.example/article',
            'attempt' => $attempt,
        ]);

        $this->handle($this->message($attempt), 'main', $logger);

        if ($expectedDelay === null) {
            self::assertSame([], $this->queued);
            return;
        }

        self::assertCount(1, $this->queued);
        self::assertEquals($this->message($attempt + 1), $this->queued[0]->getMessage());
        self::assertSame($expectedDelay, $this->queued[0]->last(DelayStamp::class)?->getDelay());
    }

    private function message(int $attempt): ReceiveWebmentionMessage
    {
        return new ReceiveWebmentionMessage('https://blog.example/post', 'https://main.example/article', 'main', '', '', $attempt);
    }

    private function handle(ReceiveWebmentionMessage $message, string $knownSite = 'main', ?LoggerInterface $logger = null): void
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByIdentifier')->willReturnCallback(
            fn(string $identifier): Site => $identifier === $knownSite && $identifier === 'main'
                ? $this->site
                : throw new SiteNotFoundException('No site', 1732700001),
        );
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(fn(object $message, array $stamps): Envelope => $this->queued[] = new Envelope($message, $stamps));

        (new ReceiveWebmentionHandler($siteFinder, $this->receiver, new MessageQueue($bus), $logger ?? $this->createMock(LoggerInterface::class)))($message);
    }
}
