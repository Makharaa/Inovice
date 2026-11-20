<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use PDO;

final class Invoice
{
    public static function findAccessible(int $id, int $userId, bool $admin = false): ?array
    {
        $sql = 'SELECT invoices.*, users.name AS owner_name
                FROM invoices
                JOIN users ON users.id = invoices.user_id
                WHERE invoices.id = :id AND invoices.deleted_at IS NULL';
        $parameters = ['id' => $id];

        if (!$admin) {
            $sql .= ' AND invoices.user_id = :user_id';
            $parameters['user_id'] = $userId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        $invoice = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            return null;
        }

        $items = Database::connection()->prepare(
            'SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY sort_order, id'
        );
        $items->execute(['invoice_id' => $id]);
        $invoice['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
        $invoice['issuer_snapshot'] = json_decode((string) $invoice['issuer_snapshot'], true) ?: [];
        $invoice['client_snapshot'] = json_decode((string) $invoice['client_snapshot'], true) ?: [];

        return $invoice;
    }

    public static function paginated(
        int $userId,
        bool $admin,
        array $filters,
        int $limit,
        int $offset
    ): array {
        [$where, $parameters] = self::filterSql($userId, $admin, $filters);
        $sql = 'SELECT invoices.*, users.name AS owner_name,
                       JSON_UNQUOTE(JSON_EXTRACT(invoices.client_snapshot, "$.name")) AS client_name
                FROM invoices
                JOIN users ON users.id = invoices.user_id
                ' . $where . '
                ORDER BY invoices.issue_date DESC, invoices.id DESC
                LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function count(int $userId, bool $admin, array $filters): int
    {
        [$where, $parameters] = self::filterSql($userId, $admin, $filters);
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM invoices ' . $where);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    public static function dashboardStats(int $userId, bool $admin): array
    {
        $scope = $admin ? 'deleted_at IS NULL' : 'user_id = :user_id AND deleted_at IS NULL';
        $parameters = $admin ? [] : ['user_id' => $userId];
        $statement = Database::connection()->prepare(
            "SELECT
                COUNT(*) AS total_invoices,
                SUM(CASE WHEN DATE_FORMAT(issue_date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m') THEN 1 ELSE 0 END) AS month_invoices,
                SUM(CASE WHEN DATE_FORMAT(issue_date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m') AND currency = 'GEL' THEN total ELSE 0 END) AS month_total_gel,
                COUNT(DISTINCT client_id) AS clients_count
             FROM invoices WHERE {$scope}"
        );
        $statement->execute($parameters);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public static function recent(int $userId, bool $admin, int $limit = 6): array
    {
        $scope = $admin ? 'invoices.deleted_at IS NULL' : 'invoices.user_id = :user_id AND invoices.deleted_at IS NULL';
        $parameters = $admin ? [] : ['user_id' => $userId];
        $sql = 'SELECT invoices.*, users.name AS owner_name,
                       JSON_UNQUOTE(JSON_EXTRACT(invoices.client_snapshot, "$.name")) AS client_name
                FROM invoices JOIN users ON users.id = invoices.user_id
                WHERE ' . $scope . '
                ORDER BY invoices.created_at DESC LIMIT ' . (int) $limit;
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function softDelete(int $id, int $userId): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE invoices SET deleted_at = NOW()
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        return $statement->rowCount() > 0;
    }

    private static function filterSql(int $userId, bool $admin, array $filters): array
    {
        $clauses = ['invoices.deleted_at IS NULL'];
        $parameters = [];

        if (!$admin) {
            $clauses[] = 'invoices.user_id = :user_id';
            $parameters['user_id'] = $userId;
        } elseif (!empty($filters['user_id'])) {
            $clauses[] = 'invoices.user_id = :filter_user_id';
            $parameters['filter_user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['search'])) {
            $clauses[] = '(invoices.invoice_number LIKE :search
                OR JSON_UNQUOTE(JSON_EXTRACT(invoices.client_snapshot, "$.name")) LIKE :search)';
            $parameters['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['draft', 'issued'], true)) {
            $clauses[] = 'invoices.status = :status';
            $parameters['status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $clauses[] = 'invoices.issue_date >= :date_from';
            $parameters['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $clauses[] = 'invoices.issue_date <= :date_to';
            $parameters['date_to'] = $filters['date_to'];
        }

        return ['WHERE ' . implode(' AND ', $clauses), $parameters];
    }
}

