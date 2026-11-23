<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Functional\Widget;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Dashboard\WidgetRegistry;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use Webicorns\Pongback\Moderation\ModerationRepository;
use Webicorns\Pongback\Tests\Functional\Moderation\ModerationTestCase;
use Webicorns\Pongback\Widget\ModuleButtonProvider;
use Webicorns\Pongback\Widget\PongbackWidget;

final class PongbackWidgetTest extends ModerationTestCase
{
    protected array $coreExtensionsToLoad = ['dashboard'];

    #[Test]
    public function isRegisteredInItsOwnGroup(): void
    {
        $this->loginAs(1);

        $widget = $this->get(WidgetRegistry::class)->getAllWidgets()['pongback'] ?? null;

        self::assertInstanceOf(WidgetConfigurationInterface::class, $widget);
        self::assertSame(['pongback'], $widget->getGroupNames());
    }

    #[Test]
    public function showsTheNewestPingbacksOfTheEditorsPagesWithTheirWebsite(): void
    {
        $this->loginAs(2);
        $widget = new PongbackWidget(
            $this->get(BackendViewFactory::class),
            $this->get(ModerationRepository::class),
            $this->createMock(WidgetConfigurationInterface::class),
            $this->get(ModuleButtonProvider::class),
        );
        $request = (new ServerRequest('https://main.example/typo3/module/dashboard'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams(['HTTP_HOST' => 'main.example', 'HTTPS' => 'on']));
        $GLOBALS['TYPO3_REQUEST'] = $request;
        $widget->setRequest($request);

        $html = $widget->renderWidgetContent();

        self::assertMatchesRegularExpression('#Subpage post.*Approved post.*Pending post#s', $html);
        self::assertStringNotContainsString('Other site post', $html);
        self::assertStringContainsString('blog.example → Article', $html);
        self::assertStringContainsString('2 Pingbacks are waiting for approval.', $html);
        self::assertStringContainsString('2 linked pages could not be notified', $html);
        self::assertSame(2, substr_count($html, 'badge-warning'));
        self::assertMatchesRegularExpression('#href="[^"]*/module/web/pongback\?token=[^"]*"[^>]*>\s*Moderate Pingbacks#', $html);
    }
}
