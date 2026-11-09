<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('admin');
$page_title='Patient History';

$query = trim($_GET['q'] ?? '');
$last_rfid_scan_id = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM rfid_scans')->fetchColumn();
$patient = null;
$visits = [];
if ($query !== '') {
  $p = $pdo->prepare('SELECT * FROM patients WHERE rfid_uid=? OR full_name LIKE ? LIMIT 1');
  $p->execute([strtoupper($query), '%'.$query.'%']);
  $patient = $p->fetch();
  if ($patient) {
    $s = $pdo->prepare('SELECT v.*, u.name AS staff_name, u.role AS staff_role FROM visits v LEFT JOIN users u ON u.id=v.created_by WHERE v.patient_id=? ORDER BY v.visit_date DESC');
    $s->execute([$patient['id']]);
    $visits = $s->fetchAll();
  }
}
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-folder2-open text-primary"></i> Patient History</h3>
<div class="card border-0 shadow-sm p-3 mb-3">
  <form class="row g-2" method="get" data-rfid-search="q" data-rfid-search-endpoint="<?= base_url('api/rfid_last.php') ?>" data-rfid-search-after="<?= $last_rfid_scan_id ?>">
    <div class="col-md-4"><label class="form-label">Patient Card Number or Name</label>
      <input name="q" class="form-control" value="<?= e($query) ?>" placeholder="Card number or patient name"></div>
    <div class="col-auto align-self-end"><button class="btn btn-primary">Search</button></div>
    <div class="col-12"><div class="form-text" data-rfid-search-status role="status" aria-live="polite">Scan a patient card to retrieve patient history.</div></div>
  </form>
</div>
<?php if ($query !== ''): ?>
  <?php if (!$patient): ?>
    <div class="alert alert-warning">No patient found for <?= e($query) ?>.</div>
  <?php else: ?>
    <div class="card border-0 shadow-sm p-3 mb-3">
      <div class="row align-items-center">
        <div class="col-12">
          <h4><?= e($patient['full_name']) ?></h4>
          <p class="text-muted">Card Number <?= e($patient['rfid_uid']) ?> &bull; <?= e($patient['gender']) ?> &bull; <?= e($patient['dob']) ?> &bull; <?= e($patient['phone']) ?></p>
        </div>
      </div>
    </div>
    <?php if ($visits): ?>
      <div class="table-responsive">
      <table class="table bg-white shadow-sm align-middle">
        <thead><tr><th>Date</th><th>Reason</th><th>Status</th><th>Handled By</th><th>Department</th></tr></thead>
        <tbody>
          <?php foreach($visits as $v): ?>
            <tr>
              <td><?= e($v['visit_date']) ?></td>
              <td><?= e($v['reason']) ?></td>
              <td><?= e($v['status']) ?></td>
              <td><?= e($v['staff_name'] ?: 'System') ?></td>
              <td><?= e($v['staff_role'] ?: 'Unknown') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php else: ?>
      <div class="alert alert-info">This patient has no visit history yet.</div>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>