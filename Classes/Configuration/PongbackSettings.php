<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Configuration;

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;

/**
 * Typed access to the site settings of the "webicorns/pongback" set.
 *
 * Sites that do not use the set (directly or as dependency of another set) get no Pingback
 * features at all: no discovery, no receiving, no sending.
 *
 * The defaults must match Configuration/Sets/Pongback/settings.definitions.yaml.
 */
final readonly class PongbackSettings
{
    public const DEFAULTS = [
        'pongback.discovery.httpHeader' => true,
        'pongback.discovery.metaTag' => true,
        'pongback.receive.autoPublish' => false,
        'pongback.receive.rateLimit' => 20,
        'pongback.send.enabled' => true,
        'pongback.send.tables' => ['pages', 'tt_content'],
        'pongback.notification.emailTo' => '',
        'pongback.notification.emailFrom' => '',
    ];

    /**
     * @param list<string> $sendTables
     */
    public function __construct(
        public bool $enabled,
        public bool $discoveryHttpHeader,
        public bool $discoveryMetaTag,
        public bool $receiveAutoPublish,
        public int $receiveRateLimit,
        public bool $sendEnabled,
        public array $sendTables,
        public string $notificationEmailTo,
        public string $notificationEmailFrom,
    ) {}

    public static function fromSite(?SiteInterface $site): self
    {
        $settings = $site instanceof Site ? $site->getSettings() : null;
        $get = static fn(string $key): mixed => $settings?->get($key, self::DEFAULTS[$key]) ?? self::DEFAULTS[$key];
        $enabled = self::usesTheSet($settings);

        return new self(
            enabled: $enabled,
            discoveryHttpHeader: $enabled && (bool) $get('pongback.discovery.httpHeader'),
            discoveryMetaTag: $enabled && (bool) $get('pongback.discovery.metaTag'),
            receiveAutoPublish: (bool) $get('pongback.receive.autoPublish'),
            receiveRateLimit: max(0, (int) $get('pongback.receive.rateLimit')),
            sendEnabled: $enabled && (bool) $get('pongback.send.enabled'),
            sendTables: array_values(array_filter(
                array_map(trim(...), (array) $get('pongback.send.tables')),
                static fn(string $table): bool => $table !== '',
            )),
            notificationEmailTo: trim((string) $get('pongback.notification.emailTo')),
            notificationEmailFrom: trim((string) $get('pongback.notification.emailFrom')),
        );
    }

    /**
     * The set provides a value for every setting it defines, so their presence tells whether a site uses it.
     */
    private static function usesTheSet(?SiteSettings $settings): bool
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            if ($settings?->has($key)) {
                return true;
            }
        }

        return false;
    }
}
