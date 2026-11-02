<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/visit_billing.php';
require_role('pharmacy');
$page_title = 'Payment Clearance';
$flash = $_SESSION['flash_msg'] ?? null;
$error = $_SESSION['flash_err'] ?? null;
unset($_SESSION['flash_msg'], $_SESSION['flash_err']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  try {
    $action = $_POST['action'] ?? '';
    if ($action === 'record_payment') {
      record_visit_payment(
        $pdo,
        billing_positive_integer($_POST['visit_id'] ?? null, 'visit'),
        $_POST['amount'] ?? '',
        billing_string_value($_POST['method'] ?? null, 'payment method'),
        billing_string_value($_POST['reference'] ?? '', 'payment reference'),
        'pharmacy',
        (int)current_user()['id'],
        'pharmacy'
      );
      $_SESSION['flash_msg'] = 'Payment recorded at pharmacy.';
    } elseif ($action === 'clear_bill') {
      clear_visit_payment($pdo, billing_positive_integer($_POST['visit_id'] ?? null, 'visit'), (int)current_user()['id'], 'pharmacy');
      $_SESSION['flash_msg'] = 'Bill cleared. Pharmacy clearance is now recorded for this visit.';
    } else {
      throw new InvalidArgumentException('Unknown payment action.');
    }
  } catch (Throwable $e) {
    $_SESSION['flash_err'] = $e->getMessage();
  }
  header('Location: clearance.php');
  exit;
}

$visits = $pdo->query("SELECT v.id, v.visit_date, v.patient_type, v.pharmacy_cleared, v.pharmacy_cleared_at,
    cleared_by.name AS cleared_by_name, p.full_name, p.rfid_uid,
    COALESCE(charges.total,0) AS total_charges, COALESCE(paid.total,0) AS total_paid
    FROM visits v
    JOIN patients p ON p.id=v.patient_id
    LEFT JOIN users cleared_by ON cleared_by.id=v.pharmacy_cleared_by
    LEFT JOIN (SELECT visit_id, SUM(line_total) AS total FROM visit_services GROUP BY visit_id) charges ON charges.visit_id=v.id
    LEFT JOIN (SELECT visit_id, SUM(amount) AS total FROM visit_payments GROUP BY visit_id) paid ON paid.visit_id=v.id
    WHERE v.status='open'
    ORDER BY v.pharmacy_cleared ASC, v.visit_date DESC LIMIT 100")->fetchAll();
$history = $pdo->query("SELECT vp.amount, vp.method, vp.reference, vp.created_at,
    p.full_name, u.name AS staff_name
    FROM visit_payments vp
    JOIN visits v ON v.id=vp.visit_id
    JOIN patients p ON p.id=v.patient_id
    LEFT JOIN users u ON u.id=vp.collected_by
    WHERE vp.collection_point='pharmacy'
    ORDER BY vp.id DESC LIMIT 30")->fetchAll();
$today = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM visit_payments WHERE collection_point='pharmacy' AND DATE(created_at)=CURDATE()")->fetchColumn();
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-shield-check text-primary"></i> Pharmacy Payment Clearance</h3>
<p class="text-muted">Record inpatient payments and any remaining balance here. Verify the full bill is paid before giving final clearance to either patient type.</p>
<div class="card card-stat p-3 my-3"><small class="text-muted">Collected at pharmacy today</small><h2>UGX <?= number_format((float)$today, 2) ?></h2></div>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card border-0 shadow-sm p-3 mb-4">
  <h5>Open patient bills</h5>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Patient / Visit</th><th>Type</th><th>Charges</th><th>Paid</th><th>Balance</th><th>Payment / Clearance</th></tr></thead>
      <tbody>
      <?php foreach ($visits as $visit):
        $balance = max(0, (int)round(((float)$visit['total_charges'] - (float)$visit['total_paid']) * 100));
      ?>
        <tr>
          <td><a href="<?= base_url('pages/patient/view.php?uid=' . urlencode($visit['rfid_uid'])) ?>"><?= e($visit['full_name']) ?></a><br><small class="text-muted"><?= e($visit['visit_date']) ?></small></td>
          <td><span class="badge bg-<?= $visit['patient_type'] === 'inpatient' ? 'danger' : 'info' ?>"><?= e(ucfirst($visit['patient_type'])) ?></span></td>
          <td>UGX <?= number_format((float)$visit['total_charges'], 2) ?></td>
          <td>UGX <?= number_format((float)$visit['total_paid'], 2) ?></td>
          <td class="<?= $balance > 0 ? 'text-danger fw-semibold' : 'text-success' ?>">UGX <?= number_format($balance / 100, 2) ?></td>
          <td>
            <?php if ($visit['pharmacy_cleared']): ?>
              <span class="badge bg-success">Cleared</span>
              <div class="small text-muted"><?= e($visit['cleared_by_name'] ?: 'Pharmacy') ?> · <?= e($visit['pharmacy_cleared_at']) ?></div>
            <?php else: ?>
              <?php if ($balance > 0): ?>
              <form method="post" class="row g-1 mb-2">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="visit_id" value="<?= (int)$visit['id'] ?>">
                <div class="col-4"><input name="amount" type="number" min="0.01" max="<?= number_format($balance / 100, 2, '.', '') ?>" step="0.01" class="form-control form-control-sm" placeholder="Amount" required></div>
                <div class="col-4"><select name="method" class="form-select form-select-sm" required><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option></select></div>
                <div class="col-4"><input name="reference" maxlength="100" class="form-control form-control-sm" placeholder="Receipt/ref"></div>
                <div class="col-12"><button class="btn btn-sm btn-outline-primary">Record pharmacy payment</button></div>
              </form>
              <?php endif; ?>
              <?php if ((float)$visit['total_charges'] <= 0): ?>
                <span class="small text-muted">Waiting for departments to add services.</span>
              <?php endif; ?>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="clear_bill">
                <input type="hidden" name="visit_id" value="<?= (int)$visit['id'] ?>">
                <button class="btn btn-sm btn-success" <?= $balance > 0 || (float)$visit['total_charges'] <= 0 ? 'disabled' : '' ?>>Final clearance</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$visits): ?><tr><td colspan="6" class="text-muted">No open patient bills.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="card border-0 shadow-sm p-3">
  <h5>Recent pharmacy receipts</h5>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>Date</th><th>Patient</th><th>Amount</th><th>Method</th><th>Reference</th><th>Received by</th></tr></thead>
      <tbody>
      <?php foreach ($history as $receipt): ?>
        <tr><td><?= e($receipt['created_at']) ?></td><td><?= e($receipt['full_name']) ?></td><td>UGX <?= number_format((float)$receipt['amount'], 2) ?></td><td><?= e(ucwords(str_replace('_', ' ', $receipt['method']))) ?></td><td><?= e($receipt['reference'] ?: '—') ?></td><td><?= e($receipt['staff_name'] ?: '—') ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$history): ?><tr><td colspan="6" class="text-muted">No pharmacy receipts recorded yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
