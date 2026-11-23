<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Mention;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webicorns\Pongback\Mention\Protocol;

final class ProtocolTest extends TestCase
{
    #[Test]
    public function storesValuesMatchingTheTcaItems(): void
    {
        defined('TYPO3') || define('TYPO3', 'BE');
        $tca = require __DIR__ . '/../../../Configuration/TCA/tx_pongback_domain_model_pingback.php';

        self::assertSame(
            array_column(Protocol::cases(), 'value'),
            array_column($tca['columns']['protocol']['config']['items'], 'value'),
        );
    }

    #[Test]
    public function hasALabel(): void
    {
        self::assertSame('Pingback', Protocol::Pingback->label());
        self::assertSame('Webmention', Protocol::Webmention->label());
    }
}
