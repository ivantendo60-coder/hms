<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('pharmacy');
$page_title='Pharmacy Dashboard';
$s=[
  'today'=>$pdo->query("SELECT COUNT(*) FROM prescriptions WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
  'dispensed'=>$pdo->query("SELECT COUNT(*) FROM prescriptions WHERE dispensed=1")->fetchColumn(),
  'pending'=>$pdo->query("SELECT COUNT(*) FROM prescriptions WHERE dispensed=0")->fetchColumn(),
  'clearance'=>$pdo->query("SELECT COUNT(*) FROM visits WHERE status='open' AND pharmacy_cleared=0")->fetchColumn(),
  'collected'=>$pdo->query("SELECT COALESCE(SUM(amount),0) FROM visit_payments WHERE collection_point='pharmacy' AND DATE(created_at)=CURDATE()")->fetchColumn(),
];
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-capsule text-primary"></i> Pharmacy Dashboard</h3>
<div class="row g-3 my-3">
  <div class="col-md-3"><div class="card card-stat p-3"><small>Today</small><h2 class="text-success"><?= $s['today'] ?></h2></div></div>
  <div class="col-md-3"><div class="card card-stat p-3"><small>Dispensed</small><h2><?= $s['dispensed'] ?></h2></div></div>
  <div class="col-md-3"><div class="card card-stat p-3"><small>Pending</small><h2 class="text-warning"><?= $s['pending'] ?></h2></div></div>
  <div class="col-md-3"><div class="card card-stat p-3"><small>Awaiting clearance</small><h2><?= $s['clearance'] ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><small>Collected at Pharmacy Today</small><h2 class="text-success">UGX <?= number_format((float)$s['collected'], 2) ?></h2></div></div>
</div>
<img src="<?= base_url('assets/img/pharmacy.jpg') ?>" class="img-fluid rounded shadow-sm" style="max-height:240px">
<div class="mt-3"><a class="btn btn-primary" href="<?= base_url('pages/scan.php') ?>"><i class="bi bi-broadcast"></i> Scan Card</a>
<a class="btn btn-outline-primary" href="queue.php">Prescription Queue</a>
<a class="btn btn-success" href="clearance.php">Payment Clearance</a></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
