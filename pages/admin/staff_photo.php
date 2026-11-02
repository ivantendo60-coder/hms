<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT photo FROM users WHERE id=?');
$stmt->execute([$id]);
$user = $stmt->fetch();

$placeholder = realpath(__DIR__ . '/../../assets/img/doctor.jpg');
if (!$user || empty($user['photo'])) {
    header('Content-Type: image/jpeg');
    readfile($placeholder);
    exit;
}
$path = realpath(__DIR__ . '/../../uploads/staff') . '/' . basename($user['photo']);
if (!is_file($path)) {
    header('Content-Type: image/jpeg');
    readfile($placeholder);
    exit;
}
header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=300');
readfile($path);
