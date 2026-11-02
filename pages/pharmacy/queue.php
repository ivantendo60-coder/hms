<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('pharmacy');
$page_title='Prescriptions';
$payment = $_GET['payment'] ?? 'all';
$allowed = ['all','pending','confirmed'];
if (!in_array($payment, $allowed, true)) {
  $payment = 'all';
}
$filterSql = '';
if ($payment === 'pending') {
  $filterSql = ' WHERE v.pharmacy_cleared = 0';
} elseif ($payment === 'confirmed') {
  $filterSql = ' WHERE v.pharmacy_cleared = 1';
}
$rows=$pdo->query("SELECT r.*, p.full_name, p.rfid_uid, v.pharmacy_cleared FROM prescriptions r JOIN visits v ON v.id=r.visit_id JOIN patients p ON p.id=v.patient_id" . $filterSql . " ORDER BY r.created_at DESC LIMIT 50")->fetchAll();
include __DIR__ . '/../../includes/header.php';
?>
<h3>Prescription Queue</h3>
<form method="get" class="mb-3 row gx-2 gy-2 align-items-center">
  <div class="col-auto">
    <label for="payment" class="form-label mb-0">Show</label>
    <select id="payment" name="payment" class="form-select form-select-sm">
      <option value="all"<?= $payment==='all' ? ' selected' : '' ?>>All visits</option>
      <option value="pending"<?= $payment==='pending' ? ' selected' : '' ?>>Awaiting pharmacy clearance</option>
      <option value="confirmed"<?= $payment==='confirmed' ? ' selected' : '' ?>>Pharmacy cleared</option>
    </select>
  </div>
  <div class="col-auto align-self-end">
    <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
  </div>
</form>
<table class="table bg-white shadow-sm"><thead><tr><th>Date</th><th>Patient</th><th>Medicine</th><th>Dosage</th><th>Dispensed</th><th>Payment</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr><td><?= e($r['created_at']) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['medicine']) ?></td><td><?= e($r['dosage']) ?></td>
<td><?= $r['dispensed']?'✅':'⌛' ?></td><td><?= $r['pharmacy_cleared'] ? '<span class="badge bg-success">Cleared</span>' : '<span class="badge bg-warning text-dark">Awaiting clearance</span>' ?></td>
<td><a class="btn btn-sm btn-primary" href="<?= base_url('pages/patient/view.php?uid='.urlencode($r['rfid_uid'])) ?>">Open</a></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
