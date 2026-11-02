<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/visit_billing.php';
require_role('receptionist');
$page_title = 'Service Charges';
$flash = $_SESSION['flash_msg'] ?? null;
$error = $_SESSION['flash_err'] ?? null;
unset($_SESSION['flash_msg'], $_SESSION['flash_err']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  try {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_service') {
      $nameInput = $_POST['name'] ?? null;
      $departmentInput = $_POST['department'] ?? null;
      if (!is_string($nameInput) || !is_string($departmentInput)) {
        throw new InvalidArgumentException('Enter a service name and choose a department.');
      }
      $name = trim($nameInput);
      $department = $departmentInput;
      if ($name === '' || strlen($name) > 150 || !in_array($department, ['doctor', 'lab', 'pharmacy'], true)) {
        throw new InvalidArgumentException('Enter a service name and a valid department.');
      }
      $priceCents = billing_amount_cents($_POST['price'] ?? '');
      $existing = $pdo->prepare('SELECT price FROM service_catalog WHERE name=? AND department=?');
      $existing->execute([$name, $department]);
      $oldPrice = $existing->fetchColumn();
      $pdo->beginTransaction();
      $save = $pdo->prepare('INSERT INTO service_catalog (name, department, price, created_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE price=VALUES(price), active=1');
      $save->execute([$name, $department, format_billing_cents($priceCents), current_user()['id']]);
      audit_log($pdo, null, 'service_charge_set', 'price', $oldPrice === false ? null : (string)$oldPrice, $department . ': ' . $name . ' = UGX ' . format_billing_cents($priceCents));
      $pdo->commit();
      $_SESSION['flash_msg'] = 'Service charge saved. Updated rates apply to new services; existing visit charges are unchanged.';
    } elseif ($action === 'toggle_service') {
      $serviceId = billing_positive_integer($_POST['service_id'] ?? null, 'service');
      $serviceQuery = $pdo->prepare('SELECT name, department, active FROM service_catalog WHERE id=?');
      $serviceQuery->execute([$serviceId]);
      $service = $serviceQuery->fetch();
      if (!$service) {
        throw new RuntimeException('Service not found; refresh the price list and try again.');
      }
      $pdo->beginTransaction();
      $toggle = $pdo->prepare('UPDATE service_catalog SET active=IF(active=1,0,1) WHERE id=?');
      $toggle->execute([$serviceId]);
      audit_log($pdo, null, 'service_charge_toggle', 'active', (string)$service['active'], $service['department'] . ': ' . $service['name'] . ' active=' . ($service['active'] ? '0' : '1'));
      $pdo->commit();
      $_SESSION['flash_msg'] = 'Service availability updated.';
    } else {
      throw new InvalidArgumentException('Unknown service-charge action.');
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $_SESSION['flash_err'] = $e->getMessage();
  }
  header('Location: charges.php');
  exit;
}

$services = $pdo->query('SELECT s.*, u.name AS staff_name FROM service_catalog s LEFT JOIN users u ON u.id=s.created_by ORDER BY s.department, s.name')->fetchAll();
include __DIR__ . '/../../includes/header.php';
?>
<h3><i class="bi bi-tags text-primary"></i> Service Charges</h3>
<p class="text-muted">Set the price for each service. Departments add performed services to a patient's visit at the current rate; existing bill items retain their recorded price.</p>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm p-3">
      <h5>Add or update a service</h5>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_service">
        <label class="form-label" for="service-name">Service name</label>
        <input id="service-name" name="name" maxlength="150" class="form-control mb-3" required>
        <label class="form-label" for="service-department">Department</label>
        <select id="service-department" name="department" class="form-select mb-3" required>
          <option value="">Select department</option>
          <option value="doctor">Doctor</option>
          <option value="lab">Lab</option>
          <option value="pharmacy">Pharmacy</option>
        </select>
        <label class="form-label" for="service-price">Price (UGX)</label>
        <input id="service-price" name="price" type="number" min="0.01" max="9999999999.99" step="0.01" class="form-control mb-3" required>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Save Charge</button>
      </form>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm p-3">
      <h5>Department price list</h5>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Service</th><th>Department</th><th>Price</th><th>Available</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($services as $service): ?>
            <tr>
              <td><?= e($service['name']) ?></td>
              <td><?= e(ucfirst($service['department'])) ?></td>
              <td>UGX <?= number_format((float)$service['price'], 2) ?></td>
              <td><span class="badge bg-<?= $service['active'] ? 'success' : 'secondary' ?>"><?= $service['active'] ? 'Active' : 'Inactive' ?></span></td>
              <td>
                <form method="post">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="toggle_service">
                  <input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>">
                  <button class="btn btn-sm btn-outline-secondary"><?= $service['active'] ? 'Deactivate' : 'Reactivate' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$services): ?><tr><td colspan="5" class="text-muted">No service prices have been set yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <a class="btn btn-outline-primary align-self-start" href="payments.php"><i class="bi bi-cash-coin"></i> Outpatient Payments</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
