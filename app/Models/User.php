<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class User
{
    public static function all(string $search = ''): array
    {
        $parameters = [];
        $sql = 'SELECT id, name, email, role, is_active, last_login_at, last_login_ip, created_at
                FROM users WHERE deleted_at IS NULL';

        if ($search !== '') {
            $sql .= ' AND (name LIKE :search OR email LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY created_at DESC';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function activeOptions(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, name FROM users
             WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name'
        );
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, email, role, is_active, last_login_at, last_login_ip, created_at
             FROM users WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email AND deleted_at IS NULL';
        $parameters = ['email' => mb_strtolower(trim($email))];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn() > 0;
    }

    public static function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_active)
             VALUES (:name, :email, :password_hash, :role, :is_active)'
        );
        $statement->execute([
            'name' => trim((string) $data['name']),
            'email' => mb_strtolower(trim((string) $data['email'])),
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'role' => ($data['role'] ?? '') === 'admin' ? 'admin' : 'user',
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET name = :name, email = :email, role = :role,
                is_active = :is_active, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $id,
            'name' => trim((string) $data['name']),
            'email' => mb_strtolower(trim((string) $data['email'])),
            'role' => ($data['role'] ?? '') === 'admin' ? 'admin' : 'user',
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);
    }

    public static function verifyPassword(int $id, string $password): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT password_hash FROM users WHERE id = :id AND is_active = 1 AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id]);
        $hash = $statement->fetchColumn();

        return is_string($hash) && password_verify($password, $hash);
    }

    public static function updatePassword(int $id, string $password): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $id,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public static function toggle(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id]);
    }

    public static function softDelete(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET
                email = CONCAT("deleted+", id, "+", LEFT(email, 150)),
                deleted_at = NOW(), is_active = 0, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id]);
    }
}
