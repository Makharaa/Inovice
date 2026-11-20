<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$configFile = $basePath . '/storage/config.php';
$errors = [];
$success = false;

session_name('makhara_invoice_installer');
session_start();

if (is_file($configFile)) {
    http_response_code(403);
    exit('სისტემა უკვე დაყენებულია. უსაფრთხოებისთვის წაშალეთ public/install.php.');
}

if (empty($_SESSION['_installer_csrf'])) {
    $_SESSION['_installer_csrf'] = bin2hex(random_bytes(32));
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$detectedUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'invoice.makhara.ge');
$values = [
    'app_url' => $_POST['app_url'] ?? $detectedUrl,
    'db_host' => $_POST['db_host'] ?? '127.0.0.1',
    'db_port' => $_POST['db_port'] ?? '3306',
    'db_name' => $_POST['db_name'] ?? '',
    'db_user' => $_POST['db_user'] ?? '',
    'admin_name' => $_POST['admin_name'] ?? '',
    'admin_email' => $_POST['admin_email'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['_installer_csrf'], (string) ($_POST['_token'] ?? ''))) {
        $errors[] = 'უსაფრთხოების კოდი არასწორია. განაახლეთ გვერდი.';
    }

    foreach ([
        'app_url' => 'საიტის მისამართი',
        'db_host' => 'ბაზის ჰოსტი',
        'db_name' => 'ბაზის სახელი',
        'db_user' => 'ბაზის მომხმარებელი',
        'admin_name' => 'ადმინისტრატორის სახელი',
        'admin_email' => 'ადმინისტრატორის ელფოსტა',
    ] as $field => $label) {
        if (trim((string) ($_POST[$field] ?? '')) === '') {
            $errors[] = "{$label} სავალდებულოა.";
        }
    }

    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    if (strlen($adminPassword) < 8) {
        $errors[] = 'ადმინისტრატორის პაროლი მინიმუმ 8 სიმბოლო უნდა იყოს.';
    }
    if ($adminPassword !== (string) ($_POST['admin_password_confirmation'] ?? '')) {
        $errors[] = 'პაროლის დადასტურება არ ემთხვევა.';
    }
    if (!filter_var((string) ($_POST['admin_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'ადმინისტრატორის ელფოსტის ფორმატი არასწორია.';
    }

    if (!$errors) {
        try {
            $pdo = new PDO(
                'mysql:host=' . trim((string) $_POST['db_host'])
                    . ';port=' . (int) $_POST['db_port']
                    . ';dbname=' . trim((string) $_POST['db_name'])
                    . ';charset=utf8mb4',
                trim((string) $_POST['db_user']),
                (string) ($_POST['db_password'] ?? ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // Never run against a database that already holds users: if storage/config.php was lost
            // (deploy, cleanup, migration), the installer would otherwise let a stranger add an admin.
            if ($pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn() !== false
                && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
                throw new RuntimeException(
                    'ეს ბაზა უკვე შეიცავს მომხმარებლებს, ამიტომ ინსტალერი მასზე არ გაეშვება. '
                    . 'თუ storage/config.php დაიკარგა, აღადგინეთ ის სარეზერვო ასლიდან ან config/config.example.php-დან.'
                );
            }

            $schema = file_get_contents($basePath . '/database/schema.sql');
            if ($schema === false) {
                throw new RuntimeException('schema.sql ვერ მოიძებნა.');
            }

            foreach (array_filter(array_map('trim', explode(";\n", $schema))) as $statement) {
                $pdo->exec($statement);
            }

            $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $exists->execute(['email' => mb_strtolower(trim((string) $_POST['admin_email']))]);
            if ((int) $exists->fetchColumn() > 0) {
                throw new RuntimeException('ეს ადმინისტრატორის ელფოსტა უკვე არსებობს.');
            }

            $admin = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, is_active)
                 VALUES (:name, :email, :password_hash, "admin", 1)'
            );
            $admin->execute([
                'name' => trim((string) $_POST['admin_name']),
                'email' => mb_strtolower(trim((string) $_POST['admin_email'])),
                'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
            ]);

            $configuration = [
                'app' => [
                    'name' => 'Makhara Invoice',
                    'url' => rtrim(trim((string) $_POST['app_url']), '/'),
                    'timezone' => 'Asia/Tbilisi',
                    'session_name' => 'makhara_invoice',
                ],
                'database' => [
                    'host' => trim((string) $_POST['db_host']),
                    'port' => (int) $_POST['db_port'],
                    'name' => trim((string) $_POST['db_name']),
                    'user' => trim((string) $_POST['db_user']),
                    'password' => (string) ($_POST['db_password'] ?? ''),
                    'charset' => 'utf8mb4',
                ],
            ];

            $php = "<?php\n\nreturn " . var_export($configuration, true) . ";\n";
            if (file_put_contents($configFile, $php, LOCK_EX) === false) {
                throw new RuntimeException('storage/config.php ვერ ჩაიწერა. შეამოწმეთ storage საქაღალდის უფლებები.');
            }

            @chmod($configFile, 0640);
            unset($_SESSION['_installer_csrf']);
            $success = true;
        } catch (Throwable $exception) {
            $errors[] = 'ინსტალაცია ვერ დასრულდა: ' . $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Makhara Invoice — ინსტალაცია</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f6f8;color:#17212b;font-family:Arial,sans-serif}
        .wrap{width:min(760px,calc(100% - 32px));margin:48px auto}.card{background:#fff;border:1px solid #e4e9ef;border-radius:22px;padding:32px;box-shadow:0 18px 60px rgba(25,39,52,.08)}
        h1{margin:0 0 8px;font-size:28px}p{color:#627181;line-height:1.6}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.full{grid-column:1/-1}
        label{display:block;font-size:13px;font-weight:700;margin-bottom:7px}input{width:100%;height:46px;border:1px solid #ccd5df;border-radius:10px;padding:0 13px;font-size:15px}
        input:focus{outline:2px solid #c9dfdc;border-color:#0b766e}.section{grid-column:1/-1;margin-top:12px;padding-top:22px;border-top:1px solid #edf0f3;font-size:17px;font-weight:700}
        button,.button{border:0;border-radius:11px;background:#0b766e;color:#fff;padding:14px 22px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}
        .alert{padding:14px 16px;border-radius:10px;margin:18px 0}.error{background:#fff1f1;color:#a12828}.success{background:#eaf8f3;color:#116248}
        ul{margin:0;padding-left:20px}@media(max-width:620px){.grid{grid-template-columns:1fr}.full,.section{grid-column:auto}.card{padding:22px}.wrap{margin:20px auto}}
    </style>
</head>
<body>
<main class="wrap">
    <div class="card">
        <h1>Makhara Invoice</h1>
        <p>ერთჯერადი ინსტალაცია — შეავსეთ მონაცემთა ბაზისა და პირველი ადმინისტრატორის ინფორმაცია.</p>

        <?php if ($errors): ?>
            <div class="alert error"><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success">სისტემა წარმატებით დაყენდა. უსაფრთხოებისთვის ახლა წაშალეთ <strong>public/install.php</strong>.</div>
            <a class="button" href="./">სისტემაში შესვლა</a>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['_installer_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="grid">
                    <div class="full"><label>საიტის მისამართი</label><input name="app_url" value="<?= htmlspecialchars($values['app_url'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                    <div class="section">მონაცემთა ბაზა</div>
                    <div><label>ჰოსტი</label><input name="db_host" value="<?= htmlspecialchars($values['db_host'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                    <div><label>პორტი</label><input name="db_port" value="<?= htmlspecialchars($values['db_port'], ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" required></div>
                    <div><label>ბაზის სახელი</label><input name="db_name" value="<?= htmlspecialchars($values['db_name'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                    <div><label>ბაზის მომხმარებელი</label><input name="db_user" value="<?= htmlspecialchars($values['db_user'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                    <div class="full"><label>ბაზის პაროლი</label><input type="password" name="db_password"></div>
                    <div class="section">ადმინისტრატორი</div>
                    <div><label>სახელი და გვარი</label><input name="admin_name" value="<?= htmlspecialchars($values['admin_name'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                    <div><label>ელფოსტა</label><input type="email" name="admin_email" value="<?= htmlspecialchars($values['admin_email'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                    <div><label>პაროლი</label><input type="password" name="admin_password" minlength="8" required></div>
                    <div><label>გაიმეორეთ პაროლი</label><input type="password" name="admin_password_confirmation" minlength="8" required></div>
                    <div class="full"><button type="submit">სისტემის დაყენება</button></div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
