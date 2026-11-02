<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$users = $pdo->query('SELECT id,name,email,role FROM users ORDER BY role, name')->fetchAll();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['new_password'])) {
  csrf_check();
  $userId = (int)$_POST['user_id'];
  $passwordInput = $_POST['new_password'];
  $newPassword = is_string($passwordInput) ? $passwordInput : '';
  if ($newPassword === '') {
    $message = 'Password cannot be empty.';
  } elseif (password_policy_error($newPassword) !== null) {
    $message = password_policy_error($newPassword);
  } else {
    $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
    $pdo->prepare('UPDATE users SET must_change_password=1 WHERE id=?')->execute([$userId]);
    if ($userId === (int)current_user()['id']) {
      $_SESSION['user']['must_change_password'] = 1;
    }
    $message = 'Password updated successfully.';
  }
}

$page_title='Password Manager';
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-key text-primary"></i> Password Manager</h3>
<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<div class="card border-0 shadow-sm p-3 mb-3">
  <p class="text-muted">Reset staff passwords securely. New passwords are stored hashed in the database.</p>
</div>
<div class="card border-0 shadow-sm p-3">
  <form method="post">
    <div class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Staff Member</label>
        <select name="user_id" class="form-select" required>
          <option value="">Select staff</option>
          <?php foreach($users as $u): ?>
            <option value="<?= $u['id'] ?>"<?= $u['id']===$id?' selected':'' ?>><?= e($u['name'].' ('.ucfirst($u['role']).')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">New Password</label>
        <input name="new_password" type="password" class="form-control" minlength="12" maxlength="72" autocomplete="new-password" required>
      </div>
      <div class="col-md-4">
        <button class="btn btn-primary w-100">Update Password</button>
      </div>
    </div>
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>