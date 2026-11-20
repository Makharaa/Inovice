<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Support/UserFacingException.php';
require dirname(__DIR__) . '/app/Services/InvoiceCalculator.php';
require dirname(__DIR__) . '/app/Services/InvoiceDocumentPaginator.php';

use App\Services\InvoiceCalculator;
use App\Services\InvoiceDocumentPaginator;

function assertAmount(float $expected, float $actual, string $label): void
{
    if (abs($expected - $actual) > 0.001) {
        throw new RuntimeException("{$label}: expected {$expected}, got {$actual}");
    }
}

$calculator = new InvoiceCalculator();
$item = [[
    'description' => 'Test item',
    'unit' => 'pcs',
    'quantity' => 2,
    'unit_price' => 50,
    'discount_percent' => 10,
]];

$nonVat = $calculator->calculate($item, false, false);
assertAmount(100, $nonVat['subtotal'], 'Non-VAT subtotal');
assertAmount(10, $nonVat['discount_total'], 'Non-VAT discount');
assertAmount(0, $nonVat['vat_total'], 'Non-VAT tax');
assertAmount(90, $nonVat['total'], 'Non-VAT total');

$vatExcluded = $calculator->calculate($item, true, false);
assertAmount(100, $vatExcluded['subtotal'], 'VAT excluded subtotal');
assertAmount(10, $vatExcluded['discount_total'], 'VAT excluded discount');
assertAmount(16.2, $vatExcluded['vat_total'], 'VAT excluded tax');
assertAmount(106.2, $vatExcluded['total'], 'VAT excluded total');

$vatIncluded = $calculator->calculate([[
    'description' => 'VAT included',
    'unit' => 'other',
    'quantity' => 1,
    'unit_price' => 118,
    'discount_percent' => 10,
]], true, true);
assertAmount(100, $vatIncluded['subtotal'], 'VAT included subtotal');
assertAmount(10, $vatIncluded['discount_total'], 'VAT included discount');
assertAmount(16.2, $vatIncluded['vat_total'], 'VAT included tax');
assertAmount(106.2, $vatIncluded['total'], 'VAT included total');


// Half-up rounding must not depend on binary floating point: 3 x 0.335 = 1.005 -> 1.01
$tie = $calculator->calculate([['description' => 'Tie', 'quantity' => 3, 'unit_price' => 0.335]], false, false);
assertAmount(1.01, $tie['total'], 'Half-up rounding of 1.005');

// The printed summary must always add up to the cent, for every VAT mode.
mt_srand(20260101);
foreach ([[false, false], [true, false], [true, true]] as [$vatPayer, $includesVat]) {
    for ($run = 0; $run < 3000; $run++) {
        $lines = [];
        for ($line = 0, $count = mt_rand(1, 10); $line < $count; $line++) {
            $lines[] = [
                'description' => 'Line',
                'quantity' => mt_rand(1, 20000) / 1000,
                'unit_price' => mt_rand(1, 5000000) / 10000,
                'discount_percent' => mt_rand(0, 1) ? 0 : mt_rand(0, 10000) / 100,
            ];
        }
        $result = $calculator->calculate($lines, $vatPayer, $includesVat);
        $label = sprintf('vat=%d incl=%d run=%d', $vatPayer, $includesVat, $run);

        $summary = round($result['subtotal'] - $result['discount_total'] + $result['vat_total'], 2);
        assertAmount($result['total'], $summary, "Summary does not add up ({$label})");

        $lineTotal = $lineVat = 0.0;
        foreach ($result['items'] as $row) {
            assertAmount($row['line_total'], round($row['net_total'] + $row['vat_total'], 2), "Line net+vat ({$label})");
            $lineTotal += $row['line_total'];
            $lineVat += $row['vat_total'];
        }
        assertAmount($result['total'], $lineTotal, "Total equals sum of printed lines ({$label})");
        assertAmount($result['vat_total'], $lineVat, "VAT equals sum of printed lines ({$label})");
        if (!$vatPayer && $result['vat_total'] != 0.0) {
            throw new RuntimeException('Non-VAT payer must not get VAT.');
        }
    }
}

// Regression: an 8-line invoice that used to be 1 tetri off (subtotal - discount + VAT != total).
$regression = $calculator->calculate([
    ['description' => 'a', 'quantity' => 13, 'unit_price' => 3891.21, 'discount_percent' => 31],
    ['description' => 'b', 'quantity' => 8, 'unit_price' => 3746.72],
    ['description' => 'c', 'quantity' => 9, 'unit_price' => 1668.15, 'discount_percent' => 37],
    ['description' => 'd', 'quantity' => 9, 'unit_price' => 1340.72, 'discount_percent' => 31],
    ['description' => 'e', 'quantity' => 7, 'unit_price' => 3480.36],
    ['description' => 'f', 'quantity' => 19, 'unit_price' => 3483.61, 'discount_percent' => 26],
    ['description' => 'g', 'quantity' => 12, 'unit_price' => 4537.36, 'discount_percent' => 11],
    ['description' => 'h', 'quantity' => 8, 'unit_price' => 3210.55, 'discount_percent' => 42],
], true, false);
assertAmount(
    $regression['total'],
    $regression['subtotal'] - $regression['discount_total'] + $regression['vat_total'],
    'Regression invoice summary'
);

