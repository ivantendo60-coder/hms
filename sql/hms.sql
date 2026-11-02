-- Hospital Management System DB
CREATE DATABASE IF NOT EXISTS hms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hms_db;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('doctor','receptionist','lab','pharmacy','admin') NOT NULL,
  phone VARCHAR(30),
  photo VARCHAR(255),
  color_theme VARCHAR(20) NOT NULL DEFAULT 'blue',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  email_hash CHAR(64) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempts_window (email_hash, ip_hash, attempted_at)
);

CREATE TABLE IF NOT EXISTS patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rfid_uid VARCHAR(64) UNIQUE NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  dob DATE,
  gender ENUM('Male','Female','Other'),
  phone VARCHAR(30),
  email VARCHAR(150),
  address TEXT,
  blood_group VARCHAR(5),
  emergency_contact VARCHAR(100),
  photo VARCHAR(255),
  registered_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (registered_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS visits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  visit_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  reason VARCHAR(255),
  status ENUM('open','closed') DEFAULT 'open',
  patient_type ENUM('outpatient','inpatient') NOT NULL DEFAULT 'outpatient',
  payment_confirmed TINYINT(1) DEFAULT 0,
  payment_confirmed_by INT NULL,
  payment_confirmed_at TIMESTAMP NULL,
  pharmacy_cleared TINYINT(1) NOT NULL DEFAULT 0,
  pharmacy_cleared_by INT NULL,
  pharmacy_cleared_at TIMESTAMP NULL,
  created_by INT,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS diagnoses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NOT NULL,
  doctor_id INT,
  symptoms TEXT,
  diagnosis TEXT,
  treatment TEXT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS lab_tests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NOT NULL,
  lab_tech_id INT,
  test_name VARCHAR(150),
  result TEXT,
  remarks TEXT,
  status ENUM('pending','completed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  FOREIGN KEY (lab_tech_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS prescriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NOT NULL,
  pharmacist_id INT,
  medicine VARCHAR(200),
  dosage VARCHAR(100),
  duration VARCHAR(100),
  instructions TEXT,
  dispensed TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  FOREIGN KEY (pharmacist_id) REFERENCES users(id) ON DELETE SET NULL
);

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

CREATE TABLE IF NOT EXISTS rfid_scans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rfid_uid VARCHAR(64) NOT NULL,
  scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  station VARCHAR(50)
);

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

-- Default users (password = "password" for all)
INSERT INTO users (name,email,password,role,must_change_password) VALUES
('Admin','admin@hms.local','$2y$10$Gu7eVteXVecz028WUEjvu.1VlsRcT/rHzoGxA5efwIoYSuY/Rl6ou','admin',1),
('Dr. John Smith','doctor@hms.local','$2y$10$Gu7eVteXVecz028WUEjvu.1VlsRcT/rHzoGxA5efwIoYSuY/Rl6ou','doctor',1),
('Sarah Reception','reception@hms.local','$2y$10$Gu7eVteXVecz028WUEjvu.1VlsRcT/rHzoGxA5efwIoYSuY/Rl6ou','receptionist',1),
('Mike Lab','lab@hms.local','$2y$10$Gu7eVteXVecz028WUEjvu.1VlsRcT/rHzoGxA5efwIoYSuY/Rl6ou','lab',1),
('Anna Pharma','pharmacy@hms.local','$2y$10$Gu7eVteXVecz028WUEjvu.1VlsRcT/rHzoGxA5efwIoYSuY/Rl6ou','pharmacy',1);

-- Sample patient with RFID UID
INSERT INTO patients (rfid_uid, full_name, dob, gender, phone, blood_group, address, registered_by)
VALUES ('A1B2C3D4', 'Jane Doe', '1990-05-12', 'Female', '+256700000000', 'O+', 'Kampala, Uganda', 3);

-- ===== Feature upgrade tables (audit log, unknown-card queue, photo mime) =====
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

ALTER TABLE patients ADD COLUMN photo_mime VARCHAR(50) NULL;
