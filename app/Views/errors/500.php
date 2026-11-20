<?php
/** @var string $reference */
$home = function_exists('url') ? url('dashboard') : '/';
?>
<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>დროებითი შეცდომა — Makhara Invoice</title>
    <style<?= function_exists('csp_nonce') ? ' nonce="' . htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f7f6;color:#17212b;font-family:system-ui,-apple-system,"Segoe UI",Arial,sans-serif}
        main{width:min(520px,calc(100% - 32px));background:#fff;border:1px solid #e4e9ef;border-radius:22px;padding:32px;box-shadow:0 18px 60px rgba(25,39,52,.08)}
        h1{margin:0 0 10px;font-size:24px}p{margin:0 0 14px;color:#627181;line-height:1.6}
        code{background:#eef2f5;border-radius:6px;padding:2px 8px;color:#17212b}
        a{display:inline-block;margin-top:6px;background:#0b766e;color:#fff;text-decoration:none;border-radius:11px;padding:12px 20px;font-weight:700}
        @media (prefers-color-scheme:dark){body{background:#111816;color:#e8efec}main{background:#18211e;border-color:#2a3732}p{color:#9fb0aa}code{background:#24312d;color:#e8efec}}
    </style>
</head>
<body>
<main>
    <h1>დაფიქსირდა შეცდომა</h1>
    <p>მოთხოვნა ვერ შესრულდა. თქვენი მონაცემები არ დაკარგულა. სცადეთ გვერდის განახლება ან დაბრუნდით მთავარ გვერდზე.</p>
    <p>თუ პრობლემა განმეორდა, გადაეცით ადმინისტრატორს კოდი: <code><?= htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') ?></code></p>
    <a href="<?= htmlspecialchars($home, ENT_QUOTES, 'UTF-8') ?>">მთავარ გვერდზე დაბრუნება</a>
</main>
</body>
</html>
