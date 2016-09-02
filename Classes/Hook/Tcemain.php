<?php

namespace PHTH\Pongback\Hook;

use fXmlRpc\Exception\ResponseException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class Tcemain
{

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;
    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext
     */
    protected $controllerContext;

    /**
     * @param $status
     * @param $table
     * @param $id
     * @param $fieldArray
     * @param $ref
     */
    public function processDatamap_postProcessFieldArray($status, $table, $id, $fieldArray, $ref)
    {
        $this->objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
        $this->controllerContext = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Mvc\\Controller\\ControllerContext');


        // @todo: make tables configurable
        /**
         * To search content after saving abou Hyperlinks
         */
        if ($table == 'tt_content' && is_array($fieldArray)) {
            $links = array();

            foreach ($fieldArray as $field) {
                preg_match_all("/((http|https):\/\/[^\s]*)/", $field, $matches);
                foreach ($matches[0] as $match) {

                    $links[$match] = $match;
                }
            }


            if (count($links) > 0) {

                /**
                 * @todo respect the enablefields!
                 * to provide to set the enablefields
                 * Takes the full tt_content-row when is not hidden
                 * we dont need to send pingbacks for hidden elements
                 *
                 */
                $row = BackendUtility::getRecord($table, $id, '*',
                    ' AND hidden = 0 AND (fe_group = \'\' OR fe_group = 0) ');


                if (is_array($row)) {

                    // @todo respect the enablefields!
                    /**
                     * take the page that is not hidden and get the unique pid to set the URL
                     */
                    $page = BackendUtility::getRecord('pages', $row['pid'], '*',
                        ' AND hidden = 0 AND (fe_group = \'\' OR fe_group = 0) ');


                    if (is_array($page)) {


                        $permalinkParameters = array();

                        $this->buildTSFE($ref);
                        $cObj = GeneralUtility::makeInstance('TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer');;

                        $cObj->start(array(), '');

                        $pingbackClient = GeneralUtility::makeInstance('PHTH\Pongback\Service\PingbackClient');

                        /**
                         *
                         */
                        foreach ($links as $link) {
                            $typolinkConf = array(
                                'additionalParams' => '',
                                'parameter' => $row['pid'],
                                'useCacheHash' => true,
                                'returnLast' => 'url',
                                'forceAbsoluteUrl' => true
                            );


                            $permaLink = $cObj->typoLink('', $typolinkConf);


                            try {
                                $response = $pingbackClient->send($link, $permaLink);
                                $o_flashMessage = GeneralUtility::makeInstance(
                                    '\TYPO3\CMS\Core\Messaging\FlashMessage',
                                    LocalizationUtility::translate("tcemain.pingback.ping.accepted",
                                        'pongback', array($link)),
                                    LocalizationUtility::translate("tcemain.pingback.ping.accepted_title",
                                        'pongback'),
                                    FlashMessage::OK
                                );
                                $this->controllerContext->getFlashMessageQueue()->addMessage($o_flashMessage);
                            } catch (ResponseException $ex) {
                                $o_flashMessage = GeneralUtility::makeInstance(
                                    '\TYPO3\CMS\Core\Messaging\FlashMessage',
                                    LocalizationUtility::translate("tcemain.pingback.ping.refused",
                                        'pongback', array($link, $ex->getFaultString())),
                                    LocalizationUtility::translate("tcemain.pingback.ping.refused_title",
                                        'pongback'),
                                    FlashMessage::WARNING
                                );
                                $this->controllerContext->getFlashMessageQueue()->addMessage($o_flashMessage);
                            }
                        }
                    } else {
                        $o_flashMessage = GeneralUtility::makeInstance(
                            '\TYPO3\CMS\Core\Messaging\FlashMessage',
                            LocalizationUtility::translate("tcemain.pingback.ping.not_sent",
                                'pongback'),
                            LocalizationUtility::translate("tcemain.pingback.ping.not_sent_title",
                                'pongback'),
                            FlashMessage::NOTICE
                        );
                        $this->controllerContext->getFlashMessageQueue()->addMessage($o_flashMessage);
                    }
                }
            }
        }
    }


    /**
     * @param $ref
     */
    public function buildTSFE($ref)
    {
        $TSFEclassName = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Controller\\TypoScriptFrontendController',
            $GLOBALS['TYPO3_CONF_VARS'], GeneralUtility::_GP('id'), GeneralUtility::_GP('type'));

        if (!is_object($GLOBALS['TT'])) {
            $GLOBALS['TT'] = new TimeTracker();
            $GLOBALS['TT']->start();
        }

        // Create the TSFE class.
        $GLOBALS['TSFE'] = new $TSFEclassName($GLOBALS['TYPO3_CONF_VARS'], $ref->pid, '0', 1, '', '', '', '');
        $GLOBALS['TSFE']->initFEuser();
        $GLOBALS['TSFE']->fetch_the_id();
        $GLOBALS['TSFE']->getPageAndRootline();
        $GLOBALS['TSFE']->initTemplate();
        $GLOBALS['TSFE']->tmpl->getFileName_backPath = PATH_site;
        $GLOBALS['TSFE']->forceTemplateParsing = 1;
        $GLOBALS['TSFE']->getConfigArray();
    }

}

