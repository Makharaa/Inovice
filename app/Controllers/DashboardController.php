<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Invoice;
use App\Models\Issuer;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAuth();
        $stats = Invoice::dashboardStats((int) $user['id'], Auth::isAdmin());
        $recent = Invoice::recent((int) $user['id'], Auth::isAdmin());
        $issuers = Issuer::allForUser((int) $user['id'], true);

        $this->render('dashboard.index', [
            'pageTitle' => 'მთავარი',
            'stats' => $stats,
            'recent' => $recent,
            'issuers' => $issuers,
        ]);
    }
}

