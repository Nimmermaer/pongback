<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Mention\Source;

use Webicorns\Pongback\Mention\MentionRejected;
use Webicorns\Pongback\Mention\RejectionReason;

/**
 * Protects against server side request forgery: the source of a mention is chosen by the
 * remote party, so it must only point to public web servers.
 */
readonly class UrlGuard
{
    public const MAX_URL_LENGTH = 255;

    private const ALLOWED_PORTS = ['http' => 80, 'https' => 443];

    /**
     * @var \Closure(string): list<string>
     */
    private \Closure $resolver;

    /**
     * @param (\Closure(string): list<string>)|null $resolver resolves a host name to its IP addresses (for tests)
     */
    public function __construct(?\Closure $resolver = null)
    {
        $this->resolver = $resolver ?? self::resolveHost(...);
    }

    /**
     * @return array{host: string, port: int, ips: non-empty-list<string>}
     *
     * @throws MentionRejected
     */
    public function assertAllowed(string $url): array
    {
        [$host, $port] = $this->parseUrl($url);
        $ips = $this->resolvePublicIps($host);

        return ['host' => $host, 'port' => $port, 'ips' => $ips];
    }

    /**
     * @return array{string, int} host and port
     */
    private function parseUrl(string $url): array
    {
        if (strlen($url) > self::MAX_URL_LENGTH) {
            throw new MentionRejected(RejectionReason::InvalidRequest, 'The source URI is too long.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if (! isset(self::ALLOWED_PORTS[$scheme]) || $host === '') {
            throw new MentionRejected(RejectionReason::InvalidRequest, 'The source URI must be an absolute http(s) URI.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new MentionRejected(RejectionReason::InvalidRequest, 'The source URI must not contain credentials.');
        }

        $port = (int) ($parts['port'] ?? self::ALLOWED_PORTS[$scheme]);
        if ($port !== self::ALLOWED_PORTS[$scheme]) {
            throw new MentionRejected(RejectionReason::InvalidRequest, 'The source URI must use the default port.');
        }

        return [$host, $port];
    }

    /**
     * @return non-empty-list<string>
     */
    private function resolvePublicIps(string $host): array
    {
        $literalIp = trim($host, '[]');
        $ips = filter_var($literalIp, FILTER_VALIDATE_IP) !== false ? [$literalIp] : ($this->resolver)($host);
        if ($ips === []) {
            throw new MentionRejected(RejectionReason::SourceNotFound, 'The source URI does not exist.');
        }

        // All addresses must be public: the HTTP client may pick any of them.
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new MentionRejected(RejectionReason::InvalidRequest, 'The source URI must point to a public host.');
            }
        }

        return $ips;
    }

    /**
     * Only globally reachable addresses (RFC 6890): excludes private, loopback, link local,
     * carrier grade NAT, documentation and IPv4-mapped IPv6 ranges.
     */
    private static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        // NAT64 (64:ff9b::/96) embeds IPv4 addresses, which may be internal ones.
        $packed = inet_pton($ip);
        return ! (is_string($packed) && strlen($packed) === 16 && str_starts_with(bin2hex($packed), '0064ff9b0000000000000000'));
    }

    /**
     * @return list<string>
     */
    private static function resolveHost(string $host): array
    {
        $ips = [];
        // dns_get_record() emits a warning for unknown hosts, which is an expected case here.
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        foreach (is_array($records) ? $records : [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip)) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }
}
