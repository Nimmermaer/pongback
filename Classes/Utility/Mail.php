<?php

declare(strict_types=1);

namespace Webicorns\Pongback\Utility;

use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final readonly class Mail
{
    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function sendNotification(string $content = '', string $template = 'Default', string $subject = ''): void
    {
        if ($subject === '' || $subject === '0') {
            $subject = LocalizationUtility::translate('pongback.notification.email.subject', 'pongback');
        }

        $configuration = $this->extensionConfiguration->get('pongback');

        $email = GeneralUtility::makeInstance(FluidEmail::class);
        $email
            ->to($configuration['notification']['email']['to'])
            ->from($this->setDefaultFromAddress($configuration['notification']['email']['from']))
            ->subject($subject)
            ->format(FluidEmail::FORMAT_BOTH)
            ->setTemplate($template)
            ->assign('content', $content);
        GeneralUtility::makeInstance(MailerInterface::class)->send($email);
    }

    private function setDefaultFromAddress(string $from): Address
    {
        if ($from === '' || $from === '0') {
            return new Address(MailUtility::getSystemFromAddress(), MailUtility::getSystemFromName());
        }

        return new Address($from);
    }
}
