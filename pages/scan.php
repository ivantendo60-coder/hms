<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$page_title='RFID Scan';
include __DIR__ . '/../includes/header.php';
$role = current_user()['role'];
$known_redirect   = $role==='receptionist' ? base_url('pages/receptionist/patient_view.php') : base_url('pages/patient/view.php');
$unknown_redirect = $role==='receptionist' ? base_url('pages/receptionist/confirm_rfid.php') : base_url('pages/patient/view.php');
?>
<div class="row">
  <div class="col-md-7">
    <div class="card border-0 shadow-sm">
      <div class="card-body text-center p-5">
        <img src="<?= base_url('assets/img/rfid.jpg') ?>" class="img-fluid rounded mb-3" style="max-height:260px">
        <h3>Tap Patient Card on Scanner</h3>
        <p class="text-muted">The Arduino RFID reader will detect the card and load the patient automatically.</p>
        <div class="my-3">
          <span class="scan-pulse"></span>
          <span id="scan-status">Listening for card…</span>
          <span id="device-status" class="ms-3 badge rounded-pill bg-secondary-subtle text-secondary-emphasis">Checking device…</span>
        </div>
        <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
          <input id="rfid-port" class="form-control form-control-sm" style="max-width:220px" placeholder="COM3 or /dev/ttyUSB0">
          <button id="rfid-check-btn" class="btn btn-outline-primary btn-sm" type="button">Check reader</button>
          <button id="rfid-start-btn" class="btn btn-primary btn-sm" type="button">Connect board</button>
        </div>
        <div class="small text-muted mb-3" id="rfid-bridge-message">Reader status updates as scans arrive; port checks do not interrupt an active scan.</div>
        <div id="rfid-listener"
             data-endpoint="<?= base_url('api/rfid_last.php') ?>"
             data-bridge-endpoint="<?= base_url('api/rfid_bridge.php') ?>"
             data-redirect-known="<?= $known_redirect ?>"
             data-redirect-unknown="<?= $unknown_redirect ?>"></div>
        <hr>
        <form method="get" action="<?= $known_redirect ?>" class="d-flex gap-2 justify-content-center">
          <input name="uid" class="form-control w-50" placeholder="Or enter card number manually">
          <button class="btn btn-primary"><i class="bi bi-search"></i> Open</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <aside class="rfid-scan-showcase" aria-label="Contactless patient card scanning">
      <div class="rfid-scan-stage" aria-hidden="true">
        <span class="rfid-scan-orbit rfid-scan-orbit-one"></span>
        <span class="rfid-scan-orbit rfid-scan-orbit-two"></span>
        <div class="rfid-scan-reader">
          <span class="rfid-scan-reader-light"></span>
          <i class="bi bi-wifi"></i>
        </div>
        <div class="rfid-scan-card">
          <span class="rfid-scan-card-mark"><i class="bi bi-plus-lg"></i></span>
          <span class="rfid-scan-card-lines"><i></i><i></i><i></i></span>
          <span class="rfid-scan-card-chip"></span>
        </div>
        <span class="rfid-scan-beam"></span>
      </div>
      <div class="rfid-scan-caption">
        <span class="rfid-scan-caption-icon"><i class="bi bi-person-vcard"></i></span>
        <div>
          <h4>Contactless patient lookup</h4>
          <p>A simple card tap connects your team to the right patient record.</p>
        </div>
      </div>
    </aside>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
