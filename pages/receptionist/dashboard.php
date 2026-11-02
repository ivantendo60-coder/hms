<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('receptionist');
$page_title='Receptionist Dashboard';
$stats = [
  'patients' => $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
  'today' => $pdo->query("SELECT COUNT(*) FROM visits WHERE DATE(visit_date)=CURDATE()")->fetchColumn(),
  'open' => $pdo->query("SELECT COUNT(*) FROM visits WHERE status='open'")->fetchColumn(),
  'collected' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM visit_payments WHERE collection_point='reception' AND DATE(created_at)=CURDATE()")->fetchColumn(),
];
$recent = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM visits v WHERE v.patient_id=p.id) AS visits FROM patients p ORDER BY p.created_at DESC LIMIT 8")->fetchAll();
include __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3><i class="bi bi-clipboard-heart text-primary"></i> Reception Desk</h3>
  <div class="d-flex gap-2">
    <a href="<?= base_url('pages/scan.php') ?>" class="btn btn-outline-primary"><i class="bi bi-broadcast"></i> Scan RFID</a>
    <a href="add_patient.php" class="btn btn-primary"><i class="bi bi-person-plus"></i> New Patient</a>
    <a href="payments.php" class="btn btn-outline-success"><i class="bi bi-cash-coin"></i> Payments</a>
  </div>
</div>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card card-stat p-3"><div class="text-muted small">Total Patients</div><h2 class="text-primary"><?= $stats['patients'] ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><div class="text-muted small">Today's Visits</div><h2 class="text-success"><?= $stats['today'] ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><div class="text-muted small">Open Visits</div><h2 class="text-warning"><?= $stats['open'] ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><div class="text-muted small">Collected at Reception Today</div><h2 class="text-success">UGX <?= number_format((float)$stats['collected'], 2) ?></h2></div></div>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body">
    <h5>Recently Registered Patients</h5>
    <table class="table table-hover align-middle">
      <thead><tr><th>RFID UID</th><th>Name</th><th>Phone</th><th>Visits</th><th></th></tr></thead>
      <tbody>
      <?php foreach($recent as $p): ?>
        <tr>
          <td><code><?= e($p['rfid_uid']) ?></code></td>
          <td><?= e($p['full_name']) ?></td>
          <td><?= e($p['phone']) ?></td>
          <td><span class="badge bg-info"><?= $p['visits'] ?></span></td>
          <td><a class="btn btn-sm btn-outline-primary" href="patient_view.php?uid=<?= urlencode($p['rfid_uid']) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
