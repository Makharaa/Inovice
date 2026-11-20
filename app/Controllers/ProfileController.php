<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\User;
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

        if (mb_strlen($password) < 8 || $password !== (string) ($_POST['password_confirmation'] ?? '')) {
            flash('error', 'პაროლი უნდა შეიცავდეს მინიმუმ 8 სიმბოლოს და დადასტურება უნდა ემთხვეოდეს.');
            redirect('profile');
        }

        User::updatePassword((int) $user['id'], $password);
        flash('success', 'პაროლი შეიცვალა.');
        redirect('profile');
    }
}

