<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Settings\Settings;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use Webicorns\Pongback\Configuration\PongbackSettings;

final class PongbackSettingsTest extends TestCase
{
    private const SETTINGS_DEFINITIONS = __DIR__ . '/../../../Configuration/Sets/Pongback/settings.definitions.yaml';

    #[Test]
    public function fallbackDefaultsMatchTheSiteSetDefinitions(): void
    {
        $definitions = Yaml::parseFile(self::SETTINGS_DEFINITIONS)['settings'];
        $pongbackDefinitions = array_filter(
            $definitions,
            static fn(string $key): bool => str_starts_with($key, 'pongback.'),
            ARRAY_FILTER_USE_KEY,
        );

        self::assertSame(
            PongbackSettings::DEFAULTS,
            array_map(static fn(array $definition): mixed => $definition['default'], $pongbackDefinitions),
        );
    }

    #[Test]
    public function everySettingHasACategoryOfThisSet(): void
    {
        $content = Yaml::parseFile(self::SETTINGS_DEFINITIONS);

        foreach ($content['settings'] as $key => $definition) {
            self::assertArrayHasKey($definition['category'] ?? '', $content['categories'], 'Category of ' . $key);
        }
    }

    #[Test]
    public function sitesWithoutTheSetUseTheDefaults(): void
    {
        $settings = PongbackSettings::fromSite(null);

        self::assertTrue($settings->discoveryHttpHeader);
        self::assertTrue($settings->discoveryMetaTag);
        self::assertFalse($settings->receiveAutoPublish);
        self::assertSame(20, $settings->receiveRateLimit);
        self::assertSame(['pages', 'tt_content'], $settings->sendTables);
        self::assertSame('', $settings->notificationEmailTo);
        self::assertSame('', $settings->notificationEmailFrom);
    }

    #[Test]
    public function normalizesConfiguredValues(): void
    {
        $site = $this->createMock(Site::class);
        // Same structure as created by TYPO3's SiteSettingsFactory: flat identifiers, lists stay arrays.
        $site->method('getSettings')->willReturn(SiteSettings::create(new Settings([
            'pongback.discovery.httpHeader' => 0,
            'pongback.discovery.metaTag' => 1,
            'pongback.receive.autoPublish' => '1',
            'pongback.receive.rateLimit' => '-5',
            'pongback.send.tables' => [' pages ', '', 'tx_news_domain_model_news'],
            'pongback.notification.emailTo' => ' editor@example.com ',
            'pongback.notification.emailFrom' => "\tnoreply@example.com\n",
        ])));

        $settings = PongbackSettings::fromSite($site);

        self::assertFalse($settings->discoveryHttpHeader);
        self::assertTrue($settings->discoveryMetaTag);
        self::assertTrue($settings->receiveAutoPublish);
        self::assertSame(0, $settings->receiveRateLimit);
        self::assertSame(['pages', 'tx_news_domain_model_news'], $settings->sendTables);
        self::assertSame('editor@example.com', $settings->notificationEmailTo);
        self::assertSame('noreply@example.com', $settings->notificationEmailFrom);
    }

    #[Test]
    public function usesConfiguredRateLimits(): void
    {
        $site = $this->createMock(Site::class);
        $site->method('getSettings')->willReturn(SiteSettings::createFromSettingsTree(['pongback' => ['receive' => ['rateLimit' => 5]]]));

        self::assertSame(5, PongbackSettings::fromSite($site)->receiveRateLimit);
    }
}
