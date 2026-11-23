<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Listener;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationLoadedEvent;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use Webicorns\Pongback\Listener\EnhanceSiteConfigurationListener;
use Webicorns\Pongback\Utility\Constants;

final class EnhanceSiteConfigurationListenerTest extends TestCase
{
    private const SET_ROUTE_ENHANCERS = __DIR__ . '/../../../Configuration/Sets/Pongback/route-enhancers.yaml';

    #[Test]
    public function setRouteEnhancersFileHasTheStructureRequiredByTypo3v14(): void
    {
        $content = Yaml::parseFile(self::SET_ROUTE_ENHANCERS);

        // TYPO3 v14 rejects files without "routeEnhancers" key or with additional keys
        self::assertSame(['routeEnhancers'], array_keys($content));
        self::assertSame(
            Constants::PINGBACK_PAGE_TYPE,
            $content['routeEnhancers']['PageTypeSuffix']['map']['pingback.ping'],
        );
    }

    #[Test]
    public function addsPingbackPageTypeToSitesWithoutRouteEnhancers(): void
    {
        $event = new SiteConfigurationLoadedEvent('main', ['base' => '/']);

        $this->createSubject()($event);

        self::assertSame(
            ['pingback.ping' => Constants::PINGBACK_PAGE_TYPE, 'webmention' => Constants::WEBMENTION_PAGE_TYPE],
            $event->getConfiguration()['routeEnhancers']['PageTypeSuffix']['map'],
        );
        self::assertSame('/', $event->getConfiguration()['base']);
    }

    #[Test]
    public function siteConfigurationTakesPrecedenceAndKeepsOwnMappings(): void
    {
        $event = new SiteConfigurationLoadedEvent('main', [
            'routeEnhancers' => [
                'PageTypeSuffix' => [
                    'type' => 'PageType',
                    'default' => '.html',
                    'map' => [
                        'feed.xml' => 9818,
                        'pingback.ping' => 42,
                    ],
                ],
            ],
        ]);

        $this->createSubject()($event);

        self::assertSame(
            [
                'type' => 'PageType',
                'map' => [
                    'pingback.ping' => 42,
                    'webmention' => Constants::WEBMENTION_PAGE_TYPE,
                    'feed.xml' => 9818,
                ],
                'default' => '.html',
            ],
            $event->getConfiguration()['routeEnhancers']['PageTypeSuffix'],
        );
    }

    private function createSubject(bool $enabled = true): EnhanceSiteConfigurationListener
    {
        $yamlFileLoader = $this->createMock(YamlFileLoader::class);
        $yamlFileLoader
            ->method('load')
            ->with(EnhanceSiteConfigurationListener::ROUTE_ENHANCERS_FILE)
            ->willReturn(Yaml::parseFile(self::SET_ROUTE_ENHANCERS));

        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->with('pongback', 'includePongbackYaml')->willReturn($enabled);

        return new EnhanceSiteConfigurationListener($yamlFileLoader, $extensionConfiguration);
    }

    #[Test]
    public function leavesTheSiteConfigurationUntouchedIfDisabled(): void
    {
        $event = new SiteConfigurationLoadedEvent('main', ['base' => '/']);

        $this->createSubject(false)($event);

        self::assertSame(['base' => '/'], $event->getConfiguration());
    }

    #[Test]
    public function isEnabledAsLongAsTheExtensionConfigurationIsNotWritten(): void
    {
        $yamlFileLoader = $this->createMock(YamlFileLoader::class);
        $yamlFileLoader->method('load')->willReturn(Yaml::parseFile(self::SET_ROUTE_ENHANCERS));
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willThrowException(new ExtensionConfigurationPathDoesNotExistException('missing', 1732400200));
        $event = new SiteConfigurationLoadedEvent('main', []);

        (new EnhanceSiteConfigurationListener($yamlFileLoader, $extensionConfiguration))($event);

        self::assertArrayHasKey('PageTypeSuffix', $event->getConfiguration()['routeEnhancers']);
    }
}
