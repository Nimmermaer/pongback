<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Functional\Moderation;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use Webicorns\Pongback\Controller\Backend\ModerationController;

final class ModerationControllerTest extends ModerationTestCase
{
    #[Test]
    public function listsThePingbacksOfThePageTree(): void
    {
        $this->loginAs(2);

        $html = (string) $this->get(ModerationController::class)
            ->indexAction($this->request('/module/web/pongback', ['id' => 2, 'pingback' => 3]))
            ->getBody();

        self::assertMatchesRegularExpression('#Subpage post.*Pending post.*Approved post#s', $html);
        self::assertStringNotContainsString('Other site post', $html);
        self::assertStringNotContainsString('Deleted post', $html);
        self::assertSame(2, substr_count($html, '>Pingback</span>'));
        self::assertSame(1, substr_count($html, '>Webmention</span>'));
        self::assertStringContainsString('<tr class="table-active" id="pingback-3">', $html);
        self::assertSame(2, substr_count($html, 'value="approve"'));
        self::assertSame(1, substr_count($html, 'value="hide"'));
        self::assertSame(3, substr_count($html, 'value="delete"'));
        self::assertMatchesRegularExpression('#<form method="post" action="[^"]*/module/web/pongback/moderate\?token=#', $html);
    }

    #[Test]
    public function asksForAPageWithoutAccessibleSelection(): void
    {
        $this->loginAs(2);

        $html = (string) $this->get(ModerationController::class)
            ->indexAction($this->request('/module/web/pongback', ['id' => 11]))
            ->getBody();

        self::assertStringContainsString('Select a page in the page tree', $html);
        self::assertStringNotContainsString('Other site post', $html);
    }

    #[Test]
    public function moderatesAndRedirectsBackToTheList(): void
    {
        $this->loginAs(2);

        $response = $this->get(ModerationController::class)->moderateAction(
            $this->request('/module/web/pongback/moderate', [], ['id' => 2, 'pingback' => 1, 'operation' => 'approve']),
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('/module/web/pongback?token=', $response->getHeaderLine('Location'));
        self::assertStringContainsString('id=2', $response->getHeaderLine('Location'));
        self::assertSame(0, (int) $this->pingback(1)['hidden']);
        $messages = $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages();
        self::assertSame(ContextualFeedbackSeverity::OK, $messages[0]->getSeverity());
    }

    #[Test]
    public function reportsRefusedOperations(): void
    {
        $this->loginAs(2);

        $this->get(ModerationController::class)->moderateAction(
            $this->request('/module/web/pongback/moderate', [], ['id' => 2, 'pingback' => 4, 'operation' => 'hide']),
        );

        $messages = $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages();
        self::assertSame(ContextualFeedbackSeverity::ERROR, $messages[0]->getSeverity());
        self::assertSame(0, (int) $this->pingback(4)['hidden']);
    }

    #[Test]
    public function ignoresUnknownOperations(): void
    {
        $this->loginAs(2);

        $response = $this->get(ModerationController::class)->moderateAction(
            $this->request('/module/web/pongback/moderate', [], ['id' => 2, 'pingback' => 1, 'operation' => 'publish-everything']),
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(1, (int) $this->pingback(1)['hidden']);
        self::assertSame([], $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages());
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $body
     */
    private function request(string $path, array $query, ?array $body = null): ServerRequestInterface
    {
        $request = (new ServerRequest('https://main.example/typo3' . $path, $body === null ? 'GET' : 'POST'))
            ->withQueryParams($query)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams(['HTTP_HOST' => 'main.example', 'HTTPS' => 'on']));
        if ($body !== null) {
            $request = $request->withParsedBody($body);
        }

        $route = $this->get(Router::class)->match($path);
        $request = $request
            ->withAttribute('route', $route)
            ->withAttribute('module', $this->get(ModuleProvider::class)->getModule(ModerationController::MODULE));
        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $request;
    }
}
