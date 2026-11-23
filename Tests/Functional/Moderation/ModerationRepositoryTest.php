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
use Webicorns\Pongback\Moderation\ModerationRepository;

final class ModerationRepositoryTest extends ModerationTestCase
{
    #[Test]
    public function listsThePageTreePendingFirstThenNewestFirst(): void
    {
        $admin = $this->loginAs(1);
        $repository = $this->get(ModerationRepository::class);

        self::assertSame([3, 1, 2], $this->uids($repository->findOnPageTree(1, $admin)));
        self::assertSame([3, 1, 2], $this->uids($repository->findOnPageTree(2, $admin)));
        self::assertSame([3], $this->uids($repository->findOnPageTree(4, $admin)));
        self::assertSame([4], $this->uids($repository->findOnPageTree(10, $admin)));
    }

    #[Test]
    public function returnsWhatTheModuleAndWidgetShow(): void
    {
        $row = $this->get(ModerationRepository::class)->findOnPageTree(2, $this->loginAs(1))[2];

        self::assertSame(2, (int) $row['uid']);
        self::assertSame(2, (int) $row['pid']);
        self::assertSame(0, (int) $row['hidden']);
        self::assertSame(200, (int) $row['crdate']);
        self::assertSame('https://www.blog.example/approved', $row['source_link']);
        self::assertSame('Approved post', $row['source_title']);
        self::assertSame('https://main.example/article', $row['target_link']);
        self::assertSame('Article', $row['page_title']);
    }

    #[Test]
    public function editorsOnlySeePingbacksOnTheirPages(): void
    {
        $editor = $this->loginAs(2);
        $repository = $this->get(ModerationRepository::class);

        self::assertSame([3, 2, 1], $this->uids($repository->findLatest(10, $editor)));
        self::assertSame([], $repository->findOnPageTree(10, $editor));
        self::assertSame(2, $repository->countPending($editor));
    }

    #[Test]
    public function adminsSeeAllSites(): void
    {
        $admin = $this->loginAs(1);
        $repository = $this->get(ModerationRepository::class);

        self::assertSame([4, 3, 2, 1], $this->uids($repository->findLatest(10, $admin)));
        self::assertSame([4, 3], $this->uids($repository->findLatest(2, $admin)));
        self::assertSame(2, $repository->countPending($admin));
    }

    #[Test]
    public function listsSendingProblemsOfThePageTree(): void
    {
        $repository = $this->get(ModerationRepository::class);
        $admin = $this->loginAs(1);

        self::assertSame([2, 1], $this->uids($repository->findSendingProblemsOnPageTree(1, $admin)), 'Rejected and failed, newest first');
        self::assertSame([2], $this->uids($repository->findSendingProblemsOnPageTree(4, $admin)));
        self::assertSame(3, $repository->countSendingProblems($admin));

        $row = $repository->findSendingProblemsOnPageTree(2, $admin)[1];
        self::assertSame(
            ['https://down.example/post', 'webmention', 'failed', 'HTTP 503', 3, 'Article'],
            [$row['target_link'], $row['protocol'], $row['status'], $row['message'], (int) $row['attempts'], $row['page_title']],
        );
    }

    #[Test]
    public function editorsOnlySeeSendingProblemsOfTheirPages(): void
    {
        $repository = $this->get(ModerationRepository::class);
        $editor = $this->loginAs(2);

        self::assertSame(2, $repository->countSendingProblems($editor));
        self::assertSame([], $repository->findSendingProblemsOnPageTree(10, $editor));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int>
     */
    private function uids(array $rows): array
    {
        return array_map(static fn(array $row): int => (int) $row['uid'], $rows);
    }
}
