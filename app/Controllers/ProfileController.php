<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\User;
use App\Services\LoginThrottle;
use App\Support\Validation;

final class ProfileController extends Controller
{
    public function edit(): void
    {
        $user = $this->requireAuth();
        $this->render('users.profile', ['pageTitle' => 'ჩემი პროფილი', 'profile' => $user]);
    }

    public function update(): void
    {
        $user = $this->requireAuth();
        $this->validateCsrf();
        $input = $_POST;
        $errors = Validation::required($input, ['name' => 'სახელი', 'email' => 'ელფოსტა']);
        $errors += Validation::email((string) ($input['email'] ?? ''));

        if (User::emailExists((string) ($input['email'] ?? ''), (int) $user['id'])) {
            $errors['email'] = 'ეს ელფოსტა უკვე გამოყენებულია.';
        }

        // The e-mail address is the login and the recovery identity: changing it needs the current password.
        $newEmail = mb_strtolower(trim((string) ($input['email'] ?? '')));
        if (!$errors && $newEmail !== mb_strtolower((string) $user['email'])
            && !$this->currentPasswordIsCorrect($user, (string) ($input['current_password'] ?? ''))) {
            $errors['current_password'] = 'ელფოსტის შესაცვლელად შეიყვანეთ მიმდინარე პაროლი სწორად.';
        }

        if ($errors) {
            set_errors($errors);
            keep_old($input);
            redirect('profile');
        }

        $statement = Database::connection()->prepare(
            'UPDATE users SET name = :name, email = :email, updated_at = NOW() WHERE id = :id'
        );
        $statement->execute([
            'id' => $user['id'],
            'name' => trim((string) $input['name']),
            'email' => mb_strtolower(trim((string) $input['email'])),
        ]);
        flash('success', 'პროფილი განახლდა.');
        redirect('profile');
    }

    public function password(): void
    {
        $user = $this->requireAuth();
        $this->validateCsrf();
        $password = (string) ($_POST['password'] ?? '');

        if (!$this->currentPasswordIsCorrect($user, (string) ($_POST['current_password'] ?? ''))) {
            flash('error', 'მიმდინარე პაროლი არასწორია.');
            redirect('profile');
        }

        if (mb_strlen($password) < 8 || $password !== (string) ($_POST['password_confirmation'] ?? '')) {
            flash('error', 'პაროლი უნდა შეიცავდეს მინიმუმ 8 სიმბოლოს და დადასტურება უნდა ემთხვეოდეს.');
            redirect('profile');
        }

        User::updatePassword((int) $user['id'], $password);
        Auth::refreshAfterPasswordChange(); // this session stays signed in, all others are ended
        flash('success', 'პაროლი შეიცვალა. სხვა მოწყობილობებზე გახსნილი სესიები დაიხურა.');
        redirect('profile');
    }

    /** Verifies the current password, with the same brute-force limit as the login form. */
    private function currentPasswordIsCorrect(array $user, string $password): bool
    {
        $throttle = new LoginThrottle();
        $key = 'profile:' . $user['email'];
        $ip = client_ip();

        if ($throttle->tooManyAttempts($key, $ip)) {
            return false;
        }

        if (User::verifyPassword((int) $user['id'], $password)) {
            $throttle->clear($key, $ip);
            return true;
        }

        $throttle->record($key, $ip);
        return false;
    }
}

