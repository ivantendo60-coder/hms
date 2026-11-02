<?php
// Browser-printable report (use Ctrl+P -> Save as PDF). Works without external libs.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role(['doctor','lab','pharmacy']);
$uid=strtoupper($_GET['uid']??''); $vid=(int)($_GET['vid']??0);
$p=$pdo->prepare("SELECT * FROM patients WHERE rfid_uid=?"); $p->execute([$uid]); $p=$p->fetch();
if(!$p) die('No patient');
if ($vid) { $vs=$pdo->prepare("SELECT * FROM visits WHERE id=? AND patient_id=?"); $vs->execute([$vid,$p['id']]); $visits=[$vs->fetch()]; }
else { $vs=$pdo->prepare("SELECT * FROM visits WHERE patient_id=? ORDER BY visit_date DESC"); $vs->execute([$p['id']]); $visits=$vs->fetchAll(); }
?>
<!doctype html><html><head><meta charset="utf-8"><title>Report - <?= e($p['full_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>@media print{.no-print{display:none}}body{padding:30px;font-family:Georgia,serif}</style>
</head><body onload="window.print()">
<div class="d-flex justify-content-between border-bottom pb-2 mb-3">
  <h2>🏥 MediCare Hospital — Medical Report</h2>
  <div class="text-end small">Generated <?= date('Y-m-d H:i') ?></div>
</div>
<h4><?= e($p['full_name']) ?></h4>
<p>RFID: <code><?= e($p['rfid_uid']) ?></code> • <?= e($p['gender']) ?> • DOB <?= e($p['dob']) ?> • Blood <?= e($p['blood_group']) ?><br>
<?= e($p['phone']) ?> • <?= e($p['address']) ?></p>
<hr>
<?php foreach($visits as $v): if(!$v) continue;
  $dx=$pdo->prepare("SELECT d.*,u.name n FROM diagnoses d LEFT JOIN users u ON u.id=d.doctor_id WHERE visit_id=?"); $dx->execute([$v['id']]); $dx=$dx->fetchAll();
  $lb=$pdo->prepare("SELECT l.*,u.name n FROM lab_tests l LEFT JOIN users u ON u.id=l.lab_tech_id WHERE visit_id=?"); $lb->execute([$v['id']]); $lb=$lb->fetchAll();
  $rx=$pdo->prepare("SELECT r.*,u.name n FROM prescriptions r LEFT JOIN users u ON u.id=r.pharmacist_id WHERE visit_id=?"); $rx->execute([$v['id']]); $rx=$rx->fetchAll();
?>
  <h5 class="text-primary">Visit: <?= e($v['visit_date']) ?> — <em><?= e($v['reason']) ?></em></h5>
  <strong>Diagnoses</strong><ul><?php foreach($dx as $d): ?><li><?= e($d['diagnosis']) ?> — Sx: <?= e($d['symptoms']) ?>; Tx: <?= e($d['treatment']) ?> <em>(<?= e($d['n']) ?>)</em></li><?php endforeach; ?></ul>
  <strong>Lab</strong><ul><?php foreach($lb as $l): ?><li><?= e($l['test_name']) ?>: <?= e($l['result']) ?></li><?php endforeach; ?></ul>
  <strong>Prescriptions</strong><ul><?php foreach($rx as $r): ?><li><?= e($r['medicine']) ?> — <?= e($r['dosage']) ?>, <?= e($r['duration']) ?></li><?php endforeach; ?></ul>
  <hr>
<?php endforeach; ?>
<button class="btn btn-primary no-print" onclick="window.print()">Print / Save as PDF</button>
</body></html>
