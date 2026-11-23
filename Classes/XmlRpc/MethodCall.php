<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\XmlRpc;

final readonly class MethodCall
{
    /**
     * @param list<mixed> $params
     */
    public function __construct(
        public string $methodName,
        public array $params,
    ) {}
}
