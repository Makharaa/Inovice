<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f7f6">
    <meta name="color-scheme" content="light dark">
    <title><?= e($pageTitle ?? 'შესვლა') ?> — Makhara Invoice</title>
    <script nonce="<?= e(csp_nonce()) ?>">
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
<body class="auth-page">
    <button class="theme-toggle auth-theme-toggle" type="button" data-theme-toggle aria-label="მუქი თემის ჩართვა" aria-pressed="false">
        <span class="theme-icon theme-icon-dark"><i data-lucide="moon" aria-hidden="true"></i></span>
        <span class="theme-icon theme-icon-light"><i data-lucide="sun" aria-hidden="true"></i></span>
    </button>
    <?= $content ?>
    <script src="<?= asset('vendor/lucide.min.js') ?>"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
