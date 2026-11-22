<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Fixtures;

use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\RejectionReason;

trait AssertRejectionTrait
{
    /**
     * @param callable(): mixed $callback
     */
    private static function assertRejected(RejectionReason $expected, callable $callback): void
    {
        try {
            $callback();
        } catch (MentionRejected $rejected) {
            self::assertSame($expected, $rejected->reason, $rejected->getMessage());
            return;
        }

        self::fail('Expected the mention to be rejected with reason "' . $expected->value . '".');
    }
}
