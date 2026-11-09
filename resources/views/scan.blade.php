<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? 'RFID Scan' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #152033;
            --muted: #6c7a93;
            --accent: #2563eb;
            --accent-soft: rgba(37, 99, 235, 0.12);
        }
        body {
            background: linear-gradient(135deg, #f8fbff 0%, var(--bg) 100%);
            color: var(--text);
            font-family: Inter, Segoe UI, Roboto, sans-serif;
        }
        .hero-card {
            border: 0;
            border-radius: 24px;
            background: var(--card);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        }
        .hero-image {
            max-height: 240px;
            object-fit: cover;
            border-radius: 18px;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.25);
        }
        .scan-pulse {
            width: 10px;
            height: 10px;
            display: inline-block;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 0 rgba(34, 197, 94, .6);
            animation: pulse 1.6s infinite;
            vertical-align: middle;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, .6); }
            70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }
        .status-badge {
            min-width: 132px;
            justify-content: center;
        }
        .glass {
            background: linear-gradient(145deg, rgba(255,255,255,0.95), rgba(244,247,251,0.95));
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 18px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-7">
                <div class="card hero-card">
                    <div class="card-body text-center p-4 p-md-5">
                        <img src="{{ asset('assets/img/rfid.jpg') }}" alt="RFID scanner" class="hero-image img-fluid mb-4" loading="lazy">
                        <h2 class="fw-semibold mb-2">Tap Patient Card on Scanner</h2>
                        <p class="text-muted mb-4">The reader will detect the card and open the patient record instantly.</p>

                        <div class="d-flex justify-content-center align-items-center gap-2 flex-wrap mb-4">
                            <span class="scan-pulse"></span>
                            <span id="scan-status" class="fw-medium">Listening for card…</span>
                            <span id="device-status" class="status-badge badge rounded-pill bg-secondary-subtle text-secondary-emphasis">Checking device…</span>
                        </div>

                        <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                            <input id="rfid-port" class="form-control shadow-sm" style="max-width: 220px;" placeholder="COM3 or /dev/ttyUSB0">
                            <button id="rfid-check-btn" class="btn btn-outline-primary px-3" type="button">Check reader</button>
                            <button id="rfid-start-btn" class="btn btn-primary px-3" type="button">Connect board</button>
                        </div>
                        <div class="small text-muted mb-3" id="rfid-bridge-message">The bridge will report the reader connection status here.</div>

                        <div id="rfid-listener"
                             data-endpoint="{{ url('/api/rfid_last.php') }}"
                             data-bridge-endpoint="{{ url('/api/rfid_bridge.php') }}"
                             data-redirect-known="{{ $knownRedirect ?? url('/pages/patient/view.php') }}"
                             data-redirect-unknown="{{ $unknownRedirect ?? url('/pages/receptionist/confirm_rfid.php') }}"></div>

                        <form method="get" action="{{ $knownRedirect ?? url('/pages/patient/view.php') }}" class="d-flex flex-wrap justify-content-center gap-2 mt-4">
                            <input name="uid" class="form-control shadow-sm" style="max-width: 320px;" placeholder="Or enter card number manually">
                            <button class="btn btn-primary px-4"><i class="bi bi-search"></i> Open</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="glass p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-info-circle-fill text-primary fs-5"></i>
                        <h5 class="mb-0">Why this feels faster</h5>
                    </div>
                    <ol class="small text-muted mb-4">
                        <li>Polling stays lightweight and runs every 1.5 seconds.</li>
                        <li>The UI updates only the status text and badge, so the scan flow stays snappy.</li>
                        <li>Modern spacing, motion, and contrast make the scanner feel more responsive.</li>
                    </ol>
                    <div class="alert alert-light border-0 mb-0">
                        <div class="fw-semibold mb-2">Live scanner state</div>
                        <div class="small text-muted">Connected devices are shown instantly, and unknown cards continue into the confirmation flow.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
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

            const setDeviceStatus = (state, text) => {
                if (!deviceStatus) return;
                deviceStatus.textContent = text;
                deviceStatus.className = 'status-badge badge rounded-pill';
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
                const params = new URLSearchParams({ action });
                if (portInput && portInput.value.trim()) {
                    params.set('port', portInput.value.trim());
                }
                return (el.dataset.bridgeEndpoint || '/api/rfid_bridge.php') + '?' + params.toString();
            };

            const checkBridge = async () => {
                setBridgeMessage('Checking the Arduino bridge…');
                try {
                    const response = await fetch(getBridgeUrl('check'), { cache: 'no-store' });
                    const data = await response.json();
                    if (data.ok) {
                        setBridgeMessage(data.message || 'The bridge is responding.', 'success');
                        if (data.device_connected) {
                            setDeviceStatus('online', 'Device connected');
                            if (!initialized) {
                                status.textContent = 'Waiting for new scan…';
                            }
                        } else {
                            setDeviceStatus('offline', 'Device offline');
                            if (!initialized) {
                                status.textContent = 'No board connected';
                            }
                        }
                    } else {
                        setBridgeMessage(data.message || 'The bridge is not responding.', 'danger');
                        setDeviceStatus('offline', 'Device offline');
                        if (!initialized) {
                            status.textContent = 'No board connected';
                        }
                    }
                } catch (error) {
                    setBridgeMessage('Unable to reach the bridge endpoint.', 'danger');
                    setDeviceStatus('offline', 'Device offline');
                    if (!initialized) {
                        status.textContent = 'No board connected';
                    }
                }
            };

            const startBridge = async () => {
                setBridgeMessage('Connecting the board…');
                try {
                    const response = await fetch(getBridgeUrl('start'), { cache: 'no-store' });
                    const data = await response.json();
                    if (data.ok) {
                        setBridgeMessage(data.message || 'Bridge start command sent.', 'success');
                    } else {
                        setBridgeMessage(data.message || 'Unable to start the bridge.', 'danger');
                    }
                } catch (error) {
                    setBridgeMessage('Unable to start the bridge.', 'danger');
                }
            };

            const poll = async () => {
                try {
                    const response = await fetch(el.dataset.endpoint, { cache: 'no-store' });
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    const data = await response.json();
                    if (data.device_connected) {
                        setDeviceStatus('online', 'Device connected');
                    } else {
                        setDeviceStatus('offline', 'Device offline');
                    }

                    if (data.id !== null && data.id !== lastId) {
                        lastId = data.id;
                        if (!initialized) {
                            initialized = true;
                            status.textContent = 'Waiting for new scan…';
                            return;
                        }

                        if (data.uid) {
                            const known = !!data.known;
                            status.textContent = (known ? 'Patient card detected: ' : 'Unknown card — review required: ') + data.uid;
                            const base = known ? el.dataset.redirectKnown : (el.dataset.redirectUnknown || el.dataset.redirectKnown);
                            window.location.assign(base + '?uid=' + encodeURIComponent(data.uid));
                        }
                    } else if (!initialized) {
                        initialized = true;
                        status.textContent = 'Waiting for new scan…';
                    }
                } catch (error) {
                    setDeviceStatus('offline', 'Device offline');
                }
            };

            checkBtn && checkBtn.addEventListener('click', checkBridge);
            startBtn && startBtn.addEventListener('click', startBridge);

            poll();
            checkBridge();
            window.setInterval(poll, 1500);
        });
    </script>
</body>
</html>
