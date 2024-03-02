<?php

declare(strict_types=1);

namespace Webicorns\Pongback\Listener;

use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationLoadedEvent;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Utility\ArrayUtility;

final class EnhanceSiteConfigurationListener
{
    public function __invoke(SiteConfigurationLoadedEvent $event): void
    {
        $newConfiguration = (new YamlFileLoader())->load(
            'EXT:pongback/Configuration/Site/config.yaml'
        );
        $currentConfiguration = $event->getConfiguration();
        ArrayUtility::mergeRecursiveWithOverrule($currentConfiguration, $newConfiguration);
        $event->setConfiguration($currentConfiguration);
    }
}
