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
            ['pingback.ping' => Constants::PINGBACK_PAGE_TYPE],
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
                    'feed.xml' => 9818,
                ],
                'default' => '.html',
            ],
            $event->getConfiguration()['routeEnhancers']['PageTypeSuffix'],
        );
    }

    private function createSubject(): EnhanceSiteConfigurationListener
    {
        $yamlFileLoader = $this->createMock(YamlFileLoader::class);
        $yamlFileLoader
            ->method('load')
            ->with(EnhanceSiteConfigurationListener::ROUTE_ENHANCERS_FILE)
            ->willReturn(Yaml::parseFile(self::SET_ROUTE_ENHANCERS));

        return new EnhanceSiteConfigurationListener($yamlFileLoader);
    }
}
