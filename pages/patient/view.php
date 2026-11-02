<?php
// Full clinical patient view for doctor/lab/pharmacy. Four sections: Details, Current Visit, History, Audit
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/../../includes/visit_billing.php';
require_role(['doctor','lab','pharmacy']);
$uid = strtoupper(trim($_GET['uid']??''));
$stmt = $pdo->prepare("SELECT * FROM patients WHERE rfid_uid=?"); $stmt->execute([$uid]); $p=$stmt->fetch();
if (!$p) { include __DIR__ . '/../../includes/header.php'; echo '<div class="alert alert-warning">Unknown RFID card <code>'.e($uid).'</code>. Ask reception to register it via the unknown-card review screen.</div>'; include __DIR__ . '/../../includes/footer.php'; exit; }

$role = current_user()['role'];
// most-recent OPEN visit is "current"; older are history
$cur = $pdo->prepare("SELECT * FROM visits WHERE patient_id=? AND status='open' ORDER BY visit_date DESC, id DESC LIMIT 1");
$cur->execute([$p['id']]); $current = $cur->fetch();
$hist = $pdo->prepare("SELECT * FROM visits WHERE patient_id=? AND (status='closed' OR id<>?) ORDER BY visit_date DESC, id DESC");
$hist->execute([$p['id'], $current['id']??0]); $history=$hist->fetchAll();

function load_visit_records($pdo,$vid){
  $d=$pdo->prepare("SELECT d.*,u.name AS doctor FROM diagnoses d LEFT JOIN users u ON u.id=d.doctor_id WHERE visit_id=? ORDER BY d.id"); $d->execute([$vid]);
  $l=$pdo->prepare("SELECT l.*,u.name AS tech FROM lab_tests l LEFT JOIN users u ON u.id=l.lab_tech_id WHERE visit_id=? ORDER BY l.id"); $l->execute([$vid]);
  $r=$pdo->prepare("SELECT pr.*,u.name AS pharmacist FROM prescriptions pr LEFT JOIN users u ON u.id=pr.pharmacist_id WHERE visit_id=? ORDER BY pr.id"); $r->execute([$vid]);
  return ['dx'=>$d->fetchAll(),'lab'=>$l->fetchAll(),'rx'=>$r->fetchAll()];
}

// Handle photo upload (any clinical role)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='photo') {
  csrf_check();
  try {
    $photo = handle_photo_upload('photo', (int)$p['id']);
    if ($photo) {
      delete_patient_photo($p['photo']);
      $pdo->prepare("UPDATE patients SET photo=?, photo_mime=? WHERE id=?")->execute([$photo[0],$photo[1],$p['id']]);
      audit_log($pdo, (int)$p['id'], 'photo_upload', 'photo', $p['photo'], $photo[0]);
    }
  } catch (Exception $e) { $_SESSION['flash_err']=$e->getMessage(); }
  header("Location: view.php?uid=$uid"); exit;
}

