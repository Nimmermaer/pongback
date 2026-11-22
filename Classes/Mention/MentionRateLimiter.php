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

use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * Uses TYPO3's CachingFrameworkStorage (see Configuration/Services.yaml).
 *
 * Limits the number of mentions a single client IP may send (sliding window per hour).
 */
readonly class MentionRateLimiter
{
    public function __construct(
        private StorageInterface $storage,
    ) {}

    /**
     * @throws MentionRejected
     */
    public function consume(string $clientIp, int $limitPerHour): void
    {
        if ($limitPerHour <= 0 || $clientIp === '') {
            return;
        }

        $factory = new RateLimiterFactory(
            [
                'id' => 'pongback-mention',
                'policy' => 'sliding_window',
                'limit' => $limitPerHour,
                'interval' => '1 hour',
            ],
            $this->storage,
        );

        if (! $factory->create($clientIp)->consume()->isAccepted()) {
            throw new MentionRejected(RejectionReason::AccessDenied, 'Too many requests, please try again later.');
        }
    }
}
