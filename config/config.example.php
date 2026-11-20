<?php

return [
    'app' => [
        'name' => 'Makhara Invoice',
        'url' => 'https://invoice.makhara.ge',
        'timezone' => 'Asia/Tbilisi',
        'session_name' => 'makhara_invoice',
        // Only if the site is behind a reverse proxy / CDN (nginx, Cloudflare, ...): its IP addresses or
        // CIDR ranges. Then the visitor IP is read from X-Forwarded-For; otherwise leave the list empty.
        'trusted_proxies' => [],
        // Minutes of inactivity after which a user is signed out (0 = never).
        'session_idle_minutes' => 120,
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'invoice_database',
        'user' => 'invoice_user',
        'password' => 'change-me',
        'charset' => 'utf8mb4',
    ],
];

