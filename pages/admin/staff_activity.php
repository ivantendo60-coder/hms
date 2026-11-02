<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('admin');

$page_title = 'Staff Patient Activity';
$department = trim($_GET['department'] ?? '');
$staffId = filter_input(INPUT_GET, 'staff_id', FILTER_VALIDATE_INT) ?: 0;

$departments = $pdo->query("SELECT DISTINCT role FROM users ORDER BY role")->fetchAll(PDO::FETCH_COLUMN);
$staff = $pdo->query("SELECT id, name, role FROM users ORDER BY role, name")->fetchAll();
$conditions = [];
$params = [];
if ($department !== '' && in_array($department, $departments, true)) {
    $conditions[] = 'activity.department = ?';
    $params[] = $department;
} else {
    $department = '';
}
if ($staffId > 0) {
    $conditions[] = 'activity.staff_id = ?';
    $params[] = $staffId;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$sql = "SELECT activity.staff_id, activity.staff_name, activity.department,
               activity.patient_id, activity.patient_name,
               MAX(activity.activity_at) AS last_worked,
               COUNT(*) AS recorded_actions,
               GROUP_CONCAT(DISTINCT activity.activity ORDER BY activity.activity SEPARATOR ', ') AS activities
        FROM (
            SELECT u.id AS staff_id, u.name AS staff_name, u.role AS department,
                   p.id AS patient_id, p.full_name AS patient_name,
                   v.visit_date AS activity_at, 'Visit' AS activity
            FROM visits v
            JOIN users u ON u.id = v.created_by
            JOIN patients p ON p.id = v.patient_id
            UNION ALL
            SELECT u.id, u.name, u.role, p.id, p.full_name, d.created_at, 'Diagnosis'
            FROM diagnoses d
            JOIN users u ON u.id = d.doctor_id
            JOIN visits v ON v.id = d.visit_id
            JOIN patients p ON p.id = v.patient_id
            UNION ALL
            SELECT u.id, u.name, u.role, p.id, p.full_name, l.created_at, 'Lab test'
            FROM lab_tests l
            JOIN users u ON u.id = l.lab_tech_id
            JOIN visits v ON v.id = l.visit_id
            JOIN patients p ON p.id = v.patient_id
            UNION ALL
            SELECT u.id, u.name, u.role, p.id, p.full_name, r.created_at, 'Prescription'
            FROM prescriptions r
            JOIN users u ON u.id = r.pharmacist_id
            JOIN visits v ON v.id = r.visit_id
            JOIN patients p ON p.id = v.patient_id
            UNION ALL
            SELECT u.id, u.name, u.role, p.id, p.full_name, a.created_at, a.action
            FROM audit_logs a
            JOIN users u ON u.id = a.user_id
            JOIN patients p ON p.id = a.patient_id
        ) AS activity
        $where
        GROUP BY activity.staff_id, activity.staff_name, activity.department,
                 activity.patient_id, activity.patient_name
        ORDER BY last_worked DESC, activity.staff_name, activity.patient_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activities = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-person-lines-fill text-primary"></i> Staff Patient Activity</h3>
<p class="text-muted">Review patients handled by staff, organized by role/department. Activity is drawn from visits, clinical records, and patient audit entries.</p>

<div class="card border-0 shadow-sm p-3 mb-3">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-4">
      <label for="department" class="form-label">Department / role</label>
      <select id="department" name="department" class="form-select">
        <option value="">All departments</option>
        <?php foreach ($departments as $option): ?>
          <option value="<?= e($option) ?>" <?= $department === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label for="staff_id" class="form-label">Staff member</label>
      <select id="staff_id" name="staff_id" class="form-select">
        <option value="">All staff</option>
        <?php foreach ($staff as $member): ?>
          <option value="<?= (int)$member['id'] ?>" <?= $staffId === (int)$member['id'] ? 'selected' : '' ?>><?= e($member['name']) ?> — <?= e(ucfirst($member['role'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto"><button class="btn btn-primary">Filter</button></div>
  </form>
</div>

<div class="card border-0 shadow-sm p-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Patients handled</h5>
    <span class="badge bg-primary"><?= count($activities) ?> staff-patient records</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Staff member</th><th>Department</th><th>Patient</th><th>Recorded activity</th><th>Activity entries</th><th>Last worked</th></tr></thead>
      <tbody>
        <?php foreach ($activities as $row): ?>
          <tr>
            <td><?= e($row['staff_name']) ?></td>
            <td><?= e(ucfirst($row['department'])) ?></td>
            <td><?= e($row['patient_name']) ?></td>
            <td><?= e($row['activities']) ?></td>
            <td><?= (int)$row['recorded_actions'] ?></td>
            <td><?= e($row['last_worked']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$activities): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No staff-patient activity found for these filters.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
