<?php

namespace Webicorns\Pongback\Widget;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use Webicorns\Pongback\Domain\Repository\PingbackRepository;

class PongbackWidget implements WidgetInterface, RequestAwareWidgetInterface
{
    private ServerRequestInterface $request;

    public function __construct(
        private readonly BackendViewFactory $backendViewFactory,
        private readonly PingbackRepository $pingbackRepository,
        private readonly WidgetConfigurationInterface $configuration,
        private readonly ?ButtonProviderInterface $buttonProvider = null,
        private readonly array $options = [],
    ) {
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function renderWidgetContent(): string
    {
        $view = $this->backendViewFactory->create($this->request);
        $view->assignMultiple([
            'button' => $this->buttonProvider,
            'items' => $this->getLastPingbackItems(),
            'options' => $this->options,
            'configuration' => $this->configuration,
        ]);
        return $view->render('Widget/PongbackWidget');
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    private function getLastPingbackItems(): QueryResultInterface
    {
        return $this->pingbackRepository->findBy([
            'hidden' => 0,
        ], [
            'crdate' => QueryInterface::ORDER_ASCENDING,
        ], 5);
    }
}
