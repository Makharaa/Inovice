<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class LoginThrottle
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    public function tooManyAttempts(string $email, string $ip): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = :email AND ip_address = :ip
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)'
        );
        $statement->execute([
            'email' => mb_strtolower(trim($email)),
            'ip' => substr($ip, 0, 45),
        ]);
        return (int) $statement->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public function record(string $email, string $ip): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip)'
        );
        $statement->execute([
            'email' => mb_strtolower(trim($email)),
            'ip' => substr($ip, 0, 45),
        ]);
    }

    public function clear(string $email, string $ip): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM login_attempts WHERE email = :email AND ip_address = :ip'
        );
        $statement->execute([
            'email' => mb_strtolower(trim($email)),
            'ip' => substr($ip, 0, 45),
        ]);
    }
}

