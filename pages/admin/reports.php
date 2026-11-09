<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role('admin');
$page_title='Hospital Reports';

$monthlyPatients = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS cnt FROM patients GROUP BY month ORDER BY month DESC LIMIT 6")->fetchAll();
$monthlyPatients = array_reverse($monthlyPatients);

$revenueByRole = $pdo->query("SELECT COALESCE(u.role, vp.collection_point) AS role,
    COUNT(DISTINCT vp.visit_id) AS visits, ROUND(COALESCE(SUM(vp.amount),0),2) AS revenue
    FROM visit_payments vp
    LEFT JOIN users u ON u.id=vp.collected_by
    GROUP BY COALESCE(u.role, vp.collection_point)
    ORDER BY revenue DESC")->fetchAll();

$topPatients = $pdo->query("SELECT p.full_name, p.rfid_uid, COUNT(v.id) AS visits FROM patients p LEFT JOIN visits v ON v.patient_id=p.id GROUP BY p.id ORDER BY visits DESC LIMIT 10")->fetchAll();

$visitStatus = $pdo->query("SELECT status, COUNT(*) AS cnt FROM visits GROUP BY status")->fetchAll();
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-graph-up text-primary"></i> Hospital Reports</h3>
<div class="row g-3 my-3">
  <div class="col-md-4"><div class="card card-stat p-3"><small class="text-muted">Last 6 Months Patients</small><h2><?= array_sum(array_column($monthlyPatients, 'cnt')) ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><small class="text-muted">Patient Payments Collected</small><h2>UGX <?= number_format((float)array_sum(array_column($revenueByRole, 'revenue')), 2) ?></h2></div></div>
  <div class="col-md-4"><div class="card card-stat p-3"><small class="text-muted">Top Active Patients</small><h2><?= count($topPatients) ?></h2></div></div>
</div>
<div class="row g-3">
  <div class="col-md-6">
    <div class="card border-0 shadow-sm p-3">
      <h5>Patient Growth</h5>
      <canvas id="patientGrowthChart" height="220"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card border-0 shadow-sm p-3">
      <h5>Patient Payments by Collection Staff</h5>
      <canvas id="revenueChart" height="220"></canvas>
    </div>
  </div>
</div>
<div class="row g-3 mt-3">
  <div class="col-md-6">
    <div class="card border-0 shadow-sm p-3">
      <h5>Visit Status</h5>
      <canvas id="visitStatusChart" height="220"></canvas>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card border-0 shadow-sm p-3">
      <h5>Top Patients by Visits</h5>
      <table class="table table-sm mb-0">
        <thead><tr><th>Patient</th><th>Card Number</th><th>Visits</th></tr></thead>
        <tbody>
          <?php foreach ($topPatients as $row): ?>
            <tr>
              <td><?= e($row['full_name']) ?></td>
              <td><code><?= e($row['rfid_uid']) ?></code></td>
              <td><?= $row['visits'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const patientLabels = <?= json_encode(array_column($monthlyPatients,'month')) ?>;
  const patientCounts = <?= json_encode(array_column($monthlyPatients,'cnt')) ?>;
  const revenueLabels = <?= json_encode(array_column($revenueByRole,'role')) ?>;
  const revenueValues = <?= json_encode(array_column($revenueByRole,'revenue')) ?>;
  const visitStatusLabels = <?= json_encode(array_column($visitStatus,'status')) ?>;
  const visitStatusValues = <?= json_encode(array_column($visitStatus,'cnt')) ?>;

  new Chart(document.getElementById('patientGrowthChart'), {
    type: 'line',
    data: {
      labels: patientLabels,
      datasets: [{
        label: 'Patients added',
        data: patientCounts,
        borderColor: '#0d6efd',
        backgroundColor: 'rgba(13,110,253,0.2)',
        fill: true,
        tension: 0.3
      }]
    },
    options: {scales:{y:{beginAtZero:true}}}
  });

  new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
      labels: revenueLabels,
      datasets: [{label:'Collected UGX',data:revenueValues,backgroundColor:'#198754'}]
    },
    options: {scales:{y:{beginAtZero:true}}}
  });

  new Chart(document.getElementById('visitStatusChart'), {
    type: 'doughnut',
    data: {
      labels: visitStatusLabels,
      datasets: [{data:visitStatusValues,backgroundColor:['#0d6efd','#fd7e14']}]
    },
    options: {plugins:{legend:{position:'bottom'}}}
  });
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>