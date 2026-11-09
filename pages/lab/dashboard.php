<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('lab');
$page_title='Lab Dashboard';
$s=[
  'pending'=>$pdo->query("SELECT COUNT(*) FROM lab_tests WHERE status='pending'")->fetchColumn(),
  'today'=>$pdo->query("SELECT COUNT(*) FROM lab_tests WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
  'total'=>$pdo->query("SELECT COUNT(*) FROM lab_tests")->fetchColumn(),
];
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-eyedropper text-primary"></i> Laboratory Dashboard</h3>
<div class="row g-3 my-3">
  <div class="col-md-4"><div class="card card-stat p-3"><small>Pending Tests</small><h2 class="text-warning"><?= $s['pending'] ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><small>Tests Today</small><h2 class="text-success"><?= $s['today'] ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><small>All Time</small><h2><?= $s['total'] ?></h2></div></div>
</div>
<div class="mt-3"><a class="btn btn-primary" href="<?= base_url('pages/scan.php') ?>"><i class="bi bi-broadcast"></i> Scan Patient Card</a>
<a class="btn btn-outline-primary" href="queue.php">Open Queue</a></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
