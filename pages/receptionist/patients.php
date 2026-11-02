<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('receptionist');
$page_title='Patients';
$q = trim($_GET['q']??'');
if ($q) {
  $stmt = $pdo->prepare("SELECT * FROM patients WHERE full_name LIKE ? OR rfid_uid LIKE ? OR phone LIKE ? ORDER BY full_name");
  $stmt->execute(["%$q%","%$q%","%$q%"]);
} else $stmt = $pdo->query("SELECT * FROM patients ORDER BY full_name");
$rows = $stmt->fetchAll();
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-people"></i> Patients Directory</h3>
<form class="d-flex gap-2 my-3"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search by name, RFID or phone"><button class="btn btn-primary">Search</button></form>
<table class="table table-striped bg-white shadow-sm">
<thead><tr><th>RFID</th><th>Name</th><th>Gender</th><th>Phone</th><th>Registered</th><th></th></tr></thead>
<tbody>
<?php foreach($rows as $p): ?>
<tr>
  <td><code><?= e($p['rfid_uid']) ?></code></td>
  <td><?= e($p['full_name']) ?></td>
  <td><?= e($p['gender']) ?></td>
  <td><?= e($p['phone']) ?></td>
  <td><?= e($p['created_at']) ?></td>
  <td>
    <a class="btn btn-sm btn-outline-primary" href="patient_view.php?uid=<?= urlencode($p['rfid_uid']) ?>">View</a>
    <a class="btn btn-sm btn-outline-secondary" href="edit_patient.php?id=<?= $p['id'] ?>">Edit</a>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
