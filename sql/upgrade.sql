-- Run this AFTER importing hms.sql to add new feature tables.
USE hms_db;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NULL,
  user_id INT NULL,
  user_role VARCHAR(30),
  action VARCHAR(50) NOT NULL,
  field_name VARCHAR(60),
  old_value TEXT,
  new_value TEXT,
  ip VARCHAR(60),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(patient_id), INDEX(user_id), INDEX(created_at),
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS pending_rfid (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rfid_uid VARCHAR(64) NOT NULL,
  station VARCHAR(50),
  reviewed TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(rfid_uid)
);

-- photo column already exists in patients; ensure size column for thumbnails
ALTER TABLE patients ADD COLUMN IF NOT EXISTS photo_mime VARCHAR(50) NULL;
ALTER TABLE visits ADD COLUMN IF NOT EXISTS payment_confirmed TINYINT(1) DEFAULT 0;
ALTER TABLE visits ADD COLUMN IF NOT EXISTS patient_type ENUM('outpatient','inpatient') NOT NULL DEFAULT 'outpatient';
ALTER TABLE visits ADD COLUMN IF NOT EXISTS payment_confirmed_by INT NULL;
ALTER TABLE visits ADD COLUMN IF NOT EXISTS payment_confirmed_at TIMESTAMP NULL;
ALTER TABLE visits ADD COLUMN IF NOT EXISTS pharmacy_cleared TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE visits ADD COLUMN IF NOT EXISTS pharmacy_cleared_by INT NULL;
ALTER TABLE visits ADD COLUMN IF NOT EXISTS pharmacy_cleared_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS color_theme VARCHAR(20) NOT NULL DEFAULT 'blue';
ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS service_catalog (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  department ENUM('doctor','lab','pharmacy') NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_service_department_name (department, name),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS visit_services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NOT NULL,
  service_id INT NULL,
  service_name VARCHAR(150) NOT NULL,
  department VARCHAR(50) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  recorded_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_visit_services_visit (visit_id),
  FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES service_catalog(id) ON DELETE SET NULL,
  FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS visit_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  method ENUM('cash','mobile_money','card','bank_transfer') NOT NULL,
  reference VARCHAR(100) NULL,
  collection_point ENUM('reception','pharmacy') NOT NULL,
  collected_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_visit_payments_visit (visit_id),
  INDEX idx_visit_payments_collection (collection_point, created_at),
  FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  FOREIGN KEY (collected_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  email_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_window (email_hash, ip_hash, attempted_at)
);

UPDATE users
SET password='$2y$10$Gu7eVteXVecz028WUEjvu.1VlsRcT/rHzoGxA5efwIoYSuY/Rl6ou',
    must_change_password=1
WHERE password IS NULL OR password='';

UPDATE users SET must_change_password=1;

CREATE TABLE IF NOT EXISTS staff_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL,
  reference VARCHAR(50) UNIQUE,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency VARCHAR(10) NOT NULL DEFAULT 'UGX',
  provider VARCHAR(20) NOT NULL,
  provider_account VARCHAR(100) NOT NULL,
  initiated_by INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'queued',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payout_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_name VARCHAR(150) NOT NULL,
  provider VARCHAR(20) NOT NULL,
  account_number VARCHAR(100) NOT NULL,
  phone_number VARCHAR(30) NULL,
  bank_name VARCHAR(150) NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'UGX',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
