<?php

declare(strict_types=1);

namespace PHTH\Pongback\Domain\Validator;

/***************************************************************
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
 ***************************************************************/
use PHTH\Pongback\Controller\PingbackController;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * @package pongback
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class PingbackValidator
{
    public function validateTargetUri(mixed &$params): void
    {
        $pingback = $params['pingback'];
        $targetLink = $params['params'][0];
        $sourceLink = $params['params'][1];
        $matchLink = $_SERVER['SERVER_NAME'];

        if (strpos((string) $targetLink, (string) $matchLink) > 0) {
            $pingback->setSourceLink($sourceLink);
            $pingback->setTargetLink($targetLink);
        } else {
            $error = GeneralUtility::makeInstance(PingbackController::class);

            $pingback->addValidationError($error->return_xmlrpc_error(
                '32',
                LocalizationUtility::translate(
                    'tx_pongback_domain_model_pingback.error_targetlink',
                    'pongback'
                )
            ));
        }
    }

    public function getInformationFromOtherWebsite(mixed &$params): void
    {
        $pingback = $params['pingback'];
        $targetLink = $params['params'][0];
        $sourceLink = $params['params'][1];

        /**
         * Pingback add to the Timestamp because its need an unique identifier
         */
        $pseudotag = '#%$PINGBACK-' . time() . '$%#';
        $sourceContent = file_get_contents($sourceLink);
        $sourceContent = preg_replace(
            '@<a [^>]*href="' . preg_quote((string) $targetLink, '@') . '"[^>]*>@',
            $pseudotag,
            $sourceContent,
            100,
            $count
        );
        /**
         * @todo other inputfile
         */

        file_put_contents('sample.php', $sourceContent);

        $tagLessData = '';

        $handle = @fopen('sample.php', 'r');
        if ($handle) {
            while (! feof($handle)) {
                // @todo could new behaviour because fgetss was removed
                $tagLessData .= fgets($handle, 4096);
            }

            fclose($handle);
        }

        /**
         * Replace the '#%$PINGBACK- with an empty String, because we dont want it in the Output
         */
        $tagLessData = preg_replace('#\s+#', ' ', $tagLessData);
        $pos = strpos($tagLessData, $pseudotag);

        if ($pos !== false) {
            $informationString = str_replace($pseudotag, '', $tagLessData);

            $informationString = '...' . trim(substr($informationString, $pos - 100, 200)) . '...';

            $pingback->setSerializedInformation($informationString);
        } else {
            $error = GeneralUtility::makeInstance(PingbackController::class);

            $pingback->addValidationError($error->return_xmlrpc_error(
                '33',
                LocalizationUtility::translate('tx_pongback_domain_model_pingback.error_noInformations'),
                'pongback'
            ));
        }
    }
}
