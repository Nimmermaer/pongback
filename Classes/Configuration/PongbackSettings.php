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
 * Values and defaults are defined only in Configuration/Sets/Pongback/settings.definitions.yaml:
 * for a site using the set, TYPO3 provides every setting, validated and converted to its type.
 * Sites that do not use the set (directly or as dependency of another set) get no Pingback
 * features at all: no discovery, no receiving, no sending.
 */
final readonly class PongbackSettings
{
    /**
     * The settings of the set; all of them are present when a site uses it.
     */
    public const KEYS = [
        'pongback.discovery.httpHeader',
        'pongback.discovery.metaTag',
        'pongback.receive.autoPublish',
        'pongback.receive.rateLimit',
        'pongback.receive.webmention',
        'pongback.send.enabled',
        'pongback.send.webmention',
        'pongback.send.tables',
        'pongback.notification.emailTo',
        'pongback.notification.emailFrom',
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
        public bool $receiveWebmention,
        public bool $sendEnabled,
        public bool $sendWebmention,
        public array $sendTables,
        public string $notificationEmailTo,
        public string $notificationEmailFrom,
    ) {}

    public static function fromSite(?SiteInterface $site): self
    {
        $settings = $site instanceof Site ? $site->getSettings() : null;
        if (! $settings instanceof SiteSettings || ! self::usesTheSet($settings)) {
            return self::disabled();
        }

        // TYPO3 validated the values against their type in settings.definitions.yaml, no casts needed.
        return new self(
            enabled: true,
            discoveryHttpHeader: $settings->get('pongback.discovery.httpHeader'),
            discoveryMetaTag: $settings->get('pongback.discovery.metaTag'),
            receiveAutoPublish: $settings->get('pongback.receive.autoPublish'),
            receiveRateLimit: max(0, $settings->get('pongback.receive.rateLimit')),
            receiveWebmention: $settings->get('pongback.receive.webmention'),
            sendEnabled: $settings->get('pongback.send.enabled'),
            sendWebmention: $settings->get('pongback.send.webmention'),
            sendTables: array_values(array_filter(
                array_map(trim(...), $settings->get('pongback.send.tables')),
                static fn(string $table): bool => $table !== '',
            )),
            notificationEmailTo: trim($settings->get('pongback.notification.emailTo')),
            notificationEmailFrom: trim($settings->get('pongback.notification.emailFrom')),
        );
    }

    /**
     * For sites without the set: no feature is active.
     */
    public static function disabled(): self
    {
        return new self(
            enabled: false,
            discoveryHttpHeader: false,
            discoveryMetaTag: false,
            receiveAutoPublish: false,
            receiveRateLimit: 0,
            receiveWebmention: false,
            sendEnabled: false,
            sendWebmention: false,
            sendTables: [],
            notificationEmailTo: '',
            notificationEmailFrom: '',
        );
    }

    private static function usesTheSet(SiteSettings $settings): bool
    {
        foreach (self::KEYS as $key) {
            if (! $settings->has($key)) {
                return false;
            }
        }

        return true;
    }
}
