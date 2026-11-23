<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Http;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;

final class ClientIp
{
    /**
     * NormalizedParams respects [SYS][reverseProxyIP], unlike REMOTE_ADDR.
     */
    public static function of(ServerRequestInterface $request): string
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        if ($normalizedParams instanceof NormalizedParams) {
            return $normalizedParams->getRemoteAddress();
        }

        $remoteAddress = $request->getServerParams()['REMOTE_ADDR'] ?? '';

        return is_string($remoteAddress) ? $remoteAddress : '';
    }
}
