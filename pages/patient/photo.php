<?php
// Secure photo streamer. All authenticated staff may view.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/upload.php';
require_role(['doctor','receptionist','lab','pharmacy','admin']);

$id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare("SELECT photo, photo_mime FROM patients WHERE id=?");
$s->execute([$id]);
$row = $s->fetch();

$placeholder = realpath(__DIR__ . '/../../assets/img/doctor.jpg');
if (!$row || empty($row['photo'])) {
    header('Content-Type: image/jpeg');
    readfile($placeholder);
    exit;
}
$path = uploads_dir() . '/' . basename($row['photo']);
if (!is_file($path)) {
    header('Content-Type: image/jpeg');
    readfile($placeholder);
    exit;
}
header('Content-Type: ' . ($row['photo_mime'] ?: 'image/jpeg'));
header('Cache-Control: private, max-age=300');
readfile($path);
