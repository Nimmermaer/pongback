<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "pongback".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webicorns\Pongback\Tests\Unit\Mention\Source;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webicorns\Pongback\Mention\RejectionReason;
use Webicorns\Pongback\Mention\Source\UrlGuard;
use Webicorns\Pongback\Tests\Unit\Fixtures\AssertRejectionTrait;
use Webicorns\Pongback\Utility\Constants;

final class UrlGuardTest extends TestCase
{
    use AssertRejectionTrait;

    #[Test]
    public function acceptsPublicHostsAndReturnsTheResolvedAddresses(): void
    {
        $subject = $this->createSubject(['blog.example' => ['93.184.215.14', '2606:2800:21f:cb07:6820:80da:af6b:8b2c']]);

        $address = $subject->assertAllowed('https://Blog.Example./post');

        self::assertSame('blog.example', $address->host);
        self::assertSame(443, $address->port);
        self::assertSame(['93.184.215.14', '2606:2800:21f:cb07:6820:80da:af6b:8b2c'], $address->ips);
        self::assertSame('blog.example:443:93.184.215.14', $address->toCurlResolveEntry());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUrlProvider(): array
    {
        return [
            'file scheme' => ['file:///etc/passwd'],
            'gopher scheme' => ['gopher://blog.example/'],
            'relative' => ['/post'],
            'credentials' => ['https://user:secret@blog.example/'],
            'custom port' => ['http://blog.example:8080/'],
            'https on port 80' => ['https://blog.example:80/'],
            'too long' => ['https://blog.example/' . str_repeat('a', 300)],
        ];
    }

    #[Test]
    #[DataProvider('invalidUrlProvider')]
    public function rejectsInvalidUrls(string $url): void
    {
        self::assertRejected(
            RejectionReason::InvalidRequest,
            fn() => $this->createSubject(['blog.example' => ['93.184.215.14']])->assertAllowed($url),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function internalAddressProvider(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'private 10/8' => ['10.0.0.5'],
            'private 192.168/16' => ['192.168.1.10'],
            'cloud metadata' => ['169.254.169.254'],
            'carrier grade nat' => ['100.64.0.1'],
            'unspecified' => ['0.0.0.0'],
            'ipv6 loopback' => ['::1'],
            'ipv6 unique local' => ['fd00::1'],
            'ipv4 mapped ipv6' => ['::ffff:127.0.0.1'],
            'nat64' => ['64:ff9b::7f00:1'],
        ];
    }

    #[Test]
    #[DataProvider('internalAddressProvider')]
    public function rejectsHostsResolvingToInternalAddresses(string $ip): void
    {
        self::assertRejected(
            RejectionReason::InvalidRequest,
            fn() => $this->createSubject(['intranet.example' => ['93.184.215.14', $ip]])->assertAllowed('https://intranet.example/'),
        );
    }

    #[Test]
    #[DataProvider('internalAddressProvider')]
    public function rejectsInternalIpLiterals(string $ip): void
    {
        $host = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;

        self::assertRejected(
            RejectionReason::InvalidRequest,
            fn() => $this->createSubject([])->assertAllowed('http://' . $host . '/'),
        );
    }

    #[Test]
    public function reportsUnresolvableHostsAsMissingSource(): void
    {
        self::assertRejected(
            RejectionReason::SourceNotFound,
            fn() => $this->createSubject([])->assertAllowed('https://does-not-exist.example/'),
        );
    }

    /**
     * @param array<string, list<string>> $dns
     */
    private function createSubject(array $dns): UrlGuard
    {
        return new UrlGuard(static fn(string $host): array => $dns[$host] ?? []);
    }

    #[Test]
    public function rejectsUserNamesWithoutPassword(): void
    {
        self::assertRejected(
            RejectionReason::InvalidRequest,
            fn() => $this->createSubject(['blog.example' => ['93.184.215.14']])->assertAllowed('https://user@blog.example/'),
        );
    }

    #[Test]
    public function acceptsUpperCaseSchemesAndExplicitDefaultPorts(): void
    {
        $subject = $this->createSubject(['blog.example' => ['93.184.215.14']]);

        self::assertSame(443, $subject->assertAllowed('HTTPS://blog.example/')->port);
        self::assertSame(80, $subject->assertAllowed('http://blog.example:80/')->port);
    }

    #[Test]
    public function acceptsUrlsUpToTheMaximumLength(): void
    {
        $url = 'https://blog.example/';
        $url .= str_repeat('a', Constants::MAX_URL_LENGTH - strlen($url));

        self::assertSame(Constants::MAX_URL_LENGTH, strlen($url));
        self::assertSame('blog.example', $this->createSubject(['blog.example' => ['93.184.215.14']])->assertAllowed($url)->host);
        self::assertRejected(
            RejectionReason::InvalidRequest,
            fn() => $this->createSubject(['blog.example' => ['93.184.215.14']])->assertAllowed($url . 'a'),
        );
    }

    #[Test]
    public function acceptsPublicIpv6AddressesOutsideNat64(): void
    {
        self::assertSame(
            ['2001:4860:4860::8888'],
            $this->createSubject([])->assertAllowed('https://[2001:4860:4860::8888]/')->ips,
        );
    }
}
