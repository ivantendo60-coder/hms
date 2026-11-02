<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/upload.php';
require_role('receptionist');

$id = (int)($_GET['id']??0);
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id=?"); $stmt->execute([$id]); $p = $stmt->fetch();
if (!$p) die('Not found');
$err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_check();
  try {
    $new = [
      'full_name'=>$_POST['full_name'],'dob'=>$_POST['dob']?:null,'gender'=>$_POST['gender'],
      'phone'=>$_POST['phone'],'email'=>$_POST['email'],'address'=>$_POST['address'],
      'blood_group'=>$_POST['blood_group'],'emergency_contact'=>$_POST['emergency_contact'],
    ];
    $u = $pdo->prepare("UPDATE patients SET full_name=?,dob=?,gender=?,phone=?,email=?,address=?,blood_group=?,emergency_contact=? WHERE id=?");
    $u->execute([$new['full_name'],$new['dob'],$new['gender'],$new['phone'],$new['email'],$new['address'],$new['blood_group'],$new['emergency_contact'],$id]);
    audit_diff($pdo, $id, $p, $new, array_keys($new));

    $photo = handle_photo_upload('photo', $id);
    if ($photo) {
      delete_patient_photo($p['photo']);
      $pdo->prepare("UPDATE patients SET photo=?, photo_mime=? WHERE id=?")->execute([$photo[0], $photo[1], $id]);
      audit_log($pdo, $id, 'photo_upload', 'photo', $p['photo'], $photo[0]);
    }
    header('Location: patient_view.php?uid='.urlencode($p['rfid_uid'])); exit;
  } catch(Exception $e){ $err=$e->getMessage(); }
}
$page_title='Edit Patient';
include __DIR__ . '/../../includes/header.php';
?>
<h3>Edit Patient</h3>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card p-4 border-0 shadow-sm">
  <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
  <div class="row g-3">
    <div class="col-md-3 text-center">
      <img src="<?= base_url('pages/patient/photo.php?id='.$p['id']) ?>" class="img-thumbnail mb-2" style="width:140px;height:140px;object-fit:cover">
      <label class="form-label small">Replace photo</label>
      <input type="file" name="photo" accept="image/*" class="form-control form-control-sm">
    </div>
    <div class="col-md-9">
      <div class="row g-3">
        <div class="col-md-8"><label>Full Name</label><input name="full_name" class="form-control" value="<?= e($p['full_name']) ?>"></div>
        <div class="col-md-4"><label>DOB</label><input type="date" name="dob" class="form-control" value="<?= e($p['dob']) ?>"></div>
        <div class="col-md-3"><label>Gender</label>
          <select name="gender" class="form-select"><?php foreach(['Male','Female','Other'] as $g): ?><option <?= $p['gender']===$g?'selected':'' ?>><?= $g ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-md-3"><label>Blood Group</label><input name="blood_group" class="form-control" value="<?= e($p['blood_group']) ?>"></div>
        <div class="col-md-3"><label>Phone</label><input name="phone" class="form-control" value="<?= e($p['phone']) ?>"></div>
        <div class="col-md-3"><label>Email</label><input name="email" class="form-control" value="<?= e($p['email']) ?>"></div>
        <div class="col-md-6"><label>Emergency Contact</label><input name="emergency_contact" class="form-control" value="<?= e($p['emergency_contact']) ?>"></div>
        <div class="col-12"><label>Address</label><textarea name="address" class="form-control"><?= e($p['address']) ?></textarea></div>
      </div>
    </div>
  </div>
  <div class="mt-3"><button class="btn btn-primary">Update</button> <a href="patients.php" class="btn btn-light">Cancel</a></div>
</form>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
