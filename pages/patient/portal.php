<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/portal.php';

$token = trim((string)($_GET['token'] ?? ''));
$patient = find_patient_portal($pdo, $token);
$page_title = 'Patient Portal';
include __DIR__ . '/../../includes/header.php';

if (!$patient): ?>
  <div class="container py-5">
    <div class="alert alert-danger shadow-sm">
      <h4 class="alert-heading">Portal link unavailable</h4>
      This link is invalid, expired, or has been revoked.
    </div>
  </div>
<?php else:
  $visits = $pdo->prepare("SELECT * FROM visits WHERE patient_id=? ORDER BY visit_date DESC LIMIT 20");
  $visits->execute([(int)$patient['patient_id']]);
  $visits = $visits->fetchAll();
?>
  <div class="container py-4">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <span class="badge bg-primary mb-2">Read-only patient portal</span>
        <h2><?= e($patient['full_name']) ?></h2>
        <div class="text-muted">Card <?= e($patient['rfid_uid']) ?> · Blood group <?= e($patient['blood_group']) ?: '—' ?></div>
        <small class="text-muted">Portal access expires <?= e($patient['expires_at']) ?></small>
      </div>
    </div>
    <?php foreach ($visits as $visit):
      $diagnoses = $pdo->prepare('SELECT symptoms,diagnosis,treatment,notes,created_at FROM diagnoses WHERE visit_id=? ORDER BY created_at DESC');
      $diagnoses->execute([(int)$visit['id']]);
      $diagnosisRows = $diagnoses->fetchAll();
      $labs = $pdo->prepare('SELECT test_name,result,remarks,status,created_at FROM lab_tests WHERE visit_id=? ORDER BY created_at DESC');
      $labs->execute([(int)$visit['id']]);
      $labRows = $labs->fetchAll();
      $prescriptions = $pdo->prepare('SELECT medicine,dosage,duration,instructions,dispensed,created_at FROM prescriptions WHERE visit_id=? ORDER BY created_at DESC');
      $prescriptions->execute([(int)$visit['id']]);
      $prescriptionRows = $prescriptions->fetchAll();
    ?>
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between">
          <strong><?= e($visit['visit_date']) ?></strong>
          <span class="badge bg-<?= $visit['status'] === 'open' ? 'warning text-dark' : 'secondary' ?>"><?= e($visit['status']) ?></span>
        </div>
        <div class="card-body">
          <h6>Diagnoses</h6>
          <?php foreach ($diagnosisRows as $row): ?>
            <div class="mb-2"><strong><?= e($row['diagnosis']) ?></strong><div class="small"><?= e($row['treatment']) ?></div></div>
          <?php endforeach; ?>
          <?php if (!$diagnosisRows): ?><p class="text-muted small">No diagnosis shared.</p><?php endif; ?>
          <h6 class="mt-3">Lab results</h6>
          <?php foreach ($labRows as $row): ?>
            <div class="mb-2"><strong><?= e($row['test_name']) ?>:</strong> <?= e($row['result']) ?><span class="badge bg-info ms-2"><?= e($row['status']) ?></span></div>
          <?php endforeach; ?>
          <?php if (!$labRows): ?><p class="text-muted small">No lab results shared.</p><?php endif; ?>
          <h6 class="mt-3">Prescriptions</h6>
          <?php foreach ($prescriptionRows as $row): ?>
            <div class="mb-2"><strong><?= e($row['medicine']) ?></strong> — <?= e($row['dosage']) ?> · <?= e($row['duration']) ?><div class="small"><?= e($row['instructions']) ?></div></div>
          <?php endforeach; ?>
          <?php if (!$prescriptionRows): ?><p class="text-muted small">No prescriptions shared.</p><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif;
include __DIR__ . '/../../includes/footer.php';
