<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('admin');
$page_title='Admin Dashboard';

$stats = [
  'staff' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
  'patients' => $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
  'visits' => $pdo->query("SELECT COUNT(*) FROM visits")->fetchColumn(),
  'active' => $pdo->query("SELECT COUNT(*) FROM visits WHERE status='open'")->fetchColumn(),
];

$patientTypes = $pdo->query("SELECT v.patient_type, COUNT(*) AS patients
    FROM visits v
    WHERE v.status='open'
      AND NOT EXISTS (
        SELECT 1
        FROM visits newer
        WHERE newer.patient_id=v.patient_id
          AND newer.status='open'
          AND (newer.visit_date > v.visit_date OR (newer.visit_date=v.visit_date AND newer.id > v.id))
      )
    GROUP BY v.patient_type")->fetchAll(PDO::FETCH_KEY_PAIR);
$stats['outpatients'] = $patientTypes['outpatient'] ?? 0;
$stats['inpatients'] = $patientTypes['inpatient'] ?? 0;

$deptCounts = $pdo->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role")->fetchAll();
$deptVisits = $pdo->query("SELECT u.role, COUNT(v.id) AS visits
    FROM visits v
    JOIN users u ON v.created_by=u.id
    GROUP BY u.role")->fetchAll();

$rank = $pdo->query("SELECT u.name, u.role, COUNT(v.id) AS patients
    FROM users u
    LEFT JOIN visits v ON v.created_by=u.id
    GROUP BY u.id ORDER BY patients DESC LIMIT 8")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-speedometer2 text-primary"></i> Admin Dashboard</h3>
<div class="row g-3 my-3">
  <div class="col-md-4"><div class="card card-stat p-3"><small class="text-muted">Total Staff</small><h2><?= $stats['staff'] ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><small class="text-muted">Total Patients</small><h2><?= $stats['patients'] ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><small class="text-muted">Total Visits</small><h2><?= $stats['visits'] ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><small class="text-muted">Open Visits</small><h2><?= $stats['active'] ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><small class="text-muted">Outpatients</small><h2><?= $stats['outpatients'] ?></h2><small class="text-muted">Currently checked in</small></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><small class="text-muted">Inpatients</small><h2><?= $stats['inpatients'] ?></h2><small class="text-muted">Currently admitted</small></div></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <a class="card border-0 shadow-sm text-decoration-none" href="<?= base_url('pages/admin/staff.php') ?>">
      <div class="card-body p-3 text-dark">
        <h6><i class="bi bi-people"></i> Manage Staff</h6>
        <p class="small text-muted mb-0">Add, update or remove hospital staff members.</p>
      </div>
    </a>
  </div>
  <div class="col-md-3">
    <a class="card border-0 shadow-sm text-decoration-none" href="<?= base_url('pages/admin/patient_history.php') ?>">
      <div class="card-body p-3 text-dark">
        <h6><i class="bi bi-folder2-open"></i> Patient History</h6>
        <p class="small text-muted mb-0">Search patient records and review visit history.</p>
      </div>
    </a>
  </div>
  <div class="col-md-3">
    <a class="card border-0 shadow-sm text-decoration-none" href="<?= base_url('pages/admin/payments.php') ?>">
      <div class="card-body p-3 text-dark">
        <h6><i class="bi bi-currency-dollar"></i> Financials &amp; Patient Payments</h6>
        <p class="small text-muted mb-0">Review patient receipts, outstanding balances, and staff payouts.</p>
      </div>
    </a>
  </div>
  <div class="col-md-3">
    <a class="card border-0 shadow-sm text-decoration-none" href="<?= base_url('pages/admin/reports.php') ?>">
      <div class="card-body p-3 text-dark">
        <h6><i class="bi bi-graph-up"></i> Hospital Reports</h6>
        <p class="small text-muted mb-0">View department and patient performance charts.</p>
      </div>
    </a>
  </div>
</div>
<div class="row g-3">
  <div class="col-md-6">
    <div class="card border-0 shadow-sm p-3">
      <h5>Staff by Department</h5>
      <ul class="list-group list-group-flush">
        <?php foreach($deptCounts as $row): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= e(ucfirst($row['role'])) ?>
            <span class="badge bg-primary rounded-pill"><?= $row['cnt'] ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card border-0 shadow-sm p-3">
      <h5>Visits by Staff Role</h5>
      <table class="table table-sm mb-0">
        <thead><tr><th>Role</th><th>Visits</th></tr></thead>
        <tbody>
          <?php foreach($deptVisits as $row): ?>
            <tr><td><?= e(ucfirst($row['role'])) ?></td><td><?= $row['visits'] ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="row g-3 mt-3">
  <div class="col-md-6">
    <div class="card border-0 shadow-sm p-3">
      <h5>Department Distribution</h5>
      <canvas id="deptChart" height="220"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card border-0 shadow-sm p-3">
      <h5>Visits by Role</h5>
      <canvas id="visitsChart" height="220"></canvas>
    </div>
  </div>
</div>
<div class="card border-0 shadow-sm mt-3 p-3">
  <h5>Top Active Staff</h5>
  <table class="table table-sm mb-0">
    <thead><tr><th>Name</th><th>Role</th><th>Patients</th></tr></thead>
    <tbody>
      <?php foreach($rank as $row): ?>
        <tr><td><?= e($row['name']) ?></td><td><?= e($row['role']) ?></td><td><?= $row['patients'] ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const deptLabels = <?= json_encode(array_column($deptCounts,'role')) ?>;
  const deptValues = <?= json_encode(array_column($deptCounts,'cnt')) ?>;
  const visitLabels = <?= json_encode(array_column($deptVisits,'role')) ?>;
  const visitValues = <?= json_encode(array_column($deptVisits,'visits')) ?>;
  const ctxDept = document.getElementById('deptChart').getContext('2d');
  new Chart(ctxDept, {
    type: 'doughnut',
    data: {
      labels: deptLabels,
      datasets: [{
        data: deptValues,
        backgroundColor: ['#0d6efd','#198754','#fd7e14','#6610f2','#dc3545'],
        borderWidth: 1
      }]
    },
    options: {
      plugins: {legend:{position:'bottom'}}
    }
  });

  const ctxVisits = document.getElementById('visitsChart').getContext('2d');
  new Chart(ctxVisits, {
    type: 'bar',
    data: {
      labels: visitLabels,
      datasets: [{
        label: 'Visits',
        data: visitValues,
        backgroundColor: '#0d6efd'
      }]
    },
    options: {
      scales: {y:{beginAtZero:true}},
      plugins: {legend:{display:false}}
    }
  });
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>