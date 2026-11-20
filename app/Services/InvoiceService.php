<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Client;
use App\Models\Issuer;
use App\Models\Invoice;
use App\Support\UserFacingException;

final class InvoiceService
{
    public function create(int $userId, array $input): int
    {
        $issuer = Issuer::findOwned((int) ($input['issuer_id'] ?? 0), $userId);
        $client = Client::findOwned((int) ($input['client_id'] ?? 0), $userId);

        if (!$issuer || !$client) {
            throw new UserFacingException('გამცემი ან მიმღები ვერ მოიძებნა.');
        }

        $calculation = (new InvoiceCalculator())->calculate(
            $input['items'] ?? [],
            (bool) $issuer['is_vat_payer'],
            !empty($input['prices_include_vat'])
        );

        if (!$calculation['items']) {
            throw new UserFacingException('ინვოისს მინიმუმ ერთი პოზიცია უნდა ჰქონდეს.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $issueDate = $this->validDate((string) ($input['issue_date'] ?? '')) ?: date('Y-m-d');
            $number = (new InvoiceNumberService())->reserve(
                (int) $issuer['id'],
                $userId,
                (int) substr($issueDate, 0, 4)
            );

            $statement = $pdo->prepare(
                'INSERT INTO invoices (
                    user_id, issuer_id, client_id, invoice_number, language, template_key,
                    issue_date, due_date, currency, prices_include_vat, vat_rate, status,
                    issuer_snapshot, client_snapshot, subtotal, discount_total, vat_total, total, notes
                 ) VALUES (
                    :user_id, :issuer_id, :client_id, :invoice_number, :language, :template_key,
                    :issue_date, :due_date, :currency, :prices_include_vat, :vat_rate, :status,
                    :issuer_snapshot, :client_snapshot, :subtotal, :discount_total, :vat_total, :total, :notes
                 )'
            );
            $statement->execute($this->invoicePayload(
                $userId,
                $issuer,
                $client,
                $input,
                $calculation,
                $number,
                $issueDate
            ));
            $invoiceId = (int) $pdo->lastInsertId();
            $this->insertItems($invoiceId, $calculation['items']);
            $pdo->commit();
            return $invoiceId;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function update(int $invoiceId, int $userId, array $input): void
    {
        $current = Invoice::findAccessible($invoiceId, $userId, false);
        if (!$current) {
            throw new UserFacingException('ინვოისი ვერ მოიძებნა.');
        }
        if (Invoice::isLocked($current)) {
            throw new UserFacingException(Invoice::LOCKED_MESSAGE);
        }

        $issuer = Issuer::findOwned((int) ($input['issuer_id'] ?? 0), $userId);
        $client = Client::findOwned((int) ($input['client_id'] ?? 0), $userId);
        if (!$issuer || !$client) {
            throw new UserFacingException('გამცემი ან მიმღები ვერ მოიძებნა.');
        }

        $calculation = (new InvoiceCalculator())->calculate(
            $input['items'] ?? [],
            (bool) $issuer['is_vat_payer'],
            !empty($input['prices_include_vat'])
        );
        if (!$calculation['items']) {
            throw new UserFacingException('ინვოისს მინიმუმ ერთი პოზიცია უნდა ჰქონდეს.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            // Re-check under a row lock: the invoice may have been issued in another request meanwhile.
            $lock = $pdo->prepare(
                'SELECT status FROM invoices WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL FOR UPDATE'
            );
            $lock->execute(['id' => $invoiceId, 'user_id' => $userId]);
            if ($lock->fetchColumn() !== 'draft') {
                throw new UserFacingException(Invoice::LOCKED_MESSAGE);
            }

            $payload = $this->invoicePayload(
                $userId,
                $issuer,
                $client,
                $input,
                $calculation,
                (string) $current['invoice_number'],
                $this->validDate((string) ($input['issue_date'] ?? '')) ?: $current['issue_date']
            );
            unset($payload['user_id'], $payload['invoice_number']);
            $payload['id'] = $invoiceId;
            $payload['owner_id'] = $userId;

            $statement = $pdo->prepare(
                'UPDATE invoices SET
                    issuer_id = :issuer_id, client_id = :client_id, language = :language,
                    template_key = :template_key, issue_date = :issue_date, due_date = :due_date,
                    currency = :currency, prices_include_vat = :prices_include_vat,
                    vat_rate = :vat_rate, status = :status, issuer_snapshot = :issuer_snapshot,
                    client_snapshot = :client_snapshot, subtotal = :subtotal,
                    discount_total = :discount_total, vat_total = :vat_total,
                    total = :total, notes = :notes, updated_at = NOW()
                 WHERE id = :id AND user_id = :owner_id AND deleted_at IS NULL AND status = \'draft\''
            );
            $statement->execute($payload);
            $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id = :id')->execute(['id' => $invoiceId]);
            $this->insertItems($invoiceId, $calculation['items']);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function duplicate(int $invoiceId, int $userId): int
    {
        $invoice = Invoice::findAccessible($invoiceId, $userId, false);
        if (!$invoice) {
            throw new UserFacingException('ინვოისი ვერ მოიძებნა.');
        }

        return $this->create($userId, [
            'issuer_id' => $invoice['issuer_id'],
            'client_id' => $invoice['client_id'],
            'language' => $invoice['language'],
            'template_key' => $invoice['template_key'],
            'issue_date' => date('Y-m-d'),
            'due_date' => null,
            'currency' => $invoice['currency'],
            'prices_include_vat' => $invoice['prices_include_vat'],
            'status' => 'draft',
            'notes' => $invoice['notes'],
            'items' => array_map(static fn (array $item): array => [
                'description' => $item['description'],
                'unit' => $item['unit'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_percent' => $item['discount_percent'],
            ], $invoice['items']),
        ]);
    }

    private function invoicePayload(
        int $userId,
        array $issuer,
        array $client,
        array $input,
        array $calculation,
        string $number,
        string $issueDate
    ): array {
        $language = in_array($input['language'] ?? '', ['ka', 'en', 'ru'], true)
            ? $input['language']
            : $issuer['default_language'];
        $template = in_array($input['template_key'] ?? '', ['standard', 'modern', 'compact'], true)
            ? $input['template_key']
            : $issuer['default_template'];
        $currency = in_array($input['currency'] ?? '', ['GEL', 'USD', 'EUR'], true)
            ? $input['currency']
            : 'GEL';
        $status = ($input['status'] ?? '') === 'issued' ? 'issued' : 'draft';

        return [
            'user_id' => $userId,
            'issuer_id' => $issuer['id'],
            'client_id' => $client['id'],
            'invoice_number' => $number,
            'language' => $language,
            'template_key' => $template,
            'issue_date' => $issueDate,
            'due_date' => $this->validDate((string) ($input['due_date'] ?? '')),
            'currency' => $currency,
            'prices_include_vat' => !empty($input['prices_include_vat']) ? 1 : 0,
            'vat_rate' => $issuer['is_vat_payer'] ? 18.00 : 0.00,
            'status' => $status,
            'issuer_snapshot' => json_encode(Issuer::snapshot($issuer), JSON_UNESCAPED_UNICODE),
            'client_snapshot' => json_encode(Client::snapshot($client), JSON_UNESCAPED_UNICODE),
            'subtotal' => $calculation['subtotal'],
            'discount_total' => $calculation['discount_total'],
            'vat_total' => $calculation['vat_total'],
            'total' => $calculation['total'],
            'notes' => trim((string) ($input['notes'] ?? '')),
        ];
    }

    private function insertItems(int $invoiceId, array $items): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO invoice_items (
                invoice_id, sort_order, description, unit, quantity, unit_price,
                discount_percent, net_total, vat_total, line_total
             ) VALUES (
                :invoice_id, :sort_order, :description, :unit, :quantity, :unit_price,
                :discount_percent, :net_total, :vat_total, :line_total
             )'
        );

        foreach ($items as $item) {
            $item['invoice_id'] = $invoiceId;
            $statement->execute($item);
        }
    }

    private function validDate(string $date): ?string
    {
        $object = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $object && $object->format('Y-m-d') === $date ? $date : null;
    }
}
