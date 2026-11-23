<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Moderation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Webicorns\Pongback\Moderation\MentionModerator;

final class MentionModeratorTest extends TestCase
{
    /**
     * @return array<string, array{string, array<string, mixed>, array<string, mixed>}>
     */
    public static function operationProvider(): array
    {
        $table = 'tx_pongback_domain_model_pingback';

        return [
            'approve' => [MentionModerator::APPROVE, [$table => [7 => ['hidden' => 0]]], []],
            'hide' => [MentionModerator::HIDE, [$table => [7 => ['hidden' => 1]]], []],
            'delete' => [MentionModerator::DELETE, [], [$table => [7 => ['delete' => 1]]]],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $commands
     */
    #[Test]
    #[DataProvider('operationProvider')]
    public function letsTheDataHandlerDoTheWork(string $operation, array $data, array $commands): void
    {
        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->expects(self::once())->method('start')->with($data, $commands);
        $dataHandler->expects(self::once())->method('process_datamap');
        $dataHandler->expects(self::once())->method('process_cmdmap');
        $dataHandler->errorLog = ['No permission'];

        self::assertSame(['No permission'], $this->createSubject($dataHandler)->moderate($operation, 7));
    }

    #[Test]
    public function rejectsUnknownOperations(): void
    {
        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->expects(self::never())->method('start');

        $this->expectExceptionCode(1732600001);
        $this->expectExceptionMessage('Unknown operation "publish"');
        $this->createSubject($dataHandler)->moderate('publish', 7);
    }

    #[Test]
    public function knowsAllOperations(): void
    {
        self::assertSame(['approve', 'hide', 'delete'], MentionModerator::OPERATIONS);
    }

    private function createSubject(DataHandler $dataHandler): MentionModerator
    {
        return new class ($dataHandler) extends MentionModerator {
            public function __construct(private readonly DataHandler $dataHandler) {}

            protected function createDataHandler(): DataHandler
            {
                return $this->dataHandler;
            }
        };
    }
}
