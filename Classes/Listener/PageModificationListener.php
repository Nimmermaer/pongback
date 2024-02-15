<?php

namespace Webicorns\Pongback\Listener;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\MathUtility;
use Webicorns\Pongback\Message\PingbackMessage;

class PageModificationListener
{
    public function __construct(
        protected MessageBusInterface $bus,
        protected LoggerInterface $logger,
        protected SiteFinder $siteFinder,
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {
    }

    public function processDatamap_afterDatabaseOperations(
        $status,
        $table,
        $id,
        array $fieldArray,
        DataHandler $dataHandler
    ): void {
        if (! str_contains($this->getConfiguredTables(), (string) $table)) {
            return;
        }

        if (! MathUtility::canBeInterpretedAsInteger($id)) {
            $id = $dataHandler->substNEWwithIDs[$id];
        }

        try {
            $id = (int) $id;
            $site = $this->siteFinder->getSiteByPageId($id);
        } catch (SiteNotFoundException) {
            return;
        }

        if ($status === 'new') {
            $message = new PingbackMessage(
                action: 'new',
                uid: $id,
                record: $fieldArray,
                table: $table,
                url: (string) $site->getRouter()
                    ->generateUri($id),
                siteIdentifier: $site->getIdentifier(),
                author: $dataHandler->BE_USER,
            );
        } else {
            if (isset($fieldArray['hidden'])) {
                $action = $fieldArray['hidden'] ? 'unpublished' : 'published';
            } else {
                $action = 'modified';
            }

            $message = new PingbackMessage(
                action: $action,
                uid: $id,
                record: BackendUtility::getRecord('pages', $id),
                table: $table,
                url: (string) $site->getRouter()
                    ->generateUri($id),
                siteIdentifier: $site->getIdentifier(),
                author: $dataHandler->BE_USER,
                modifiedFields: $fieldArray
            );
        }

        $this->dispatchMessage($message);
    }

    protected function dispatchMessage(PingbackMessage $message): void
    {
        try {
            $this->bus->dispatch($message);
        } catch (\Throwable $throwable) {
            $this->logger->error($message::class . ': ' . $throwable->getMessage());
        }
    }

    private function getConfiguredTables(): string
    {
        return $this->extensionConfiguration->get('pongback')['tablesToListenToInTheEventOfChanges'];
    }
}
