<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>myPatient HMS</title>
  <link rel="icon" href="{{ asset('assets/img/logo.png') }}">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
      <img src="{{ asset('assets/img/logo.png') }}" width="44" height="44" alt="">
      <strong>myPatient HMS</strong>
    </a>
  </div>
</nav>
<main class="public-main">
  <div class="landing-page">
    <section class="landing-hero">
      <div class="landing-copy">
        <span class="landing-eyebrow"><i class="bi bi-shield-check"></i> Secure tools for hospital teams</span>
        <h1>Better care starts with a <span>clearer picture.</span></h1>
        <p class="landing-lead">Find the right patient information, coordinate each step of a visit, and help your team stay focused on care.</p>
        <div class="landing-actions">
          <a href="{{ rtrim(config('app.legacy_url'), '/') }}/pages/auth/login.php" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right"></i> Staff Login</a>
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
        @foreach ([
          ['rfid.jpg', 'bi-person-vcard', 'Patient cards', 'Look up patient records quickly with a card scan.'],
          ['doc1.webp', 'bi-heart-pulse', 'Doctors', 'Review patient history and coordinate care.'],
          ['recep1.webp', 'bi-clipboard2-check', 'Reception', 'Register patients and keep visits moving.'],
          ['lab1.webp', 'bi-eyedropper', 'Laboratory', 'Record test results as part of each visit.'],
          ['pham3.avif', 'bi-capsule', 'Pharmacy', 'Manage prescriptions and bill clearance.'],
        ] as $index => [$image, $icon, $title, $description])
          <article class="landing-service-card" role="listitem" style="--card-order: {{ $index }}">
            <img class="landing-service-image" src="{{ asset('assets/img/' . $image) }}" alt="" loading="lazy">
            <div class="landing-service-content">
              <span class="landing-service-icon"><i class="bi {{ $icon }}"></i></span>
              <h3>{{ $title }}</h3>
              <p>{{ $description }}</p>
            </div>
            <span class="landing-service-accent"></span>
          </article>
        @endforeach
      </div>
    </section>

    <section class="landing-bottom">
      <span class="landing-bottom-icon"><i class="bi bi-shield-lock"></i></span>
      <div>
        <h2>Your team. Your patients. One secure place.</h2>
        <p>Patient information is available only to authorized hospital staff.</p>
      </div>
      <a href="{{ rtrim(config('app.legacy_url'), '/') }}/pages/auth/login.php" class="btn btn-outline-primary">Continue to staff login <i class="bi bi-arrow-right"></i></a>
    </section>
  </div>
</main>
<footer class="text-center text-muted py-3 small border-top">© {{ date('Y') }} myPatient HMS</footer>
</body>
</html>
