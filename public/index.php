<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

if (!is_file($basePath . '/storage/config.php')) {
    header('Location: install.php');
    exit;
}

require $basePath . '/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\ClientController;
use App\Controllers\DashboardController;
use App\Controllers\InvoiceController;
use App\Controllers\IssuerController;
use App\Controllers\ProfileController;
use App\Controllers\UserController;
use App\Core\Router;

$router = new Router();

$router->get('/', [DashboardController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/profile', [ProfileController::class, 'edit']);
$router->post('/profile', [ProfileController::class, 'update']);
$router->post('/profile/password', [ProfileController::class, 'password']);

$router->get('/users', [UserController::class, 'index']);
$router->get('/users/create', [UserController::class, 'create']);
$router->post('/users', [UserController::class, 'store']);
$router->get('/users/{id}/edit', [UserController::class, 'edit']);
$router->post('/users/{id}', [UserController::class, 'update']);
$router->post('/users/{id}/password', [UserController::class, 'password']);
$router->post('/users/{id}/toggle', [UserController::class, 'toggle']);
$router->post('/users/{id}/delete', [UserController::class, 'delete']);

$router->get('/issuers', [IssuerController::class, 'index']);
$router->get('/issuers/create', [IssuerController::class, 'create']);
$router->post('/issuers', [IssuerController::class, 'store']);
$router->get('/issuers/{id}/edit', [IssuerController::class, 'edit']);
$router->post('/issuers/{id}', [IssuerController::class, 'update']);
$router->post('/issuers/{id}/delete', [IssuerController::class, 'delete']);

$router->get('/clients', [ClientController::class, 'index']);
$router->get('/clients/create', [ClientController::class, 'create']);
$router->post('/clients', [ClientController::class, 'store']);
$router->get('/clients/{id}/edit', [ClientController::class, 'edit']);
$router->post('/clients/{id}', [ClientController::class, 'update']);
$router->post('/clients/{id}/delete', [ClientController::class, 'delete']);

$router->get('/invoices', [InvoiceController::class, 'index']);
$router->get('/invoices/create', [InvoiceController::class, 'create']);
$router->post('/invoices', [InvoiceController::class, 'store']);
$router->get('/invoices/{id}', [InvoiceController::class, 'show']);
$router->get('/invoices/{id}/edit', [InvoiceController::class, 'edit']);
$router->post('/invoices/{id}', [InvoiceController::class, 'update']);
$router->post('/invoices/{id}/duplicate', [InvoiceController::class, 'duplicate']);
$router->post('/invoices/{id}/delete', [InvoiceController::class, 'delete']);
$router->get('/invoices/{id}/print', [InvoiceController::class, 'print']);
$router->get('/invoices/{id}/pdf', [InvoiceController::class, 'pdf']);

$router->dispatch();

