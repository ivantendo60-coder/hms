<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('admin');
$page_title='Payout Accounts';

$flash = $_SESSION['flash_msg'] ?? null;
if ($flash) { unset($_SESSION['flash_msg']); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? 'save';

  if ($action === 'save') {
    $accountName = trim($_POST['account_name'] ?? '');
    $provider = strtolower($_POST['provider'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $isDefault = !empty($_POST['is_default']) ? 1 : 0;

    if ($accountName === '' || $provider === '' || $accountNumber === '') {
      $_SESSION['flash_msg'] = 'Please fill the required fields.';
      header('Location: payout_accounts.php');
      exit;
    }

    if ($isDefault) {
      $pdo->prepare('UPDATE payout_accounts SET is_default=0')->execute();
    }

    $stmt = $pdo->prepare('INSERT INTO payout_accounts (account_name, provider, account_number, phone_number, bank_name, currency, is_default, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$accountName, $provider, $accountNumber, $phoneNumber, $bankName, 'UGX', $isDefault, $notes]);
    $_SESSION['flash_msg'] = 'Payout account saved successfully.';
    header('Location: payout_accounts.php');
    exit;
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $pdo->prepare('DELETE FROM payout_accounts WHERE id=?')->execute([$id]);
      $_SESSION['flash_msg'] = 'Payout account deleted.';
    }
    header('Location: payout_accounts.php');
    exit;
  }
}

$accounts = $pdo->query('SELECT * FROM payout_accounts ORDER BY is_default DESC, account_name ASC')->fetchAll();
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-bank text-primary"></i> Payout Accounts</h3>
<div class="card border-0 shadow-sm p-3 mb-3">
  <p class="text-muted">Create and maintain MTN, Airtel, and bank payout accounts that can be used for worker payments.</p>
</div>
<?php if ($flash): ?><div class="alert alert-info"><?= e($flash) ?></div><?php endif; ?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm p-3">
      <h5 class="mb-3">Add New Account</h5>
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="mb-3">
          <label class="form-label">Account Name</label>
          <input name="account_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Provider</label>
          <select name="provider" class="form-select" required>
            <option value="mtn">MTN Mobile Money</option>
            <option value="airtel">Airtel Money</option>
            <option value="bank">Bank</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Account Number</label>
          <input name="account_number" class="form-control" placeholder="Mobile number or bank account number" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Phone Number</label>
          <input name="phone_number" class="form-control" placeholder="Optional for mobile money">
        </div>
        <div class="mb-3">
          <label class="form-label">Bank Name</label>
          <input name="bank_name" class="form-control" placeholder="Required for bank accounts">
        </div>
        <div class="mb-3 form-check">
          <input class="form-check-input" type="checkbox" name="is_default" value="1">
          <label class="form-check-label">Set as default payout account</label>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="3"></textarea>
        </div>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Save Account</button>
      </form>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm p-3">
      <h5 class="mb-3">Saved Accounts</h5>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Name</th><th>Provider</th><th>Details</th><th>Default</th><th></th></tr></thead>
          <tbody>
            <?php foreach($accounts as $account): ?>
            <tr>
              <td><?= e($account['account_name']) ?></td>
              <td><?= e(ucfirst($account['provider'])) ?></td>
              <td>
                <?= e($account['account_number']) ?><br>
                <?php if ($account['phone_number']): ?><small class="text-muted">Phone: <?= e($account['phone_number']) ?></small><?php endif; ?>
                <?php if ($account['bank_name']): ?><br><small class="text-muted">Bank: <?= e($account['bank_name']) ?></small><?php endif; ?>
              </td>
              <td><?= $account['is_default'] ? '<span class="badge bg-success">Default</span>' : '' ?></td>
              <td>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this payout account?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>