// Handle inline adds based on role
if ($_SERVER['REQUEST_METHOD']==='POST' && $current) {
  csrf_check();
  $action=$_POST['action']??'';
  if ($action==='set_patient_type' && $role==='doctor') {
    $patientType = $_POST['patient_type'] ?? '';
    if (!in_array($patientType, ['outpatient', 'inpatient'], true)) {
      $_SESSION['flash_err'] = 'Choose a valid patient type.';
    } elseif ($patientType !== $current['patient_type']) {
      $pdo->beginTransaction();
      try {
        $update = $pdo->prepare("UPDATE visits SET patient_type=? WHERE id=? AND patient_id=? AND status='open'");
        $update->execute([$patientType, $current['id'], $p['id']]);
        if ($update->rowCount() !== 1) {
          throw new RuntimeException('The visit is no longer open. Refresh the patient record and try again.');
        }
        audit_log($pdo, (int)$p['id'], 'visit_type_change', 'patient_type', $current['patient_type'], $patientType);
        $pdo->commit();
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        throw $e;
      }
    }
  } elseif ($action==='add_service' && in_array($role, ['doctor','lab','pharmacy'], true)) {
    try {
      record_visit_service(
        $pdo,
        (int)$current['id'],
        billing_positive_integer($_POST['service_id'] ?? null, 'service'),
        billing_positive_integer($_POST['quantity'] ?? null, 'quantity', 1000),
        (int)current_user()['id'],
        $role
      );
    } catch (Throwable $e) {
      $_SESSION['flash_err'] = $e->getMessage();
    }
  } elseif ($action==='dx' && $role==='doctor') {
    $pdo->prepare("INSERT INTO diagnoses (visit_id,doctor_id,symptoms,diagnosis,treatment,notes) VALUES (?,?,?,?,?,?)")
        ->execute([$current['id'], current_user()['id'], $_POST['symptoms'],$_POST['diagnosis'],$_POST['treatment'],$_POST['notes']]);
    invalidate_visit_clearance($pdo, (int)$current['id']);
    audit_log($pdo, (int)$p['id'], 'diagnosis_add', 'diagnoses', null, $_POST['diagnosis']);
  } elseif ($action==='lab' && $role==='lab') {
    $pdo->prepare("INSERT INTO lab_tests (visit_id,lab_tech_id,test_name,result,remarks,status) VALUES (?,?,?,?,?, 'completed')")
        ->execute([$current['id'], current_user()['id'], $_POST['test_name'],$_POST['result'],$_POST['remarks']]);
    invalidate_visit_clearance($pdo, (int)$current['id']);
    audit_log($pdo, (int)$p['id'], 'lab_add', 'lab_tests', null, $_POST['test_name'].': '.$_POST['result']);
  } elseif ($action==='rx' && $role==='pharmacy') {
    $pdo->prepare("INSERT INTO prescriptions (visit_id,pharmacist_id,medicine,dosage,duration,instructions,dispensed) VALUES (?,?,?,?,?,?,1)")
        ->execute([$current['id'], current_user()['id'], $_POST['medicine'],$_POST['dosage'],$_POST['duration'],$_POST['instructions']]);
    invalidate_visit_clearance($pdo, (int)$current['id']);
    audit_log($pdo, (int)$p['id'], 'rx_add', 'prescriptions', null, $_POST['medicine']);
  } elseif ($action==='close' && $role==='doctor') {
    if (!$current['pharmacy_cleared']) {
      $_SESSION['flash_err'] = 'Pharmacy must clear the full bill before this visit can be closed.';
    } else {
      $close = $pdo->prepare("UPDATE visits SET status='closed' WHERE id=? AND status='open' AND pharmacy_cleared=1");
      $close->execute([$current['id']]);
      if ($close->rowCount() !== 1) {
        $_SESSION['flash_err'] = 'The visit billing changed. Refresh the patient record and confirm pharmacy clearance.';
      } else {
        audit_log($pdo, (int)$p['id'], 'visit_close', 'visits', 'open', 'closed');
      }
    }
    header("Location: view.php?uid=$uid"); exit;
  }
  header("Location: view.php?uid=$uid#current"); exit;
}

$audit = fetch_audit($pdo, (int)$p['id']);
$flashError = $_SESSION['flash_err'] ?? null;
unset($_SESSION['flash_err']);

$page_title=$p['full_name'];
include __DIR__ . '/../../includes/header.php';
$curData = $current ? load_visit_records($pdo,$current['id']) : null;
$billing = $current ? visit_billing_summary($pdo, (int)$current['id']) : null;
$billableServices = [];
if ($current) {
  $serviceQuery = $pdo->prepare('SELECT id, name, price FROM service_catalog WHERE department=? AND active=1 ORDER BY name');
  $serviceQuery->execute([$role]);
  $billableServices = $serviceQuery->fetchAll();
}
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body d-flex gap-4 align-items-center">
    <img src="<?= base_url('pages/patient/photo.php?id='.$p['id']) ?>" class="rounded-circle border" style="width:84px;height:84px;object-fit:cover" alt="patient">
    <div class="flex-grow-1">
      <h3 class="mb-1"><?= e($p['full_name']) ?> <span class="badge bg-primary"><?= e(strtoupper($role)) ?> VIEW</span></h3>
      <div class="text-muted">RFID <code><?= e($p['rfid_uid']) ?></code> • <?= e($p['gender']) ?> • DOB <?= e($p['dob']) ?> • Blood <?= e($p['blood_group']) ?></div>
      <div class="small mt-1"><i class="bi bi-telephone"></i> <?= e($p['phone']) ?> • <?= e($p['address']) ?></div>
    </div>
    <div class="text-end no-print">
      <a class="btn btn-outline-dark btn-sm" href="print.php?uid=<?= urlencode($uid) ?>" target="_blank"><i class="bi bi-printer"></i> Print PDF</a>
      <button class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#photoUp"><i class="bi bi-camera"></i> Photo</button>
    </div>
  </div>
  <div id="photoUp" class="collapse border-top">
    <form method="post" enctype="multipart/form-data" class="p-3 d-flex gap-2 align-items-center">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="photo">
      <input type="file" name="photo" accept="image/*" class="form-control form-control-sm" required>
      <button class="btn btn-sm btn-primary"><i class="bi bi-upload"></i> Upload</button>
      <span class="small text-muted">Stored securely — accessible only to authenticated staff.</span>
    </form>
  </div>
