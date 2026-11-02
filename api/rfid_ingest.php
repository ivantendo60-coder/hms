<?php
// Endpoint called by the Arduino/ESP module via HTTP POST when a card is scanned.
// Example: curl -X POST http://localhost/hms/api/rfid_ingest.php -d "uid=A1B2C3D4&station=reception"
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

function normalize_uid($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $value = preg_replace('/[^A-Fa-f0-9]/', '', $value);
    return strtoupper($value);
}

function extract_payload() {
    $payload = [];
    foreach (['uid', 'UID', 'rfid_uid', 'rfid'] as $key) {
        if (isset($_REQUEST[$key])) {
            $payload[$key] = $_REQUEST[$key];
        }
    }

    if (isset($_REQUEST['station'])) {
        $payload['station'] = $_REQUEST['station'];
    }

    $rawBody = file_get_contents('php://input');
    if (is_string($rawBody) && $rawBody !== '') {
        $payload['raw_body'] = $rawBody;
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (strpos($contentType, 'application/json') !== false) {
            $json = json_decode($rawBody, true);
            if (is_array($json)) {
                $payload = array_merge($payload, $json);
            }
        } else {
            parse_str($rawBody, $parsed);
            if (is_array($parsed)) {
                $payload = array_merge($payload, $parsed);
            }
        }
    }

    return $payload;
}

$payload = extract_payload();
$uid = normalize_uid($payload['uid'] ?? $payload['UID'] ?? $payload['rfid_uid'] ?? $payload['rfid'] ?? '');
$station = trim((string) ($payload['station'] ?? 'unknown'));

if ($uid === '') {
    $rawBody = $payload['raw_body'] ?? '';
    if (preg_match('/UID\s*:?\s*([A-Fa-f0-9]+)/', $rawBody, $m)) {
        $uid = normalize_uid($m[1]);
    } elseif (preg_match('/([A-Fa-f0-9]{4,})/', $rawBody, $m)) {
        $uid = normalize_uid($m[1]);
    }
}

if (!$uid) {
    http_response_code(400);
    echo json_encode([
        'error' => 'uid required',
        'received' => $payload,
        'raw_body' => $payload['raw_body'] ?? '',
    ]);
    exit;
}

$pdo->prepare("INSERT INTO rfid_scans (rfid_uid, station) VALUES (?,?)")->execute([$uid, $station]);

$p = $pdo->prepare("SELECT id, full_name FROM patients WHERE rfid_uid=?");
$p->execute([$uid]);
$patient = $p->fetch();

if (!$patient) {
    // Queue for receptionist confirmation
    $pdo->prepare("INSERT INTO pending_rfid (rfid_uid, station) VALUES (?,?)")->execute([$uid, $station]);
}

echo json_encode([
    'ok'      => true,
    'uid'     => $uid,
    'known'   => (bool)$patient,
    'patient' => $patient,
]);
