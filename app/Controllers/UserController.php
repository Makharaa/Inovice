<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;
use App\Support\Validation;

final class UserController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        $search = trim((string) ($_GET['search'] ?? ''));
        $this->render('users.index', [
            'pageTitle' => 'მომხმარებლები',
            'users' => User::all($search),
            'search' => $search,
        ]);
    }

    public function create(): void
    {
        $this->requireAdmin();
        $this->render('users.form', ['pageTitle' => 'მომხმარებლის დამატება', 'editUser' => null]);
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->validateCsrf();
        $input = $_POST;
        $errors = $this->validate($input);

        if (mb_strlen((string) ($input['password'] ?? '')) < 8) {
            $errors['password'] = 'პაროლი უნდა შეიცავდეს მინიმუმ 8 სიმბოლოს.';
        }

        if ($errors) {
            set_errors($errors);
            keep_old($input);
            redirect('users/create');
        }

        $input['is_active'] = isset($input['is_active']);
        User::create($input);
        clear_old();
        flash('success', 'მომხმარებელი დაემატა.');
        redirect('users');
    }

    public function edit(string $id): void
    {
        $this->requireAdmin();
        $user = User::find((int) $id);
        if (!$user) {
            $this->notFound();
        }
        $this->render('users.form', ['pageTitle' => 'მომხმარებლის რედაქტირება', 'editUser' => $user]);
    }

    public function update(string $id): void
    {
        $this->requireAdmin();
        $this->validateCsrf();
        $user = User::find((int) $id);
        if (!$user) {
            $this->notFound();
        }

        $input = $_POST;
        $errors = $this->validate($input, (int) $id);
        if ($errors) {
            set_errors($errors);
            keep_old($input);
            redirect("users/{$id}/edit");
        }

        if ((int) $id === Auth::id()) {
            $input['is_active'] = 1;
            $input['role'] = 'admin';
        } else {
            $input['is_active'] = isset($input['is_active']);
        }

        User::update((int) $id, $input);
        clear_old();
        flash('success', 'მომხმარებელი განახლდა.');
        redirect('users');
    }

    public function password(string $id): void
    {
        $this->requireAdmin();
        $this->validateCsrf();
        if (!User::find((int) $id)) {
            $this->notFound();
        }

        $password = (string) ($_POST['password'] ?? '');
        if (mb_strlen($password) < 8 || $password !== (string) ($_POST['password_confirmation'] ?? '')) {
            flash('error', 'პაროლი უნდა შეიცავდეს მინიმუმ 8 სიმბოლოს და დადასტურება უნდა ემთხვეოდეს.');
            redirect("users/{$id}/edit");
        }

        User::updatePassword((int) $id, $password);
        if ((int) $id === Auth::id()) {
            Auth::refreshAfterPasswordChange();
        }
        flash('success', 'მომხმარებლის პაროლი შეიცვალა.');
        redirect("users/{$id}/edit");
    }

    public function toggle(string $id): void
    {
        $this->requireAdmin();
        $this->validateCsrf();
        if ((int) $id === Auth::id()) {
            flash('error', 'საკუთარი ანგარიშის გათიშვა შეუძლებელია.');
        } else {
            User::toggle((int) $id);
            flash('success', 'მომხმარებლის სტატუსი შეიცვალა.');
        }
        redirect('users');
    }

    public function delete(string $id): void
    {
        $this->requireAdmin();
        $this->validateCsrf();
        if ((int) $id === Auth::id()) {
            flash('error', 'საკუთარი ანგარიშის წაშლა შეუძლებელია.');
        } else {
            User::softDelete((int) $id);
            flash('success', 'მომხმარებელი წაიშალა. მისი მონაცემები ისტორიაში შენარჩუნებულია.');
        }
        redirect('users');
    }

    private function validate(array $input, ?int $exceptId = null): array
    {
        $errors = Validation::required($input, ['name' => 'სახელი', 'email' => 'ელფოსტა']);
        $errors += Validation::email((string) ($input['email'] ?? ''));
        if (User::emailExists((string) ($input['email'] ?? ''), $exceptId)) {
            $errors['email'] = 'ეს ელფოსტა უკვე გამოყენებულია.';
        }
        return $errors;
    }

    private function notFound(): never
    {
        http_response_code(404);
        $this->render('errors.404', ['pageTitle' => 'მომხმარებელი ვერ მოიძებნა']);
        exit;
    }
}

