<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Webicorns\Pongback\Utility;

use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Webicorns\Pongback\Configuration\PongbackSettings;

final class Mail
{
    public function sendNotification(PongbackSettings $settings, string $content = '', string $template = 'Default', string $subject = ''): void
    {
        if ($settings->notificationEmailTo === '') {
            return;
        }

        if ($subject === '' || $subject === '0') {
            $subject = LocalizationUtility::translate('pongback.notification.email.subject', 'pongback');
        }

        $email = GeneralUtility::makeInstance(FluidEmail::class);
        $email
            ->to($settings->notificationEmailTo)
            ->from($this->setDefaultFromAddress($settings->notificationEmailFrom))
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
