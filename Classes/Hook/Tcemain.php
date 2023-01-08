<?php

declare(strict_types=1);

namespace PHTH\Pongback\Hook;

use PHTH\Pongback\Service\PingbackClient;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\TypoScriptAspect;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class Tcemain
{
    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        // @todo: make tables configurable
        /**
         * To search content after saving abou Hyperlinks
         */
        if (key($dataHandler->datamap) === 'tt_content' && $dataHandler->getHistoryRecords() !== []) {
            $links = [];
            foreach ($dataHandler->getHistoryRecords()[key($dataHandler->datamap) . ':' . $dataHandler->checkValue_currentRecord['uid']] as $recordType => $fields) {
                if ($recordType === 'newRecord') {
                    // iterate over all changed fields in tt_content e.g. header, subheader, bodytext
                    foreach ($fields as $field) {
                        $document = new \DOMDocument();
                        $document->loadHTML((string) $field);
                        $xPath = new \DOMXPath($document);
                        $nodeList = $xPath->query('//a/@href');
                        foreach ($nodeList as $node) {
                            $links[] = $node->value;
                        }
                    }
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
                    key($dataHandler->datamap),
                    $dataHandler->checkValue_currentRecord['uid'],
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

                            $permaLink = $cObj->typoLink_URL($typolinkConf);

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

    public function buildTSFE($dataHandler): void
    {
        /** @var ServerRequest $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $context = GeneralUtility::makeInstance(Context::class);
        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($dataHandler->checkValue_currentRecord['pid']);
        $GLOBALS['TSFE'] = GeneralUtility::makeInstance(
            TypoScriptFrontendController::class,
            $context,
            $site,
            $request->getAttribute('language', $site->getDefaultLanguage()),
            new PageArguments($dataHandler->checkValue_currentRecord['pid'], '0', []),
            new FrontendUserAuthentication()
        );

        if (! is_object($GLOBALS['TT'])) {
            $GLOBALS['TT'] = new TimeTracker();
            GeneralUtility::makeInstance(TimeTracker::class)->start();
        }

        GeneralUtility::makeInstance(Context::class)->setAspect('typoscript', GeneralUtility::makeInstance(TypoScriptAspect::class, true));
    }
}
