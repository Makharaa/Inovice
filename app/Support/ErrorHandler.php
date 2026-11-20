<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * Last line of defence: logs any uncaught exception or fatal error to storage/logs
 * and shows a neutral 500 page with a reference code instead of a blank page or a stack trace.
 */
final class ErrorHandler
{
    private static bool $rendering = false;

    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(Throwable $exception): void
    {
        $reference = self::log(
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile() . ':' . $exception->getLine(),
            $exception->getTraceAsString()
        );
        self::render($reference);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

        if ($error === null || ($error['type'] & $fatal) === 0) {
            return;
        }

        $reference = self::log('Fatal error', $error['message'], $error['file'] . ':' . $error['line'], '');
        self::render($reference);
    }

    private static function log(string $type, string $message, string $location, string $trace): string
    {
        $reference = bin2hex(random_bytes(4));
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH); // no query string: may hold search terms
        $entry = sprintf(
            "[%s] #%s %s %s | %s: %s | %s\n%s",
            date('Y-m-d H:i:s'),
            $reference,
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
            $path,
            $type,
            $message,
            $location,
            $trace !== '' ? $trace . "\n" : ''
        );

        $directory = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/logs';
        $written = is_dir($directory)
            && @file_put_contents($directory . '/app-' . date('Y-m-d') . '.log', $entry . "\n", FILE_APPEND | LOCK_EX) !== false;

        if (!$written) {
            error_log('[Makhara Invoice] ' . trim($entry));
        }

        return $reference;
    }

    private static function render(string $reference): void
    {
        if (self::$rendering) {
            return;
        }
        self::$rendering = true;

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
        }

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Internal error (reference {$reference}). See storage/logs.\n");
            return;
        }

        $view = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/app/Views/errors/500.php';
        if (is_file($view)) {
            require $view;
        } else {
            echo 'Internal server error. Reference: ' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8');
        }
    }
}
