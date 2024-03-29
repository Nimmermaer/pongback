<?php

declare(strict_types=1);

namespace PHTH\Pongback\Controller;

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Michael Blunck <michael.blunck@phth.de>, PHTH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */
use PHTH\Pongback\Domain\Model\Pingback;
use PHTH\Pongback\Domain\Repository\PingbackRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * @package pongback
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class PingbackController extends ActionController
{
    public function __construct(
        private readonly PingbackRepository $pingbackRepository
    ) {
    }

    public function administerAction(): ResponseInterface
    {
        $pingbacks = $this->pingbackRepository->findByDeleted('0');

        $this->view->assign('pingbacks', $pingbacks);
        return $this->htmlResponse();
    }

    public function listAction(): ResponseInterface
    {
        $pingbacks = $this->pingbackRepository->findVisible();

        $this->view->assign('pingbacks', $pingbacks);
        return $this->htmlResponse();
    }

    public function showAction(Pingback $pingback): ResponseInterface
    {
        $this->view->assign('pingback', $pingback);
        return $this->htmlResponse();
    }

    public function unpublishAction($pingback): void
    {
        $pingback = $this->pingbackRepository->findByUid((int) $pingback);
        $pingback->setHidden(1);
        $this->pingbackRepository->update($pingback);
        $this->redirect('administer');
    }

    /**
     * action unpublish
     *
     * @param string $pingback
     */
    public function publishAction($pingback): void
    {
        $pingback = $this->pingbackRepository->findByUid((int) $pingback);
        $pingback->setHidden(0);
        $this->pingbackRepository->update($pingback);
        $this->redirect('administer');
    }

    /**
     * action delete
     *
     * @param string $pingback
     */
    public function deleteAction($pingback): void
    {
        $pingback = $this->pingbackRepository->findByUid((int) $pingback);
        $this->pingbackRepository->remove($pingback);
        //$this->persistenceManager->persist();

        $this->redirect('administer');
    }

    /**
     * edit delete
     */
    public function editAction(Pingback $pingback): ResponseInterface
    {
        $this->view->assign('pingback', $pingback);
        return $this->htmlResponse();
    }

    /**
     * server
     *
     * @return string
     */
    public function serverAction(): ResponseInterface
    {
        $fp = fopen('php://input', 'rb');
        stream_filter_append($fp, 'dechunk', STREAM_FILTER_READ);
        $HTTP_RAW_POST_DATA = stream_get_contents($fp);
        $xmlrpcServerHandler = xmlrpc_server_create();
        xmlrpc_server_register_method($xmlrpcServerHandler, 'pingback.ping', $this->pingback(...));
        $response = xmlrpc_server_call_method($xmlrpcServerHandler, $HTTP_RAW_POST_DATA, null);
        return $this->htmlResponse($response);
    }

    /**
     * @throws IllegalObjectTypeException
     */
    public function pingback(string $methodName, array $xmlRpcParams, mixed $appData): ?string
    {
        /**
         * We must implement some Hooks to validate some important content, the URL, the content from other page an  send an email to the owner of the blog
         */
        $pingback = GeneralUtility::makeInstance(Pingback::class);

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pongback']['validatePingback'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pongback']['validatePingback'] as $userFunc) {
                if ((is_countable($pingback->getValidationErrors()) ? count($pingback->getValidationErrors()) : 0) < 1) {
                    $params = [
                        'pingback' => $pingback,
                        'params' => $xmlRpcParams,
                    ];

                    GeneralUtility::callUserFunction($userFunc, $params, $this);
                } else {
                    break;
                }
            }
        }

        if ((is_countable($pingback->getValidationErrors()) ? count($pingback->getValidationErrors()) : 0) > 0) {
            $errors = $pingback->getValidationErrors();

            return $errors[0];
        }
        /**
         * saved the incoming Pongback to the Database
         */
        $pongbackConf = (array) @GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('pongback');
        if (! $pongbackConf['enableAutoPublishing']) {
            $pingback->setHidden(true);
        }
        $this->pingbackRepository->add($pingback);

        return LocalizationUtility::translate(
            'xmlrpc.pingback.ping.success',
            'pongback'
        );
    }

    /**
     * Generates an XML formatted Fault Code
     *
     * @param string $errno
     * @param string $errstr
     * @param string|null $errfile
     * @param string|null $errline
     * @param string|null $errcontext
     */
    public function return_xmlrpc_error($errno, $errstr, $errfile = null, $errline = null, $errcontext = null): never
    {
        header('Content-type: text/xml; charset=UTF-8');
        print xmlrpc_encode_request(null, [
            'faultCode' => $errno,
            'faultString' => 'Remote XMLRPC Error from ' . $_SERVER['SERVER_NAME'] . ": {$errstr} in {$errfile}:{$errline}",
        ]);
        die();
    }

    /**
     *  Overrides getErrorFlashMessage to deactivate
     */
    protected function getErrorFlashMessage(): bool
    {
        return false;
    }
}
