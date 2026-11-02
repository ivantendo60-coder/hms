<?php
// Confirmation step for an unknown RFID card.
// Reception lands here when a scanned UID has no matching patient.
// They review the UID, confirm, and are taken to add_patient.php with the UID pre-filled.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('receptionist');

$uid = strtoupper(trim($_GET['uid'] ?? ''));
if (!$uid) { header('Location: ' . base_url('pages/scan.php')); exit; }

// Defensive: if the patient now exists, go straight to their record.
$s = $pdo->prepare("SELECT id FROM patients WHERE rfid_uid=?");
$s->execute([$uid]);
if ($s->fetch()) { header('Location: patient_view.php?uid=' . urlencode($uid)); exit; }

// Mark any pending scan rows as reviewed
$pdo->prepare("UPDATE pending_rfid SET reviewed=1 WHERE rfid_uid=?")->execute([$uid]);

$last = $pdo->prepare("SELECT scanned_at, station FROM rfid_scans WHERE rfid_uid=? ORDER BY id DESC LIMIT 1");
$last->execute([$uid]); $scan = $last->fetch();

$page_title = 'Unknown RFID Card';
include __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card border-warning shadow-sm">
      <div class="card-header bg-warning-subtle">
        <h4 class="mb-0"><i class="bi bi-exclamation-triangle text-warning"></i> Unknown RFID Card</h4>
      </div>
      <div class="card-body">
        <p>This card has never been registered in the system. Please verify the UID with the patient before issuing a new record.</p>

        <table class="table table-sm">
          <tr><th style="width:160px">RFID UID</th><td><code class="fs-5"><?= e($uid) ?></code></td></tr>
          <tr><th>Last Scan</th><td><?= e($scan['scanned_at'] ?? 'just now') ?></td></tr>
          <tr><th>Station</th><td><?= e($scan['station'] ?? 'unknown') ?></td></tr>
          <tr><th>Reviewed By</th><td><?= e(current_user()['name']) ?> (Receptionist)</td></tr>
        </table>

        <div class="alert alert-info small">
          <i class="bi bi-info-circle"></i> After registering, the system will automatically create
          a new visit and route the patient to the doctor's queue.
        </div>

        <div class="d-flex gap-2">
          <a href="add_patient.php?uid=<?= urlencode($uid) ?>&confirm=1"
             class="btn btn-primary"><i class="bi bi-person-plus"></i> Confirm &amp; Register Patient</a>
          <a href="<?= base_url('pages/scan.php') ?>" class="btn btn-light"><i class="bi bi-x-circle"></i> Cancel — Rescan</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
