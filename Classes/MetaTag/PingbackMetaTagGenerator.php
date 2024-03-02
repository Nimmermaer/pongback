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

namespace Webicorns\Pongback\MetaTag;

use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

final readonly class PingbackMetaTagGenerator
{
    private TypoScriptFrontendController $typoScriptFrontendController;

    public function __construct(TypoScriptFrontendController $typoScriptFrontendController = null)
    {
        $this->typoScriptFrontendController = $typoScriptFrontendController ?? $this->getTypoScriptFrontendController();
    }

    public function generate(array $params): string
    {
        /** @var ServerRequest $request */
        $request = $params['request'];
        /** @var Site $site */
        $site = $request->getAttribute('site');
        $pingbackHeader = (string) $site->getRouter()
            ->generateUri($site->getRootPageId(), [
                'type' => 1_709_405_325_110,
            ]);
        $url = '<link rel="pingback" href="' . $pingbackHeader . '">';
        $this->typoScriptFrontendController->additionalHeaderData[] = $url;
        return $url;
    }

    private function getTypoScriptFrontendController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }
}
