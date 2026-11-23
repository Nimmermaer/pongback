<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Sending;

enum PingStatus: string
{
    /** The linked site registered the Pingback. */
    case Sent = 'sent';

    /** The linked site already knew the Pingback (fault 48). */
    case AlreadyRegistered = 'already_registered';

    /** The linked page announces no Pingback endpoint. */
    case NoEndpoint = 'no_endpoint';

    /** The linked site refused the Pingback for good (faults 16, 17, 32, 33, 49 or an invalid URL). */
    case Rejected = 'rejected';

    /** Temporary problem (network, server error, fault 0/50): worth another attempt. */
    case Failed = 'failed';

    /**
     * Final results are never sent again for the same source and target.
     */
    public function isFinal(): bool
    {
        return $this !== self::Failed;
    }
}
