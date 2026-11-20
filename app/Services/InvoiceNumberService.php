<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\UserFacingException;
use PDO;

final class InvoiceNumberService
{
    /**
     * Reserves the next invoice number for the given issuer and year.
     *
     * The number is derived from the invoices that already exist (soft-deleted ones
     * included, because their numbers are never reused) instead of a stored counter.
     * This keeps every year's sequence independent, so back-dated invoices cannot
     * corrupt the numbering of the current year.
     *
     * Must be called inside a transaction: the issuer row is locked to serialise
     * concurrent reservations for the same issuer.
     */
    public function reserve(int $issuerId, int $userId, int $year): string
    {
        $statement = Database::connection()->prepare(
            'SELECT id, invoice_prefix
             FROM issuers
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
             FOR UPDATE'
        );
        $statement->execute(['id' => $issuerId, 'user_id' => $userId]);
        $issuer = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$issuer) {
            throw new UserFacingException('გამცემი ვერ მოიძებნა.');
        }

        $prefix = ($issuer['invoice_prefix'] ?: 'INV') . '-' . $year . '-';
        $last = Database::connection()->prepare(
            'SELECT MAX(CAST(SUBSTRING(invoice_number, :offset) AS UNSIGNED))
             FROM invoices
             WHERE issuer_id = :issuer_id AND LEFT(invoice_number, :length) = :prefix'
        );
        $last->execute([
            'offset' => mb_strlen($prefix) + 1,
            'issuer_id' => $issuerId,
            'length' => mb_strlen($prefix),
            'prefix' => $prefix,
        ]);

        return $prefix . sprintf('%04d', ((int) $last->fetchColumn()) + 1);
    }
}
