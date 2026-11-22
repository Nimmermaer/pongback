<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Functional;

use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Two sites: "main" (https://main.example/, root page 1) with the Pongback set and "other" (https://other.example/, root page 10).
 */
abstract class AbstractPongbackTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['webhooks'];

    protected array $testExtensionsToLoad = ['webicorns/pongback'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->writeSite('main', 1, 'https://main.example/', ['webicorns/pongback']);
        $this->writeSite('other', 10, 'https://other.example/', []);
    }

    /**
     * @param list<string> $dependencies
     */
    protected function writeSite(string $identifier, int $rootPageId, string $base, array $dependencies): void
    {
        $this->get(SiteWriter::class)->write($identifier, [
            'rootPageId' => $rootPageId,
            'base' => $base,
            'dependencies' => $dependencies,
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'navigationTitle' => 'English',
                    'locale' => 'en_US.UTF-8',
                    'base' => '/',
                    'enabled' => true,
                    'flag' => 'us',
                ],
            ],
        ]);
    }
}
