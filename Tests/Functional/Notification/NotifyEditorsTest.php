<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Functional\Notification;

use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mime\RawMessage;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webicorns\Pongback\Domain\Model\Pingback;
use Webicorns\Pongback\Event\AfterPingbackCreatedEvent;
use Webicorns\Pongback\Event\SendingFailedEvent;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Mention\Protocol;
use Webicorns\Pongback\Mention\ResolvedTarget;
use Webicorns\Pongback\Notification\NotifyEditors;
use Webicorns\Pongback\Sending\SendResult;
use Webicorns\Pongback\Sending\SendStatus;
use Webicorns\Pongback\Tests\Functional\AbstractPongbackTestCase;

final class NotifyEditorsTest extends AbstractPongbackTestCase
{
    /**
     * @var list<FluidEmail>
     */
    private array $sent = [];

    #[Test]
    public function isRegisteredForNewPingbacks(): void
    {
        $definitions = $this->get(ListenerProvider::class)->getAllListenerDefinitions();

        self::assertContains(
            ['service' => NotifyEditors::class, 'method' => 'pingbackCreated'],
            array_map(static fn(array $listener): array => ['service' => $listener['service'], 'method' => $listener['method']], $definitions[AfterPingbackCreatedEvent::class] ?? []),
        );
        self::assertContains(
            ['service' => NotifyEditors::class, 'method' => 'sendingFailed'],
            array_map(static fn(array $listener): array => ['service' => $listener['service'], 'method' => $listener['method']], $definitions[SendingFailedEvent::class] ?? []),
        );
    }

    #[Test]
    public function sendsNothingWithoutRecipient(): void
    {
        $this->notify(true);

        self::assertSame([], $this->sent);
    }

    #[Test]
    public function emailsTheRecipientsWithALinkIntoTheModule(): void
    {
        $this->writeSiteSettings('main', ['pongback' => ['notification' => [
            'emailTo' => 'editor@main.example, chief@main.example',
            'emailFrom' => 'pingbacks@main.example',
        ]]]);

        $this->notify(true);

        self::assertCount(1, $this->sent);
        $email = $this->sent[0];
        self::assertSame(['editor@main.example', 'chief@main.example'], array_map(static fn($address): string => $address->getAddress(), $email->getTo()));
        self::assertSame('pingbacks@main.example', $email->getFrom()[0]->getAddress());

        $html = (string) $email->getHtmlBody();
        self::assertSame('New Pingback from A <great> post', $email->getSubject());
        self::assertStringContainsString('New Pingback', $html);
        self::assertStringContainsString('href="https://blog.example/post"', $html);
        self::assertStringContainsString('A &lt;great&gt; post', $html, 'Escaped');
        self::assertStringContainsString('waiting for approval', $html);

        preg_match('#<a href="([^"]+)">Open it in the backend</a>#', $html, $link);
        $query = [];
        parse_str((string) parse_url(html_entity_decode($link[1] ?? ''), PHP_URL_QUERY), $query);
        self::assertSame('web_pongback', $query['redirect'] ?? null);
        self::assertSame('id=2&pingback=7', $query['redirectParams'] ?? null);
        self::assertArrayNotHasKey('token', $query, 'The login route needs no token, the module link is created after the login');

        $text = (string) $email->getTextBody();
        self::assertStringContainsString('waiting for approval', $text);
        self::assertStringContainsString('A <great> post', $text, 'Plain text is not HTML escaped');
        self::assertStringContainsString('redirect=web_pongback', $text);
    }

    #[Test]
    public function mentionsPublishedPingbacks(): void
    {
        $this->writeSiteSettings('main', ['pongback' => ['notification' => ['emailTo' => 'editor@main.example']]]);

        $this->notify(false);

        self::assertStringContainsString('already shown on your website', (string) $this->sent[0]->getHtmlBody());
    }

