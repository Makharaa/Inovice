<?php
$user = current_user();
$path = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$successMessage = flash('success');
$errorMessage = flash('error');
$validationErrors = errors();
?>
<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
    <meta name="theme-color" content="#f4f7f6">
    <meta name="color-scheme" content="light dark">
    <title><?= e($pageTitle ?? 'Makhara Invoice') ?> — Makhara Invoice</title>
    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem('makhara-theme');
                const theme = savedTheme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.dataset.theme = theme;
            } catch (error) {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/modern-ui.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= url('dashboard') ?>">
            <span class="brand-mark"><i data-lucide="receipt-text" aria-hidden="true"></i></span>
            <span class="brand-copy"><strong>Makhara</strong><small>Invoice workspace</small></span>
        </a>

        <nav class="main-nav" aria-label="მთავარი მენიუ">
            <span class="nav-label">სამუშაო სივრცე</span>
            <a class="<?= str_contains($path, '/dashboard') || $path === '/' ? 'active' : '' ?>" href="<?= url('dashboard') ?>">
                <span class="nav-icon"><i data-lucide="layout-dashboard" aria-hidden="true"></i></span>
                <span>მთავარი</span>
            </a>
            <a class="<?= str_contains($path, '/invoices') ? 'active' : '' ?>" href="<?= url('invoices') ?>">
                <span class="nav-icon"><i data-lucide="files" aria-hidden="true"></i></span>
                <span>ინვოისები</span>
            </a>
            <a class="<?= str_contains($path, '/issuers') ? 'active' : '' ?>" href="<?= url('issuers') ?>">
                <span class="nav-icon"><i data-lucide="building-2" aria-hidden="true"></i></span>
                <span>ჩემი გამცემები</span>
            </a>
            <a class="<?= str_contains($path, '/clients') ? 'active' : '' ?>" href="<?= url('clients') ?>">
                <span class="nav-icon"><i data-lucide="contact-round" aria-hidden="true"></i></span>
                <span>მიმღებები</span>
            </a>

            <?php if (is_admin()): ?>
                <span class="nav-label nav-label-spaced">ადმინისტრირება</span>
                <a class="<?= str_contains($path, '/users') ? 'active' : '' ?>" href="<?= url('users') ?>">
                    <span class="nav-icon"><i data-lucide="users-round" aria-hidden="true"></i></span>
                    <span>მომხმარებლები</span>
                </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <a href="<?= url('profile') ?>" class="user-card">
                <span class="avatar"><?= e(mb_strtoupper(mb_substr((string) ($user['name'] ?? 'U'), 0, 1))) ?></span>
                <span class="user-card-copy">
                    <strong><?= e($user['name'] ?? '') ?></strong>
                    <small><?= ($user['role'] ?? '') === 'admin' ? 'ადმინისტრატორი' : 'მომხმარებელი' ?></small>
                </span>
            </a>
            <form method="post" action="<?= url('logout') ?>">
                <?= csrf_field() ?>
                <button class="logout-button" type="submit" title="გასვლა" aria-label="სისტემიდან გასვლა">
                    <i data-lucide="log-out" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <button class="menu-toggle" type="button" data-sidebar-toggle aria-label="მენიუს გახსნა" aria-controls="sidebar" aria-expanded="false">
                <i data-lucide="menu" aria-hidden="true"></i>
            </button>
            <div class="topbar-title">
                <h1><?= e($pageTitle ?? '') ?></h1>
                <p><i data-lucide="calendar-days" aria-hidden="true"></i><?= e(date('d.m.Y')) ?></p>
            </div>
            <div class="topbar-tools">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="მუქი თემის ჩართვა" aria-pressed="false">
                    <span class="theme-icon theme-icon-dark"><i data-lucide="moon" aria-hidden="true"></i></span>
                    <span class="theme-icon theme-icon-light"><i data-lucide="sun" aria-hidden="true"></i></span>
                </button>
                <a class="button primary topbar-action" href="<?= url('invoices/create') ?>">
                    <i data-lucide="plus" aria-hidden="true"></i><span>ახალი ინვოისი</span>
                </a>
            </div>
        </header>

        <main class="page-content">
            <?php if ($successMessage): ?>
                <div class="alert success" role="status"><span>✓</span><div><?= e($successMessage) ?></div></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="alert danger" role="alert"><span>!</span><div><?= e($errorMessage) ?></div></div>
            <?php endif; ?>
            <?php if ($validationErrors): ?>
                <div class="alert danger" role="alert">
                    <span>!</span>
                    <div><strong>შეასწორეთ მონაცემები:</strong><ul><?php foreach ($validationErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>
</div>
<div class="sidebar-backdrop" data-sidebar-toggle></div>
<script src="<?= asset('vendor/lucide.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach (($pageScripts ?? []) as $script): ?>
    <script src="<?= asset('js/' . $script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
