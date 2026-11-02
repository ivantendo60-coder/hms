<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/visit_billing.php';
require_role('receptionist');
$page_title = 'Outpatient Payments';
$flash = $_SESSION['flash_msg'] ?? null;
$error = $_SESSION['flash_err'] ?? null;
unset($_SESSION['flash_msg'], $_SESSION['flash_err']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  try {
    record_visit_payment(
      $pdo,
      billing_positive_integer($_POST['visit_id'] ?? null, 'visit'),
      $_POST['amount'] ?? '',
      billing_string_value($_POST['method'] ?? null, 'payment method'),
      billing_string_value($_POST['reference'] ?? '', 'payment reference'),
      'reception',
      (int)current_user()['id'],
      'receptionist'
    );
    $_SESSION['flash_msg'] = 'Outpatient payment recorded. Pharmacy must still provide final bill clearance.';
  } catch (Throwable $e) {
    $_SESSION['flash_err'] = $e->getMessage();
  }
  header('Location: payments.php');
  exit;
}

$visits = $pdo->query("SELECT v.id, v.visit_date, v.patient_type, v.pharmacy_cleared, p.full_name, p.rfid_uid,
    COALESCE(charges.total,0) AS total_charges, COALESCE(paid.total,0) AS total_paid
    FROM visits v
    JOIN patients p ON p.id=v.patient_id
    LEFT JOIN (SELECT visit_id, SUM(line_total) AS total FROM visit_services GROUP BY visit_id) charges ON charges.visit_id=v.id
    LEFT JOIN (SELECT visit_id, SUM(amount) AS total FROM visit_payments GROUP BY visit_id) paid ON paid.visit_id=v.id
    WHERE v.status='open' AND v.patient_type='outpatient'
    ORDER BY v.visit_date DESC LIMIT 100")->fetchAll();
$history = $pdo->query("SELECT vp.amount, vp.method, vp.reference, vp.collection_point, vp.created_at,
    p.full_name, u.name AS staff_name
    FROM visit_payments vp
    JOIN visits v ON v.id=vp.visit_id
    JOIN patients p ON p.id=v.patient_id
    LEFT JOIN users u ON u.id=vp.collected_by
    WHERE vp.collection_point='reception'
    ORDER BY vp.id DESC LIMIT 30")->fetchAll();
$today = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM visit_payments WHERE collection_point='reception' AND DATE(created_at)=CURDATE()")->fetchColumn();
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-cash-coin text-primary"></i> Outpatient Payments</h3>
<div class="card card-stat p-3 my-3"><small class="text-muted">Collected at reception today</small><h2>UGX <?= number_format((float)$today, 2) ?></h2></div>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card border-0 shadow-sm p-3 mb-4">
  <h5>Open outpatient bills</h5>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Patient / Visit</th><th>Charges</th><th>Paid</th><th>Balance</th><th>Record payment</th></tr></thead>
      <tbody>
      <?php foreach ($visits as $visit):
        $balance = max(0, (int)round(((float)$visit['total_charges'] - (float)$visit['total_paid']) * 100));
      ?>
        <tr>
          <td><a href="<?= base_url('pages/patient/view.php?uid=' . urlencode($visit['rfid_uid'])) ?>"><?= e($visit['full_name']) ?></a><br><small class="text-muted"><?= e($visit['visit_date']) ?></small></td>
          <td>UGX <?= number_format((float)$visit['total_charges'], 2) ?></td>
          <td>UGX <?= number_format((float)$visit['total_paid'], 2) ?></td>
          <td class="<?= $balance > 0 ? 'text-danger fw-semibold' : 'text-success' ?>">UGX <?= number_format($balance / 100, 2) ?></td>
          <td>
            <?php if (!$visit['pharmacy_cleared'] && $balance > 0): ?>
            <form method="post" class="row g-1">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="visit_id" value="<?= (int)$visit['id'] ?>">
              <div class="col-4"><input name="amount" type="number" min="0.01" max="<?= number_format($balance / 100, 2, '.', '') ?>" step="0.01" class="form-control form-control-sm" placeholder="Amount" required></div>
              <div class="col-4"><select name="method" class="form-select form-select-sm" required><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option></select></div>
              <div class="col-4"><input name="reference" maxlength="100" class="form-control form-control-sm" placeholder="Receipt/ref"></div>
              <div class="col-12"><button class="btn btn-sm btn-primary">Record payment</button></div>
            </form>
            <?php elseif ($visit['pharmacy_cleared']): ?>
              <span class="badge bg-success">Pharmacy cleared</span>
            <?php else: ?>
              <span class="badge bg-secondary">No balance due</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$visits): ?><tr><td colspan="5" class="text-muted">No open outpatient bills.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="card border-0 shadow-sm p-3">
  <h5>Recent reception receipts</h5>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>Date</th><th>Patient</th><th>Amount</th><th>Method</th><th>Reference</th><th>Received by</th></tr></thead>
      <tbody>
      <?php foreach ($history as $receipt): ?>
        <tr><td><?= e($receipt['created_at']) ?></td><td><?= e($receipt['full_name']) ?></td><td>UGX <?= number_format((float)$receipt['amount'], 2) ?></td><td><?= e(ucwords(str_replace('_', ' ', $receipt['method']))) ?></td><td><?= e($receipt['reference'] ?: '—') ?></td><td><?= e($receipt['staff_name'] ?: '—') ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$history): ?><tr><td colspan="6" class="text-muted">No reception receipts recorded yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
