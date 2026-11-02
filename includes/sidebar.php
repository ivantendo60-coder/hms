<?php $role = $u['role']; ?>
<?php require_once __DIR__ . '/theme.php'; ?>
<aside class="sidebar bg-dark text-white p-3">
  <h6 class="text-uppercase text-secondary small">Menu</h6>
  <ul class="nav flex-column gap-1">
    <li><a class="nav-link text-white" href="<?= base_url('pages/'.$role.'/dashboard.php') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
    <li><a class="nav-link text-white" href="<?= base_url('pages/scan.php') ?>"><i class="bi bi-broadcast"></i> RFID Scan</a></li>
    <?php if ($role==='admin'): ?>
      <li><a class="nav-link text-white" href="<?= base_url('pages/admin/staff.php') ?>"><i class="bi bi-people"></i> Staff Directory</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/admin/staff_edit.php') ?>"><i class="bi bi-person-plus"></i> Add Staff</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/admin/patient_history.php') ?>"><i class="bi bi-folder2-open"></i> Patient History</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/admin/payments.php') ?>"><i class="bi bi-currency-dollar"></i> Financials</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/admin/payout_accounts.php') ?>"><i class="bi bi-bank"></i> Payout Accounts</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/admin/reports.php') ?>"><i class="bi bi-graph-up"></i> Hospital Reports</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/admin/staff_activity.php') ?>"><i class="bi bi-person-lines-fill"></i> Staff Patient Activity</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/admin/passwords.php') ?>"><i class="bi bi-key"></i> Password Manager</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/admin/dashboard.php') ?>"><i class="bi bi-bar-chart-line"></i> Department Reports</a></li>
    <?php elseif ($role==='receptionist'): ?>
      <li><a class="nav-link text-white" href="<?= base_url('pages/receptionist/patients.php') ?>"><i class="bi bi-people"></i> Patients</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/receptionist/add_patient.php') ?>"><i class="bi bi-person-plus"></i> Add Patient</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/patient/search.php') ?>"><i class="bi bi-search"></i> Find Patient</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/receptionist/charges.php') ?>"><i class="bi bi-tags"></i> Service Charges</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/receptionist/payments.php') ?>"><i class="bi bi-cash-coin"></i> Outpatient Payments</a></li>
    <?php else: ?>
      <li><a class="nav-link text-white" href="<?= base_url('pages/patient/search.php') ?>"><i class="bi bi-search"></i> Find Patient</a></li>
    <?php endif; ?>
    <?php if (false): ?>
    <?php endif; ?>
    <?php if ($role==='doctor'): ?>
      <li><a class="nav-link text-white" href="<?= base_url('pages/doctor/visits.php') ?>"><i class="bi bi-clipboard2-pulse"></i> Today's Visits</a></li>
    <?php endif; ?>
    <?php if ($role==='lab'): ?>
      <li><a class="nav-link text-white" href="<?= base_url('pages/lab/queue.php') ?>"><i class="bi bi-eyedropper"></i> Lab Queue</a></li>
    <?php endif; ?>
    <?php if ($role==='pharmacy'): ?>
      <li><a class="nav-link text-white" href="<?= base_url('pages/pharmacy/queue.php') ?>"><i class="bi bi-capsule"></i> Prescriptions</a></li>
      <li><a class="nav-link text-white" href="<?= base_url('pages/pharmacy/clearance.php') ?>"><i class="bi bi-shield-check"></i> Payment Clearance</a></li>
    <?php endif; ?>
  </ul>
  <hr class="border-secondary">
  <a class="nav-link text-white mb-2" href="<?= base_url('pages/auth/change_password.php') ?>"><i class="bi bi-shield-lock"></i> Change Password</a>
  <form id="color-theme-form" method="post" action="<?= base_url('pages/settings/theme.php') ?>">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <label for="color-theme" class="form-label small text-secondary mb-1">Color theme</label>
    <div class="d-flex gap-2">
      <select id="color-theme" name="color_theme" class="form-select form-select-sm">
        <?php foreach (hms_color_themes() as $theme => $label): ?>
          <option value="<?= e($theme) ?>" <?= ($u['color_theme'] ?? 'blue') === $theme ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-outline-light" type="submit">Apply</button>
    </div>
  </form>
</aside>