    #[Test]
    public function namesTheProtocol(): void
    {
        $this->writeSiteSettings('main', ['pongback' => ['notification' => ['emailTo' => 'editor@main.example']]]);

        $this->notify(true, 'webmention');

        self::assertSame('New Webmention from A <great> post', $this->sent[0]->getSubject());
        self::assertStringContainsString('New Webmention', (string) $this->sent[0]->getHtmlBody());
    }

    #[Test]
    public function mailProblemsAreLoggedOnly(): void
    {
        $this->writeSiteSettings('main', ['pongback' => ['notification' => ['emailTo' => 'editor@main.example']]]);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willThrowException(new \RuntimeException('SMTP down', 1732600100));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Could not send the Pongback notification: SMTP down', ['uid' => 7]);

        (new NotifyEditors($mailer, $this->get(UriBuilder::class), $this->get(LanguageServiceFactory::class), $logger))->pingbackCreated($this->event(true));
    }

    #[Test]
    public function reportsSendingProblems(): void
    {
        $this->writeSiteSettings('main', ['pongback' => ['notification' => ['emailTo' => 'editor@main.example']]]);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (RawMessage $message): void {
            self::assertInstanceOf(FluidEmail::class, $message);
            $this->sent[] = $message;
        });

        $this->subject($mailer)->sendingFailed(new SendingFailedEvent(
            $this->get(SiteFinder::class)->getSiteByIdentifier('main'),
            2,
            'https://main.example/article',
            [
                'https://down.example/post' => new SendResult(SendStatus::Failed, 'HTTP 503 <busy>', 0, 'https://down.example/wm', Protocol::Webmention),
                'https://strict.example/post' => new SendResult(SendStatus::Rejected, 'No link', 17, 'https://strict.example/xmlrpc', Protocol::Pingback),
            ],
        ));

        self::assertCount(1, $this->sent);
        $email = $this->sent[0];
        self::assertSame('Linked pages could not be notified: https://main.example/article', $email->getSubject());
        $html = (string) $email->getHtmlBody();
        self::assertStringContainsString('href="https://down.example/post"', $html);
        self::assertStringContainsString('(Webmention)', $html);
        self::assertStringContainsString('HTTP 503 &lt;busy&gt;', $html);
        self::assertStringContainsString('rejected by the linked site', $html);
        self::assertStringContainsString('also after 3 attempts', $html);
        self::assertStringContainsString('redirect=web_pongback', html_entity_decode($html));
        self::assertStringContainsString('redirectParams=id%3D2', $html);
        $text = (string) $email->getTextBody();
        self::assertStringContainsString('- https://strict.example/post (Pingback): rejected by the linked site – No link', $text);
        self::assertStringContainsString('HTTP 503 <busy>', $text);
    }

    private function subject(MailerInterface $mailer): NotifyEditors
    {
        return new NotifyEditors($mailer, $this->get(UriBuilder::class), $this->get(LanguageServiceFactory::class), new NullLogger());
    }

    private function notify(bool $pending, string $protocol = 'pingback'): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (RawMessage $message): void {
            self::assertInstanceOf(FluidEmail::class, $message);
            $this->sent[] = $message;
        });

        $this->subject($mailer)->pingbackCreated($this->event($pending, $protocol));
    }

    private function event(bool $pending, string $protocol = 'pingback'): AfterPingbackCreatedEvent
    {
        $pingback = new Pingback();
        $pingback->setPid(2);
        $pingback->setHidden($pending);
        $pingback->setProtocol(Protocol::from($protocol));
        $pingback->setSourceLink('https://blog.example/post');
        $pingback->setSourceTitle('A <great> post');
        $pingback->setTargetLink('https://main.example/article');
        $pingback->setExcerpt('… links to the article …');

        return new AfterPingbackCreatedEvent(
            7,
            $pingback,
            new IncomingMention('https://blog.example/post', 'https://main.example/article', Protocol::Pingback),
            new ResolvedTarget($this->get(SiteFinder::class)->getSiteByIdentifier('main'), 2, 0),
        );
    }
}
