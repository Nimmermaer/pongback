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

    public const PINGBACK_TABLE = 'tx_pongback_domain_model_pingback';

    /**
     * Length of the source_link/target_link columns (ext_tables.sql); longer URLs cannot be stored.
     */
    public const MAX_URL_LENGTH = 255;

    /**
     * Page type ("typeNum") of the Pingback XML-RPC endpoint.
     * Must match Configuration/Sets/Pongback/setup.typoscript and Configuration/Sets/Pongback/route-enhancers.yaml.
     */
    public const PINGBACK_PAGE_TYPE = 1709405325110;
}
