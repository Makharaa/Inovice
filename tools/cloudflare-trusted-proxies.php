<?php

declare(strict_types=1);

/**
 * Prints the `trusted_proxies` line for storage/config.php when the site runs behind Cloudflare.
 * It downloads Cloudflare's official, current IP lists, so nothing has to be typed by hand.
 *
 *   php tools/cloudflare-trusted-proxies.php
 *
 * Run it on the server (it needs outbound HTTPS). Repeat every few months: Cloudflare adds ranges rarely.
 * Optional arguments for offline use: php tools/cloudflare-trusted-proxies.php <ipv4-list-file> <ipv6-list-file>
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}

$sources = [
    $argv[1] ?? 'https://www.cloudflare.com/ips-v4',
    $argv[2] ?? 'https://www.cloudflare.com/ips-v6',
];

$ranges = [];
foreach ($sources as $source) {
    $body = @file_get_contents($source, false, stream_context_create(['http' => ['timeout' => 15]]));
    if ($body === false || trim($body) === '') {
        fwrite(STDERR, "Could not download {$source}\n");
        exit(1);
    }

    foreach (preg_split('/\s+/', trim($body)) ?: [] as $line) {
        // accept only well-formed CIDR ranges: never paste unchecked text into the config
        if (!preg_match('#^([0-9a-fA-F:.]+)/(\d{1,3})$#', $line, $match)
            || filter_var($match[1], FILTER_VALIDATE_IP) === false
            || (int) $match[2] > (str_contains($match[1], ':') ? 128 : 32)) {
            fwrite(STDERR, "Unexpected line in {$source}: {$line}\n");
            exit(1);
        }
        $ranges[] = $line;
    }
}

if (count($ranges) < 10) {
    fwrite(STDERR, "Suspiciously few ranges (" . count($ranges) . "), refusing to print.\n");
    exit(1);
}

// 127.0.0.1 / ::1: the web server in front of PHP (nginx -> Apache/PHP-FPM on the same machine)
$all = array_merge(['127.0.0.1', '::1'], $ranges);

echo "Add this line to the 'app' section of storage/config.php:\n\n";
echo "        'trusted_proxies' => [\n";
foreach ($all as $range) {
    echo "            '{$range}',\n";
}
echo "        ],\n";
