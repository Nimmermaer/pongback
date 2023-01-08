<?php

declare(strict_types=1);

namespace PHTH\Pongback\Service;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
use fXmlRpc\Client;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Class PingbackClient
 * @package PHTH\Pongback\Service
 */
class PingbackClient
{
    /**
     * @var string
     */
    protected $targetLink;

    /**
     * @return string
     */
    public function getTargetLink()
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
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);

        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $searchPattern = '/X-Pingback/';
        $proofLink = $this->sendRequest($targetLink);
        preg_match($searchPattern, substr((string) $proofLink, 3), $success, PREG_OFFSET_CAPTURE, 3);
        if ($success == 'Pingback' | 'pingback') {
            preg_match_all("#( (http|https):\/\/[^\s]*)#", (string) $proofLink, $output);
            $this->setTargetLink((string) $output);
        } elseif ($this->htmlHeader($targetLink)) {
            $this->setTargetLink($this->htmlHeader($targetLink));
        } else {
            $o_flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                ' Kein Pingback vorhanden ',
                AbstractMessage::OK
            );

            $messageQueue->addMessage($o_flashMessage);
        }
    }

    /**
     * @return mixed
     */
    public function sendRequest($targetLink)
    {
//        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
//        $response = $requestFactory->request($targetLink, 'POST');
//        debug($response);
//        debug($response->getHeaders());
//        debug($response->getBody()->getContents());
//        dd($response->getStatusCode());

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetLink);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, "callback");
       return curl_exec($ch);
    }

    /**
     * @return mixed
     */
    public function callback($ch, $header)
    {
        return $header;
    }

    /**
     * @return mixed
     */
    public function htmlHeader($website)
    {
        $response = file_get_contents($website, true, null, 0, 5000);
        preg_match_all('#(link[^>].*pingback.*href=")(.*)(".*>)#iU', $response, $treffer);

        if (! isset($treffer[1][0])) {
            $treffer[1][0] = '';
        } else {
            return $treffer[2][0];
        }
        return '';
    }
}
