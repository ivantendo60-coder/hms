<?php
// Database configuration for XAMPP
$DB_HOST = getenv('HMS_DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('HMS_DB_PORT') ?: '3307';
$DB_USER = getenv('HMS_DB_USER') ?: 'xampp_hms_db';
$DB_PASS = getenv('HMS_DB_PASS') ?: 'C80BD57D43371AFF15E7A62FD8CD4C8F3957F18E07A5EFBBE38E048ACD8051F4';
$DB_NAME = getenv('HMS_DB_NAME') ?: 'hms_db';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Service temporarily unavailable.');
}
