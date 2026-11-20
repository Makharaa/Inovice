<?php

declare(strict_types=1);

namespace App\Services;

final class InvoiceDocumentPaginator
{
    private const PROFILES = [
        'standard' => [
            'single' => 10,
            'first' => 12,
            'middle' => 20,
            'final' => 10,
            'min_final' => 5,
            'chars_per_line' => 62,
        ],
        'modern' => [
            'single' => 9,
            'first' => 11,
            'middle' => 18,
            'final' => 9,
            'min_final' => 5,
            'chars_per_line' => 58,
        ],
        'compact' => [
            'single' => 18,
            'first' => 21,
            'middle' => 30,
            'final' => 18,
            'min_final' => 7,
            'chars_per_line' => 96,
        ],
    ];

    public function paginate(array $items, string $template): array
    {
        $profile = self::PROFILES[$template] ?? self::PROFILES['standard'];
        $numberedItems = [];

        foreach (array_values($items) as $index => $item) {
            $item['_document_number'] = $index + 1;
            $item['_document_weight'] = $this->weight($item, $profile['chars_per_line']);
            $numberedItems[] = $item;
        }

        if (!$numberedItems || $this->totalWeight($numberedItems) <= $profile['single']) {
            return [$numberedItems];
        }

        $pages = [];
        $remaining = $numberedItems;
        $firstTarget = min(
            $profile['first'],
            max(1, $this->totalWeight($remaining) - $profile['min_final'])
        );
        $pages[] = $this->takeByWeight($remaining, $firstTarget);

        while ($this->totalWeight($remaining) > $profile['final']) {
            $target = min(
                $profile['middle'],
                max(1, $this->totalWeight($remaining) - $profile['final'])
            );
            $pages[] = $this->takeByWeight($remaining, $target);
        }

        if ($remaining) {
            $pages[] = $remaining;
        }

        return array_values(array_filter($pages));
    }

    private function takeByWeight(array &$items, int $capacity): array
    {
        $page = [];
        $used = 0;

        while ($items) {
            $weight = (int) ($items[0]['_document_weight'] ?? 1);
            if ($page && $used + $weight > $capacity) {
                break;
            }

            $page[] = array_shift($items);
            $used += $weight;
        }

        return $page;
    }

    private function totalWeight(array $items): int
    {
        return array_sum(array_map(
            static fn (array $item): int => (int) ($item['_document_weight'] ?? 1),
            $items
        ));
    }

    private function weight(array $item, int $charsPerLine): int
    {
        $description = trim((string) ($item['description'] ?? ''));
        $textLines = max(1, substr_count($description, "\n") + 1);
        $wrappedLines = max(1, (int) ceil(mb_strlen($description) / $charsPerLine));

        return min(5, max($textLines, $wrappedLines));
    }
}

