<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Functional\Sending;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webicorns\Pongback\Sending\SendPingbacksMessage;
use Webicorns\Pongback\Tests\Functional\AbstractPongbackTestCase;

/**
 * Saving in the backend queues one SendPingbacksMessage per changed page in the real Messenger queue.
 */
final class PageChangeHookTest extends AbstractPongbackTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function changedContentQueuesItsPage(): void
    {
        $this->process(['tt_content' => [1 => ['bodytext' => '<p>Changed</p>']]]);

        self::assertEquals([new SendPingbacksMessage(2, 0)], $this->queuedMessages());
    }

    #[Test]
    public function newContentQueuesItsPage(): void
    {
        $this->process(['tt_content' => ['NEW1' => ['pid' => 2, 'CType' => 'text', 'header' => 'New']]]);

        self::assertEquals([new SendPingbacksMessage(2, 0)], $this->queuedMessages());
    }

    #[Test]
    public function changedPagesAreQueued(): void
    {
        $this->process(['pages' => [2 => ['title' => 'Changed']]]);

        self::assertEquals([new SendPingbacksMessage(2, 0)], $this->queuedMessages());
    }

    #[Test]
    public function queuesEveryPageOnlyOncePerSave(): void
    {
        $this->process([
            'pages' => [2 => ['title' => 'Changed']],
            'tt_content' => [
                1 => ['header' => 'Changed'],
                2 => ['header' => 'Changed'],
            ],
        ]);

        self::assertEquals([new SendPingbacksMessage(2, 0)], $this->queuedMessages());
    }

    #[Test]
    public function ignoresSitesWithoutTheSet(): void
    {
        $this->process(['tt_content' => [3 => ['header' => 'Changed']]]);

        self::assertSame([], $this->queuedMessages());
    }

    #[Test]
    public function ignoresTablesThatAreNotConfigured(): void
    {
        $this->writeSiteSettings('main', ['pongback' => ['send' => ['tables' => ['pages']]]]);

        $this->process(['tt_content' => [1 => ['header' => 'Changed']]]);

        self::assertSame([], $this->queuedMessages());
    }

    #[Test]
    public function queuesNothingIfSendingIsDisabled(): void
    {
        $this->writeSiteSettings('main', ['pongback' => ['send' => ['enabled' => false]]]);

        $this->process(['pages' => [2 => ['title' => 'Changed']]]);

        self::assertSame([], $this->queuedMessages());
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $data
     */
    private function process(array $data): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog, 'DataHandler errors');
    }

    /**
     * @return list<object>
     */
    private function queuedMessages(): array
    {
        $rows = $this->getConnectionPool()->getConnectionForTable('sys_messenger_messages')
            ->select(['body', 'headers'], 'sys_messenger_messages', [], [], ['id' => 'ASC'])
            ->fetchAllAssociative();

        $serializer = new PhpSerializer();

        return array_map(
            static fn(array $row): object => $serializer->decode(['body' => $row['body']])->getMessage(),
            $rows,
        );
    }
}
