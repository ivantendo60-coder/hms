<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) {
    header('Location: ' . base_url('pages/'.current_user()['role'].'/dashboard.php'));
    exit;
}
$page_title = 'Welcome';
include __DIR__ . '/includes/header.php';
?>
<div class="landing-page">
  <section class="landing-hero">
    <div class="landing-copy">
      <span class="landing-eyebrow"><i class="bi bi-shield-check"></i> Secure tools for hospital teams</span>
      <h1>Better care starts with a <span>clearer picture.</span></h1>
      <p class="landing-lead">Find the right patient information, coordinate each step of a visit, and help your team stay focused on care.</p>
      <div class="landing-actions">
        <a href="<?= base_url('pages/auth/login.php') ?>" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right"></i> Staff Login</a>
        <span class="landing-security"><i class="bi bi-lock-fill"></i> For authorized hospital staff</span>
      </div>
      <div class="landing-highlights">
        <span><i class="bi bi-person-vcard"></i> Card-enabled lookup</span>
        <span><i class="bi bi-clock-history"></i> Connected visit history</span>
        <span><i class="bi bi-people"></i> Coordinated departments</span>
      </div>
    </div>
    <div class="landing-visual" aria-hidden="true">
      <div class="landing-orbit landing-orbit-one"></div>
      <div class="landing-orbit landing-orbit-two"></div>
      <div class="landing-medical-mark"><i class="bi bi-heart-pulse-fill"></i></div>
      <div class="landing-float-card landing-card-record">
        <span class="landing-float-icon"><i class="bi bi-person-vcard-fill"></i></span>
        <span><strong>Patient record</strong><small>Ready when you are</small></span>
        <i class="bi bi-check-circle-fill landing-check"></i>
      </div>
      <div class="landing-float-card landing-card-care">
        <span class="landing-care-dots"><i></i><i></i><i></i></span>
        <span><strong>Connected care</strong><small>One team, one record</small></span>
      </div>
      <span class="landing-spark landing-spark-one"><i class="bi bi-plus-lg"></i></span>
      <span class="landing-spark landing-spark-two"><i class="bi bi-plus-lg"></i></span>
    </div>
  </section>

  <section class="landing-services" aria-labelledby="landing-services-title">
    <div class="landing-section-heading">
      <div>
        <span class="landing-section-kicker">One connected system</span>
        <h2 id="landing-services-title">Support for every step of care</h2>
      </div>
    </div>
    <p class="landing-services-intro">Explore the tools that support each department.</p>
    <div class="landing-service-grid" role="list" aria-label="Hospital departments">
      <?php $services = [
        ['rfid.jpg', 'bi-person-vcard', 'Patient cards', 'Look up patient records quickly with a card scan.'],
        ['doc1.webp', 'bi-heart-pulse', 'Doctors', 'Review patient history and coordinate care.'],
        ['recep1.webp', 'bi-clipboard2-check', 'Reception', 'Register patients and keep visits moving.'],
        ['lab1.webp', 'bi-eyedropper', 'Laboratory', 'Record test results as part of each visit.'],
        ['pham3.avif', 'bi-capsule', 'Pharmacy', 'Manage prescriptions and bill clearance.'],
      ]; foreach ($services as $index => $service): ?>
        <article class="landing-service-card" role="listitem" style="--card-order: <?= (int)$index ?>">
          <img class="landing-service-image" src="<?= base_url('assets/img/'.$service[0]) ?>" alt="" loading="lazy">
          <div class="landing-service-content">
            <span class="landing-service-icon"><i class="bi <?= e($service[1]) ?>"></i></span>
            <h3><?= e($service[2]) ?></h3>
            <p><?= e($service[3]) ?></p>
          </div>
          <span class="landing-service-accent"></span>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="landing-bottom">
    <span class="landing-bottom-icon"><i class="bi bi-shield-lock"></i></span>
    <div>
      <h2>Your team. Your patients. One secure place.</h2>
      <p>Patient information is available only to authorized hospital staff.</p>
    </div>
    <a href="<?= base_url('pages/auth/login.php') ?>" class="btn btn-outline-primary">Continue to staff login <i class="bi bi-arrow-right"></i></a>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
