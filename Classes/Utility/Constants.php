<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Utility;

final class Constants
{
    public const PONGBACK_CACHE = 'tx_pongback_cache';

    /**
     * Page type ("typeNum") of the Pingback XML-RPC endpoint.
     * Must match Configuration/TypoScript/setup.typoscript and Configuration/Site/config.yaml.
     */
    public const PINGBACK_PAGE_TYPE = 1709405325110;
}
