<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/theme.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
csrf_check();

$theme = $_POST['color_theme'] ?? '';
$themes = hms_color_themes();
if (!is_string($theme) || !isset($themes[$theme])) {
    http_response_code(400);
    exit('Invalid color theme');
}

$user = current_user();
$stmt = $pdo->prepare('UPDATE users SET color_theme=? WHERE id=?');
$stmt->execute([$theme, $user['id']]);
$_SESSION['user']['color_theme'] = $theme;

header('Location: ' . base_url('pages/' . $user['role'] . '/dashboard.php'));
exit;
