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
 * Outcome of MentionReceiverInterface::synchronize().
 */
enum SyncResult: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Removed = 'removed';
}
