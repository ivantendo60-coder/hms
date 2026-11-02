<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('doctor');
$page_title='Doctor Dashboard';
$s = [
  'today' => $pdo->query("SELECT COUNT(*) FROM visits WHERE DATE(visit_date)=CURDATE()")->fetchColumn(),
  'patients' => $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
  'open' => $pdo->query("SELECT COUNT(*) FROM visits WHERE status='open'")->fetchColumn(),
  'dx' => $pdo->query("SELECT COUNT(*) FROM diagnoses WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
];
$recent = $pdo->query("SELECT v.*, p.full_name, p.rfid_uid FROM visits v JOIN patients p ON p.id=v.patient_id ORDER BY v.visit_date DESC LIMIT 10")->fetchAll();
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-clipboard2-pulse text-primary"></i> Doctor's Dashboard</h3>
<div class="row g-3 my-3">
  <div class="col-md-3"><div class="card card-stat p-3"><small class="text-muted">Total Patients</small><h2><?= $s['patients'] ?></h2></div></div>
  <div class="col-md-3"><div class="card card-stat p-3"><small class="text-muted">Today's Visits</small><h2 class="text-success"><?= $s['today'] ?></h2></div></div>
  <div class="col-md-3"><div class="card card-stat p-3"><small class="text-muted">Open Cases</small><h2 class="text-warning"><?= $s['open'] ?></h2></div></div>
  <div class="col-md-3"><div class="card card-stat p-3"><small class="text-muted">Diagnoses Today</small><h2 class="text-info"><?= $s['dx'] ?></h2></div></div>
</div>
<div class="row g-3">
  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h5>Recent Visits</h5>
        <table class="table"><thead><tr><th>When</th><th>Patient</th><th>Type</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach($recent as $v): ?>
        <tr><td><?= e($v['visit_date']) ?></td><td><?= e($v['full_name']) ?></td>
          <td><span class="badge bg-<?= $v['patient_type']==='inpatient'?'danger':'info' ?>"><?= e(ucfirst($v['patient_type'])) ?></span></td>
          <td><span class="badge bg-<?= $v['status']==='open'?'warning':'secondary' ?>"><?= e($v['status']) ?></span></td>
          <td><a class="btn btn-sm btn-primary" href="<?= base_url('pages/patient/view.php?uid='.urlencode($v['rfid_uid'])) ?>">Open</a></td></tr>
        <?php endforeach; ?>
        </tbody></table>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <img src="<?= base_url('assets/img/doctor.jpg') ?>" class="img-fluid rounded shadow-sm">
    <a href="<?= base_url('pages/scan.php') ?>" class="btn btn-primary w-100 mt-3"><i class="bi bi-broadcast"></i> Scan Patient Card</a>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
