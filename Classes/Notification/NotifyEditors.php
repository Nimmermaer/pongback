<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Notification;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Backend\Routing\RouteRedirect;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webicorns\Pongback\Configuration\PongbackSettings;
use Webicorns\Pongback\Controller\Backend\ModerationController;
use Webicorns\Pongback\Event\AfterPingbackCreatedEvent;
use Webicorns\Pongback\Event\SendingFailedEvent;
use Webicorns\Pongback\Mention\IncomingMention;
use Webicorns\Pongback\Sending\PingStatus;

/**
 * Emails the recipients of the site setting "pongback.notification.emailTo":
 * about new Pingbacks and Webmentions, and about linked pages that could not be notified.
 * Each email links to the module "Web > Pongback".
 */
readonly class NotifyEditors
{
    public const TEMPLATE = 'PongbackPingbackReceived';
    public const TEMPLATE_SENDING_FAILED = 'PongbackSendingFailed';

    private const LABELS = 'LLL:EXT:pongback/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private MailerInterface $mailer,
        private UriBuilder $uriBuilder,
        private LanguageServiceFactory $languageServiceFactory,
        private LoggerInterface $logger,
    ) {}

    #[AsEventListener('pongback/notify-editors')]
    public function pingbackCreated(AfterPingbackCreatedEvent $event): void
    {
        $pingback = $event->pingback;
        $subject = sprintf(
            $this->label($event->target->site, 'notification.subject'),
            self::protocolName($pingback->getProtocol()),
            $pingback->getSourceTitle() !== '' ? $pingback->getSourceTitle() : $pingback->getSourceLink(),
        );

        // The mention is stored, a mail problem must not turn it into an error for the sender.
        $this->send($event->target->site, $subject, self::TEMPLATE, [
            'pingback' => $pingback,
            'pending' => $pingback->isHidden(),
            'protocol' => self::protocolName($pingback->getProtocol()),
            'moderationUrl' => $this->moduleUrl(['id' => $event->target->pageId, 'pingback' => $event->uid]),
        ], ['uid' => $event->uid]);
    }

    #[AsEventListener('pongback/notify-editors-sending-failed')]
    public function sendingFailed(SendingFailedEvent $event): void
    {
        $problems = [];
        foreach ($event->problems as $target => $result) {
            $problems[] = [
                'target' => $target,
                'protocol' => $result->protocol === '' ? '' : self::protocolName($result->protocol),
                'rejected' => $result->status === PingStatus::Rejected,
                'message' => $result->message,
            ];
        }

        $this->send($event->site, sprintf($this->label($event->site, 'notification.sendingFailed.subject'), $event->pageUrl), self::TEMPLATE_SENDING_FAILED, [
            'pageUrl' => $event->pageUrl,
            'problems' => $problems,
            'moduleUrl' => $this->moduleUrl(['id' => $event->pageId]),
        ], ['pageId' => $event->pageId]);
    }

    protected function createEmail(): FluidEmail
    {
        return GeneralUtility::makeInstance(FluidEmail::class)->format(FluidEmail::FORMAT_BOTH);
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<string, mixed> $logContext
     */
    private function send(Site $site, string $subject, string $template, array $variables, array $logContext): void
    {
        $settings = PongbackSettings::fromSite($site);
        if ($settings->notificationEmailTo === '') {
            return;
        }

        try {
            $email = $this->createEmail()
                ->to(...array_map(static fn(string $address): Address => new Address($address), GeneralUtility::trimExplode(',', $settings->notificationEmailTo, true)))
                ->subject($subject)
                ->setTemplate($template)
                ->assignMultiple($variables);
            if ($settings->notificationEmailFrom !== '') {
                $email->from(new Address($settings->notificationEmailFrom));
            }

            $this->mailer->send($email);
        } catch (\Throwable $throwable) {
            $this->logger->error('Could not send the Pongback notification: ' . $throwable->getMessage(), $logContext);
        }
    }

    /**
     * Subjects are plain text headers: set here, a template section would escape them as HTML.
     */
    private function label(Site $site, string $key): string
    {
        return $this->languageServiceFactory->createFromSiteLanguage($site->getDefaultLanguage())->sL(self::LABELS . $key);
    }

    private static function protocolName(string $protocol): string
    {
        return $protocol === IncomingMention::PROTOCOL_WEBMENTION ? 'Webmention' : 'Pingback';
    }

    /**
     * Module URLs need a token of the logged in user. The link goes to the login, which forwards to the
     * module after logging in (or right away with a session). Opening it changes nothing: approving needs
     * a click in the module and the page permissions.
     *
     * @param array<string, int> $parameters
     */
    private function moduleUrl(array $parameters): string
    {
        return (string) $this->uriBuilder->buildUriWithRedirect(
            'login',
            [],
            RouteRedirect::create(ModerationController::MODULE, $parameters),
            UriBuilder::ABSOLUTE_URL,
        );
    }
}
