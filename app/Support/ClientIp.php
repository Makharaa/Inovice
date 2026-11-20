<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Determines the visitor's IP address.
 *
 * X-Forwarded-For is only honoured when the direct peer (REMOTE_ADDR) is a configured trusted proxy
 * (config app.trusted_proxies, IPs or CIDR ranges); otherwise anyone could spoof it and dodge the
 * login rate limit. The list is walked from the right, skipping trusted proxies, so entries the
 * client prepended itself are never believed.
 */
final class ClientIp
{
    /**
     * @param array<string,mixed> $server
     * @param list<string>        $trustedProxies
     */
    public static function resolve(array $server, array $trustedProxies): string
    {
        $remote = (string) ($server['REMOTE_ADDR'] ?? '');

        if ($trustedProxies === [] || !self::isTrusted($remote, $trustedProxies)) {
            return $remote;
        }

        $forwarded = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded === '') {
            return $remote;
        }

        foreach (array_reverse(array_map('trim', explode(',', $forwarded))) as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                return $remote; // malformed header: do not guess
            }
            if (!self::isTrusted($candidate, $trustedProxies)) {
                return $candidate;
            }
        }

        return $remote;
    }

    /** @param list<string> $ranges */
    public static function isTrusted(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if (self::matches($ip, trim((string) $range))) {
                return true;
            }
        }

        return false;
    }

    private static function matches(string $ip, string $range): bool
    {
        $address = @inet_pton($ip);
        if ($address === false || $range === '') {
            return false;
        }

        [$network, $bits] = array_pad(explode('/', $range, 2), 2, null);
        $networkAddress = @inet_pton((string) $network);
        if ($networkAddress === false || strlen($networkAddress) !== strlen($address)) {
            return false;
        }

        $prefix = $bits === null ? strlen($address) * 8 : (int) $bits;
        if ($prefix < 0 || $prefix > strlen($address) * 8) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        if (substr($address, 0, $fullBytes) !== substr($networkAddress, 0, $fullBytes)) {
            return false;
        }

        $remainder = $prefix % 8;
        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($address[$fullBytes]) & $mask) === (ord($networkAddress[$fullBytes]) & $mask);
    }
}
