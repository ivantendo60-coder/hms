<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
require_login();

$role = (string)current_user()['role'];
$payload = [
    'open_visits' => (int)$pdo->query("SELECT COUNT(*) FROM visits WHERE status='open'")->fetchColumn(),
    'today_visits' => (int)$pdo->query("SELECT COUNT(*) FROM visits WHERE DATE(visit_date)=CURDATE()")->fetchColumn(),
];

if ($role === 'lab') {
    $payload['lab_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM lab_tests WHERE status='pending'")->fetchColumn();
    $payload['lab_today'] = (int)$pdo->query("SELECT COUNT(*) FROM lab_tests WHERE DATE(created_at)=CURDATE()")->fetchColumn();
}
if ($role === 'pharmacy') {
    $payload['prescriptions_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM prescriptions WHERE dispensed=0")->fetchColumn();
    $payload['clearance_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM visits WHERE status='open' AND pharmacy_cleared=0")->fetchColumn();
}
if ($role === 'receptionist') {
    $payload['unregistered_rfids'] = (int)$pdo->query("SELECT COUNT(*) FROM pending_rfid WHERE reviewed=0")->fetchColumn();
}
if (in_array($role, ['doctor', 'receptionist'], true)) {
    $payload['appointments_today'] = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_at)=CURDATE() AND status='scheduled'")->fetchColumn();
}

echo json_encode($payload);
