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
use Webicorns\Pongback\Mention\RejectionReason;

final class RejectionReasonTest extends TestCase
{
    #[Test]
    public function onlyNetworkAndServerProblemsAreTemporary(): void
    {
        $temporary = array_values(array_filter(RejectionReason::cases(), static fn(RejectionReason $reason): bool => $reason->isTemporary()));

        self::assertSame([RejectionReason::SourceNotFound, RejectionReason::UpstreamError], $temporary);
    }
}
