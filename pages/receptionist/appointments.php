<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_role(['receptionist','doctor','admin']);

$role = current_user()['role'];
$flash = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $appointmentAt = trim((string)($_POST['appointment_at'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        $timestamp = strtotime($appointmentAt);
        if ($patientId <= 0 || $doctorId <= 0 || $timestamp === false) {
            $_SESSION['flash_msg'] = 'Choose a patient, doctor, and valid appointment time.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO appointments (patient_id,doctor_id,appointment_at,reason,created_by,status)
                                   VALUES (?,?,?,?,?, 'scheduled')");
            $stmt->execute([$patientId, $doctorId, date('Y-m-d H:i:s', $timestamp), $reason ?: null, current_user()['id']]);
            $_SESSION['flash_msg'] = 'Appointment scheduled successfully.';
        }
        header('Location: appointments.php');
        exit;
    }

    if ($action === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($id > 0 && in_array($status, ['scheduled','arrived','completed','cancelled'], true)) {
            $pdo->prepare('UPDATE appointments SET status=? WHERE id=?')->execute([$status, $id]);
            $_SESSION['flash_msg'] = 'Appointment status updated.';
        }
        header('Location: appointments.php');
        exit;
    }
}

$patients = $pdo->query("SELECT id,full_name,rfid_uid FROM patients ORDER BY full_name")->fetchAll();
$doctors = $pdo->query("SELECT id,name FROM users WHERE role='doctor' ORDER BY name")->fetchAll();
$appointments = $pdo->query("SELECT a.*,p.full_name,p.rfid_uid,u.name AS doctor_name
                             FROM appointments a
                             JOIN patients p ON p.id=a.patient_id
                             LEFT JOIN users u ON u.id=a.doctor_id
                             WHERE a.appointment_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                             ORDER BY a.appointment_at ASC LIMIT 200")->fetchAll();

$page_title = 'Appointments';
include __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3><i class="bi bi-calendar2-check text-primary"></i> Appointments</h3>
  <span class="badge bg-primary-subtle text-primary-emphasis" id="appointment-live-status">Live queue</span>
</div>
<?php if ($flash): ?><div class="alert alert-info"><?= e($flash) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm p-3">
      <h5>Schedule Appointment</h5>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="create">
        <div class="mb-3">
          <label class="form-label">Patient</label>
          <select name="patient_id" class="form-select" required>
            <option value="">Select patient</option>
            <?php foreach ($patients as $patient): ?>
              <option value="<?= (int)$patient['id'] ?>"><?= e($patient['full_name']) ?> (<?= e($patient['rfid_uid']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Doctor</label>
          <select name="doctor_id" class="form-select" required>
            <option value="">Select doctor</option>
            <?php foreach ($doctors as $doctor): ?>
              <option value="<?= (int)$doctor['id'] ?>"><?= e($doctor['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Date and time</label>
          <input type="datetime-local" name="appointment_at" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Reason</label>
          <textarea name="reason" class="form-control" rows="3" maxlength="255"></textarea>
        </div>
        <button class="btn btn-primary"><i class="bi bi-calendar-plus"></i> Schedule</button>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm p-3">
      <h5>Upcoming Queue</h5>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($appointments as $appointment): ?>
              <tr>
                <td><?= e($appointment['appointment_at']) ?></td>
                <td><?= e($appointment['full_name']) ?><br><code class="small"><?= e($appointment['rfid_uid']) ?></code></td>
                <td><?= e($appointment['doctor_name'] ?: '—') ?></td>
                <td><span class="badge bg-<?= $appointment['status'] === 'completed' ? 'success' : ($appointment['status'] === 'cancelled' ? 'danger' : 'warning text-dark') ?>"><?= e($appointment['status']) ?></span></td>
                <td>
                  <?php if (in_array($appointment['status'], ['scheduled','arrived'], true)): ?>
                    <form method="post" class="d-flex gap-1">
                      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                      <input type="hidden" name="action" value="status">
                      <input type="hidden" name="id" value="<?= (int)$appointment['id'] ?>">
                      <select name="status" class="form-select form-select-sm">
                        <option value="arrived">Arrived</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                      </select>
                      <button class="btn btn-sm btn-outline-primary">Update</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$appointments): ?><tr><td colspan="5" class="text-muted">No upcoming appointments.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>
setInterval(async () => {
  try {
    const response = await fetch('<?= base_url('api/queue_status.php') ?>', {cache:'no-store'});
    if (!response.ok) return;
    const data = await response.json();
    document.getElementById('appointment-live-status').textContent =
      (data.appointments_today || 0) + ' today · Live';
  } catch (_) {}
}, 15000);
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
