<?php

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationLoadedEvent;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Translation\Command\XliffLintCommand;
use Webicorns\Pongback\Listener\EnhanceSiteConfigurationListener;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    if (class_exists(XliffLintCommand::class)) {
        $services->set(XliffLintCommand::class)
            ->public()
            ->tag('console.command', [
                    'command' => 'xliff:lint',
                ]);
    }

    $extensionConfiguration = GeneralUtility::makeInstance(
        ExtensionConfiguration::class
    );
    if ($extensionConfiguration->get('pongback', 'includePongbackYaml')) {
        $services->set('pongback/enhance-pongback-configuration', EnhanceSiteConfigurationListener::class)
            ->tag(
                'event.listener',
                [
                    'event' => SiteConfigurationLoadedEvent::class,
                ]
            );
    }
};
