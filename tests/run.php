<?php

declare(strict_types=1);

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
