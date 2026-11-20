<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $viewName, array $data = [], ?string $layout = 'layouts/app'): void
    {
        view($viewName, $data, $layout);
    }

    protected function requireAuth(): array
    {
        $user = Auth::user();

        if (!$user) {
            flash('error', 'გთხოვთ, გაიაროთ ავტორიზაცია.');
            redirect('login');
        }

        return $user;
    }

    protected function requireAdmin(): array
    {
        $user = $this->requireAuth();

        if (($user['role'] ?? '') !== 'admin') {
            http_response_code(403);
            $this->render('errors.403', ['pageTitle' => 'წვდომა აკრძალულია']);
            exit;
        }

        return $user;
    }

    protected function validateCsrf(): void
    {
        if (!Csrf::verify($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('CSRF token mismatch. Please refresh the page.');
        }
    }
}

