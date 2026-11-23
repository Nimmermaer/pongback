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
use Webicorns\Pongback\Tests\Unit\Fixtures\SetSettings;

final class PongbackSettingsTest extends TestCase
{
    #[Test]
    public function knowsExactlyTheSettingsOfTheSet(): void
    {
        $definitions = Yaml::parseFile(SetSettings::DEFINITIONS)['settings'];
        $pongbackKeys = array_values(array_filter(array_keys($definitions), static fn(string $key): bool => str_starts_with($key, 'pongback.')));

        self::assertEqualsCanonicalizing($pongbackKeys, PongbackSettings::KEYS);
    }

    #[Test]
    public function everySettingHasACategoryOfThisSet(): void
    {
        $content = Yaml::parseFile(SetSettings::DEFINITIONS);

        foreach ($content['settings'] as $key => $definition) {
            self::assertArrayHasKey($definition['category'] ?? '', $content['categories'], 'Category of ' . $key);
        }
    }

    #[Test]
    public function readsTheValuesOfTheSet(): void
    {
        $defaults = SetSettings::defaults();

        $settings = PongbackSettings::fromSite($this->site(SetSettings::create()));

        self::assertTrue($settings->enabled);
        self::assertSame($defaults['pongback.discovery.httpHeader'], $settings->discoveryHttpHeader);
        self::assertSame($defaults['pongback.discovery.metaTag'], $settings->discoveryMetaTag);
        self::assertSame($defaults['pongback.receive.autoPublish'], $settings->receiveAutoPublish);
        self::assertSame($defaults['pongback.receive.rateLimit'], $settings->receiveRateLimit);
        self::assertSame($defaults['pongback.receive.webmention'], $settings->receiveWebmention);
        self::assertSame($defaults['pongback.send.enabled'], $settings->sendEnabled);
        self::assertSame($defaults['pongback.send.webmention'], $settings->sendWebmention);
        self::assertSame($defaults['pongback.send.tables'], $settings->sendTables);
        self::assertSame($defaults['pongback.notification.emailTo'], $settings->notificationEmailTo);
        self::assertSame($defaults['pongback.notification.emailFrom'], $settings->notificationEmailFrom);
    }

    #[Test]
    public function readsConfiguredValues(): void
    {
        $settings = PongbackSettings::fromSite($this->site(SetSettings::create([
            'pongback.discovery.httpHeader' => false,
            'pongback.discovery.metaTag' => false,
            'pongback.receive.autoPublish' => true,
            'pongback.receive.rateLimit' => 5,
            'pongback.receive.webmention' => false,
            'pongback.send.enabled' => false,
            'pongback.send.webmention' => false,
            'pongback.send.tables' => ['pages', 'tx_news_domain_model_news'],
            'pongback.notification.emailTo' => 'editor@example.com',
            'pongback.notification.emailFrom' => 'noreply@example.com',
        ])));

        self::assertFalse($settings->discoveryHttpHeader);
        self::assertFalse($settings->discoveryMetaTag);
        self::assertTrue($settings->receiveAutoPublish);
        self::assertSame(5, $settings->receiveRateLimit);
        self::assertFalse($settings->receiveWebmention);
        self::assertFalse($settings->sendEnabled);
        self::assertFalse($settings->sendWebmention);
        self::assertSame(['pages', 'tx_news_domain_model_news'], $settings->sendTables);
        self::assertSame('editor@example.com', $settings->notificationEmailTo);
        self::assertSame('noreply@example.com', $settings->notificationEmailFrom);
    }

    #[Test]
    public function normalizesWhatTheTypesAllow(): void
    {
        $settings = PongbackSettings::fromSite($this->site(SetSettings::create([
            'pongback.receive.rateLimit' => -5,
            'pongback.send.tables' => ['', ' pages '],
            'pongback.notification.emailTo' => ' editor@example.com ',
            'pongback.notification.emailFrom' => "\tnoreply@example.com\n",
        ])));

        self::assertSame(0, $settings->receiveRateLimit);
        self::assertSame(['pages'], $settings->sendTables);
        self::assertSame('editor@example.com', $settings->notificationEmailTo);
        self::assertSame('noreply@example.com', $settings->notificationEmailFrom);
    }

    #[Test]
    public function sitesWithoutTheSetGetNoFeatures(): void
    {
        $withoutSet = $this->site(SiteSettings::create(new Settings(['other.setting' => 1])));
        $incomplete = $this->site(SiteSettings::create(new Settings(array_diff_key(SetSettings::defaults(), ['pongback.send.tables' => true]))));

        foreach ([PongbackSettings::fromSite($withoutSet), PongbackSettings::fromSite($incomplete), PongbackSettings::fromSite(null)] as $settings) {
            self::assertEquals(PongbackSettings::disabled(), $settings);
            self::assertFalse($settings->enabled);
            self::assertFalse($settings->discoveryHttpHeader);
            self::assertFalse($settings->discoveryMetaTag);
            self::assertFalse($settings->receiveAutoPublish);
            self::assertSame(0, $settings->receiveRateLimit);
            self::assertFalse($settings->receiveWebmention);
            self::assertFalse($settings->sendEnabled);
            self::assertFalse($settings->sendWebmention);
            self::assertSame([], $settings->sendTables);
            self::assertSame('', $settings->notificationEmailTo);
            self::assertSame('', $settings->notificationEmailFrom);
        }
    }

    private function site(SiteSettings $settings): Site
    {
        $site = $this->createMock(Site::class);
        $site->method('getSettings')->willReturn($settings);

        return $site;
    }
}
