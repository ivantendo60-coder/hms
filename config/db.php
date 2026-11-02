<?php
// Database configuration for XAMPP
$DB_HOST = getenv('HMS_DB_HOST') ?: 'localhost';
$DB_USER = getenv('HMS_DB_USER') ?: 'root';
$DB_PASS = getenv('HMS_DB_PASS') ?: '';
$DB_NAME = getenv('HMS_DB_NAME') ?: 'hms_db';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Service temporarily unavailable.');
}
