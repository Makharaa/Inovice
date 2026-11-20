<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Issuer;
use App\Support\Validation;

final class IssuerController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();
        $this->render('issuers.index', [
            'pageTitle' => 'ჩემი გამცემები',
            'issuers' => Issuer::allForUser((int) $user['id']),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->render('issuers.form', ['pageTitle' => 'გამცემის დამატება', 'issuer' => null]);
    }

    public function store(): void
    {
        $user = $this->requireAuth();
        $this->validateCsrf();
        $input = $_POST;
        $errors = $this->validate($input);

        if ($errors) {
            set_errors($errors);
            keep_old($input);
            redirect('issuers/create');
        }

        $input['is_active'] = 1;
        Issuer::create((int) $user['id'], $input);
        clear_old();
        flash('success', 'გამცემის მონაცემები დაემატა.');
        redirect('issuers');
    }

    public function edit(string $id): void
    {
        $user = $this->requireAuth();
        $issuer = Issuer::findOwned((int) $id, (int) $user['id']);
        if (!$issuer) {
            $this->notFound();
        }
        $this->render('issuers.form', ['pageTitle' => 'გამცემის რედაქტირება', 'issuer' => $issuer]);
    }

    public function update(string $id): void
    {
        $user = $this->requireAuth();
        $this->validateCsrf();
        $issuer = Issuer::findOwned((int) $id, (int) $user['id']);
        if (!$issuer) {
            $this->notFound();
        }

        $input = $_POST;
        $errors = $this->validate($input);
        if ($errors) {
            set_errors($errors);
            keep_old($input);
            redirect("issuers/{$id}/edit");
        }

        $input['is_active'] = isset($input['is_active']);
        Issuer::update((int) $id, (int) $user['id'], $input);
        clear_old();
        flash('success', 'გამცემის მონაცემები განახლდა.');
        redirect('issuers');
    }

    public function delete(string $id): void
    {
        $user = $this->requireAuth();
        $this->validateCsrf();
        if (Issuer::softDelete((int) $id, (int) $user['id'])) {
            flash('success', 'გამცემი წაიშალა.');
        } else {
            flash('error', 'ამ გამცემზე უკვე არსებობს ინვოისი. შეგიძლიათ გამცემი მხოლოდ გათიშოთ.');
        }
        redirect('issuers');
    }

    private function validate(array $input): array
    {
        $errors = Validation::required($input, [
            'entity_type' => 'ტიპი',
            'name' => 'დასახელება',
            'identification_number' => 'საიდენტიფიკაციო/პირადი ნომერი',
        ]);

        if (!in_array($input['entity_type'] ?? '', ['company', 'individual_entrepreneur', 'individual'], true)) {
            $errors['entity_type'] = 'აირჩიეთ სწორი ტიპი.';
        }
        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'ელფოსტის ფორმატი არასწორია.';
        }
        return $errors;
    }

    private function notFound(): never
    {
        http_response_code(404);
        $this->render('errors.404', ['pageTitle' => 'გამცემი ვერ მოიძებნა']);
        exit;
    }
}

