<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/payments.php';
require_role('admin');
$page_title='Financials & Patient Payments';

$receiptPage = max(1, (int)($_GET['receipt_page'] ?? 1));
$pendingPage = max(1, (int)($_GET['pending_page'] ?? 1));
$pageSize = 50;
$receiptCount = (int)$pdo->query('SELECT COUNT(*) FROM visit_payments')->fetchColumn();
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM visits v
    LEFT JOIN (SELECT visit_id, SUM(line_total) AS total FROM visit_services GROUP BY visit_id) charges ON charges.visit_id=v.id
    LEFT JOIN (SELECT visit_id, SUM(amount) AS total FROM visit_payments GROUP BY visit_id) paid ON paid.visit_id=v.id
    WHERE COALESCE(charges.total,0) > COALESCE(paid.total,0)")->fetchColumn();
$receiptPageCount = max(1, (int)ceil($receiptCount / $pageSize));
$pendingPageCount = max(1, (int)ceil($pendingCount / $pageSize));
$receiptPage = min($receiptPage, $receiptPageCount);
$pendingPage = min($pendingPage, $pendingPageCount);
$receiptOffset = ($receiptPage - 1) * $pageSize;
$pendingOffset = ($pendingPage - 1) * $pageSize;
$billingTotals = $pdo->query("SELECT
    COALESCE(SUM(COALESCE(charges.total,0)),0) AS total_charges,
    COALESCE(SUM(COALESCE(paid.total,0)),0) AS total_paid,
    COALESCE(SUM(GREATEST(COALESCE(charges.total,0)-COALESCE(paid.total,0),0)),0) AS total_pending
    FROM visits v
    LEFT JOIN (SELECT visit_id, SUM(line_total) AS total FROM visit_services GROUP BY visit_id) charges ON charges.visit_id=v.id
    LEFT JOIN (SELECT visit_id, SUM(amount) AS total FROM visit_payments GROUP BY visit_id) paid ON paid.visit_id=v.id")->fetch();
$patientReceiptsQuery = $pdo->prepare("SELECT vp.id, vp.amount, vp.method, vp.reference, vp.collection_point, vp.created_at,
    p.full_name, p.rfid_uid, v.patient_type, u.name AS collected_by_name
    FROM visit_payments vp
    JOIN visits v ON v.id=vp.visit_id
    JOIN patients p ON p.id=v.patient_id
    LEFT JOIN users u ON u.id=vp.collected_by
    ORDER BY vp.created_at DESC, vp.id DESC LIMIT ? OFFSET ?");
$patientReceiptsQuery->bindValue(1, $pageSize, PDO::PARAM_INT);
$patientReceiptsQuery->bindValue(2, $receiptOffset, PDO::PARAM_INT);
$patientReceiptsQuery->execute();
$patientReceipts = $patientReceiptsQuery->fetchAll();
$pendingVisitsQuery = $pdo->prepare("SELECT v.id, v.visit_date, v.status, v.patient_type, v.pharmacy_cleared,
    p.full_name, p.rfid_uid, COALESCE(charges.total,0) AS total_charges, COALESCE(paid.total,0) AS total_paid
    FROM visits v
    JOIN patients p ON p.id=v.patient_id
    LEFT JOIN (SELECT visit_id, SUM(line_total) AS total FROM visit_services GROUP BY visit_id) charges ON charges.visit_id=v.id
    LEFT JOIN (SELECT visit_id, SUM(amount) AS total FROM visit_payments GROUP BY visit_id) paid ON paid.visit_id=v.id
    WHERE COALESCE(charges.total,0) > COALESCE(paid.total,0)
    ORDER BY v.visit_date DESC, v.id DESC LIMIT ? OFFSET ?");
$pendingVisitsQuery->bindValue(1, $pageSize, PDO::PARAM_INT);
$pendingVisitsQuery->bindValue(2, $pendingOffset, PDO::PARAM_INT);
$pendingVisitsQuery->execute();
$pendingVisits = $pendingVisitsQuery->fetchAll();

$payments = $pdo->query("SELECT u.id,u.name,u.role,u.phone,u.email,COUNT(v.id) AS patient_count,
    SUM(v.pharmacy_cleared) AS confirmed_count,
    ROUND(COUNT(v.id)*15,2) AS estimated_revenue
    FROM users u
    LEFT JOIN visits v ON v.created_by=u.id
    WHERE u.role <> 'admin'
    GROUP BY u.id ORDER BY patient_count DESC")->fetchAll();

$flash = $_SESSION['flash_msg'] ?? null;
if ($flash) { unset($_SESSION['flash_msg']); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_payment') {
  csrf_check();
  $result = create_staff_payment($pdo, $_POST, (int)$_SESSION['user']['id']);
  $_SESSION['flash_msg'] = $result['message'];
  header('Location: payments.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_queue') {
  csrf_check();
  $pdo->exec("DELETE FROM staff_payments WHERE status='queued'");
  $_SESSION['flash_msg'] = 'Queued payouts cleared successfully.';
  header('Location: payments.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'retry_payment') {
  csrf_check();
  $paymentId = (int)($_POST['payment_id'] ?? 0);
  $result = retry_staff_payment($pdo, $paymentId);
  $_SESSION['flash_msg'] = $result['message'];
  header('Location: payments.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_failed') {
  csrf_check();
  $pdo->exec("DELETE FROM staff_payments WHERE status='failed'");
  $_SESSION['flash_msg'] = 'Failed payouts cleared successfully.';
  header('Location: payments.php');
  exit;
}

$paymentProviders = payment_provider_options();
$history = get_staff_payment_history($pdo);

include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-wallet2 text-primary"></i> Financials &amp; Patient Payments</h3>
<div class="row g-3 my-3">
  <div class="col-md-4"><div class="card card-stat p-3"><small class="text-muted">Patient charges</small><h2>UGX <?= number_format((float)$billingTotals['total_charges'], 2) ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><small class="text-muted">Payments received</small><h2 class="text-success">UGX <?= number_format((float)$billingTotals['total_paid'], 2) ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><small class="text-muted">Outstanding balances (<?= $pendingCount ?> visits)</small><h2 class="text-danger">UGX <?= number_format((float)$billingTotals['total_pending'], 2) ?></h2></div></div>
</div>
<div class="card border-0 shadow-sm p-3 mb-4" id="pending-payments">
  <h5>Pending patient payments</h5>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Visit date</th><th>Patient</th><th>Type</th><th>Status</th><th>Charges</th><th>Paid</th><th>Balance</th><th>Pharmacy</th></tr></thead>
      <tbody>
      <?php foreach($pendingVisits as $visit): ?>
        <tr>
          <td><?= e($visit['visit_date']) ?></td>
          <td><a href="<?= base_url('pages/patient/view.php?uid=' . urlencode($visit['rfid_uid'])) ?>"><?= e($visit['full_name']) ?></a></td>
          <td><?= e(ucfirst($visit['patient_type'])) ?></td>
          <td><?= e(ucfirst($visit['status'])) ?></td>
          <td>UGX <?= number_format((float)$visit['total_charges'], 2) ?></td>
          <td>UGX <?= number_format((float)$visit['total_paid'], 2) ?></td>
          <td class="text-danger fw-semibold">UGX <?= number_format(max(0, (float)$visit['total_charges'] - (float)$visit['total_paid']), 2) ?></td>
          <td><span class="badge bg-<?= $visit['pharmacy_cleared'] ? 'success' : 'warning text-dark' ?>"><?= $visit['pharmacy_cleared'] ? 'Cleared' : 'Pending' ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$pendingVisits): ?><tr><td colspan="8" class="text-muted">No outstanding patient balances.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($pendingPageCount > 1): ?>
    <nav aria-label="Pending payment pages"><ul class="pagination pagination-sm mb-0">
      <?php for($page=1;$page<=$pendingPageCount;$page++): ?>
        <li class="page-item <?= $page===$pendingPage?'active':'' ?>"><a class="page-link" href="?pending_page=<?= $page ?>&receipt_page=<?= $receiptPage ?>#pending-payments"><?= $page ?></a></li>
      <?php endfor; ?>
    </ul></nav>
  <?php endif; ?>
</div>
<div class="card border-0 shadow-sm p-3 mb-4" id="patient-payments">
  <h5>All patient payments (<?= $receiptCount ?>)</h5>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Date</th><th>Patient</th><th>Type</th><th>Amount</th><th>Method</th><th>Collection point</th><th>Reference</th><th>Received by</th></tr></thead>
      <tbody>
      <?php foreach($patientReceipts as $receipt): ?>
        <tr>
          <td><?= e($receipt['created_at']) ?></td>
          <td><a href="<?= base_url('pages/patient/view.php?uid=' . urlencode($receipt['rfid_uid'])) ?>"><?= e($receipt['full_name']) ?></a></td>
          <td><?= e(ucfirst($receipt['patient_type'])) ?></td>
          <td>UGX <?= number_format((float)$receipt['amount'], 2) ?></td>
          <td><?= e(ucwords(str_replace('_', ' ', $receipt['method']))) ?></td>
          <td><?= e(ucfirst($receipt['collection_point'])) ?></td>
          <td><?= e($receipt['reference'] ?: '—') ?></td>
          <td><?= e($receipt['collected_by_name'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$patientReceipts): ?><tr><td colspan="8" class="text-muted">No patient payments have been recorded.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($receiptPageCount > 1): ?>
    <nav aria-label="Patient payment receipt pages"><ul class="pagination pagination-sm mb-0">
      <?php for($page=1;$page<=$receiptPageCount;$page++): ?>
        <li class="page-item <?= $page===$receiptPage?'active':'' ?>"><a class="page-link" href="?receipt_page=<?= $page ?>&pending_page=<?= $pendingPage ?>#patient-payments"><?= $page ?></a></li>
      <?php endfor; ?>
    </ul></nav>
  <?php endif; ?>
</div>
<div class="card border-0 shadow-sm p-3 mb-3">
  <p class="text-muted">Review patient receipts and outstanding balances above. Staff payouts below are separate and use estimated visit activity.</p>
</div>
<?php if ($flash): ?>
<div class="alert alert-info"><?= e($flash) ?></div>
<?php endif; ?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm p-3 mb-3">
      <h5 class="mb-3">Queue Staff Payout</h5>
      <form method="post">
        <input type="hidden" name="action" value="create_payment">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Staff Member</label>
            <select name="staff_id" class="form-select" required>
              <option value="">Select staff</option>
              <?php foreach($payments as $row): ?>
              <option value="<?= (int)$row['id'] ?>"><?= e($row['name']) ?> (<?= e($row['role']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Amount (UGX)</label>
            <input name="amount" type="number" min="1000" step="1000" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Provider</label>
            <select name="provider" id="provider" class="form-select" required>
              <option value="">Select provider</option>
              <?php foreach($paymentProviders as $key => $opt): ?>
              <option value="<?= e($key) ?>"><?= e($opt['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6" id="provider-detail-block">
            <label class="form-label">Mobile/Bank Details</label>
            <input name="phone" id="provider-phone" class="form-control" placeholder="Phone number">
          </div>
          <div class="col-12">
            <label class="form-label">Bank Name</label>
            <input name="bank_name" id="bank-name" class="form-control" placeholder="Bank name">
          </div>
          <div class="col-12">
            <label class="form-label">Account Number</label>
            <input name="account_number" id="account-number" class="form-control" placeholder="Account number">
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Optional note for the payout"></textarea>
          </div>
          <div class="col-12">
            <button class="btn btn-primary"><i class="bi bi-send"></i> Queue Payout</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm p-3 mb-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Recent Payouts</h5>
        <div class="d-flex gap-2">
          <form method="post" onsubmit="return confirm('Clear all queued payouts?')">
            <input type="hidden" name="action" value="clear_queue">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Clear Queued</button>
          </form>
          <form method="post" onsubmit="return confirm('Clear all failed payouts?')">
            <input type="hidden" name="action" value="clear_failed">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-x-circle"></i> Clear Failed</button>
          </form>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Ref</th><th>Staff</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($history as $row): ?>
          <tr>
            <td><?= e($row['reference']) ?></td>
            <td><?= e($row['staff_name']) ?></td>
            <td>UGX <?= number_format($row['amount']) ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-info text-dark"><?= e($row['status']) ?></span>
                <?php if ($row['status'] === 'queued' || $row['status'] === 'failed'): ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="retry_payment">
                  <input type="hidden" name="payment_id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <button class="btn btn-xs btn-outline-primary py-0 px-2">Retry</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<div class="card border-0 shadow-sm p-3">
  <h5 class="mb-3">Staff Payment Summary</h5>
  <div class="table-responsive">
    <table class="table bg-white shadow-sm">
    <thead><tr><th>Name</th><th>Role</th><th>Patients</th><th>Pharmacy-Cleared Visits</th><th>Payment Estimate</th><th>Contact</th></tr></thead>
    <tbody>
    <?php foreach($payments as $row): ?>
    <tr>
      <td><?= e($row['name']) ?></td>
      <td><?= e($row['role']) ?></td>
      <td><?= $row['patient_count'] ?></td>
      <td><?= $row['confirmed_count'] ?? 0 ?></td>
      <td>UGX <?= number_format($row['estimated_revenue']) ?></td>
      <td><?= e($row['email']) ?><br><?= e($row['phone']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
  </div>
</div>
<script>
const provider = document.getElementById('provider');
const phoneField = document.getElementById('provider-phone');
const bankNameField = document.getElementById('bank-name');
const accountNumberField = document.getElementById('account-number');
function syncProviderFields(){
  const value = provider ? provider.value : '';
  if (value === 'bank') {
    phoneField.placeholder = 'Phone number (optional for bank)';
    bankNameField.parentElement.style.display = '';
    accountNumberField.parentElement.style.display = '';
  } else {
    bankNameField.parentElement.style.display = 'none';
    accountNumberField.parentElement.style.display = 'none';
  }
}
if (provider) {
  provider.addEventListener('change', syncProviderFields);
  syncProviderFields();
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>