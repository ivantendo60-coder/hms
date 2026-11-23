<?php

function create_patient_portal_token(PDO $pdo, int $patientId, int $createdBy, int $days = 7): array {
    $patient = $pdo->prepare('SELECT id,full_name FROM patients WHERE id=?');
    $patient->execute([$patientId]);
    $patient = $patient->fetch();
    if (!$patient) {
        throw new RuntimeException('Patient not found.');
    }

    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $expires = date('Y-m-d H:i:s', time() + max(1, $days) * 86400);
    $stmt = $pdo->prepare('INSERT INTO patient_portal_tokens
        (patient_id,token_hash,expires_at,created_by) VALUES (?,?,?,?)');
    $stmt->execute([$patientId, hash('sha256', $token), $expires, $createdBy]);
    return [
        'token' => $token,
        'expires_at' => $expires,
        'patient' => $patient,
    ];
}

function find_patient_portal(PDO $pdo, string $token): ?array {
    if (strlen($token) < 32) {
        return null;
    }
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT ppt.*,p.*
                           FROM patient_portal_tokens ppt
                           JOIN patients p ON p.id=ppt.patient_id
                           WHERE ppt.token_hash=? AND ppt.revoked_at IS NULL
                             AND ppt.expires_at > NOW()");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ?: null;
}
