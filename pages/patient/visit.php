<?php
// Read-only single-visit report (linked from history dates)
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/visit_billing.php';
require_role(['doctor','lab','pharmacy']);
$uid=strtoupper($_GET['uid']??''); $vid=(int)($_GET['vid']??0);
$p=$pdo->prepare("SELECT * FROM patients WHERE rfid_uid=?"); $p->execute([$uid]); $p=$p->fetch();
$v=$pdo->prepare("SELECT * FROM visits WHERE id=? AND patient_id=?"); $v->execute([$vid,$p['id']]); $v=$v->fetch();
if(!$v) die('Visit not found');
$dx=$pdo->prepare("SELECT d.*,u.name AS doctor FROM diagnoses d LEFT JOIN users u ON u.id=d.doctor_id WHERE visit_id=?"); $dx->execute([$vid]); $dx=$dx->fetchAll();
$lb=$pdo->prepare("SELECT l.*,u.name AS tech FROM lab_tests l LEFT JOIN users u ON u.id=l.lab_tech_id WHERE visit_id=?"); $lb->execute([$vid]); $lb=$lb->fetchAll();
$rx=$pdo->prepare("SELECT r.*,u.name AS ph FROM prescriptions r LEFT JOIN users u ON u.id=r.pharmacist_id WHERE visit_id=?"); $rx->execute([$vid]); $rx=$rx->fetchAll();
$billing=visit_billing_summary($pdo, $vid);
$page_title='Visit '.$v['visit_date'];
include __DIR__ . '/../../includes/header.php';
?>
<a href="view.php?uid=<?= urlencode($uid) ?>" class="btn btn-light btn-sm no-print"><i class="bi bi-arrow-left"></i> Back</a>
<a href="print.php?uid=<?= urlencode($uid) ?>&vid=<?= $vid ?>" target="_blank" class="btn btn-outline-dark btn-sm no-print"><i class="bi bi-printer"></i> Print PDF</a>
<h3 class="mt-3">Visit Report — <?= e($v['visit_date']) ?></h3>
<p><strong>Patient type:</strong> <?= e(ucfirst($v['patient_type'])) ?></p>
<div class="card border-0 shadow-sm p-3 mb-3">
  <h5>Visit Bill</h5>
  <p class="mb-1">Charges: UGX <?= number_format($billing['total_cents'] / 100, 2) ?> · Paid: UGX <?= number_format($billing['paid_cents'] / 100, 2) ?> · Balance: UGX <?= number_format($billing['balance_cents'] / 100, 2) ?></p>
  <p class="mb-0">Pharmacy clearance: <?= $v['pharmacy_cleared'] ? 'Cleared' : 'Pending' ?></p>
</div>
<p><strong>Patient:</strong> <?= e($p['full_name']) ?> • RFID <code><?= e($uid) ?></code></p>
<h5 class="text-primary mt-3">Diagnoses</h5>
<?php foreach($dx as $d): ?><div class="border rounded p-2 mb-2"><strong>Dr <?= e($d['doctor']) ?></strong> — <?= e($d['diagnosis']) ?><div class="small">Symptoms: <?= e($d['symptoms']) ?> • Tx: <?= e($d['treatment']) ?></div><div class="small text-muted"><?= e($d['notes']) ?></div></div><?php endforeach; ?>
<h5 class="text-primary mt-3">Lab</h5>
<?php foreach($lb as $l): ?><div class="border rounded p-2 mb-2"><strong><?= e($l['test_name']) ?></strong>: <?= e($l['result']) ?> <span class="small text-muted">(<?= e($l['tech']) ?>)</span></div><?php endforeach; ?>
<h5 class="text-primary mt-3">Prescriptions</h5>
<?php foreach($rx as $r): ?><div class="border rounded p-2 mb-2"><strong><?= e($r['medicine']) ?></strong> <?= e($r['dosage']) ?> • <?= e($r['duration']) ?><div class="small"><?= e($r['instructions']) ?></div></div><?php endforeach; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
