<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) {
    header('Location: ' . base_url('pages/'.current_user()['role'].'/dashboard.php'));
    exit;
}
$page_title = 'Welcome';
include __DIR__ . '/includes/header.php';
?>
<div class="hero d-flex flex-column flex-md-row align-items-center gap-4">
  <div class="flex-grow-1">
    <h1 class="display-5 fw-bold">MediCare Hospital Management</h1>
    <p class="lead">RFID-powered patient records. One tap of the card unlocks the full medical history — securely accessible only to authorized hospital staff.</p>
    <a href="<?= base_url('pages/auth/login.php') ?>" class="btn btn-light btn-lg fw-semibold"><i class="bi bi-box-arrow-in-right"></i> Staff Login</a>
  </div>
  <img src="<?= base_url('assets/img/hospital.jpg') ?>" class="rounded shadow" style="max-width:420px;width:100%" alt="Hospital">
</div>
<div class="row g-4 mt-4">
  <?php foreach([
    ['rfid.jpg','RFID Patient Cards','Each patient carries an RFID card scanned by staff at any department.'],
    ['doc1.webp','Doctors','Full access to records, diagnoses, lab and pharmacy data.'],
    ['recep1.webp','Reception','Register and update patients. No clinical data access.'],
    ['lab1.webp','Laboratory','Record test results linked to each visit.'],
    ['pham3.avif','Pharmacy','Dispense and track prescriptions.'],
  ] as $c): ?>
  <div class="col-md-4">
    <div class="card card-stat h-100">
      <img src="<?= base_url('assets/img/'.$c[0]) ?>" class="card-img-top" style="height:180px;object-fit:cover" alt="">
      <div class="card-body"><h5><?= e($c[1]) ?></h5><p class="text-muted"><?= e($c[2]) ?></p></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
