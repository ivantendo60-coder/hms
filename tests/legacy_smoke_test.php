<?php

$root = dirname(__DIR__);
$failures = [];
$passed = 0;

function checkLegacyTest(bool $condition, string $name, array &$failures, int &$passed): void
{
    if ($condition) {
        $passed++;
        echo "PASS {$name}\n";
        return;
    }
    $failures[] = $name;
    echo "FAIL {$name}\n";
}

require_once $root . '/includes/auth.php';
require_once $root . '/includes/visit_billing.php';
require_once $root . '/includes/payments.php';

checkLegacyTest(billing_amount_cents('125.50') === 12550, 'billing amount parsing', $failures, $passed);

try {
    billing_amount_cents('0');
    checkLegacyTest(false, 'billing rejects zero', $failures, $passed);
} catch (InvalidArgumentException $e) {
    checkLegacyTest(true, 'billing rejects zero', $failures, $passed);
}

$policy = password_policy_error('Short1!');
checkLegacyTest(is_string($policy), 'password policy rejects weak password', $failures, $passed);

$bridge = file_get_contents($root . '/api/rfid_bridge.php');
checkLegacyTest(str_contains($bridge, 'require_login()'), 'RFID bridge requires login', $failures, $passed);
checkLegacyTest(str_contains($bridge, 'csrf_check()'), 'RFID bridge requires CSRF', $failures, $passed);
checkLegacyTest(str_contains($bridge, 'Invalid serial port'), 'RFID bridge validates serial port', $failures, $passed);

$ingest = file_get_contents($root . '/api/rfid_ingest.php');
checkLegacyTest(str_contains($ingest, 'HMS_RFID_INGEST_TOKEN'), 'RFID ingest supports token authentication', $failures, $passed);

$hmsSql = file_get_contents($root . '/sql/hms.sql');
$upgradeSql = file_get_contents($root . '/sql/upgrade.sql');
checkLegacyTest(str_contains($hmsSql, 'payout_account_id'), 'base schema includes payout account link', $failures, $passed);
checkLegacyTest(str_contains($upgradeSql, 'payout_account_id'), 'upgrade schema includes payout account link', $failures, $passed);

$paymentsSource = file_get_contents($root . '/includes/payments.php');
checkLegacyTest(str_contains($paymentsSource, 'payout_account_id'), 'payout helper writes account link', $failures, $passed);
checkLegacyTest(str_contains($paymentsSource, 'payout_accounts pa'), 'payout history joins saved account', $failures, $passed);

$requiredPages = [
    'billing' => 'pages/receptionist/payments.php',
    'pharmacy clearance' => 'pages/pharmacy/clearance.php',
    'audit log' => 'pages/patient/view.php',
    'photo upload' => 'pages/patient/photo.php',
];
foreach ($requiredPages as $name => $relative) {
    checkLegacyTest(file_exists($root . '/' . $relative), "legacy coverage target: {$name}", $failures, $passed);
}

$rootPass = getenv('HMS_TEST_ROOT_PASS');
$appPass = getenv('HMS_TEST_APP_PASS');
if ($appPass) {
    try {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;port=3307;dbname=hms_db;charset=utf8mb4',
            'xampp_hms_db',
            $appPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ([
            'users',
            'patients',
            'visits',
            'diagnoses',
            'lab_tests',
            'prescriptions',
            'visit_services',
            'visit_payments',
            'rfid_scans',
            'audit_logs',
            'payout_accounts',
            'staff_payments',
        ] as $table) {
            checkLegacyTest(in_array($table, $tables, true), "database table {$table}", $failures, $passed);
        }

        if (in_array('staff_payments', $tables, true)) {
            $columns = $pdo->query('SHOW COLUMNS FROM staff_payments')->fetchAll(PDO::FETCH_COLUMN);
            checkLegacyTest(in_array('payout_account_id', $columns, true), 'database payout_account_id column', $failures, $passed);
        }
    } catch (Throwable $e) {
        $failures[] = 'database smoke connection';
        echo "FAIL database smoke connection: " . $e->getMessage() . "\n";
    }
}

echo "\nRESULT passed={$passed} failed=" . count($failures) . "\n";
exit($failures ? 1 : 0);
