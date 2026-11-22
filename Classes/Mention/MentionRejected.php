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
 * Thrown when a mention cannot be accepted. The message is sent back to the remote party,
 * so it must not contain internal details.
 */
final class MentionRejected extends \RuntimeException
{
    public function __construct(
        public readonly RejectionReason $reason,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 1732400000, $previous);
    }
}
