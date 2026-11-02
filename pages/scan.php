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
          <input name="uid" class="form-control w-50" placeholder="Or enter RFID UID manually">
          <button class="btn btn-primary"><i class="bi bi-search"></i> Open</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h5><i class="bi bi-info-circle text-primary"></i> How it works</h5>
        <ol class="small">
          <li>Arduino (UNO/ESP8266) + MFRC522 RFID module reads the card UID.</li>
          <li>It POSTs the UID to <code>/api/rfid_ingest.php</code>.</li>
          <li>This page polls and auto-opens the patient's profile.</li>
          <li>If the card is unknown, receptionists are taken to a confirmation screen before registering.</li>
        </ol>
        <p class="small text-muted">Wiring &amp; sketch are in the <code>/arduino</code> folder.</p>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
