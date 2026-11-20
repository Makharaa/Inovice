<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public static function page(): int
    {
        return max(1, (int) ($_GET['page'] ?? 1));
    }

    public static function meta(int $total, int $perPage, int $page): array
    {
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        return [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }
}

