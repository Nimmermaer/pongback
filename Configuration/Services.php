<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationLoadedEvent;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use Webicorns\Pongback\Listener\EnhanceSiteConfigurationListener;
use Webicorns\Pongback\Widget\PongbackWidget;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // TYPO3 v13 only: v14 applies the route enhancers of the site set natively.
    $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
    if ((new Typo3Version())->getMajorVersion() < 14 && $extensionConfiguration->get('pongback', 'includePongbackYaml')) {
        $services->set('pongback/enhance-pongback-configuration', EnhanceSiteConfigurationListener::class)
            ->autowire()
            ->tag('event.listener', [
                'event' => SiteConfigurationLoadedEvent::class,
            ]);
    }

    // EXT:dashboard is optional
    if (interface_exists(WidgetInterface::class)) {
        $services->set('dashboard.widget.pongback', PongbackWidget::class)
            ->autowire()
            ->tag('dashboard.widget', [
                'identifier' => 'pongback',
                'groupNames' => 'general',
                'title' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_be.xlf:widgets.pongback.title',
                'description' => 'LLL:EXT:pongback/Resources/Private/Language/locallang_be.xlf:widgets.pongback.description',
                'iconIdentifier' => 'tx-pongback-pong',
                'height' => 'medium',
                'width' => 'small',
            ]);
    }
};