</div>
<?php if($flashError): ?><div class="alert alert-danger"><?= e($flashError) ?></div><?php endif; ?>

<ul class="nav nav-tabs no-print">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#details"><i class="bi bi-person"></i> Details</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#current"><i class="bi bi-activity"></i> Current Visit</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#history"><i class="bi bi-clock-history"></i> History (<?= count($history) ?>)</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#audit"><i class="bi bi-shield-check"></i> Audit Log (<?= count($audit) ?>)</a></li>
</ul>
<div class="tab-content bg-white p-4 shadow-sm">
  <div class="tab-pane fade show active" id="details">
    <dl class="row">
      <dt class="col-sm-3">Date of Birth</dt><dd class="col-sm-9"><?= e($p['dob']) ?></dd>
      <dt class="col-sm-3">Gender</dt><dd class="col-sm-9"><?= e($p['gender']) ?></dd>
      <dt class="col-sm-3">Blood Group</dt><dd class="col-sm-9"><?= e($p['blood_group']) ?></dd>
      <dt class="col-sm-3">Address</dt><dd class="col-sm-9"><?= e($p['address']) ?></dd>
      <dt class="col-sm-3">Emergency</dt><dd class="col-sm-9"><?= e($p['emergency_contact']) ?></dd>
    </dl>
  </div>

  <div class="tab-pane fade" id="current">
    <?php if(!$current): ?>
      <div class="alert alert-info">No open visit. Ask reception to check-in the patient.</div>
    <?php else: ?>
      <h5>Visit on <?= e($current['visit_date']) ?>
        <span class="badge bg-<?= $current['patient_type']==='inpatient'?'danger':'info' ?> ms-2"><?= e(ucfirst($current['patient_type'])) ?></span>
        <?php if($role==='doctor'): ?>
          <form method="post" class="d-flex flex-wrap align-items-center gap-2 mt-2">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="set_patient_type">
            <label for="patientType" class="form-label mb-0">Classify this visit:</label>
            <select id="patientType" name="patient_type" class="form-select form-select-sm w-auto">
              <option value="outpatient" <?= $current['patient_type']==='outpatient'?'selected':'' ?>>Outpatient</option>
              <option value="inpatient" <?= $current['patient_type']==='inpatient'?'selected':'' ?>>Inpatient</option>
            </select>
            <button class="btn btn-sm btn-outline-primary">Save Type</button>
          </form>
          <form method="post" class="d-inline">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="close">
            <button class="btn btn-sm btn-outline-success ms-2" <?= !$current['pharmacy_cleared'] ? 'disabled':'' ?>><i class="bi bi-check2-circle"></i> Close Visit</button>
          </form>
        <?php endif; ?>
      </h5>

      <section class="border rounded p-3 my-3">
        <p class="small text-muted">Each department should add every billable service it provides. Reception sets the rates; adding a new service requires pharmacy to clear the bill again.</p>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h6 class="mb-0">Visit Bill</h6>
          <span class="badge bg-<?= $current['pharmacy_cleared'] ? 'success' : 'warning text-dark' ?>">
            <?= $current['pharmacy_cleared'] ? 'Pharmacy cleared' : 'Awaiting pharmacy clearance' ?>
          </span>
        </div>
        <div class="row g-2 my-2">
          <div class="col-sm-4"><strong>Charges</strong><br>UGX <?= number_format($billing['total_cents'] / 100, 2) ?></div>
          <div class="col-sm-4"><strong>Paid</strong><br>UGX <?= number_format($billing['paid_cents'] / 100, 2) ?></div>
          <div class="col-sm-4"><strong>Balance</strong><br>UGX <?= number_format($billing['balance_cents'] / 100, 2) ?></div>
        </div>
        <?php if($role==='pharmacy'): ?>
          <a class="btn btn-sm btn-outline-success mb-2" href="<?= base_url('pages/pharmacy/clearance.php') ?>">Open Pharmacy Clearance</a>
        <?php endif; ?>
        <div class="table-responsive">
          <table class="table table-sm mb-2">
            <thead><tr><th>Service</th><th>Department</th><th>Qty</th><th>Unit price</th><th>Total</th></tr></thead>
            <tbody>
              <?php foreach($billing['services'] as $line): ?>
                <tr><td><?= e($line['service_name']) ?></td><td><?= e(ucfirst($line['department'])) ?></td><td><?= (int)$line['quantity'] ?></td><td>UGX <?= number_format((float)$line['unit_price'], 2) ?></td><td>UGX <?= number_format((float)$line['line_total'], 2) ?></td></tr>
              <?php endforeach; ?>
              <?php if(!$billing['services']): ?><tr><td colspan="5" class="text-muted">No services have been added to this bill.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if($billing['payments']): ?>
          <h6 class="small">Receipts</h6>
          <ul class="small mb-2">
            <?php foreach($billing['payments'] as $receipt): ?>
              <li><?= e($receipt['created_at']) ?> — UGX <?= number_format((float)$receipt['amount'], 2) ?> at <?= e(ucfirst($receipt['collection_point'])) ?> (<?= e(ucwords(str_replace('_', ' ', $receipt['method']))) ?><?= $receipt['reference'] ? ', ref ' . e($receipt['reference']) : '' ?>)</li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if($billableServices): ?>
          <form method="post" class="row g-2 align-items-end mt-2">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_service">
            <div class="col-md-6">
              <label for="billable-service" class="form-label">Add a <?= e($role) ?> service performed</label>
              <select id="billable-service" name="service_id" class="form-select form-select-sm" required>
                <?php foreach($billableServices as $service): ?>
                  <option value="<?= (int)$service['id'] ?>"><?= e($service['name']) ?> — UGX <?= number_format((float)$service['price'], 2) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2"><label for="service-quantity" class="form-label">Quantity</label><input id="service-quantity" name="quantity" type="number" min="1" max="1000" value="1" class="form-control form-control-sm" required></div>
            <div class="col-md-4"><button class="btn btn-sm btn-primary">Add to bill</button></div>
          </form>
        <?php else: ?>
          <p class="small text-muted mb-0">Reception has not set up any active <?= e($role) ?> service charges yet.</p>
        <?php endif; ?>
      </section>

      <h6 class="mt-3 text-primary">Diagnoses</h6>
      <?php foreach($curData['dx'] as $d): ?>
        <div class="border rounded p-2 mb-2">
          <div><strong>Dr <?= e($d['doctor']) ?></strong> — <?= e($d['diagnosis']) ?></div>
          <div class="small">Symptoms: <?= e($d['symptoms']) ?> | Treatment: <?= e($d['treatment']) ?></div>
          <div class="small text-muted"><?= e($d['notes']) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if($role==='doctor'): ?>
        <form method="post" class="row g-2 mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="dx">
          <div class="col-md-6"><input name="symptoms" class="form-control" placeholder="Symptoms"></div>
          <div class="col-md-6"><input name="diagnosis" class="form-control" placeholder="Diagnosis"></div>
          <div class="col-md-6"><input name="treatment" class="form-control" placeholder="Treatment"></div>
          <div class="col-md-6"><input name="notes" class="form-control" placeholder="Notes"></div>
          <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Add Diagnosis</button></div>
        </form>
      <?php endif; ?>

      <h6 class="mt-3 text-primary">Lab Tests</h6>
      <?php if (count($curData['lab']) === 0): ?>
        <div class="alert alert-secondary">No lab tests added yet. Doctors will review results and prescribe treatment once the lab has submitted findings.</div>
      <?php else: ?>
        <?php foreach($curData['lab'] as $l): ?>
          <div class="border rounded p-2 mb-2"><strong><?= e($l['test_name']) ?></strong>: <?= e($l['result']) ?> <span class="small text-muted">(by <?= e($l['tech']) ?>)</span></div>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php if($role==='lab'): ?>
        <form method="post" class="row g-2 mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="lab">
          <div class="col-md-4"><input name="test_name" class="form-control" placeholder="Test name" required></div>
          <div class="col-md-4"><input name="result" class="form-control" placeholder="Result"></div>
          <div class="col-md-4"><input name="remarks" class="form-control" placeholder="Remarks"></div>
          <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Add Lab Result</button></div>
        </form>
      <?php endif; ?>

      <?php if($role==='doctor'): ?>
        <?php if (count($curData['lab']) === 0): ?>
          <div class="alert alert-warning">Awaiting lab results before treatment. Once lab findings are added, enter diagnosis and treatment below.</div>
        <?php else: ?>
          <h6 class="mt-3 text-primary">Diagnoses</h6>
          <?php foreach($curData['dx'] as $d): ?>
            <div class="border rounded p-2 mb-2">
              <div><strong>Dr <?= e($d['doctor']) ?></strong> — <?= e($d['diagnosis']) ?></div>
              <div class="small">Symptoms: <?= e($d['symptoms']) ?> | Treatment: <?= e($d['treatment']) ?></div>
              <div class="small text-muted"><?= e($d['notes']) ?></div>
            </div>
          <?php endforeach; ?>
          <form method="post" class="row g-2 mb-3"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="dx">
            <div class="col-md-6"><input name="symptoms" class="form-control" placeholder="Symptoms"></div>
            <div class="col-md-6"><input name="diagnosis" class="form-control" placeholder="Diagnosis"></div>
            <div class="col-md-6"><input name="treatment" class="form-control" placeholder="Treatment"></div>
            <div class="col-md-6"><input name="notes" class="form-control" placeholder="Notes"></div>
            <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Add Diagnosis</button></div>
          </form>
        <?php endif; ?>
      <?php endif; ?>

      <h6 class="mt-3 text-primary">Prescriptions</h6>
      <?php foreach($curData['rx'] as $r): ?>
        <div class="border rounded p-2 mb-2"><strong><?= e($r['medicine']) ?></strong> — <?= e($r['dosage']) ?>, <?= e($r['duration']) ?> <span class="small text-muted">(by <?= e($r['pharmacist']) ?>)</span><div class="small"><?= e($r['instructions']) ?></div></div>
      <?php endforeach; ?>
      <?php if($role==='pharmacy'): ?>
        <form method="post" class="row g-2"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="rx">
          <div class="col-md-3"><input name="medicine" class="form-control" placeholder="Medicine" required></div>
          <div class="col-md-3"><input name="dosage" class="form-control" placeholder="Dosage"></div>
          <div class="col-md-3"><input name="duration" class="form-control" placeholder="Duration"></div>
          <div class="col-md-3"><input name="instructions" class="form-control" placeholder="Instructions"></div>
          <div class="col-12 mt-2"><button class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Dispense</button></div>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="tab-pane fade" id="history">
    <p class="text-muted small">Every previous visit is archived here. Click a date to view that visit's full report.</p>
    <div class="timeline">
      <?php foreach($history as $v): ?>
        <div class="item">
          <a href="visit.php?uid=<?= urlencode($uid) ?>&vid=<?= $v['id'] ?>" class="fw-semibold"><?= e($v['visit_date']) ?></a>
          <span class="badge bg-secondary"><?= e($v['status']) ?></span>
          <span class="badge bg-<?= $v['patient_type']==='inpatient'?'danger':'info' ?>"><?= e(ucfirst($v['patient_type'])) ?></span>
          <div class="small text-muted"><?= e($v['reason']) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if(!$history): ?><p class="text-muted">No past visits.</p><?php endif; ?>
    </div>
  </div>

  <div class="tab-pane fade" id="audit">
    <p class="text-muted small">Tamper-evident record of every change made to this patient's data.</p>
    <table class="table table-sm table-striped">
      <thead><tr><th>When</th><th>User</th><th>Role</th><th>Action</th><th>Field</th><th>Old → New</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach($audit as $a): ?>
        <tr>
          <td class="small"><?= e($a['created_at']) ?></td>
          <td><?= e($a['user_name'] ?? '—') ?></td>
          <td><span class="badge bg-secondary"><?= e($a['user_role']) ?></span></td>
          <td><code><?= e($a['action']) ?></code></td>
          <td><?= e($a['field_name']) ?></td>
          <td class="small"><?= e(mb_strimwidth((string)$a['old_value'],0,40,'…')) ?> → <strong><?= e(mb_strimwidth((string)$a['new_value'],0,40,'…')) ?></strong></td>
          <td class="small text-muted"><?= e($a['ip']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$audit): ?><tr><td colspan="7" class="text-muted">No audit entries yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
