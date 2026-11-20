<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;

function config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['app_config'] ?? [];

    if ($key === null) {
        return $config;
    }

    foreach (explode('.', $key) as $segment) {
        if (!is_array($config) || !array_key_exists($segment, $config)) {
            return $default;
        }
        $config = $config[$segment];
    }

    return $config;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) config('app.url', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $relativePath = ltrim($path, '/');
    $assetUrl = url('assets/' . $relativePath);
    $localFile = BASE_PATH . '/public/assets/' . $relativePath;

    return is_file($localFile)
        ? $assetUrl . '?v=' . filemtime($localFile)
        : $assetUrl;
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function view(string $view, array $data = [], ?string $layout = 'layouts/app'): void
{
    $viewFile = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';

    if (!is_file($viewFile)) {
        throw new RuntimeException("View not found: {$view}");
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $viewFile;
    $content = (string) ob_get_clean();

    if ($layout === null) {
        echo $content;
        return;
    }

    $layoutFile = BASE_PATH . '/app/Views/' . str_replace('.', '/', $layout) . '.php';
    require $layoutFile;
}

function flash(string $key, mixed $value = null): mixed
{
    if (func_num_args() === 2) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    $result = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $result;
}

function keep_old(array $input): void
{
    unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['_token']);
    $_SESSION['_old'] = $input;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

function set_errors(array $errors): void
{
    $_SESSION['_errors'] = $errors;
}

function errors(): array
{
    $result = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);
    return is_array($result) ? $result : [];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function current_user(): ?array
{
    return Auth::user();
}

function is_admin(): bool
{
    return Auth::isAdmin();
}

function money(float|int|string|null $amount, string $currency = 'GEL'): string
{
    return number_format((float) $amount, 2, '.', ' ') . ' ' . e($currency);
}

function selected(mixed $actual, mixed $expected): string
{
    return (string) $actual === (string) $expected ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

function invoice_label(array $labels, string $key): string
{
    return (string) ($labels[$key] ?? $key);
}


/**
 * Allowed invoice units (key => Georgian label), taken from the Georgian language file.
 */
function invoice_units(): array
{
    static $units = null;
    if ($units === null) {
        $labels = require BASE_PATH . '/app/Lang/ka/invoice.php';
        $units = $labels['units'];
    }

    return $units;
}
