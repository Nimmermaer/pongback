<?php

declare(strict_types=1);

namespace PHTH\Pongback\Service;

use fXmlRpc\Client;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class PingbackClient
 * @package PHTH\Pongback\Service
 */
class PingbackClient
{
    protected string $targetLink;


    public function getTargetLink(): string
    {
        return $this->targetLink;
    }

    public function setTargetLink(string $targetLink): void
    {
        $this->targetLink = $targetLink;
    }

    public function mailPingbackArrived(&$params): void
    {
        $sourceLink = $params['params'][1];

        /**
         * An Hook from PingbackController
         *
         * require  [defaultMailFromAddress] and [defaultMailFromName]
         */
        $pongbackConf = (array) GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('pongback');
        if (GeneralUtility::validEmail($pongbackConf['notificationAddress'])) {
            $mailer = GeneralUtility::makeInstance(MailMessage::class);

            $subject = LocalizationUtility::translate(
                'tx_pongback_domain_model_pingback.pingback_arrived_alert_mail_subject',
                'pongback'
            );
            $body = LocalizationUtility::translate(
                'tx_pongback_domain_model_pingback.pingback_arrived_alert_mail',
                'pongback'
            ) . sprintf(' %s ', $sourceLink);

            $systemFrom = MailUtility::getSystemFrom();

            $mailer->setFrom($systemFrom);
            $mailer->setTo([
                $pongbackConf['notificationAddress'] => 'mail',
            ])
                ->setSubject($subject)
                ->text($body);

            $mailer->send();
        }
    }

    public function send($targetUri, $sourceUri): void
    {
        $this->autoDiscovery($targetUri);

        $client = new Client($this->getTargetLink());
        $client->call('pingback.ping', [$sourceUri, $targetUri]);
    }

    public function autoDiscovery($targetLink): void
    {
        try {
            $pingbackLink = $this->getPingbackUrlFromHeader($targetLink);
            if (empty($pingbackLink)) {
                $pingbackLink = $this->getPingbackUrlFromHTMLHeader($targetLink);
            }
            $this->setTargetLink($pingbackLink);
        } catch (\Exception) {
            // no need
        }
    }

    /**
     * @return mixed
     */
    public function getPingbackUrlFromHeader($targetLink): string
    {
        $client = new \GuzzleHttp\Client();
        $options = [
            'verify' => false,
        ];
        $response = $client->get($targetLink, $options);
        $pingbackHeader = $response->getHeader('X-Pingback');
        return $pingbackHeader[0] ?? '';
    }

    /**
     * @return mixed
     */
    public function getPingbackUrlFromHTMLHeader($targetLink): string
    {
        $client = new \GuzzleHttp\Client();
        $options = [
            'verify' => false,
        ];
        $response = $client->get($targetLink, $options);
        $html = $response->getBody()->getContents();
        $dom = new \DomDocument();
        $dom->loadHTML($html);
        $links = $dom->getElementsByTagName('link');
        foreach ($links as $link) {
            if (str_contains($link->getAttribute('rel'), 'pingback')) {
                $pingbackUrl = $link->getattribute('href');
            }
        }
        return $pingbackUrl ?? '';
    }
}
