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
echo "\nIssued invoices are final\n";
$lockUser = make_user('lock@example.com');
$lockIssuer = make_issuer($lockUser);
$lockClient = make_client($lockUser);
$draft = make_invoice($lockUser, $lockIssuer, $lockClient, '2026-05-01', 'draft');
$issued = make_invoice($lockUser, $lockIssuer, $lockClient, '2026-05-02', 'issued');
$editInput = [
    'issuer_id' => $lockIssuer, 'client_id' => $lockClient, 'issue_date' => '2026-05-03', 'status' => 'draft',
    'items' => [['description' => 'CHANGED', 'unit' => 'pcs', 'quantity' => 1, 'unit_price' => 5]],
];
$descriptionOf = static fn (int $id): string => (string) Database::connection()
    ->query("SELECT description FROM invoice_items WHERE invoice_id = {$id}")->fetchColumn();

(new InvoiceService())->update($draft, $lockUser, $editInput);
check('draft invoice can be edited', $descriptionOf($draft) === 'CHANGED');

$rejected = false;
try {
    (new InvoiceService())->update($issued, $lockUser, $editInput);
} catch (UserFacingException) {
    $rejected = true;
}
check('issued invoice edit is rejected', $rejected);
check('issued invoice content unchanged', $descriptionOf($issued) === 'Service');
check('issued invoice stays issued (no downgrade to draft)', (string) Database::connection()->query("SELECT status FROM invoices WHERE id = {$issued}")->fetchColumn() === 'issued');
check('issued invoice cannot be deleted', Invoice::softDelete($issued, $lockUser) === false);
check('issued invoice is still listed', Invoice::findAccessible($issued, $lockUser) !== null);
check('draft invoice can be deleted', Invoice::softDelete($draft, $lockUser) === true);

$copy = (new InvoiceService())->duplicate($issued, $lockUser);
check('issued invoice can be duplicated as a draft', (string) Database::connection()->query("SELECT status FROM invoices WHERE id = {$copy}")->fetchColumn() === 'draft');

// ---------------------------------------------------------------------------
echo "\nChanging issuer or year of a draft\n";
$mvUser = make_user('move@example.com');
$issuerA = make_issuer($mvUser, ['name' => 'A', 'invoice_prefix' => 'AAA']);
$issuerB = make_issuer($mvUser, ['name' => 'B', 'invoice_prefix' => 'BBB', 'is_default' => 0]);
$mvClient = make_client($mvUser);
make_invoice($mvUser, $issuerB, $mvClient, '2026-03-01');                    // BBB-2026-0001 (issued)
$movable = make_invoice($mvUser, $issuerA, $mvClient, '2026-03-02', 'draft'); // AAA-2026-0001 (draft)
check('draft starts with the number of its issuer', number_of($movable) === 'AAA-2026-0001');

$edit = static fn (int $issuerId, string $date): array => [
    'issuer_id' => $issuerId, 'client_id' => $mvClient, 'issue_date' => $date, 'status' => 'draft',
    'items' => [['description' => 'Service', 'unit' => 'pcs', 'quantity' => 1, 'unit_price' => 100]],
];

(new InvoiceService())->update($movable, $mvUser, $edit($issuerA, '2026-04-10'));
check('editing within the same issuer and year keeps the number', number_of($movable) === 'AAA-2026-0001');

(new InvoiceService())->update($movable, $mvUser, $edit($issuerB, '2026-04-10'));
check('moving to another issuer takes that issuer\'s next number', number_of($movable) === 'BBB-2026-0002', number_of($movable));
check('invoice now belongs to the new issuer', (int) Database::connection()->query("SELECT issuer_id FROM invoices WHERE id = {$movable}")->fetchColumn() === $issuerB);

(new InvoiceService())->update($movable, $mvUser, $edit($issuerB, '2025-12-20'));
check('changing the year renumbers within that year', number_of($movable) === 'BBB-2025-0001', number_of($movable));

// Old behaviour: same number at two issuers could collide. Both issuers have ...-2026-0001 free/used.
$collide = make_invoice($mvUser, $issuerA, $mvClient, '2026-06-01', 'draft');
(new InvoiceService())->update($collide, $mvUser, $edit($issuerB, '2026-06-01'));
check('moving a draft never hits a duplicate-number error', str_starts_with(number_of($collide), 'BBB-2026-'), number_of($collide));
$numbers = Database::connection()->query("SELECT COUNT(*) - COUNT(DISTINCT issuer_id, invoice_number) FROM invoices")->fetchColumn();
check('no duplicate numbers anywhere', (int) $numbers === 0);

// ---------------------------------------------------------------------------
echo "\nLogin throttle\n";
$throttle = new \App\Services\LoginThrottle();
Database::connection()->exec('DELETE FROM login_attempts');

for ($i = 0; $i < 4; $i++) {
    $throttle->record('victim@example.com', '203.0.113.1');
}
check('4 failures are still allowed', !$throttle->tooManyAttempts('victim@example.com', '203.0.113.1'));
$throttle->record('victim@example.com', '203.0.113.1');
check('5th failure locks this e-mail + IP', $throttle->tooManyAttempts('victim@example.com', '203.0.113.1'));
check('same e-mail from another IP is not locked', !$throttle->tooManyAttempts('victim@example.com', '203.0.113.2'));
check('e-mail is case/space insensitive', $throttle->tooManyAttempts('  VICTIM@example.com ', '203.0.113.1'));
$throttle->clear('victim@example.com', '203.0.113.1');
check('successful login clears the counter', !$throttle->tooManyAttempts('victim@example.com', '203.0.113.1'));

for ($i = 0; $i < 29; $i++) {
    $throttle->record("spray{$i}@example.com", '198.51.100.50');   // password spraying: one try per e-mail
}
check('29 spray attempts from one IP are tolerated', !$throttle->tooManyAttempts('fresh@example.com', '198.51.100.50'));
$throttle->record('spray29@example.com', '198.51.100.50');
check('30th attempt locks the whole IP, even for a new e-mail', $throttle->tooManyAttempts('fresh@example.com', '198.51.100.50'));
check('other IPs are unaffected', !$throttle->tooManyAttempts('fresh@example.com', '198.51.100.51'));

Database::connection()->exec("INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES ('old@example.com', '192.0.2.1', DATE_SUB(NOW(), INTERVAL 2 DAY))");
$before = (int) Database::connection()->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn();
check('purge removes only rows older than a day', $throttle->purgeOld() === 1
    && (int) Database::connection()->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn() === $before - 1);

// ---------------------------------------------------------------------------
echo $failures === 0 ? "\nAll integration tests passed.\n" : "\n{$failures} integration check(s) FAILED.\n";
exit($failures === 0 ? 0 : 1);
