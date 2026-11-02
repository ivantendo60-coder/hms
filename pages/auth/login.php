<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
if (current_user()) {
    header('Location: ' . base_url('pages/' . current_user()['role'] . '/dashboard.php'));
    exit;
}

$email = '';
$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
    $pass = is_string($passwordInput) ? $passwordInput : '';
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $emailHash = hash('sha256', $email);
    $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $attempts = $pdo->prepare("SELECT COUNT(*) FROM login_attempts
        WHERE email_hash=? AND ip_hash=? AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $attempts->execute([$emailHash, $ipHash]);
    $locked = (int)$attempts->fetchColumn() >= 5;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    $passwordHash = $u['password'] ?? '$2y$10$Gu7eVteXVecz028WUEjvu.1VlsRcT/rHzoGxA5efwIoYSuY/Rl6ou';
    $validPassword = password_verify($pass, $passwordHash);
    if (!$locked && $u && $validPassword) {
        $pdo->prepare('DELETE FROM login_attempts WHERE email_hash=? AND ip_hash=?')->execute([$emailHash, $ipHash]);
        if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
        }
        session_regenerate_id(true);
        unset($_SESSION['csrf']);
        unset($u['password']);
        $_SESSION['user'] = $u;
        $_SESSION['last_activity'] = time();
        header('Location: ' . base_url('pages/'.$u['role'].'/dashboard.php'));
        exit;
    }
    if (!$locked) {
        $pdo->prepare('INSERT INTO login_attempts (email_hash, ip_hash) VALUES (?,?)')
            ->execute([$emailHash, $ipHash]);
    }
    $err = 'Invalid email or password, or sign-in temporarily unavailable. Try again later.';
}
$page_title='Login';
include __DIR__ . '/../../includes/header.php';
?>
<div class="login-layout">
  <section class="login-intro" aria-label="MediCare HMS">
    <div class="login-intro-art" aria-hidden="true"></div>
    <div class="login-intro-content">
      <a class="login-brand" href="<?= base_url('index.php') ?>">
        <img src="<?= base_url('assets/img/logo.png') ?>" alt="">
        <span>MediCare <strong>HMS</strong></span>
      </a>
      <div class="login-welcome">
        <span class="login-eyebrow"><span class="login-live-dot"></span> HOSPITAL OPERATIONS PORTAL</span>
        <h1>Better care<br>starts <span>together.</span></h1>
        <p>One secure workspace for your care team to coordinate visits, services, and patient records.</p>
        <div class="login-feature-list">
          <div><i class="bi bi-shield-lock" aria-hidden="true"></i><span>Private, staff-only access</span></div>
          <div><i class="bi bi-person-vcard" aria-hidden="true"></i><span>Connected patient records</span></div>
          <div><i class="bi bi-heart-pulse" aria-hidden="true"></i><span>Care teams, working in sync</span></div>
        </div>
      </div>
      <p class="login-intro-foot">A clear view of care, at every step.</p>
    </div>
  </section>

  <section class="login-panel">
    <div class="login-form-wrap">
      <div class="login-mobile-brand">
        <img src="<?= base_url('assets/img/logo.png') ?>" alt="">
        <span>MediCare <strong>HMS</strong></span>
      </div>
      <div class="login-form-heading">
        <span class="login-icon"><i class="bi bi-person-lock" aria-hidden="true"></i></span>
        <p class="login-form-eyebrow">STAFF ACCESS</p>
        <h2>Welcome back</h2>
        <p>Sign in with your hospital staff account.</p>
      </div>
      <?php if($err): ?>
        <div class="alert alert-danger login-alert" role="alert">
          <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
          <span><?= e($err) ?></span>
        </div>
      <?php endif; ?>
      <form method="post" class="login-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="mb-3">
          <label class="form-label" for="login-email">Work email</label>
          <div class="login-input-wrap">
            <i class="bi bi-envelope" aria-hidden="true"></i>
            <input id="login-email" name="email" type="email" class="form-control" value="<?= e($email) ?>" placeholder="you@hospital.org" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="login-password">Password</label>
          <div class="login-input-wrap login-password-wrap">
            <i class="bi bi-key" aria-hidden="true"></i>
            <input id="login-password" name="password" type="password" class="form-control" placeholder="Enter your password" autocomplete="current-password" required>
            <button type="button" class="login-password-toggle" data-password-toggle="login-password" aria-label="Show password" aria-pressed="false">
              <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
          </div>
        </div>
        <button class="btn btn-primary login-submit" type="submit">
          <span>Sign in securely</span><i class="bi bi-arrow-right" aria-hidden="true"></i>
        </button>
      </form>
      <div class="login-security-note">
        <i class="bi bi-shield-check" aria-hidden="true"></i>
        <span>Secure access for authorized hospital personnel only.</span>
      </div>
      <a class="login-back-link" href="<?= base_url('index.php') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to home</a>
    </div>
  </section>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
