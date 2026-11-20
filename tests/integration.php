<?php

declare(strict_types=1);

/**
 * Database integration tests.
 *
 * WARNING: all tables of the target database are dropped and re-created.
 * Use a throw-away database only.
 *
 *   TEST_DB_NAME=invoice_test TEST_DB_USER=root TEST_DB_PASS=secret php tests/integration.php
 *
 * Optional: TEST_DB_HOST (127.0.0.1), TEST_DB_PORT (3306).
 */

$databaseName = getenv('TEST_DB_NAME');
if ($databaseName === false || $databaseName === '') {
    echo "Integration tests skipped (set TEST_DB_NAME, TEST_DB_USER, TEST_DB_PASS to run them).\n";
    exit(0);
}

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
require BASE_PATH . '/app/Support/helpers.php';

use App\Core\Database;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Issuer;
use App\Models\User;
use App\Services\InvoiceService;
use App\Support\UserFacingException;

$GLOBALS['app_config'] = ['app' => ['url' => 'http://localhost', 'timezone' => 'Asia/Tbilisi']];
$_SESSION = [];

Database::configure([
    'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
    'name' => $databaseName,
    'user' => (string) getenv('TEST_DB_USER'),
    'password' => (string) getenv('TEST_DB_PASS'),
    'charset' => 'utf8mb4',
]);

$pdo = Database::connection();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $pdo->exec('DROP TABLE `' . $table . '`');
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
foreach (array_filter(array_map('trim', explode(";\n", (string) file_get_contents(BASE_PATH . '/database/schema.sql')))) as $statement) {
    $pdo->exec($statement);
}

$failures = 0;
function check(string $label, bool $condition, string $detail = ''): void
{
    global $failures;
    echo ($condition ? '  ok   ' : '  FAIL ') . $label . ($condition || $detail === '' ? '' : " ({$detail})") . "\n";
    if (!$condition) {
        $failures++;
    }
}

function make_user(string $email): int
{
    return User::create(['name' => $email, 'email' => $email, 'password' => 'password-123', 'role' => 'user', 'is_active' => 1]);
}

function make_issuer(int $userId, array $override = []): int
{
    return Issuer::create($userId, $override + [
        'entity_type' => 'company', 'name' => 'Issuer', 'identification_number' => '123456789',
        'invoice_prefix' => 'INV', 'is_active' => 1, 'is_default' => 1,
    ]);
}

function make_client(int $userId): int
{
    return Client::create($userId, ['entity_type' => 'company', 'name' => 'Client', 'identification_number' => '987654321']);
}

function make_invoice(int $userId, int $issuerId, int $clientId, string $date, string $status = 'issued'): int
{
    return (new InvoiceService())->create($userId, [
        'issuer_id' => $issuerId, 'client_id' => $clientId, 'issue_date' => $date, 'status' => $status,
        'items' => [['description' => 'Service', 'unit' => 'pcs', 'quantity' => 1, 'unit_price' => 100]],
    ]);
}

function number_of(int $invoiceId): string
{
    return (string) Database::connection()->query('SELECT invoice_number FROM invoices WHERE id = ' . $invoiceId)->fetchColumn();
}

// ---------------------------------------------------------------------------
echo "Invoice numbering\n";
$user = make_user('numbering@example.com');
$issuer = make_issuer($user);
$client = make_client($user);

check('first invoice of the year is 0001', number_of(make_invoice($user, $issuer, $client, '2026-11-01')) === 'INV-2026-0001');
check('second invoice is 0002', number_of(make_invoice($user, $issuer, $client, '2026-11-02')) === 'INV-2026-0002');

$backdated = make_invoice($user, $issuer, $client, '2025-12-31');
check('back-dated invoice starts its own year at 0001', number_of($backdated) === 'INV-2025-0001', number_of($backdated));
check('current year continues after a back-dated invoice', number_of(make_invoice($user, $issuer, $client, '2026-11-03')) === 'INV-2026-0003');
check('older year continues its own sequence', number_of(make_invoice($user, $issuer, $client, '2025-06-01')) === 'INV-2025-0002');

// Issuer already damaged by the old counter logic (counter stuck on another year).
Database::connection()->exec("UPDATE issuers SET numbering_year = 2025, next_invoice_number = 2 WHERE id = {$issuer}");
check('issuer with a stale legacy counter recovers', number_of(make_invoice($user, $issuer, $client, '2026-11-04')) === 'INV-2026-0004');

// Soft-deleted invoices keep their number reserved.
$last = make_invoice($user, $issuer, $client, '2026-11-05');
Database::connection()->exec("UPDATE invoices SET deleted_at = NOW() WHERE id = {$last}");
check('soft-deleted invoice number is not reused', number_of(make_invoice($user, $issuer, $client, '2026-11-06')) === 'INV-2026-0006');

// Numbering is independent per issuer.
$second = make_issuer($user, ['name' => 'Second', 'invoice_prefix' => 'SEC', 'is_default' => 0]);
check('second issuer has independent numbering', number_of(make_invoice($user, $second, $client, '2026-11-01')) === 'SEC-2026-0001');

// Prefixes containing LIKE wildcards or dashes must not collide.
$weird = make_issuer($user, ['name' => 'Weird', 'invoice_prefix' => 'A_B-C', 'is_default' => 0]);
check('prefix with _ and - works', number_of(make_invoice($user, $weird, $client, '2026-01-01')) === 'A_B-C-2026-0001');
check('prefix with _ and - continues', number_of(make_invoice($user, $weird, $client, '2026-01-02')) === 'A_B-C-2026-0002');

// ---------------------------------------------------------------------------
echo $failures === 0 ? "\nAll integration tests passed.\n" : "\n{$failures} integration check(s) FAILED.\n";
exit($failures === 0 ? 0 : 1);
