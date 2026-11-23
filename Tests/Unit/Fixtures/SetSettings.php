<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Fixtures;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Settings\Settings;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;

/**
 * Site settings as TYPO3 provides them for a site using the set: the defaults of
 * settings.definitions.yaml, optionally overridden. The YAML file is the only source of the values.
 */
final class SetSettings
{
    public const DEFINITIONS = __DIR__ . '/../../../Configuration/Sets/Pongback/settings.definitions.yaml';

    /**
     * @return array<string, mixed> flat identifiers, like TYPO3's SiteSettingsFactory creates them
     */
    public static function defaults(): array
    {
        $definitions = Yaml::parseFile(self::DEFINITIONS)['settings'];

        return array_map(static fn(array $definition): mixed => $definition['default'], $definitions);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function create(array $overrides = []): SiteSettings
    {
        return SiteSettings::create(new Settings(array_replace(self::defaults(), $overrides)));
    }
}
