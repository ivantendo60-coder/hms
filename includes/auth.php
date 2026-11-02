<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('HMSSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/hms/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function require_login() {
    if (empty($_SESSION['user'])) {
        header('Location: ' . base_url('pages/auth/login.php'));
        exit;
    }
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: ' . base_url('pages/auth/login?timeout=1'));
        exit;
    }
    $_SESSION['last_activity'] = time();
    if (!empty($_SESSION['user']['must_change_password'])
        && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'change_password.php') {
        header('Location: ' . base_url('pages/auth/change_password.php'));
        exit;
    }
}
function require_role($roles) {
    require_login();
    $roles = (array)$roles;
    if (!in_array($_SESSION['user']['role'], $roles)) {
        http_response_code(403);
        die('Access denied. Only doctors and authorized staff may access this system.');
    }
}
function current_user() { return $_SESSION['user'] ?? null; }

function close_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function base_url($path = '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = dirname($_SERVER['SCRIPT_NAME']);
    // walk up to project root (folder containing index.php)
    $root = '/hms';
    // Try to autodetect by finding 'hms' in path
    if (strpos($script, '/hms') !== false) {
        $root = substr($script, 0, strpos($script, '/hms') + 4);
    }
    $path = ltrim((string)$path, '/');
    if (preg_match('~(?:^|/)index\.php$~i', $path)) {
        $path = substr($path, 0, -strlen('index.php'));
    } else {
        $path = preg_replace('~\.php$~i', '', $path);
    }
    return $scheme . '://' . $host . $root . '/' . $path;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check() {
    $submitted = $_POST['csrf'] ?? null;
    $expected = $_SESSION['csrf'] ?? null;
    if (!is_string($submitted) || !is_string($expected) || !hash_equals($expected, $submitted)) {
        http_response_code(400);
        exit('Invalid CSRF token');
    }
}
function password_policy_error(string $password): ?string {
    if (strlen($password) > 72) return 'Use no more than 72 bytes (some symbols count as multiple bytes).';

    $characterCount = preg_match_all('/./us', $password, $matches);
    if ($characterCount === false) return 'Use valid text characters in your password.';
    if ($characterCount < 12) return 'Use at least 12 characters.';

    $requirements = [
        'an uppercase letter' => '/\p{Lu}/u',
        'a lowercase letter' => '/\p{Ll}/u',
        'a number' => '/\p{N}/u',
        'a symbol (such as !, @, or #)' => '/[\p{P}\p{S}]/u',
    ];
    $missing = [];
    foreach ($requirements as $label => $pattern) {
        if (preg_match($pattern, $password) !== 1) {
            $missing[] = $label;
        }
    }
    return $missing ? 'Password is missing ' . implode(', ', $missing) . '.' : null;
}
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
