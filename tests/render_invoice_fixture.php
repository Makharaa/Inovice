<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = BASE_PATH . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asset(string $path): string
{
    return 'file://' . BASE_PATH . '/public/assets/' . ltrim($path, '/');
}

function invoice_label(array $labels, string $key): string
{
    return (string) ($labels[$key] ?? $key);
}

$template = in_array($argv[1] ?? '', ['standard', 'modern', 'compact'], true)
    ? $argv[1]
    : 'standard';
$items = [];

for ($index = 1; $index <= 18; $index++) {
    $price = 95 + ($index * 13);
    $discount = $index % 6 === 0 ? 5 : 0;
    $net = $price * (1 - ($discount / 100));
    $vat = $net * .18;
    $items[] = [
        'description' => $index % 5 === 0
            ? "ავეჯის დეტალი {$index} - დამზადება, დამუშავება და ადგილზე მონტაჟი"
            : "ავეჯის დეტალი {$index}",
        'unit' => $index % 4 === 0 ? 'other' : 'pcs',
        'quantity' => 1,
        'unit_price' => $price,
        'discount_percent' => $discount,
        'net_total' => round($net, 2),
        'vat_total' => round($vat, 2),
        'line_total' => round($net + $vat, 2),
    ];
}

$subtotal = array_sum(array_column($items, 'net_total'));
$vatTotal = array_sum(array_column($items, 'vat_total'));
$invoice = [
    'invoice_number' => 'INV-2026-0042',
    'language' => 'ka',
    'template_key' => $template,
    'issue_date' => '2026-07-30',
    'due_date' => '2026-08-06',
    'currency' => 'GEL',
    'prices_include_vat' => 0,
    'vat_rate' => 18,
    'status' => 'issued',
    'issuer_snapshot' => [
        'name' => 'შპს დოჩიტო',
        'identification_number' => '400123456',
        'address' => 'საქართველო, ქ. გორი, მშვიდობის გამზირი 18',
        'email' => 'info@example.ge',
        'phone' => '+995 599 00 00 00',
        'bank_name' => 'სს საქართველოს ბანკი',
        'bank_code' => 'BAGAGE22',
        'bank_account' => 'GE00BG0000000000000000',
        'is_vat_payer' => 1,
    ],
    'client_snapshot' => [
        'name' => 'შპს სატესტო კომპანია',
        'identification_number' => '405987654',
        'address' => 'საქართველო, ქ. თბილისი, წერეთლის გამზირი 42',
        'email' => 'office@example.ge',
        'phone' => '+995 555 11 22 33',
    ],
    'subtotal' => round($subtotal, 2),
    'discount_total' => 57.50,
    'vat_total' => round($vatTotal, 2),
    'total' => round($subtotal + $vatTotal, 2),
    'notes' => 'გადახდა განხორციელდეს ინვოისში მითითებულ საბანკო ანგარიშზე.',
    'items' => $items,
];

$labels = require BASE_PATH . '/app/Lang/ka/invoice.php';
$documentPages = (new App\Services\InvoiceDocumentPaginator())->paginate($items, $template);
$autoDownload = false;

require BASE_PATH . '/app/Views/invoices/document.php';

