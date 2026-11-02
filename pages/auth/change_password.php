<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_login();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $passwordInput = $_POST['new_password'] ?? '';
    $confirmationInput = $_POST['confirm_password'] ?? '';
    $password = is_string($passwordInput) ? $passwordInput : '';
    $confirmation = is_string($confirmationInput) ? $confirmationInput : '';
    $error = password_policy_error($password) ?? '';

    if ($error === '' && empty(current_user()['must_change_password'])) {
        $currentPasswordInput = $_POST['current_password'] ?? '';
        $currentPassword = is_string($currentPasswordInput) ? $currentPasswordInput : '';
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id=?');
        $stmt->execute([current_user()['id']]);
        $hash = $stmt->fetchColumn();
        if (!is_string($hash) || !password_verify($currentPassword, $hash)) {
            $error = 'Current password is incorrect.';
        }
    }
    if ($error === '' && !hash_equals($password, $confirmation)) {
        $error = 'The password confirmation does not match.';
    }
    if ($error === '') {
        $stmt = $pdo->prepare('UPDATE users SET password=?, must_change_password=0 WHERE id=?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), current_user()['id']]);
        $_SESSION['user']['must_change_password'] = 0;
        session_regenerate_id(true);
        unset($_SESSION['csrf']);
        header('Location: ' . base_url('pages/' . current_user()['role'] . '/dashboard.php'));
        exit;
    }
}

$page_title = 'Change Password';
include __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm p-4">
      <h3><i class="bi bi-shield-lock text-primary"></i> Change your password</h3>
      <p class="text-muted">Use at least 12 characters and no more than 72 bytes, including an uppercase letter, a lowercase letter, a number, and a symbol.</p>
      <?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <?php if (empty(current_user()['must_change_password'])): ?>
          <div class="mb-3">
            <label for="current-password" class="form-label">Current password</label>
            <input id="current-password" name="current_password" type="password" class="form-control" autocomplete="current-password" required>
          </div>
        <?php endif; ?>
        <div class="mb-3">
          <label for="new-password" class="form-label">New password</label>
          <input id="new-password" name="new_password" type="password" class="form-control" minlength="12" maxlength="72" autocomplete="new-password" autocapitalize="none" spellcheck="false" required>
        </div>
        <div class="mb-3">
          <label for="confirm-password" class="form-label">Confirm new password</label>
          <input id="confirm-password" name="confirm_password" type="password" class="form-control" minlength="12" maxlength="72" autocomplete="new-password" autocapitalize="none" spellcheck="false" required>
        </div>
        <button class="btn btn-primary">Save password</button>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
