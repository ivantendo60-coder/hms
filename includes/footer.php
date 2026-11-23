</main></div>
<footer class="site-footer text-center text-muted py-3 small border-top">
  <div class="site-footer-brand">
    <span>Powered by</span>
    <img src="<?= base_url('assets/img/irium-technologies.png') ?>" alt="Irium Technologies">
  </div>
  <div class="site-footer-copyright">© <?= date('Y') ?> myPatient HMS</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js?v=' . filemtime(__DIR__ . '/../assets/js/app.js')) ?>"></script>
<script>
if ('serviceWorker' in navigator) {
  const manifestLink = document.querySelector('link[rel="manifest"]');
  if (manifestLink) {
    const serviceWorkerUrl = new URL('service-worker.js', manifestLink.href);
    const serviceWorkerScope = new URL('./', serviceWorkerUrl).pathname;
    navigator.serviceWorker.register(serviceWorkerUrl.href, {scope: serviceWorkerScope})
      .catch((error) => console.error('Unable to register the myPatient HMS service worker:', error));
  }
}
</script>
</body></html>
