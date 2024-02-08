<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    if (class_exists('Symfony\Component\Translation\Command\XliffLintCommand')) {
        $services->set('Symfony\Component\Translation\Command\XliffLintCommand')
            ->public()
            ->tag('console.command', [
                'command' => 'xliff:lint',
            ]);
    }
};
