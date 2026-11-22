<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Mention;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Webicorns\Pongback\Mention\MentionRateLimiter;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Tests\Unit\Fixtures\AssertRejectionTrait;

final class MentionRateLimiterTest extends TestCase
{
    use AssertRejectionTrait;

    #[Test]
    public function rejectsClientsExceedingTheLimit(): void
    {
        $subject = new MentionRateLimiter(new InMemoryStorage());

        $subject->consume('203.0.113.5', 1);

        self::assertRejected(RejectionReason::AccessDenied, static fn() => $subject->consume('203.0.113.5', 1));
    }

    #[Test]
    public function countsPerClientIp(): void
    {
        $subject = new MentionRateLimiter(new InMemoryStorage());

        $subject->consume('203.0.113.5', 1);
        $subject->consume('203.0.113.6', 1);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function limitZeroDisablesTheLimit(): void
    {
        $subject = new MentionRateLimiter(new InMemoryStorage());

        for ($i = 0; $i < 5; $i++) {
            $subject->consume('203.0.113.5', 0);
        }

        // The disabled limit must not have consumed tokens of a later enabled limit.
        $subject->consume('203.0.113.5', 1);
        self::assertRejected(RejectionReason::AccessDenied, static fn() => $subject->consume('203.0.113.5', 1));
    }

    #[Test]
    public function requestsWithoutClientIpAreNotLimited(): void
    {
        $subject = new MentionRateLimiter(new InMemoryStorage());

        $subject->consume('', 1);
        $subject->consume('', 1);

        $this->addToAssertionCount(1);
    }
}
