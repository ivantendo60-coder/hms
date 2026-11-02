<?php
// Returns the most recent scan + whether it matches a known patient.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
require_login();
$row = $pdo->query("SELECT id, rfid_uid, scanned_at FROM rfid_scans ORDER BY id DESC LIMIT 1")->fetch();
$known = false;
$device_connected = false;
if ($row && (time() - strtotime($row['scanned_at'])) <= 10) {
    $device_connected = true;
    $p = $pdo->prepare("SELECT id FROM patients WHERE rfid_uid=?");
    $p->execute([$row['rfid_uid']]);
    $known = (bool)$p->fetch();
}

echo json_encode([
    'id' => $row ? (int)$row['id'] : null,
    'uid' => $row ? $row['rfid_uid'] : null,
    'at' => $row ? $row['scanned_at'] : null,
    'known' => $known,
    'device_connected' => $device_connected,
]);
