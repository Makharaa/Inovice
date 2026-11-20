<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Paginator;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use App\Services\InvoiceDocumentPaginator;
use App\Services\InvoiceService;
use App\Support\UserFacingException;

final class InvoiceController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();
        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'status' => (string) ($_GET['status'] ?? ''),
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to' => (string) ($_GET['date_to'] ?? ''),
            'user_id' => (int) ($_GET['user_id'] ?? 0),
        ];
        $page = Paginator::page();
        $total = Invoice::count((int) $user['id'], Auth::isAdmin(), $filters);
        $pagination = Paginator::meta($total, 15, $page);

        $this->render('invoices.index', [
            'pageTitle' => 'ინვოისები',
            'invoices' => Invoice::paginated(
                (int) $user['id'],
                Auth::isAdmin(),
                $filters,
                $pagination['per_page'],
                $pagination['offset']
            ),
            'pagination' => $pagination,
            'filters' => $filters,
            'userOptions' => Auth::isAdmin() ? User::activeOptions() : [],
        ]);
    }

    public function create(): void
    {
        $user = $this->requireAuth();
        $issuers = Issuer::allForUser((int) $user['id'], true);
        $clients = Client::allForUser((int) $user['id']);

        if (!$issuers) {
            flash('error', 'ინვოისის შექმნამდე დაამატეთ მინიმუმ ერთი გამცემი.');
            redirect('issuers/create');
        }
        if (!$clients) {
            flash('error', 'ინვოისის შექმნამდე დაამატეთ მინიმუმ ერთი მიმღები.');
            redirect('clients/create');
        }

        $this->render('invoices.form', [
            'pageTitle' => 'ინვოისის შექმნა',
            'invoice' => null,
            'issuers' => $issuers,
            'clients' => $clients,
        ]);
    }

    public function store(): void
    {
        $user = $this->requireAuth();
        $this->validateCsrf();
        $input = $this->normalizedInput($_POST);
        $errors = $this->validate($input);

        if ($errors) {
            set_errors($errors);
            keep_old($input);
            redirect('invoices/create');
        }

        try {
            $invoiceId = (new InvoiceService())->create((int) $user['id'], $input);
            clear_old();
            flash('success', 'ინვოისი შეიქმნა.');
            redirect("invoices/{$invoiceId}");
        } catch (\Throwable $exception) {
            keep_old($input);
            $this->handleSaveError($exception);
            redirect('invoices/create');
        }
    }

    public function show(string $id): void
    {
        $user = $this->requireAuth();
        $invoice = Invoice::findAccessible((int) $id, (int) $user['id'], Auth::isAdmin());
        if (!$invoice) {
            $this->notFound();
        }
        $this->render('invoices.show', ['pageTitle' => $invoice['invoice_number'], 'invoice' => $invoice]);
    }

    public function edit(string $id): void
    {
        $user = $this->requireAuth();
        $invoice = Invoice::findAccessible((int) $id, (int) $user['id'], false);
        if (!$invoice) {
            $this->notFound();
        }
        if (Invoice::isLocked($invoice)) {
            flash('error', Invoice::LOCKED_MESSAGE);
            redirect("invoices/{$id}");
        }
        $this->render('invoices.form', [
            'pageTitle' => 'ინვოისის რედაქტირება',
            'invoice' => $invoice,
            'issuers' => Issuer::allForUser((int) $user['id'], true),
            'clients' => Client::allForUser((int) $user['id']),
        ]);
    }

    public function update(string $id): void
    {
        $user = $this->requireAuth();
        $this->validateCsrf();
        $input = $this->normalizedInput($_POST);
        $errors = $this->validate($input);

        if ($errors) {
            set_errors($errors);
            keep_old($input);
            redirect("invoices/{$id}/edit");
        }

        try {
            (new InvoiceService())->update((int) $id, (int) $user['id'], $input);
            clear_old();
            flash('success', 'ინვოისი განახლდა.');
            redirect("invoices/{$id}");
        } catch (\Throwable $exception) {
            keep_old($input);
            $this->handleSaveError($exception);
            redirect("invoices/{$id}/edit");
        }
    }

    public function duplicate(string $id): void
    {
        $user = $this->requireAuth();
        $this->validateCsrf();
        try {
            $newId = (new InvoiceService())->duplicate((int) $id, (int) $user['id']);
            flash('success', 'ინვოისის ასლი შეიქმნა ახალი ნომრით.');
            redirect("invoices/{$newId}/edit");
        } catch (\Throwable $exception) {
            $this->handleSaveError($exception);
            redirect('invoices');
        }
    }

    public function delete(string $id): void
    {
        $user = $this->requireAuth();
        $this->validateCsrf();
        if (Invoice::softDelete((int) $id, (int) $user['id'])) {
            flash('success', 'ინვოისი წაიშალა და ისტორიიდან დამალულია.');
        } else {
            flash('error', Invoice::LOCKED_MESSAGE);
        }
        redirect('invoices');
    }

    public function print(string $id): void
    {
        $this->document((int) $id, false);
    }

    public function pdf(string $id): void
    {
        $this->document((int) $id, true);
    }

    private function document(int $id, bool $autoDownload): void
    {
        $user = $this->requireAuth();
        $invoice = Invoice::findAccessible($id, (int) $user['id'], Auth::isAdmin());
        if (!$invoice) {
            $this->notFound();
        }

        if ($autoDownload) {
            // The bundled html2pdf library injects inline styles while building the PDF. Scripts stay nonce-only.
            send_csp("style-src-elem 'self' 'unsafe-inline'; style-src-attr 'unsafe-inline'");
        }

        $language = in_array($invoice['language'], ['ka', 'en', 'ru'], true) ? $invoice['language'] : 'ka';
        $labels = require BASE_PATH . "/app/Lang/{$language}/invoice.php";
        $documentPages = (new InvoiceDocumentPaginator())->paginate(
            $invoice['items'],
            (string) $invoice['template_key']
        );
        $this->render('invoices.document', [
            'invoice' => $invoice,
            'labels' => $labels,
            'autoDownload' => $autoDownload,
            'documentPages' => $documentPages,
        ], null);
    }

    private function validate(array $input): array
    {
        $errors = [];
        if ((int) ($input['issuer_id'] ?? 0) < 1) {
            $errors['issuer_id'] = 'აირჩიეთ გამცემი.';
        }
        if ((int) ($input['client_id'] ?? 0) < 1) {
            $errors['client_id'] = 'აირჩიეთ მიმღები.';
        }
        if (empty($input['issue_date'])) {
            $errors['issue_date'] = 'მიუთითეთ ინვოისის თარიღი.';
        }
        if (empty($input['items']) || !is_array($input['items'])) {
            $errors['items'] = 'დაამატეთ მინიმუმ ერთი პოზიცია.';
        }
        return $errors;
    }

    private function normalizedInput(array $input): array
    {
        $input['prices_include_vat'] = isset($input['prices_include_vat']);
        $input['items'] = is_array($input['items'] ?? null) ? array_values($input['items']) : [];
        return $input;
    }

    private function handleSaveError(\Throwable $exception): void
    {
        if ($exception instanceof UserFacingException) {
            flash('error', $exception->getMessage());
            return;
        }

        error_log('[Makhara Invoice] ' . $exception->getMessage());
        flash('error', 'ოპერაცია ვერ დასრულდა. გადაამოწმეთ მონაცემები და სცადეთ ხელახლა.');
    }

    private function notFound(): never
    {
        http_response_code(404);
        $this->render('errors.404', ['pageTitle' => 'ინვოისი ვერ მოიძებნა']);
        exit;
    }
}
