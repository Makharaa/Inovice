<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    private static ?array $resolvedUser = null;
    private static bool $resolved = false;

    public static function attempt(string $email, string $password): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users
             WHERE email = :email AND is_active = 1 AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['email' => mb_strtolower(trim($email))]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        unset($_SESSION['_csrf']); // never carry the pre-login CSRF token into the authenticated session
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['pw_fp'] = self::fingerprint((string) $user['password_hash']);
        unset($user['password_hash']);
        self::$resolvedUser = $user;
        self::$resolved = true;

        $update = Database::connection()->prepare(
            'UPDATE users SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id'
        );
        $update->execute([
            'id' => $user['id'],
            'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);

        return true;
    }

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$resolvedUser;
        }

        self::$resolved = true;
        $id = (int) ($_SESSION['user_id'] ?? 0);

        if ($id < 1) {
            return null;
        }

        $statement = Database::connection()->prepare(
            'SELECT id, name, email, role, is_active, last_login_at, last_login_ip, password_hash
             FROM users WHERE id = :id AND is_active = 1 AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        // A changed password ends every session that was opened with the old one.
        if (!$user || !hash_equals(self::fingerprint((string) $user['password_hash']), (string) ($_SESSION['pw_fp'] ?? ''))) {
            self::logout();
            return null;
        }
        unset($user['password_hash']);

        self::$resolvedUser = $user;
        return $user;
    }

    public static function id(): ?int
    {
        return self::user() ? (int) self::$resolvedUser['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    /**
     * Call after the signed-in user's own password changed: keeps this session alive
     * (all other sessions of the user become invalid) and starts a fresh session id and CSRF token.
     */
    public static function refreshAfterPasswordChange(): void
    {
        $id = (int) ($_SESSION['user_id'] ?? 0);
        $statement = Database::connection()->prepare(
            'SELECT password_hash FROM users WHERE id = :id AND is_active = 1 AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $id]);
        $hash = $statement->fetchColumn();

        if ($hash === false) {
            self::logout();
            return;
        }

        $_SESSION['pw_fp'] = self::fingerprint((string) $hash);
        unset($_SESSION['_csrf']);
        session_regenerate_id(true);
    }

    public static function logout(): void
    {
        self::$resolved = true;
        self::$resolvedUser = null;
        unset($_SESSION['user_id'], $_SESSION['pw_fp'], $_SESSION['_csrf']);
        session_regenerate_id(true);
    }

    private static function fingerprint(string $passwordHash): string
    {
        return hash('sha256', $passwordHash);
    }
}

