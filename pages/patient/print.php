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
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  * { box-sizing: border-box; }
  body { max-width: 900px; margin: 0 auto; padding: 32px; color: #172033; font: 14px/1.5 Arial, sans-serif; }
  .report-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 16px; border-bottom: 2px solid #244c86; }
  .report-header h1 { margin: 0; color: #17365f; font-size: 24px; }
  .report-header p, .muted { margin: 4px 0 0; color: #667085; font-size: 12px; }
  .patient { margin: 20px 0; padding: 16px; border: 1px solid #d9e1eb; border-radius: 8px; background: #f7f9fc; }
  .patient h2 { margin: 0 0 8px; font-size: 20px; }
  .patient p { margin: 3px 0; }
  .visit { margin-top: 20px; page-break-inside: avoid; }
  .visit h3 { margin: 0 0 12px; padding-bottom: 7px; color: #244c86; border-bottom: 1px solid #d9e1eb; font-size: 17px; }
  .section { margin: 12px 0; page-break-inside: avoid; }
  .section h4 { margin: 0 0 5px; font-size: 14px; }
  .section ul { margin: 0; padding-left: 20px; }
  .section li { margin: 3px 0; }
  .empty { color: #667085; font-style: italic; }
  .report-footer { margin-top: 28px; padding-top: 8px; color: #667085; border-top: 1px solid #d9e1eb; font-size: 11px; }
  .no-print { margin-top: 20px; padding: 9px 16px; color: #fff; background: #244c86; border: 0; border-radius: 5px; cursor: pointer; }
  @page { margin: 16mm; }
  @media print {
    body { max-width: none; padding: 0; font-size: 12px; }
    .no-print { display: none; }
    .patient { background: #fff; }
  }
  @media (max-width: 600px) {
    body { padding: 16px; }
    .report-header { flex-direction: column; gap: 5px; }
  }
</style>
</head><body onload="window.print()">
<header class="report-header">
  <div>
    <h1>myPatient Hospital</h1>
    <p>Medical Report</p>
  </div>
  <div class="muted">Generated <?= date('Y-m-d H:i') ?></div>
</header>
<section class="patient">
  <h2><?= e($p['full_name']) ?></h2>
  <p><strong>Card Number:</strong> <code><?= e($p['rfid_uid']) ?></code></p>
  <p><strong>Gender:</strong> <?= e($p['gender']) ?> &nbsp; <strong>Date of birth:</strong> <?= e($p['dob']) ?> &nbsp; <strong>Blood group:</strong> <?= e($p['blood_group']) ?></p>
  <p><strong>Phone:</strong> <?= e($p['phone']) ?> &nbsp; <strong>Address:</strong> <?= e($p['address']) ?></p>
</section>
<?php foreach($visits as $v): if(!$v) continue;
  $dx=$pdo->prepare("SELECT d.*,u.name n FROM diagnoses d LEFT JOIN users u ON u.id=d.doctor_id WHERE visit_id=?"); $dx->execute([$v['id']]); $dx=$dx->fetchAll();
  $lb=$pdo->prepare("SELECT l.*,u.name n FROM lab_tests l LEFT JOIN users u ON u.id=l.lab_tech_id WHERE visit_id=?"); $lb->execute([$v['id']]); $lb=$lb->fetchAll();
  $rx=$pdo->prepare("SELECT r.*,u.name n FROM prescriptions r LEFT JOIN users u ON u.id=r.pharmacist_id WHERE visit_id=?"); $rx->execute([$v['id']]); $rx=$rx->fetchAll();
?>
<section class="visit">
  <h3>Visit — <?= e($v['visit_date']) ?></h3>
  <?php if (!empty($v['reason'])): ?><p><strong>Reason:</strong> <?= e($v['reason']) ?></p><?php endif; ?>
  <div class="section">
    <h4>Diagnoses</h4>
    <?php if ($dx): ?><ul><?php foreach($dx as $d): ?>
      <li><strong><?= e($d['diagnosis']) ?></strong>
        <?php if ($d['symptoms']): ?> — Symptoms: <?= e($d['symptoms']) ?><?php endif; ?>
        <?php if ($d['treatment']): ?> — Treatment: <?= e($d['treatment']) ?><?php endif; ?>
        <?php if ($d['n']): ?> <span class="muted">(<?= e($d['n']) ?>)</span><?php endif; ?>
      </li>
    <?php endforeach; ?></ul><?php else: ?><p class="empty">No diagnoses recorded.</p><?php endif; ?>
  </div>
  <div class="section">
    <h4>Lab Results</h4>
    <?php if ($lb): ?><ul><?php foreach($lb as $l): ?>
      <li><strong><?= e($l['test_name']) ?>:</strong> <?= e($l['result']) ?></li>
    <?php endforeach; ?></ul><?php else: ?><p class="empty">No lab results recorded.</p><?php endif; ?>
  </div>
  <div class="section">
    <h4>Prescriptions</h4>
    <?php if ($rx): ?><ul><?php foreach($rx as $r): ?>
      <li><strong><?= e($r['medicine']) ?></strong> — <?= e($r['dosage']) ?>, <?= e($r['duration']) ?><?php if ($r['instructions']): ?>. <?= e($r['instructions']) ?><?php endif; ?></li>
    <?php endforeach; ?></ul><?php else: ?><p class="empty">No prescriptions recorded.</p><?php endif; ?>
  </div>
</section>
<?php endforeach; ?>
<footer class="report-footer">Confidential patient information · myPatient Hospital</footer>
<button class="no-print" onclick="window.print()">Print / Save as PDF</button>
</body></html>
