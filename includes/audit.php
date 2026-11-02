<?php
// Audit logging helpers — records WHO changed WHAT on a patient.
require_once __DIR__ . '/auth.php';

function audit_log(PDO $pdo, ?int $patient_id, string $action, ?string $field = null, $old = null, $new = null): void {
    $u = current_user();
    $stmt = $pdo->prepare("INSERT INTO audit_logs (patient_id,user_id,user_role,action,field_name,old_value,new_value,ip) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $patient_id,
        $u['id'] ?? null,
        $u['role'] ?? null,
        $action,
        $field,
        is_scalar($old) ? (string)$old : json_encode($old),
        is_scalar($new) ? (string)$new : json_encode($new),
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/**
 * Diff old vs new patient row and log each changed field.
 * @param array $tracked  field names to track
 */
function audit_diff(PDO $pdo, int $patient_id, array $old, array $new, array $tracked): void {
    foreach ($tracked as $f) {
        $o = $old[$f] ?? null; $n = $new[$f] ?? null;
        if ((string)$o !== (string)$n) {
            audit_log($pdo, $patient_id, 'update', $f, $o, $n);
        }
    }
}

function fetch_audit(PDO $pdo, int $patient_id, int $limit = 100): array {
    $s = $pdo->prepare("SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE patient_id=? ORDER BY a.id DESC LIMIT $limit");
    $s->execute([$patient_id]);
    return $s->fetchAll();
}