// Invalid and hostile input.
$ignored = $calculator->calculate([
    'not-an-array',
    ['description' => '', 'quantity' => 1, 'unit_price' => 5],
    ['description' => 'Zero quantity', 'quantity' => 0, 'unit_price' => 5],
    ['description' => 'NaN', 'quantity' => 'NaN', 'unit_price' => 5],
    ['description' => ['array'], 'quantity' => 1, 'unit_price' => 5],
    ['description' => 'Negative', 'quantity' => -3, 'unit_price' => 5],
], false, false);
if ($ignored['items'] !== [] || $ignored['total'] != 0.0) {
    throw new RuntimeException('Invalid items must be ignored.');
}
$negativePrice = $calculator->calculate([['description' => 'Free', 'quantity' => 1, 'unit_price' => -50]], false, false);
assertAmount(0, $negativePrice['total'], 'Negative price is treated as 0');

foreach ([
    ['quantity' => '1e30', 'unit_price' => 1],
    ['quantity' => '1e999', 'unit_price' => 1],
    ['quantity' => 1, 'unit_price' => '1e30'],
    ['quantity' => 1000000, 'unit_price' => 100000000],
] as $hostile) {
    try {
        $calculator->calculate([['description' => 'Huge'] + $hostile], true, true);
        throw new RuntimeException('Oversized amounts must be rejected: ' . json_encode($hostile));
    } catch (App\Support\UserFacingException) {
        // expected
    }
}

echo "InvoiceCalculator tests passed.\n";

$items = [];
for ($index = 1; $index <= 18; $index++) {
    $items[] = ['description' => "Item {$index}"];
}

$paginator = new InvoiceDocumentPaginator();
$standardPages = $paginator->paginate($items, 'standard');
if (array_map('count', $standardPages) !== [12, 6]) {
    throw new RuntimeException('Standard pagination did not balance 18 items across two pages.');
}

$compactPages = $paginator->paginate($items, 'compact');
if (array_map('count', $compactPages) !== [18]) {
    throw new RuntimeException('Compact pagination should keep 18 short items on one landscape page.');
}

$manyItems = [];
for ($index = 1; $index <= 35; $index++) {
    $manyItems[] = ['description' => "Item {$index}"];
}
$manyPages = $paginator->paginate($manyItems, 'standard');
if (array_map('count', $manyPages) !== [12, 13, 10]) {
    throw new RuntimeException('Standard pagination did not preserve a balanced final page.');
}

$numbers = array_column(array_merge(...$manyPages), '_document_number');
if ($numbers !== range(1, 35)) {
    throw new RuntimeException('Document item numbering is not continuous across pages.');
}

echo "InvoiceDocumentPaginator tests passed.\n";

// ErrorHandler: an uncaught exception and a fatal error are logged with a reference and do not leak details.
foreach ([
    'exception' => "throw new RuntimeException('secret db detail');",
    'fatal' => "\$a = []; while (true) { \$a[] = str_repeat('x', 1000000); }",
] as $kind => $code) {
    $base = sys_get_temp_dir() . '/invoice-errtest-' . bin2hex(random_bytes(4));
    mkdir($base . '/storage/logs', 0777, true);
    $script = "define('BASE_PATH', " . var_export($base, true) . "); require " . var_export(dirname(__DIR__) . '/app/Support/ErrorHandler.php', true)
        . "; App\\Support\\ErrorHandler::register(); {$code}";
    file_put_contents($base . '/case.php', "<?php\n" . $script); // not `php -r`: it bypasses exception handlers
    exec(escapeshellarg(PHP_BINARY) . ' -d memory_limit=4M -d display_errors=0 ' . escapeshellarg($base . '/case.php') . ' 2>&1', $output);
    $logs = glob($base . '/storage/logs/app-*.log') ?: [];
    $log = $logs ? (string) file_get_contents($logs[0]) : '';
    $shown = implode("\n", $output);
    $output = [];
    if (!preg_match('/#([0-9a-f]{8}) /', $log, $match) || !str_contains($shown, $match[1])) {
        throw new RuntimeException("ErrorHandler ({$kind}): reference missing from log or output.");
    }
    if (str_contains($shown, 'secret db detail')) {
        throw new RuntimeException("ErrorHandler ({$kind}): internal detail leaked to the user.");
    }
    array_map('unlink', $logs);
    unlink($base . '/case.php');
    rmdir($base . '/storage/logs');
    rmdir($base . '/storage');
    rmdir($base);
}

echo "ErrorHandler tests passed.\n";
