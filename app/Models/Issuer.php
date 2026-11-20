<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Issuer
{
    public static function allForUser(int $userId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM issuers WHERE user_id = :user_id AND deleted_at IS NULL';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY is_default DESC, name ASC';

        $statement = Database::connection()->prepare($sql);
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findOwned(int $id, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM issuers
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        $issuer = $statement->fetch(PDO::FETCH_ASSOC);
        return $issuer ?: null;
    }

    public static function create(int $userId, array $data): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if (!empty($data['is_default'])) {
                $clear = $pdo->prepare('UPDATE issuers SET is_default = 0 WHERE user_id = :user_id');
                $clear->execute(['user_id' => $userId]);
            }

            $statement = $pdo->prepare(
                'INSERT INTO issuers (
                    user_id, entity_type, name, identification_number, address, email, phone,
                    bank_name, bank_code, bank_account, is_vat_payer, invoice_prefix,
                    default_language, default_template, is_default, is_active
                ) VALUES (
                    :user_id, :entity_type, :name, :identification_number, :address, :email, :phone,
                    :bank_name, :bank_code, :bank_account, :is_vat_payer, :invoice_prefix,
                    :default_language, :default_template, :is_default, :is_active
                )'
            );
            $statement->execute(self::payload($userId, $data));
            $id = (int) $pdo->lastInsertId();

            if (empty($data['is_default'])) {
                $count = $pdo->prepare(
                    'SELECT COUNT(*) FROM issuers WHERE user_id = :user_id AND deleted_at IS NULL'
                );
                $count->execute(['user_id' => $userId]);
                if ((int) $count->fetchColumn() === 1) {
                    $pdo->prepare('UPDATE issuers SET is_default = 1 WHERE id = :id')->execute(['id' => $id]);
                }
            }

            $pdo->commit();
            return $id;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function update(int $id, int $userId, array $data): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            if (!empty($data['is_default'])) {
                $clear = $pdo->prepare('UPDATE issuers SET is_default = 0 WHERE user_id = :user_id');
                $clear->execute(['user_id' => $userId]);
            }

            $payload = self::payload($userId, $data);
            $payload['id'] = $id;
            $statement = $pdo->prepare(
                'UPDATE issuers SET
                    entity_type = :entity_type, name = :name,
                    identification_number = :identification_number, address = :address,
                    email = :email, phone = :phone, bank_name = :bank_name,
                    bank_code = :bank_code, bank_account = :bank_account,
                    is_vat_payer = :is_vat_payer, invoice_prefix = :invoice_prefix,
                    default_language = :default_language, default_template = :default_template,
                    is_default = :is_default, is_active = :is_active, updated_at = NOW()
                 WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
            );
            $statement->execute($payload);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function softDelete(int $id, int $userId): bool
    {
        $used = Database::connection()->prepare(
            'SELECT COUNT(*) FROM invoices
             WHERE issuer_id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $used->execute(['id' => $id, 'user_id' => $userId]);

        if ((int) $used->fetchColumn() > 0) {
            return false;
        }

        $statement = Database::connection()->prepare(
            'UPDATE issuers SET deleted_at = NOW(), is_active = 0
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        return $statement->rowCount() > 0;
    }

    public static function snapshot(array $issuer): array
    {
        return array_intersect_key($issuer, array_flip([
            'entity_type', 'name', 'identification_number', 'address', 'email', 'phone',
            'bank_name', 'bank_code', 'bank_account', 'is_vat_payer',
        ]));
    }

    private static function payload(int $userId, array $data): array
    {
        $prefix = strtoupper((string) preg_replace('/[^A-Za-z0-9_-]/', '', $data['invoice_prefix'] ?? 'INV'));

        return [
            'user_id' => $userId,
            'entity_type' => $data['entity_type'],
            'name' => trim((string) $data['name']),
            'identification_number' => trim((string) $data['identification_number']),
            'address' => trim((string) ($data['address'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'bank_name' => trim((string) ($data['bank_name'] ?? '')),
            'bank_code' => trim((string) ($data['bank_code'] ?? '')),
            'bank_account' => trim((string) ($data['bank_account'] ?? '')),
            'is_vat_payer' => !empty($data['is_vat_payer']) ? 1 : 0,
            'invoice_prefix' => $prefix !== '' ? substr($prefix, 0, 20) : 'INV',
            'default_language' => in_array($data['default_language'] ?? '', ['ka', 'en', 'ru'], true)
                ? $data['default_language']
                : 'ka',
            'default_template' => in_array($data['default_template'] ?? '', ['standard', 'modern', 'compact'], true)
                ? $data['default_template']
                : 'standard',
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];
    }
}

