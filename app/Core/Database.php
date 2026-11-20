<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static array $config = [];
    private static ?PDO $connection = null;

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = self::$config['host'] ?? '127.0.0.1';
        $port = self::$config['port'] ?? 3306;
        $database = self::$config['name'] ?? '';
        $charset = self::$config['charset'] ?? 'utf8mb4';

        try {
            self::$connection = new PDO(
                "mysql:host={$host};port={$port};dbname={$database};charset={$charset}",
                (string) (self::$config['user'] ?? ''),
                (string) (self::$config['password'] ?? ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed.', 0, $exception);
        }

        return self::$connection;
    }
}

