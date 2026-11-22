<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Webicorns\Pongback\Listener;

use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationLoadedEvent;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * TYPO3 v13 only: adds the route enhancers of the site set to every site.
 *
 * TYPO3 v14 reads Configuration/Sets/Pongback/route-enhancers.yaml natively when
 * the set is added to a site, so this listener is not registered there
 * (see Configuration/Services.php). Remove it once TYPO3 v13 support is dropped.
 */
final readonly class EnhanceSiteConfigurationListener
{
    public const ROUTE_ENHANCERS_FILE = 'EXT:pongback/Configuration/Sets/Pongback/route-enhancers.yaml';

    public function __construct(
        private YamlFileLoader $yamlFileLoader,
        private ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function __invoke(SiteConfigurationLoadedEvent $event): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $routeEnhancers = $this->yamlFileLoader->load(self::ROUTE_ENHANCERS_FILE)['routeEnhancers'] ?? [];
        $configuration = $event->getConfiguration();

        // Same precedence as site sets in TYPO3 v14: the site configuration wins over the extension defaults.
        $configuration['routeEnhancers'] = ArrayUtility::replaceAndAppendScalarValuesRecursive(
            $routeEnhancers,
            $configuration['routeEnhancers'] ?? [],
        );
        $event->setConfiguration($configuration);
    }

    private function isEnabled(): bool
    {
        try {
            return (bool) $this->extensionConfiguration->get('pongback', 'includePongbackYaml');
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            // Not written yet (e.g. before "extension:setup"): default of ext_conf_template.txt
            return true;
        }
    }
}
