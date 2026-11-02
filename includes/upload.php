<?php
// Secure patient-photo upload helper.
// Photos are stored OUTSIDE the web-served path under /uploads/patients/
// and are accessed only through pages/patient/photo.php which checks auth.

function uploads_dir(): string {
    $dir = realpath(__DIR__ . '/..') . '/uploads/patients';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir;
}

/**
 * Validate + save uploaded photo. Returns [filename, mime] or null on no upload.
 * Throws Exception on invalid file.
 */
function handle_photo_upload(string $field, int $patient_id): ?array {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error');
    if ($f['size'] > 4 * 1024 * 1024) throw new Exception('Photo must be ≤ 4 MB');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($f['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) throw new Exception('Only JPG, PNG or WebP allowed');
    $ext = $allowed[$mime];
    $name = 'p' . $patient_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = uploads_dir() . '/' . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) throw new Exception('Could not save photo');
    @chmod($dest, 0640);
    return [$name, $mime];
}

function delete_patient_photo(?string $name): void {
    if (!$name) return;
    $p = uploads_dir() . '/' . basename($name);
    if (is_file($p)) @unlink($p);
}
