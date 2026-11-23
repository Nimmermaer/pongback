<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Functional\Mention;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webicorns\Pongback\Domain\Model\Pingback;
use Webicorns\Pongback\Mention\MentionStorage;
use Webicorns\Pongback\Tests\Functional\AbstractPongbackTestCase;
use Webicorns\Pongback\Utility\Constants;

final class MentionStorageTest extends AbstractPongbackTestCase
{
    #[Test]
    public function publishedPingbacksFlushThePagesShowingPingbacks(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects(self::once())->method('flushCachesInGroupByTags')->with('pages', [Constants::PINGBACK_TABLE]);

        $this->storage($cacheManager)->add($this->pingback(hidden: false), 0);
    }

    #[Test]
    public function pendingPingbacksChangeNoPage(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects(self::never())->method('flushCachesInGroupByTags');

        $uid = $this->storage($cacheManager)->add($this->pingback(hidden: true), 1);

        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(Constants::PINGBACK_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('*')->from(Constants::PINGBACK_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->executeQuery()->fetchAssociative();
        self::assertIsArray($row);
        self::assertSame([2, 1, 1], [(int) $row['pid'], (int) $row['hidden'], (int) $row['sys_language_uid']]);
    }

    #[Test]
    public function findsUpdatesAndRemovesMentions(): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects(self::exactly(2))->method('flushCachesInGroupByTags')->with('pages', [Constants::PINGBACK_TABLE]);
        $storage = $this->storage($cacheManager);
        $pingback = $this->pingback(hidden: true);
        $pingback->setProtocol('webmention');
        $uid = $storage->add($pingback, 0);

        self::assertSame($uid, $storage->find('https://blog.example/post', 'https://main.example/article'));
        self::assertNull($storage->find('https://blog.example/post', 'https://main.example/other'));

        $storage->update($uid, 'New title', 'New excerpt');
        $row = $this->row($uid);
        self::assertSame(['New title', 'New excerpt', 1, 'webmention'], [$row['source_title'], $row['excerpt'], (int) $row['hidden'], $row['protocol']]);

        $storage->remove($uid);
        self::assertSame(1, (int) $this->row($uid)['deleted']);
        self::assertNull($storage->find('https://blog.example/post', 'https://main.example/article'));
        self::assertFalse($storage->exists('https://blog.example/post', 'https://main.example/article'));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $uid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(Constants::PINGBACK_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('*')->from(Constants::PINGBACK_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->executeQuery()->fetchAssociative();
        self::assertIsArray($row);

        return $row;
    }

    private function storage(CacheManager $cacheManager): MentionStorage
    {
        return new MentionStorage($this->get(ConnectionPool::class), $cacheManager);
    }

    private function pingback(bool $hidden): Pingback
    {
        $pingback = new Pingback();
        $pingback->setPid(2);
        $pingback->setHidden($hidden);
        $pingback->setSourceLink('https://blog.example/post');
        $pingback->setTargetLink('https://main.example/article');

        return $pingback;
    }
}
