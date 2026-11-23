<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Functional\Moderation;

use PHPUnit\Framework\Attributes\Test;
use Webicorns\Pongback\Moderation\PingbackModerator;

final class PingbackModeratorTest extends ModerationTestCase
{
    #[Test]
    public function approvesHidesAndDeletes(): void
    {
        $this->loginAs(2);
        $moderator = $this->get(PingbackModerator::class);

        self::assertSame([], $moderator->moderate(PingbackModerator::APPROVE, 1));
        self::assertSame(0, (int) $this->pingback(1)['hidden']);

        self::assertSame([], $moderator->moderate(PingbackModerator::HIDE, 2));
        self::assertSame(1, (int) $this->pingback(2)['hidden']);

        self::assertSame([], $moderator->moderate(PingbackModerator::DELETE, 3));
        self::assertSame(1, (int) $this->pingback(3)['deleted']);
    }

    #[Test]
    public function editorsCannotModeratePingbacksOnOtherPages(): void
    {
        $this->loginAs(2);

        self::assertNotSame([], $this->get(PingbackModerator::class)->moderate(PingbackModerator::HIDE, 4));
        self::assertSame(0, (int) $this->pingback(4)['hidden']);
    }
}
