<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class LoginThrottle
{
    /** Failed attempts allowed per e-mail + IP pair inside the window. */
    private const MAX_ATTEMPTS = 5;
    /** Failed attempts allowed per IP (any e-mail) inside the window: stops password spraying. */
    private const MAX_ATTEMPTS_PER_IP = 30;
    private const WINDOW_MINUTES = 15;
    /** Rows older than this are deleted; they no longer influence any decision. */
    private const KEEP_HOURS = 24;

    public function tooManyAttempts(string $email, string $ip): bool
    {
        $ip = substr($ip, 0, 45);
        $window = 'attempted_at >= DATE_SUB(NOW(), INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)';

        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE email = :email AND ip_address = :ip AND {$window}"
        );
        $statement->execute(['email' => mb_strtolower(trim($email)), 'ip' => $ip]);
        if ((int) $statement->fetchColumn() >= self::MAX_ATTEMPTS) {
            return true;
        }

        $statement = Database::connection()->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip AND {$window}"
        );
        $statement->execute(['ip' => $ip]);

        return (int) $statement->fetchColumn() >= self::MAX_ATTEMPTS_PER_IP;
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

        if (random_int(1, 20) === 1) {
            $this->purgeOld();
        }
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

    /** Deletes attempts that are outside every window. Returns the number of removed rows. */
    public function purgeOld(): int
    {
        return Database::connection()->exec(
            'DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ' . self::KEEP_HOURS . ' HOUR)'
        );
    }
}
