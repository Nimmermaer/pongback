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

/**
 * Typed access to the site settings of the "webicorns/pongback" set.
 *
 * The defaults are used for sites that do not include the set and
 * must match Configuration/Sets/Pongback/settings.definitions.yaml.
 */
final readonly class PongbackSettings
{
    public const DEFAULTS = [
        'pongback.discovery.httpHeader' => true,
        'pongback.discovery.metaTag' => true,
        'pongback.receive.autoPublish' => true,
        'pongback.send.tables' => ['pages', 'tt_content'],
        'pongback.notification.emailTo' => '',
        'pongback.notification.emailFrom' => '',
    ];

    /**
     * @param list<string> $sendTables
     */
    public function __construct(
        public bool $discoveryHttpHeader,
        public bool $discoveryMetaTag,
        public bool $receiveAutoPublish,
        public array $sendTables,
        public string $notificationEmailTo,
        public string $notificationEmailFrom,
    ) {}

    public static function fromSite(?SiteInterface $site): self
    {
        $settings = $site instanceof Site ? $site->getSettings() : null;
        $get = static fn(string $key): mixed => $settings?->get($key, self::DEFAULTS[$key]) ?? self::DEFAULTS[$key];

        return new self(
            discoveryHttpHeader: (bool) $get('pongback.discovery.httpHeader'),
            discoveryMetaTag: (bool) $get('pongback.discovery.metaTag'),
            receiveAutoPublish: (bool) $get('pongback.receive.autoPublish'),
            sendTables: array_values(array_filter(
                array_map(trim(...), (array) $get('pongback.send.tables')),
                static fn(string $table): bool => $table !== '',
            )),
            notificationEmailTo: trim((string) $get('pongback.notification.emailTo')),
            notificationEmailFrom: trim((string) $get('pongback.notification.emailFrom')),
        );
    }
}
