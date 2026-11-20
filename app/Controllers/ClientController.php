<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Paginator;
use App\Models\Client;
use App\Support\Validation;

final class ClientController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();
        $search = trim((string) ($_GET['search'] ?? ''));
        $page = Paginator::page();
        $total = Client::countForUser((int) $user['id'], $search);
        $pagination = Paginator::meta($total, 15, $page);

        $this->render('clients.index', [
            'pageTitle' => 'მიმღებები',
            'clients' => Client::allForUser(
                (int) $user['id'],
                $search,
                $pagination['per_page'],
                $pagination['offset']
            ),
            'pagination' => $pagination,
            'search' => $search,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->render('clients.form', ['pageTitle' => 'მიმღების დამატება', 'client' => null]);
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
            redirect('clients/create');
        }

        Client::create((int) $user['id'], $input);
        clear_old();
        flash('success', 'მიმღები დაემატა და მომავალ ინვოისებში არჩევა შესაძლებელი იქნება.');
        redirect('clients');
    }

    public function edit(string $id): void
    {
        $user = $this->requireAuth();
        $client = Client::findOwned((int) $id, (int) $user['id']);
        if (!$client) {
            $this->notFound();
        }
        $this->render('clients.form', ['pageTitle' => 'მიმღების რედაქტირება', 'client' => $client]);
    }

    public function update(string $id): void
    {
        $user = $this->requireAuth();
        $this->validateCsrf();
        if (!Client::findOwned((int) $id, (int) $user['id'])) {
            $this->notFound();
        }

        $input = $_POST;
        $errors = $this->validate($input);
        if ($errors) {
            set_errors($errors);
            keep_old($input);
            redirect("clients/{$id}/edit");
        }

        Client::update((int) $id, (int) $user['id'], $input);
        clear_old();
        flash('success', 'მიმღების მონაცემები განახლდა.');
        redirect('clients');
    }

    public function delete(string $id): void
    {
        $user = $this->requireAuth();
        $this->validateCsrf();
        Client::softDelete((int) $id, (int) $user['id']);
        flash('success', 'მიმღები წაიშალა. ძველ ინვოისებში მონაცემები შენარჩუნებულია.');
        redirect('clients');
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
        $this->render('errors.404', ['pageTitle' => 'მიმღები ვერ მოიძებნა']);
        exit;
    }
}

