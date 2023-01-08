<?php

declare(strict_types=1);

namespace PHTH\Pongback\Hook;

use fXmlRpc\Exception\HttpException;
use PHTH\Pongback\Service\PingbackClient;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\TypoScriptAspect;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class Tcemain
{
    public function processDatamap_postProcessFieldArray($status, $table, $id, $fieldArray, $ref): void
    {
        // @todo: make tables configurable
        /**
         * To search content after saving abou Hyperlinks
         */
        if ($table === 'tt_content' && is_array($fieldArray)) {
            $links = [];

            foreach ($fieldArray as $field) {
                preg_match_all("#((http|https):\/\/[^\s]*)#", (string) $field, $matches);
                foreach ($matches[0] as $match) {
                    $links[$match] = $match;
                }
            }

            if ($links !== []) {
                /**
                 * @todo respect the enablefields!
                 * to provide to set the enablefields
                 * Takes the full tt_content-row when is not hidden
                 * we dont need to send pingbacks for hidden elements
                 */
                $row = BackendUtility::getRecord(
                    $table,
                    $id,
                    '*',
                    " AND hidden = 0 AND (fe_group = '' OR fe_group = 0) "
                );

                if (is_array($row)) {
                    // @todo respect the enablefields!
                    /**
                     * take the page that is not hidden and get the unique pid to set the URL
                     */
                    $page = BackendUtility::getRecord(
                        'pages',
                        $row['pid'],
                        '*',
                        " AND hidden = 0 AND (fe_group = '' OR fe_group = 0) "
                    );

                    if (is_array($page)) {
                        $this->buildTSFE($ref);
                        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);

                        $cObj->start([], '');

                        $pingbackClient = GeneralUtility::makeInstance(PingbackClient::class);

                        foreach ($links as $link) {
                            $typolinkConf = [
                                'additionalParams' => '',
                                'parameter' => $row['pid'],
                                'useCacheHash' => true,
                                'returnLast' => 'url',
                                'forceAbsoluteUrl' => true,
                            ];

                            $permaLink = $cObj->typoLink('', $typolinkConf);

                            try {
                                $pingbackClient->send($link, $permaLink);
                                $o_flashMessage = GeneralUtility::makeInstance(
                                    '\\' . FlashMessage::class,
                                    LocalizationUtility::translate(
                                        'tcemain.pingback.ping.accepted',
                                        'pongback',
                                        [$link]
                                    ),
                                    LocalizationUtility::translate(
                                        'tcemain.pingback.ping.accepted_title',
                                        'pongback'
                                    ),
                                    ContextualFeedbackSeverity::OK
                                );
                            } catch (HttpException $httpResponseException) {
                                $o_flashMessage = GeneralUtility::makeInstance(
                                    '\\' . FlashMessage::class,
                                    LocalizationUtility::translate(
                                        'tcemain.pingback.ping.refused',
                                        'pongback',
                                        [$link, $httpResponseException->getFaultString()]
                                    ),
                                    LocalizationUtility::translate(
                                        'tcemain.pingback.ping.refused_title',
                                        'pongback'
                                    ),
                                    ContextualFeedbackSeverity::WARNING
                                );
                            }
                        }
                    } else {
                        $o_flashMessage = GeneralUtility::makeInstance(
                            '\\' . FlashMessage::class,
                            LocalizationUtility::translate(
                                'tcemain.pingback.ping.not_sent',
                                'pongback'
                            ),
                            LocalizationUtility::translate(
                                'tcemain.pingback.ping.not_sent_title',
                                'pongback'
                            ),
                            ContextualFeedbackSeverity::NOTICE
                        );
                    }
                }
            }
        }
    }

    public function buildTSFE($ref): void
    {
        $TSFEclassName = GeneralUtility::makeInstance(
            TypoScriptFrontendController::class,
            $GLOBALS['TYPO3_CONF_VARS'],
            GeneralUtility::_GP('id'),
            GeneralUtility::_GP('type')
        );

        if (! is_object($GLOBALS['TT'])) {
            $GLOBALS['TT'] = new TimeTracker();
            GeneralUtility::makeInstance(TimeTracker::class)->start();
        }

        // Create the TSFE class.
        $GLOBALS['TSFE'] = new $TSFEclassName($GLOBALS['TYPO3_CONF_VARS'], $ref->pid, '0', 1, '', '', '', '');
        $GLOBALS['TSFE']->fetch_the_id();
        //  $GLOBALS['TSFE']->getPageAndRootline();
        //  $GLOBALS['TSFE']->tmpl->getFileName_backPath = PATH_SITE;
        GeneralUtility::makeInstance(Context::class)->setAspect('typoscript', GeneralUtility::makeInstance(TypoScriptAspect::class, true));
        $GLOBALS['TSFE']->getConfigArray();
    }
}
