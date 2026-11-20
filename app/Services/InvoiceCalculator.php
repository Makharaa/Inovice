<?php

declare(strict_types=1);

namespace App\Services;

final class InvoiceCalculator
{
    /** Allowed unit keys come from the Georgian language file (single source of truth). */
    private static function allowedUnits(): array
    {
        static $units = null;
        if ($units === null) {
            $labels = require dirname(__DIR__) . '/Lang/ka/invoice.php';
            $units = $labels['units'];
        }

        return $units;
    }

    public function calculate(array $rawItems, bool $vatPayer, bool $pricesIncludeVat, float $vatRate = 18.0): array
    {
        $items = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $vatTotal = 0.0;
        $grandTotal = 0.0;

        foreach ($rawItems as $index => $item) {
            $description = trim((string) ($item['description'] ?? ''));
            $quantity = round(max(0, (float) ($item['quantity'] ?? 0)), 3);
            $unitPrice = round(max(0, (float) ($item['unit_price'] ?? 0)), 4);
            $unit = trim((string) ($item['unit'] ?? 'pcs'));
            if (!array_key_exists($unit, self::allowedUnits())) {
                $unit = 'pcs';
            }
            $discountPercent = min(100, max(0, (float) ($item['discount_percent'] ?? 0)));

            if ($description === '' || $quantity <= 0) {
                continue;
            }

            $base = $quantity * $unitPrice;
            $discount = $base * ($discountPercent / 100);
            $discounted = $base - $discount;

            if ($vatPayer && $pricesIncludeVat) {
                $baseNet = $base / (1 + ($vatRate / 100));
                $discountNet = $discount / (1 + ($vatRate / 100));
                $net = $discounted / (1 + ($vatRate / 100));
                $vat = $discounted - $net;
                $total = $discounted;
            } elseif ($vatPayer) {
                $baseNet = $base;
                $discountNet = $discount;
                $net = $discounted;
                $vat = $net * ($vatRate / 100);
                $total = $net + $vat;
            } else {
                $baseNet = $base;
                $discountNet = $discount;
                $net = $discounted;
                $vat = 0.0;
                $total = $discounted;
            }

            $baseNet = round($baseNet, 2);
            $discountNet = round($discountNet, 2);
            $net = round($net, 2);
            $vat = round($vat, 2);
            $total = round($total, 2);

            $items[] = [
                'sort_order' => count($items) + 1,
                'description' => $description,
                'unit' => $unit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => round($discountPercent, 2),
                'net_total' => $net,
                'vat_total' => $vat,
                'line_total' => $total,
            ];

            $subtotal += $baseNet;
            $discountTotal += $discountNet;
            $vatTotal += $vat;
            $grandTotal += $total;
        }

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'vat_total' => round($vatTotal, 2),
            'total' => round($grandTotal, 2),
        ];
    }
}
