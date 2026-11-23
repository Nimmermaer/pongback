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
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use Webicorns\Pongback\Sending\SendPingbacksMessage;
use Webicorns\Pongback\Tests\Functional\AbstractPongbackTestCase;
use Webicorns\Pongback\Webmention\ReceiveWebmentionMessage;

/**
 * Documented alternative to a worker: routing the messages to the synchronous transport "default".
 *
 * There is no network in the test: the handler cannot retrieve the page and logs a warning,
 * which shows that it ran while saving.
 */
final class WithoutWorkerTest extends AbstractPongbackTestCase
{
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'messenger' => [
                'routing' => [
                    SendPingbacksMessage::class => 'default',
                    ReceiveWebmentionMessage::class => 'default',
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function savingAPageSendsRightAwayInsteadOfQueueing(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['pages' => [2 => ['title' => 'Changed']]], []);
        $dataHandler->process_datamap();

        self::assertSame(0, $this->getConnectionPool()->getConnectionForTable('sys_messenger_messages')->count('*', 'sys_messenger_messages', []));
        self::assertStringContainsString('Page https://main.example/article', $this->logFileContents(), 'The handler ran while saving');
    }

    #[Test]
    public function webmentionsAreVerifiedRightAway(): void
    {
        $body = new Stream('php://temp', 'rw');
        $body->write(http_build_query(['source' => 'https://blog.example/post', 'target' => 'https://main.example/article']));
        $body->rewind();

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('https://main.example/webmention'))
                ->withMethod('POST')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody($body),
        );

        self::assertSame(202, $response->getStatusCode());
        self::assertSame(0, $this->getConnectionPool()->getConnectionForTable('sys_messenger_messages')->count('*', 'sys_messenger_messages', []), 'Nothing waits for a worker');
    }

    private function logFileContents(): string
    {
        $contents = '';
        foreach (glob(Environment::getVarPath() . '/log/*.log') ?: [] as $file) {
            $contents .= (string) file_get_contents($file);
        }

        return $contents;
    }
}
