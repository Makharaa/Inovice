<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\UserFacingException;

/**
 * All money maths is done with integers (tetri/cents) and half-up rounding.
 *
 * Every line satisfies   base_net - discount_net = net   and   net + vat = total,
 * so the invoice summary always adds up to the cent:
 *
 *   subtotal - discount_total + vat_total = total
 *
 * public/assets/js/invoice-editor.js implements the same algorithm for the live preview.
 */
final class InvoiceCalculator
{
    /** Largest allowed value of a single line before discount (in tetri) = 100 000 000.00. */
    private const MAX_LINE_CENTS = 10_000_000_000;
    private const MAX_QUANTITY = 1_000_000;
    private const MAX_UNIT_PRICE = 100_000_000;

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
        $rateBasis = (int) round($vatRate * 100);           // 18.00 % => 1800
        $items = [];
        $subtotal = 0;
        $discountTotal = 0;
        $vatTotal = 0;
        $grandTotal = 0;

        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $description = is_scalar($item['description'] ?? null) ? trim((string) $item['description']) : '';
            $quantity = $this->number($item['quantity'] ?? 0);
            $unitPrice = $this->number($item['unit_price'] ?? 0);
            $unit = is_scalar($item['unit'] ?? null) ? trim((string) $item['unit']) : 'pcs';
            if (!array_key_exists($unit, self::allowedUnits())) {
                $unit = 'pcs';
            }
            $discountBasis = (int) round(min(100.0, max(0.0, $this->number($item['discount_percent'] ?? 0))) * 100);

            if ($description === '' || $quantity <= 0) {
                continue;
            }
            if ($quantity > self::MAX_QUANTITY || $unitPrice > self::MAX_UNIT_PRICE) {
                throw new UserFacingException('რაოდენობა ან ფასი დასაშვებ ზღვარს აჭარბებს.');
            }

            $quantityMilli = (int) round($quantity * 1000);   // 3 decimals
            $priceUnits = (int) round($unitPrice * 10000);    // 4 decimals
            $product = $quantityMilli * $priceUnits;           // = base * 10^7
            if ($product > self::MAX_LINE_CENTS * 100_000) {
                throw new UserFacingException('პოზიციის თანხა ზედმეტად დიდია.');
            }

            $base = intdiv($product + 50_000, 100_000);                    // base, tetri
            $discount = intdiv($base * $discountBasis + 5_000, 10_000);    // discount, tetri
            $discounted = $base - $discount;

            if ($vatPayer && $pricesIncludeVat) {
                $total = $discounted;
                $net = $this->netFromGross($discounted, $rateBasis);
                $vat = $total - $net;
                $baseNet = $this->netFromGross($base, $rateBasis);
                $discountNet = $baseNet - $net;
            } elseif ($vatPayer) {
                $net = $discounted;
                $vat = intdiv($net * $rateBasis + 5_000, 10_000);
                $total = $net + $vat;
                $baseNet = $base;
                $discountNet = $discount;
            } else {
                $net = $discounted;
                $vat = 0;
                $total = $discounted;
                $baseNet = $base;
                $discountNet = $discount;
            }

            $items[] = [
                'sort_order' => count($items) + 1,
                'description' => $description,
                'unit' => $unit,
                'quantity' => $quantityMilli / 1000,
                'unit_price' => $priceUnits / 10000,
                'discount_percent' => $discountBasis / 100,
                'net_total' => $this->money($net),
                'vat_total' => $this->money($vat),
                'line_total' => $this->money($total),
            ];

            $subtotal += $baseNet;
            $discountTotal += $discountNet;
            $vatTotal += $vat;
            $grandTotal += $total;
        }

        // DECIMAL(14,2) columns hold up to 999 999 999 999.99
        if ($grandTotal > 99_999_999_999_999 || $subtotal > 99_999_999_999_999) {
            throw new UserFacingException('ინვოისის ჯამი ზედმეტად დიდია.');
        }

        return [
            'items' => $items,
            'subtotal' => $this->money($subtotal),
            'discount_total' => $this->money($discountTotal),
            'vat_total' => $this->money($vatTotal),
            'total' => $this->money($grandTotal),
        ];
    }

    /** Non-negative number from user input (0 for garbage/NaN/negative, PHP_FLOAT_MAX for +INF). */
    private function number(mixed $value): float
    {
        if (!is_scalar($value)) {
            return 0.0;
        }

        $number = (float) $value;
        if (is_infinite($number) && $number > 0) {
            return PHP_FLOAT_MAX; // rejected by the range checks instead of being silently dropped
        }

        return is_finite($number) && $number > 0 ? $number : 0.0;
    }

    /** Net amount (tetri) contained in a gross amount, half-up. */
    private function netFromGross(int $gross, int $rateBasis): int
    {
        $divisor = 10_000 + $rateBasis;

        return intdiv($gross * 20_000 + $divisor, 2 * $divisor);
    }

    private function money(int $tetri): float
    {
        return round($tetri / 100, 2);
    }
}
