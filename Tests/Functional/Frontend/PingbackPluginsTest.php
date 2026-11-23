<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Functional\Frontend;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use Webicorns\Pongback\Tests\Functional\AbstractPongbackTestCase;

/**
 * "List": approved Pingbacks of the current page, newest first, one per page (settings.listItem = 1).
 * "Latest": approved Pingbacks of the whole site.
 */
final class PingbackPluginsTest extends AbstractPongbackTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pingbacks.csv');
        $this->setUpFrontendRootPage(1, ['EXT:pongback/Tests/Functional/Frontend/Fixtures/plugins.typoscript']);
    }

    #[Test]
    public function listShowsTheApprovedPingbacksOfThePageNewestFirst(): void
    {
        $list = $this->section('list', $this->render('https://main.example/article'));

        self::assertStringContainsString('Newer post', $list);
        self::assertStringNotContainsString('Older post', $list, 'On the second page');
        self::assertStringNotContainsString('Pending post', $list);
        self::assertStringNotContainsString('Home post', $list);
        self::assertStringNotContainsString('Deleted post', $list);
        self::assertStringContainsString('href="https://blog.example/newer"', $list);
        self::assertStringContainsString('<time datetime="1970-01-01T00:03:20+00:00">', $list);
    }

    #[Test]
    public function listIsPaginated(): void
    {
        $list = $this->section('list', $this->render('https://main.example/article'));
        self::assertMatchesRegularExpression('#<a class="page-link" href="([^"]+)">2</a>#', $list);
        preg_match('#<a class="page-link" href="([^"]+)">2</a>#', $list, $link);

        $secondPage = $this->section('list', $this->render('https://main.example' . html_entity_decode($link[1])));

        self::assertStringContainsString('Older post', $secondPage);
        self::assertStringNotContainsString('Newer post', $secondPage);
    }

    #[Test]
    public function latestShowsTheApprovedPingbacksOfTheSite(): void
    {
        $latest = $this->section('latest', $this->render('https://main.example/article'));

        self::assertMatchesRegularExpression('#Home post.*Newer post.*Older post#s', $latest);
        self::assertStringNotContainsString('Other site post', $latest);
        self::assertStringNotContainsString('Pending post', $latest);
    }

    private function render(string $url): string
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest($url));
        self::assertSame(200, $response->getStatusCode());

        return (string) $response->getBody();
    }

    private function section(string $id, string $html): string
    {
        self::assertMatchesRegularExpression('#<section id="' . $id . '">(.*?)</section>#s', $html);
        preg_match('#<section id="' . $id . '">(.*?)</section>#s', $html, $match);

        return $match[1];
    }
}
