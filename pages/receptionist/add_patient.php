<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/upload.php';
require_role('receptionist');

$err=''; $ok='';
$prefill_uid = strtoupper(trim($_GET['uid'] ?? ''));
$auto_visit  = !empty($_GET['confirm']);  // arrived from confirm_rfid.php

if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_check();
  try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO patients (rfid_uid,full_name,dob,gender,phone,email,address,blood_group,emergency_contact,registered_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
      strtoupper(trim($_POST['rfid_uid'])),
      trim($_POST['full_name']),
      $_POST['dob']?:null,
      $_POST['gender'],
      $_POST['phone'],
      $_POST['email'],
      $_POST['address'],
      $_POST['blood_group'],
      $_POST['emergency_contact'],
      current_user()['id'],
    ]);
    $new_id = (int)$pdo->lastInsertId();

    // Optional photo
    try {
      $photo = handle_photo_upload('photo', $new_id);
      if ($photo) {
        $pdo->prepare("UPDATE patients SET photo=?, photo_mime=? WHERE id=?")->execute([$photo[0], $photo[1], $new_id]);
        audit_log($pdo, $new_id, 'photo_upload', 'photo', null, $photo[0]);
      }
    } catch (Exception $pe) { /* skip photo error so registration still succeeds */ $err = 'Photo skipped: '.$pe->getMessage(); }

    audit_log($pdo, $new_id, 'create', null, null, trim($_POST['full_name']));

    // Auto-create a visit when arriving from the unknown-card confirm flow
    if (!empty($_POST['auto_visit'])) {
      $pdo->prepare("INSERT INTO visits (patient_id,reason,created_by) VALUES (?,?,?)")
          ->execute([$new_id, 'First visit (RFID registration)', current_user()['id']]);
      audit_log($pdo, $new_id, 'visit_open', 'visits', null, 'First visit');
      $pdo->commit();
      header('Location: patient_view.php?uid=' . urlencode(strtoupper(trim($_POST['rfid_uid']))) . '&new=1');
      exit;
    }
    $pdo->commit();
    $ok = 'Patient registered successfully';
    $prefill_uid='';
  } catch(Exception $e){
    if ($pdo->inTransaction()) $pdo->rollBack();
    $err = $e->getMessage();
  }
}
$page_title='Add Patient';
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-person-plus"></i> Register New Patient
<?php if($auto_visit): ?><span class="badge bg-warning text-dark">From unknown-card confirmation</span><?php endif; ?>
</h3>
<?php if($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm p-4">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <?php if ($auto_visit): ?><input type="hidden" name="auto_visit" value="1"><?php endif; ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">RFID Card UID *</label><input name="rfid_uid" value="<?= e($prefill_uid) ?>" required class="form-control" placeholder="Tap card to capture" <?= $auto_visit?'readonly':'' ?>></div>
    <div class="col-md-8"><label class="form-label">Full Name *</label><input name="full_name" required class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Gender</label><select name="gender" class="form-select"><option>Male</option><option>Female</option><option>Other</option></select></div>
    <div class="col-md-3"><label class="form-label">Blood Group</label><select name="blood_group" class="form-select"><option>O+</option><option>O-</option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option></select></div>
    <div class="col-md-3"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Emergency Contact</label><input name="emergency_contact" class="form-control"></div>
    <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
    <div class="col-md-6"><label class="form-label">Patient Photo (JPG/PNG/WebP ≤ 4 MB)</label><input type="file" name="photo" accept="image/*" class="form-control"></div>
  </div>
  <div class="mt-3">
    <button class="btn btn-primary"><i class="bi bi-save"></i>
      <?= $auto_visit ? 'Save &amp; Start First Visit' : 'Save Patient' ?>
    </button>
  </div>
</form>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
