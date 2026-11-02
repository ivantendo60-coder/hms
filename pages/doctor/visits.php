<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('doctor');
$page_title="Today's Visits";
$rows = $pdo->query("SELECT v.*, p.full_name, p.rfid_uid FROM visits v JOIN patients p ON p.id=v.patient_id WHERE DATE(v.visit_date)=CURDATE() ORDER BY v.visit_date DESC")->fetchAll();
include __DIR__ . '/../../includes/header.php';
?>
<h3>Today's Visits</h3>
<table class="table bg-white shadow-sm"><thead><tr><th>Time</th><th>Patient</th><th>RFID</th><th>Reason</th><th>Patient Type</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach($rows as $v): ?>
<tr><td><?= e($v['visit_date']) ?></td><td><?= e($v['full_name']) ?></td><td><code><?= e($v['rfid_uid']) ?></code></td>
<td><?= e($v['reason']) ?></td><td><span class="badge bg-<?= $v['patient_type']==='inpatient'?'danger':'info' ?>"><?= e(ucfirst($v['patient_type'])) ?></span></td><td><?= e($v['status']) ?></td>
<td><a class="btn btn-sm btn-primary" href="<?= base_url('pages/patient/view.php?uid='.urlencode($v['rfid_uid'])) ?>">Attend</a></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
