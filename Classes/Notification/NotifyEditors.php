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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webicorns\Pongback\Configuration\PongbackSettings;
use Webicorns\Pongback\Controller\Backend\ModerationController;
use Webicorns\Pongback\Event\AfterPingbackCreatedEvent;
use Webicorns\Pongback\Mention\IncomingMention;

/**
 * Emails the recipient of the site setting "pongback.notification.emailTo" about a new Pingback,
 * with a link to it in the module "Web > Pongback".
 */
#[AsEventListener('pongback/notify-editors')]
readonly class NotifyEditors
{
    public const TEMPLATE = 'PongbackPingbackReceived';

    public function __construct(
        private MailerInterface $mailer,
        private UriBuilder $uriBuilder,
        private LanguageServiceFactory $languageServiceFactory,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(AfterPingbackCreatedEvent $event): void
    {
        $settings = PongbackSettings::fromSite($event->target->site);
        if ($settings->notificationEmailTo === '') {
            return;
        }

        try {
            $email = $this->createEmail()
                ->to(...array_map(static fn(string $address): Address => new Address($address), GeneralUtility::trimExplode(',', $settings->notificationEmailTo, true)))
                ->subject($this->subject($event))
                ->setTemplate(self::TEMPLATE)
                ->assignMultiple([
                    'pingback' => $event->pingback,
                    'pending' => $event->pingback->isHidden(),
                    'protocol' => self::protocolName($event->pingback->getProtocol()),
                    'moderationUrl' => $this->moderationUrl($event),
                ]);
            if ($settings->notificationEmailFrom !== '') {
                $email->from(new Address($settings->notificationEmailFrom));
            }

            $this->mailer->send($email);
        } catch (\Throwable $throwable) {
            // The Pingback is stored, a mail problem must not turn it into a fault for the sender.
            $this->logger->error('Could not send the Pingback notification: ' . $throwable->getMessage(), ['uid' => $event->uid]);
        }
    }

    protected function createEmail(): FluidEmail
    {
        return GeneralUtility::makeInstance(FluidEmail::class)->format(FluidEmail::FORMAT_BOTH);
    }

    /**
     * A plain text header: set here, a template section would escape it as HTML.
     */
    private function subject(AfterPingbackCreatedEvent $event): string
    {
        $pingback = $event->pingback;
        $label = $this->languageServiceFactory
            ->createFromSiteLanguage($event->target->site->getDefaultLanguage())
            ->sL('LLL:EXT:pongback/Resources/Private/Language/locallang.xlf:notification.subject');

        return sprintf(
            $label,
            self::protocolName($pingback->getProtocol()),
            $pingback->getSourceTitle() !== '' ? $pingback->getSourceTitle() : $pingback->getSourceLink(),
        );
    }

    private static function protocolName(string $protocol): string
    {
        return $protocol === IncomingMention::PROTOCOL_WEBMENTION ? 'Webmention' : 'Pingback';
    }

    /**
     * Module URLs need a token of the logged in user. The link goes to the login, which forwards to the
     * module after logging in (or right away with a session). Opening it changes nothing: approving needs
     * a click in the module and the page permissions.
     */
    private function moderationUrl(AfterPingbackCreatedEvent $event): string
    {
        return (string) $this->uriBuilder->buildUriWithRedirect(
            'login',
            [],
            RouteRedirect::create(ModerationController::MODULE, ['id' => $event->target->pageId, 'pingback' => $event->uid]),
            UriBuilder::ABSOLUTE_URL,
        );
    }
}
