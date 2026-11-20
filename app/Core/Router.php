<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, array $handler): void
    {
        $normalized = '/' . trim($path, '/');
        $normalized = $normalized === '//' ? '/' : $normalized;
        $this->routes[] = compact('method', 'normalized', 'handler');
    }

    public function dispatch(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
        $uri = $uri === '//' ? '/' : $uri;

        $basePath = (string) parse_url((string) config('app.url', ''), PHP_URL_PATH);
        if ($basePath && $basePath !== '/' && str_starts_with($uri, rtrim($basePath, '/'))) {
            $uri = substr($uri, strlen(rtrim($basePath, '/'))) ?: '/';
        }

        if ($uri === '/index.php') {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[0-9]+)', $route['normalized']);
            if (!preg_match('#^' . $pattern . '$#', $uri, $matches)) {
                continue;
            }

            $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$controllerClass, $action] = $route['handler'];
            $controller = new $controllerClass();
            $controller->{$action}(...array_values($parameters));
            return;
        }

        http_response_code(404);
        view('errors.404', ['pageTitle' => 'გვერდი ვერ მოიძებნა']);
    }
}
