<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payments.php';

header('Content-Type: application/json');
require_login();
require_role('admin');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

if ($action === 'create') {
    $result = create_staff_payment($pdo, $input, (int)$_SESSION['user']['id']);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
