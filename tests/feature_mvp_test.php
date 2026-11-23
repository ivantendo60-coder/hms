<?php
$root = dirname(__DIR__);
$failures = [];
$passed = 0;

function featureCheck(bool $condition, string $name, array &$failures, int &$passed): void {
    if ($condition) {
        $passed++;
        echo "PASS {$name}\n";
    } else {
        $failures[] = $name;
        echo "FAIL {$name}\n";
    }
}

$requiredFiles = [
    'live queue API' => 'api/queue_status.php',
    'appointment page' => 'pages/receptionist/appointments.php',
    'patient portal' => 'pages/patient/portal.php',
    'portal helper' => 'includes/portal.php',
];
foreach ($requiredFiles as $name => $file) {
    featureCheck(file_exists($root . '/' . $file), $name, $failures, $passed);
}

$hms = file_get_contents($root . '/sql/hms.sql');
$upgrade = file_get_contents($root . '/sql/upgrade.sql');
featureCheck(str_contains($hms, 'CREATE TABLE IF NOT EXISTS appointments'), 'base appointments schema', $failures, $passed);
featureCheck(str_contains($upgrade, 'CREATE TABLE IF NOT EXISTS appointments'), 'upgrade appointments schema', $failures, $passed);
featureCheck(str_contains($hms, 'CREATE TABLE IF NOT EXISTS patient_portal_tokens'), 'base patient portal schema', $failures, $passed);
featureCheck(str_contains($upgrade, 'CREATE TABLE IF NOT EXISTS patient_portal_tokens'), 'upgrade patient portal schema', $failures, $passed);

$reports = file_get_contents($root . '/pages/admin/reports.php');
$payments = file_get_contents($root . '/pages/admin/payments.php');
featureCheck(str_contains($reports, "export=csv"), 'report CSV export', $failures, $passed);
featureCheck(str_contains($payments, "export=csv"), 'receipt CSV export', $failures, $passed);

$receptionView = file_get_contents($root . '/pages/receptionist/patient_view.php');
featureCheck(str_contains($receptionView, 'QRCode'), 'patient identity QR', $failures, $passed);
featureCheck(str_contains($receptionView, "json_encode(\$p['rfid_uid'])"), 'patient QR contains card number', $failures, $passed);
featureCheck(str_contains($receptionView, 'issue_portal'), 'patient portal issuing', $failures, $passed);
$patientDirectory = file_get_contents($root . '/pages/receptionist/patients.php');
featureCheck(str_contains($patientDirectory, 'SELECT rfid_uid FROM patients WHERE rfid_uid=?'), 'QR scan opens exact patient from directory search', $failures, $passed);
$patientSearch = file_get_contents($root . '/pages/patient/search.php');
featureCheck(str_contains($patientSearch, 'SELECT rfid_uid FROM patients WHERE rfid_uid=?'), 'QR scan opens exact patient from patient search', $failures, $passed);
$patientPrint = file_get_contents($root . '/pages/patient/print.php');
featureCheck(str_contains($patientPrint, 'patient-card-qr'), 'printable patient QR', $failures, $passed);
featureCheck(str_contains($patientPrint, "json_encode(\$p['rfid_uid'])"), 'print QR contains card number', $failures, $passed);
featureCheck(str_contains($patientPrint, "base_url('assets/img/logo.png')"), 'printable HMS logo', $failures, $passed);

$sidebar = file_get_contents($root . '/includes/sidebar.php');
featureCheck(str_contains($sidebar, 'appointments.php'), 'appointment navigation', $failures, $passed);

$appPass = getenv('HMS_TEST_APP_PASS');
if ($appPass) {
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=hms_db;charset=utf8mb4',
            'xampp_hms_db', $appPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach (['appointments','patient_portal_tokens'] as $table) {
            featureCheck(in_array($table, $tables, true), "live database table {$table}", $failures, $passed);
        }
    } catch (Throwable $e) {
        $failures[] = 'live database feature connection';
        echo "FAIL live database feature connection: " . $e->getMessage() . "\n";
    }
}

echo "\nRESULT passed={$passed} failed=" . count($failures) . "\n";
exit($failures ? 1 : 0);
