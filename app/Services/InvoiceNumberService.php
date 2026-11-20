<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\UserFacingException;
use PDO;

final class InvoiceNumberService
{
    public function reserve(int $issuerId, int $userId, int $year): string
    {
        $statement = Database::connection()->prepare(
            'SELECT id, invoice_prefix, next_invoice_number, numbering_year
             FROM issuers
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
             FOR UPDATE'
        );
        $statement->execute(['id' => $issuerId, 'user_id' => $userId]);
        $issuer = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$issuer) {
            throw new UserFacingException('გამცემი ვერ მოიძებნა.');
        }

        $next = (int) $issuer['next_invoice_number'];
        if ((int) $issuer['numbering_year'] !== $year) {
            $next = 1;
        }

        $number = sprintf('%s-%d-%04d', $issuer['invoice_prefix'] ?: 'INV', $year, $next);

        $update = Database::connection()->prepare(
            'UPDATE issuers
             SET next_invoice_number = :next_number, numbering_year = :year, updated_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            'next_number' => $next + 1,
            'year' => $year,
            'id' => $issuerId,
        ]);

        return $number;
    }
}
