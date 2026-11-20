<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Client
{
    public static function allForUser(int $userId, string $search = '', int $limit = 0, int $offset = 0): array
    {
        $parameters = ['user_id' => $userId];
        $sql = 'SELECT * FROM clients WHERE user_id = :user_id AND deleted_at IS NULL';

        if ($search !== '') {
            $sql .= ' AND (name LIKE :search OR identification_number LIKE :search OR email LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY updated_at DESC, name ASC';

        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function countForUser(int $userId, string $search = ''): int
    {
        $parameters = ['user_id' => $userId];
        $sql = 'SELECT COUNT(*) FROM clients WHERE user_id = :user_id AND deleted_at IS NULL';

        if ($search !== '') {
            $sql .= ' AND (name LIKE :search OR identification_number LIKE :search OR email LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    public static function findOwned(int $id, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM clients
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        $client = $statement->fetch(PDO::FETCH_ASSOC);
        return $client ?: null;
    }

    public static function create(int $userId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO clients (
                user_id, entity_type, name, identification_number, address, email, phone
             ) VALUES (
                :user_id, :entity_type, :name, :identification_number, :address, :email, :phone
             )'
        );
        $statement->execute(self::payload($userId, $data));
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, int $userId, array $data): void
    {
        $payload = self::payload($userId, $data);
        $payload['id'] = $id;

        $statement = Database::connection()->prepare(
            'UPDATE clients SET
                entity_type = :entity_type, name = :name,
                identification_number = :identification_number, address = :address,
                email = :email, phone = :phone, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $statement->execute($payload);
    }

    public static function softDelete(int $id, int $userId): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE clients SET deleted_at = NOW()
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        return $statement->rowCount() > 0;
    }

    public static function snapshot(array $client): array
    {
        return array_intersect_key($client, array_flip([
            'entity_type', 'name', 'identification_number', 'address', 'email', 'phone',
        ]));
    }

    private static function payload(int $userId, array $data): array
    {
        return [
            'user_id' => $userId,
            'entity_type' => $data['entity_type'],
            'name' => trim((string) $data['name']),
            'identification_number' => trim((string) $data['identification_number']),
            'address' => trim((string) ($data['address'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
        ];
    }
}

