<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Mention;

/**
 * The protocol a mention was received or sent with. The value is stored in the database.
 */
enum Protocol: string
{
    case Pingback = 'pingback';
    case Webmention = 'webmention';

    public function label(): string
    {
        return match ($this) {
            self::Pingback => 'Pingback',
            self::Webmention => 'Webmention',
        };
    }
}
