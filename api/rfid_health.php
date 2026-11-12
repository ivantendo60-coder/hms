<?php
// Simple health check for ESP8266/ESP32 boards and other devices.
// GET or POST to /api/rfid_health.php returns JSON ready for network tests.
header('Content-Type: application/json');

echo json_encode([
    'ok' => true,
    'service' => 'myPatient HMS RFID ingest',
    'endpoint' => '/api/rfid_health.php',
    'time' => date('c'),
]);
