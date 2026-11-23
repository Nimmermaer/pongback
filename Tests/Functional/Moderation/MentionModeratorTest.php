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
use Webicorns\Pongback\Moderation\MentionModerator;

final class MentionModeratorTest extends ModerationTestCase
{
    #[Test]
    public function approvesHidesAndDeletes(): void
    {
        $this->loginAs(2);
        $moderator = $this->get(MentionModerator::class);

        self::assertSame([], $moderator->moderate(MentionModerator::APPROVE, 1));
        self::assertSame(0, (int) $this->pingback(1)['hidden']);

        self::assertSame([], $moderator->moderate(MentionModerator::HIDE, 2));
        self::assertSame(1, (int) $this->pingback(2)['hidden']);

        self::assertSame([], $moderator->moderate(MentionModerator::DELETE, 3));
        self::assertSame(1, (int) $this->pingback(3)['deleted']);
    }

    #[Test]
    public function editorsCannotModeratePingbacksOnOtherPages(): void
    {
        $this->loginAs(2);

        self::assertNotSame([], $this->get(MentionModerator::class)->moderate(MentionModerator::HIDE, 4));
        self::assertSame(0, (int) $this->pingback(4)['hidden']);
    }
}
