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

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use Webicorns\Pongback\Tests\Functional\AbstractPongbackTestCase;

/**
 * Pages 1, 2 and 4 (subpage of 2) belong to the group of the editor (uid 2), the other site does not.
 */
abstract class ModerationTestCase extends AbstractPongbackTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/moderation.csv');

        $connection = $this->getConnectionPool()->getConnectionForTable('pages');
        $connection->update('pages', ['perms_groupid' => 1, 'perms_group' => 31, 'perms_everybody' => 0], ['pid' => 1]);
        $connection->update('pages', ['perms_groupid' => 1, 'perms_group' => 31, 'perms_everybody' => 0], ['uid' => 1]);
        $connection->update('pages', ['perms_groupid' => 1, 'perms_group' => 31, 'perms_everybody' => 0], ['uid' => 4]);
        $connection->update('pages', ['perms_groupid' => 0, 'perms_group' => 0, 'perms_everybody' => 0], ['pid' => 10]);
        $connection->update('pages', ['perms_groupid' => 0, 'perms_group' => 0, 'perms_everybody' => 0], ['uid' => 10]);
    }

    protected function loginAs(int $uid): BackendUserAuthentication
    {
        $user = $this->setUpBackendUser($uid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function pingback(int $uid): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_pongback_domain_model_pingback');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('*')
            ->from('tx_pongback_domain_model_pingback')
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->executeQuery()
            ->fetchAssociative();
        self::assertIsArray($row);

        return $row;
    }
}
