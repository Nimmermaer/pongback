<?php
namespace PHTH\Pongback\Service;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
use fXmlRpc\Client;
use TYPO3\CMS\Core\Messaging\FlashMessage;
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
     *
     * @var string $targetLink
     */
    protected $targetLink;


    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext
     */
    protected $controllerContext;

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * @return string
     */
    public function getTargetLink()
    {
        return $this->targetLink;
    }

    /**
     * @param $targetLink
     */
    public function setTargetLink($targetLink)
    {
        $this->targetLink = $targetLink;
    }

    /**
     * @param $params
     */
    public function mailPingbackArrived(&$params)
    {

        $sourceLink = $params['params'][1];

        /**
         * An Hook from PingbackController
         *
         * require  [defaultMailFromAddress] and [defaultMailFromName]
         */
        $pongbackConf = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pongback']);
        if (GeneralUtility::validEmail($pongbackConf['notificationAddress'])) {
            $mailer = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Mail\\MailMessage');

            $subject = LocalizationUtility::translate("tx_pongback_domain_model_pingback.pingback_arrived_alert_mail_subject",
                'pongback');
            $body = LocalizationUtility::translate("tx_pongback_domain_model_pingback.pingback_arrived_alert_mail",
                    'pongback') . " $sourceLink ";

            $from = MailUtility::getSystemFrom();

            $mailer->setFrom($from);
            $mailer->setTo(array($pongbackConf['notificationAddress'] => 'mail'))
                ->setSubject($subject)
                ->setBody($body);


            $mailer->send();
        }
    }


    /**
     * @param $targetUri
     * @param $sourceUri
     */
    public function send($targetUri, $sourceUri)
    {

        $this->autoDiscovery($targetUri);

        $client = new Client($this->getTargetLink());

        $response = $client->call('pingback.ping', array($sourceUri, $targetUri));
    }

    /**
     * @param $targetLink
     */
    public function autoDiscovery($targetLink)
    {

        $this->objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');

        $flashMessageService = $this->objectManager->get(\TYPO3\CMS\Core\Messaging\FlashMessageService::class);

        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $searchPattern = '/X-Pingback/';
        $proofLink = $this->sendRequest($targetLink);

        preg_match($searchPattern, substr($proofLink, 3), $success, PREG_OFFSET_CAPTURE, 3);
        if ($success === "Pingback" | "pingback") {
            preg_match_all("/( (http|https):\/\/[^\s]*)/", $proofLink, $output);
            $this->setTargetLink($output);


        } elseif ($this->htmlHeader($targetLink)) {

            $this->setTargetLink($this->htmlHeader($targetLink));


        } else {


            $o_flashMessage = GeneralUtility::makeInstance(
                'TYPO3\\CMS\\Core\\Messaging\\FlashMessage', ' Kein Pingback vorhanden ', FlashMessage::OK
            );

            $messageQueue->addMessage($o_flashMessage);
        }
    }

    /**
     * @param $targetLink
     * @return mixed
     */
    public function sendRequest($targetLink)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetLink);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'callback'));

        $page = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $httpcode;

    }

    /**
     * @param $ch
     * @param $header
     * @return mixed
     */
    public function callback($ch, $header)
    {
        return $header;
    }

    /**
     * @param $website
     * @return mixed
     */
    public function htmlHeader($website)
    {

        $response = file_get_contents($website, true, null, 0, 5000);
        preg_match_all("/(link[^>].*pingback.*href=\")(.*)(\".*>)/iU", $response, $treffer);

        if (!isset($treffer[1][0])) {
            $treffer[1][0] = "";
        } else {

            return $treffer[2][0];
        }
    }
}
