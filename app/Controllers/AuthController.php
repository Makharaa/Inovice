<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\LoginThrottle;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('dashboard');
        }

        $this->render('auth.login', [
            'pageTitle' => 'შესვლა',
            'errorMessage' => flash('error'),
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $this->validateCsrf();
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ip = client_ip();
        $throttle = new LoginThrottle();

        if ($throttle->tooManyAttempts($email, $ip)) {
            keep_old(['email' => $email]);
            flash('error', 'შესვლის მცდელობების ლიმიტი ამოიწურა. სცადეთ 15 წუთში.');
            redirect('login');
        }

        if (!Auth::attempt($email, $password)) {
            $throttle->record($email, $ip);
            keep_old(['email' => $email]);
            flash('error', 'ელფოსტა ან პაროლი არასწორია.');
            redirect('login');
        }

        $throttle->clear($email, $ip);
        clear_old();
        redirect('dashboard');
    }

    public function logout(): void
    {
        $this->validateCsrf();
        Auth::logout();
        redirect('login');
    }
}
