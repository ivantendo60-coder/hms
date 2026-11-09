<?php
// Reception can view demographics and visit classification/history, but not clinical records.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_role('receptionist');
$uid = strtoupper(trim($_GET['uid']??''));
$stmt = $pdo->prepare("SELECT * FROM patients WHERE rfid_uid=?"); $stmt->execute([$uid]); $p=$stmt->fetch();

// Unknown card → confirmation step
if (!$p) {
  header('Location: confirm_rfid.php?uid=' . urlencode($uid));
  exit;
}

// New visit on check-in
if (isset($_GET['checkin'])) {
  $pdo->prepare("INSERT INTO visits (patient_id,reason,created_by) VALUES (?,?,?)")->execute([$p['id'], 'Return visit', current_user()['id']]);
  audit_log($pdo, (int)$p['id'], 'visit_open', 'visits', null, 'Return visit');
  header('Location: patient_view.php?uid='.urlencode($uid)); exit;
}

$visits = $pdo->prepare("SELECT * FROM visits WHERE patient_id=? ORDER BY visit_date DESC"); $visits->execute([$p['id']]); $visits=$visits->fetchAll();
$currentVisit = null;
foreach ($visits as $visit) {
  if ($visit['status'] === 'open') {
    $currentVisit = $visit;
    break;
  }
}
$audit = fetch_audit($pdo, (int)$p['id']);
$flashError = $_SESSION['flash_err'] ?? null;
unset($_SESSION['flash_err']);
$page_title=$p['full_name'];
include __DIR__ . '/../../includes/header.php';
?>
<?php if(!empty($_GET['new'])): ?><div class="alert alert-success"><i class="bi bi-check2-circle"></i> Patient registered and first visit started — they may now proceed to the doctor.</div><?php endif; ?>
<?php if($flashError): ?><div class="alert alert-danger"><?= e($flashError) ?></div><?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body d-flex gap-4 align-items-center">
    <div class="flex-grow-1">
      <h3 class="mb-1"><?= e($p['full_name']) ?></h3>
      <div class="text-muted">Card Number <code><?= e($p['rfid_uid']) ?></code> • <?= e($p['gender']) ?> • <?= e($p['blood_group']) ?></div>
      <div class="small mt-1"><i class="bi bi-telephone"></i> <?= e($p['phone']) ?> &nbsp; <i class="bi bi-envelope"></i> <?= e($p['email']) ?></div>
    </div>
    <div class="text-end">
      <span class="badge bg-info">Visits: <?= count($visits) ?></span>
      <div class="mt-2">
        <a class="btn btn-success btn-sm" href="?uid=<?= urlencode($uid) ?>&checkin=1"><i class="bi bi-clipboard-check"></i> Check-in Visit</a>
        <a class="btn btn-outline-secondary btn-sm" href="edit_patient.php?id=<?= $p['id'] ?>"><i class="bi bi-pencil"></i> Edit</a>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h5 class="mb-1"><i class="bi bi-clipboard2-pulse text-primary"></i> Current visit</h5>
      <?php if ($currentVisit): ?>
        <div class="text-muted small">Visit date: <?= e($currentVisit['visit_date']) ?></div>
      <?php else: ?>
        <div class="text-muted small">There is no open visit for this patient.</div>
      <?php endif; ?>
    </div>
    <?php if ($currentVisit): ?>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge bg-<?= $currentVisit['patient_type'] === 'inpatient' ? 'danger' : 'info' ?>">
          <?= e(ucfirst($currentVisit['patient_type'])) ?>
        </span>
        <span class="badge bg-<?= $currentVisit['pharmacy_cleared'] ? 'success' : 'secondary' ?>">
          <?= $currentVisit['pharmacy_cleared'] ? 'Pharmacy cleared' : 'Not pharmacy cleared' ?>
        </span>
      </div>
    <?php endif; ?>
  </div>
</div>

<ul class="nav nav-tabs">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#details">Details</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#history">Visit History</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#audit">Audit Log (<?= count($audit) ?>)</a></li>
</ul>
<div class="tab-content bg-white p-4 shadow-sm">
  <div id="details" class="tab-pane fade show active">
    <dl class="row">
      <dt class="col-sm-3">Date of Birth</dt><dd class="col-sm-9"><?= e($p['dob']) ?></dd>
      <dt class="col-sm-3">Address</dt><dd class="col-sm-9"><?= e($p['address']) ?></dd>
      <dt class="col-sm-3">Emergency Contact</dt><dd class="col-sm-9"><?= e($p['emergency_contact']) ?></dd>
      <dt class="col-sm-3">Registered</dt><dd class="col-sm-9"><?= e($p['created_at']) ?></dd>
    </dl>
    <div class="alert alert-info small"><i class="bi bi-shield-lock"></i> Reception can view visit dates and inpatient/outpatient classification. Diagnoses, lab results, and prescriptions are restricted to medical staff.</div>
  </div>
  <div id="history" class="tab-pane fade">
    <div class="timeline">
      <?php if(!$visits): ?><p class="text-muted">No previous visits.</p><?php endif; ?>
      <?php foreach($visits as $v): ?>
      <div class="item">
        <strong><?= e($v['visit_date']) ?></strong>
        <span class="badge bg-<?= $v['patient_type']==='inpatient'?'danger':'info' ?>"><?= e(ucfirst($v['patient_type'])) ?></span>
        <span class="badge bg-<?= $v['status']==='open'?'warning':'secondary' ?>"><?= e($v['status']) ?></span>
        <span class="badge bg-<?= $v['pharmacy_cleared']?'success':'secondary' ?>"><?= $v['pharmacy_cleared']?'Pharmacy cleared':'Not cleared' ?></span>
        <div class="text-muted small"><?= e($v['reason']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div id="audit" class="tab-pane fade">
    <table class="table table-sm table-striped">
      <thead><tr><th>When</th><th>User</th><th>Role</th><th>Action</th><th>Field</th><th>Change</th></tr></thead>
      <tbody>
        <?php foreach($audit as $a): ?>
        <tr>
          <td class="small"><?= e($a['created_at']) ?></td>
          <td><?= e($a['user_name'] ?? '—') ?></td>
          <td><span class="badge bg-secondary"><?= e($a['user_role']) ?></span></td>
          <td><code><?= e($a['action']) ?></code></td>
          <td><?= e($a['field_name']) ?></td>
          <td class="small"><?= e(mb_strimwidth((string)$a['old_value'],0,40,'…')) ?> → <strong><?= e(mb_strimwidth((string)$a['new_value'],0,40,'…')) ?></strong></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$audit): ?><tr><td colspan="6" class="text-muted">No audit entries yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
