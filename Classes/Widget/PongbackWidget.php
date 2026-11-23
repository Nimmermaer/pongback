<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Widget;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use Webicorns\Pongback\Moderation\ModerationRepository;

/**
 * Dashboard widget "Latest Pingbacks": newest first, with the linking website, pending ones marked.
 * Shows only Pingbacks on pages the user may see.
 */
class PongbackWidget implements WidgetInterface, RequestAwareWidgetInterface
{
    public const ITEMS = 5;

    private ?ServerRequestInterface $request = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly BackendViewFactory $backendViewFactory,
        private readonly ModerationRepository $repository,
        private readonly WidgetConfigurationInterface $configuration,
        private readonly ModuleButtonProvider $buttonProvider,
        private readonly array $options = [],
    ) {}

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function renderWidgetContent(): string
    {
        $user = $this->getBackendUser();
        $view = $this->backendViewFactory->create($this->request, ['typo3/cms-dashboard', 'webicorns/pongback']);
        $view->assignMultiple([
            'items' => array_map(self::withSourceHost(...), $this->repository->findLatest(self::ITEMS, $user)),
            'pending' => $this->repository->countPending($user),
            'button' => $this->buttonProvider,
            'options' => $this->options,
            'configuration' => $this->configuration,
        ]);

        return $view->render('Widget/PongbackWidget');
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function withSourceHost(array $row): array
    {
        $host = (string) parse_url((string) ($row['source_link'] ?? ''), PHP_URL_HOST);
        $row['source_host'] = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        return $row;
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
