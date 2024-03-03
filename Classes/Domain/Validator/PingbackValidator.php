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

namespace Webicorns\Pongback\Domain\Validator;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webicorns\Pongback\Controller\PingbackController;
use Webicorns\Pongback\Domain\Model\Pingback;

class PingbackValidator
{
    public function validateTargetUri(mixed &$params): void
    {
        /** @var Pingback $pingback */
        $pingback = $params['pingback'];
        $targetLink = $params['params'][0];
        $sourceLink = $params['params'][1];
        $matchLink = $_SERVER['SERVER_NAME'];
        if (strpos((string) $targetLink, (string) $matchLink) > 0) {
            $pingback->sourceLink = $sourceLink;
            $pingback-> targetLink = $targetLink;
        } else {
            /** @var PingbackController $error */
            $error = GeneralUtility::makeInstance(PingbackController::class);

            $pingback->validationError[] = $error->xmlErrorResponse('32', 'Error');
        }
    }
}
