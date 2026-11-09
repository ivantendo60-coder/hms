(function(){
  const form = document.getElementById('color-theme-form');
  const selector = document.getElementById('color-theme');
  if (!form || !selector) return;

  selector.addEventListener('change', () => {
    document.documentElement.dataset.theme = selector.value;
    form.submit();
  });
})();

(function(){
  const forms = Array.from(document.querySelectorAll('form[data-rfid-search]'));
  if (!forms.length) return;

  const endpoint = forms[0].dataset.rfidSearchEndpoint;
  if (!endpoint) return;

  const updateStatus = (form, message) => {
    const status = form.querySelector('[data-rfid-search-status]');
    if (status) status.textContent = message;
  };
  let lastScanId = Number(forms[0].dataset.rfidSearchAfter || 0);

  const poll = async () => {
    try {
      const response = await fetch(endpoint, {cache: 'no-store'});
      if (!response.ok) throw new Error('HTTP ' + response.status);

      const scan = await response.json();
      if (scan.id !== null && Number(scan.id) > lastScanId) {
        lastScanId = scan.id;
        if (scan.uid) {
          forms.forEach((form) => {
            if (scan.known && form.dataset.rfidSearchRedirect) {
              const patientUrl = new URL(form.dataset.rfidSearchRedirect, window.location.href);
              patientUrl.searchParams.set('uid', scan.uid);
              window.location.assign(patientUrl.toString());
              return;
            }

            const field = form.elements.namedItem(form.dataset.rfidSearch);
            if (!field || !('value' in field)) return;

            field.value = scan.uid;
            Array.from(form.elements).forEach((control) => {
              if (control !== field && control.type !== 'hidden' && control.type !== 'submit' && control.type !== 'button') {
                control.value = '';
              }
            });
            updateStatus(form, 'Patient card scanned. Searching for patient information…');
            form.requestSubmit();
          });
        }
      }
      forms.forEach((form) => updateStatus(form, 'Ready to scan a patient card to retrieve patient information.'));
    } catch (error) {
      forms.forEach((form) => updateStatus(form, 'Unable to check for card scans. Check the reader connection and try again.'));
    } finally {
      window.setTimeout(poll, 1200);
    }
  };

  poll();
})();

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.passwordToggle);
    if (!input) return;

    const showPassword = input.type === 'password';
    input.type = showPassword ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(showPassword));
    button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
    const icon = button.querySelector('i');
    if (icon) {
      icon.classList.toggle('bi-eye', !showPassword);
      icon.classList.toggle('bi-eye-slash', showPassword);
    }
  });
});

// Live RFID polling for scan page
(function(){
  const el = document.getElementById('rfid-listener');
  if (!el) return;

  const status = document.getElementById('scan-status');
  const deviceStatus = document.getElementById('device-status');
  const bridgeMessage = document.getElementById('rfid-bridge-message');
  const checkBtn = document.getElementById('rfid-check-btn');
  const startBtn = document.getElementById('rfid-start-btn');
  const portInput = document.getElementById('rfid-port');
  let lastId = null;
  let initialized = false;

  const updateDeviceStatus = (state, text) => {
    if (!deviceStatus) return;
    deviceStatus.textContent = text;
    deviceStatus.className = 'ms-3 badge rounded-pill';

    if (state === 'online') {
      deviceStatus.classList.add('bg-success-subtle', 'text-success-emphasis');
    } else if (state === 'offline') {
      deviceStatus.classList.add('bg-danger-subtle', 'text-danger-emphasis');
    } else {
      deviceStatus.classList.add('bg-secondary-subtle', 'text-secondary-emphasis');
    }
  };

  const setBridgeMessage = (text, tone = 'muted') => {
    if (!bridgeMessage) return;
    bridgeMessage.textContent = text;
    bridgeMessage.className = 'small mb-3';
    if (tone === 'success') {
      bridgeMessage.classList.add('text-success-emphasis');
    } else if (tone === 'danger') {
      bridgeMessage.classList.add('text-danger-emphasis');
    } else {
      bridgeMessage.classList.add('text-muted');
    }
  };

  const getBridgeUrl = (action) => {
    const base = el.dataset.bridgeEndpoint || '/api/rfid_bridge.php';
    const params = new URLSearchParams({action});
    if (portInput && portInput.value.trim()) {
      params.set('port', portInput.value.trim());
    }
    return base + '?' + params.toString();
  };

  const checkBridge = async () => {
    setBridgeMessage('Checking the Arduino bridge…');
    try {
      const r = await fetch(getBridgeUrl('check'), {cache:'no-store'});
      const data = await r.json();
      if (data.ok) {
        setBridgeMessage(data.message || 'The bridge is responding.', 'success');
        if (data.device_connected) {
          updateDeviceStatus('online', 'Device connected');
        } else if (data.device_detected) {
          updateDeviceStatus('idle', 'Reader port detected');
        } else {
          updateDeviceStatus('idle', 'Waiting for scanner');
        }
      } else {
        setBridgeMessage(data.message || 'The bridge is not responding.', 'danger');
        updateDeviceStatus('offline', 'Device offline');
      }
    } catch (e) {
      setBridgeMessage('Unable to reach the bridge endpoint.', 'danger');
      updateDeviceStatus('offline', 'Device offline');
    }
  };

  const startBridge = async () => {
    setBridgeMessage('Connecting the board…');
    try {
      const r = await fetch(getBridgeUrl('start'), {cache:'no-store'});
      const data = await r.json();
      if (data.ok) {
        setBridgeMessage(data.message || 'Bridge start command sent.', 'success');
      } else {
        setBridgeMessage(data.message || 'Unable to start the bridge.', 'danger');
      }
    } catch (e) {
      setBridgeMessage('Unable to start the bridge.', 'danger');
    }
  };

  const poll = async () => {
    try {
      const r = await fetch(el.dataset.endpoint, {cache:'no-store'});
      if (!r.ok) throw new Error('HTTP ' + r.status);

      const d = await r.json();
      updateDeviceStatus(d.device_connected ? 'online' : 'idle', d.device_connected ? 'Device connected' : 'Waiting for scanner');

      if (d.id !== null && d.id !== lastId) {
        lastId = d.id;
        if (!initialized) {
          initialized = true;
          status.textContent = 'Waiting for new scan…';
          return;
        }

        if (d.uid) {
          const known = !!d.known;
          status.textContent = (known ? 'Patient card detected: ' : 'Unknown card — review required: ') + d.uid;
          const base = known ? el.dataset.redirectKnown : (el.dataset.redirectUnknown || el.dataset.redirectKnown);
          window.location = base + '?uid=' + encodeURIComponent(d.uid);
        }
      } else if (!initialized) {
        initialized = true;
        status.textContent = 'Waiting for new scan…';
      }
    } catch (e) {
      updateDeviceStatus('offline', 'Device offline');
    }
  };

  checkBtn && checkBtn.addEventListener('click', checkBridge);
  startBtn && startBtn.addEventListener('click', startBridge);

  poll();
  checkBridge();
  setInterval(poll, 1500);
})();
