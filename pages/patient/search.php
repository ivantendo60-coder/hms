<?php
// Fast patient search with department-specific filters.
// - Receptionist/doctor/lab/pharmacy all allowed; receptionist results hide clinical badges.
// - Filters: name, phone, rfid uid, visit date range.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role(['doctor','lab','pharmacy','receptionist']);

$role = current_user()['role'];
$name = trim($_GET['name'] ?? '');
$phone= trim($_GET['phone'] ?? '');
$uid  = strtoupper(trim($_GET['uid'] ?? ''));
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
$q    = trim($_GET['q'] ?? '');  // legacy "any" field

$where = []; $args = [];
if ($q)    { $where[]='(p.full_name LIKE ? OR p.rfid_uid LIKE ? OR p.phone LIKE ?)'; array_push($args,"%$q%","%$q%","%$q%"); }
if ($name) { $where[]='p.full_name LIKE ?'; $args[]="%$name%"; }
if ($phone){ $where[]='p.phone LIKE ?';     $args[]="%$phone%"; }
if ($uid)  { $where[]='p.rfid_uid LIKE ?';  $args[]="%$uid%"; }
if ($from) { $where[]='EXISTS(SELECT 1 FROM visits v WHERE v.patient_id=p.id AND v.visit_date>=?)'; $args[]=$from.' 00:00:00'; }
if ($to)   { $where[]='EXISTS(SELECT 1 FROM visits v WHERE v.patient_id=p.id AND v.visit_date<=?)'; $args[]=$to.' 23:59:59'; }

$rows = [];
if ($where) {
  $sql = "SELECT p.*, (SELECT COUNT(*) FROM visits WHERE patient_id=p.id) AS visit_count,
                 (SELECT MAX(visit_date) FROM visits WHERE patient_id=p.id) AS last_visit
          FROM patients p WHERE " . implode(' AND ', $where) . " ORDER BY p.full_name LIMIT 100";
  $s = $pdo->prepare($sql); $s->execute($args); $rows = $s->fetchAll();
}

$open_link = function($p) use ($role) {
  if ($role==='receptionist') return base_url('pages/receptionist/patient_view.php?uid=' . urlencode($p['rfid_uid']));
  return 'view.php?uid=' . urlencode($p['rfid_uid']);
};

$last_rfid_scan_id = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM rfid_scans')->fetchColumn();
$patient_view_url = $role === 'receptionist'
  ? base_url('pages/receptionist/patient_view.php')
  : base_url('pages/patient/view.php');
$page_title='Find Patient';
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-search"></i> Find Patient
  <small class="text-muted fs-6">— <?= e(ucfirst($role)) ?> filters</small>
</h3>

<form class="card border-0 shadow-sm p-3 mb-3" data-rfid-search="uid" data-rfid-search-endpoint="<?= base_url('api/rfid_last.php') ?>" data-rfid-search-after="<?= $last_rfid_scan_id ?>" data-rfid-search-redirect="<?= e($patient_view_url) ?>">
  <div class="row g-2">
    <div class="col-md-3"><input name="name"  class="form-control" placeholder="Patient name" value="<?= e($name) ?>"></div>
    <div class="col-md-2"><input name="phone" class="form-control" placeholder="Phone" value="<?= e($phone) ?>"></div>
    <div class="col-md-2"><input name="uid"   class="form-control" placeholder="Card Number" value="<?= e($uid) ?>"></div>
    <?php if ($role !== 'receptionist'): ?>
      <div class="col-md-2"><input type="date" name="from" class="form-control" value="<?= e($from) ?>" title="Visit from"></div>
      <div class="col-md-2"><input type="date" name="to"   class="form-control" value="<?= e($to)   ?>" title="Visit to"></div>
    <?php else: ?>
      <div class="col-md-4"><input name="q" class="form-control" placeholder="Quick search (any field)" value="<?= e($q) ?>"></div>
    <?php endif; ?>
    <div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
  </div>
  <div class="form-text mt-2">
    <?php if ($role==='receptionist'): ?>
      Receptionist view: demographic search only — clinical filters are restricted.
    <?php else: ?>
      Department filters: search by visit date to find your active patients quickly.
    <?php endif; ?>
  </div>
  <div class="form-text mt-1" data-rfid-search-status role="status" aria-live="polite">Scan a patient card to retrieve patient information.</div>
</form>

<?php if ($rows): ?>
<table class="table bg-white shadow-sm align-middle">
<thead><tr>
  <th>Card Number</th><th>Name</th><th>Phone</th>
  <?php if ($role!=='receptionist'): ?><th>Visits</th><th>Last Visit</th><?php endif; ?>
  <th></th>
</tr></thead>
<tbody>
<?php foreach($rows as $p): ?>
<tr>
  <td><code><?= e($p['rfid_uid']) ?></code></td>
  <td><?= e($p['full_name']) ?><div class="small text-muted"><?= e($p['gender']) ?> • <?= e($p['blood_group']) ?></div></td>
  <td><?= e($p['phone']) ?></td>
  <?php if ($role!=='receptionist'): ?>
    <td><span class="badge bg-info"><?= (int)$p['visit_count'] ?></span></td>
    <td class="small"><?= e($p['last_visit']) ?></td>
  <?php endif; ?>
  <td><a class="btn btn-sm btn-primary" href="<?= $open_link($p) ?>">Open</a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php elseif ($where): ?>
  <div class="alert alert-warning">No patients matched those filters.</div>
<?php endif; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
