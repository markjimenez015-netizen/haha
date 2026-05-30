-- ============================================================
--  University Clinic — MySQL Database Schema
--  Import this in phpMyAdmin or run: mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS university_clinic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE university_clinic;

-- ── Users (Patients & Staff) ──────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(180) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('patient','staff') DEFAULT 'patient',
  student_id  VARCHAR(40),
  phone       VARCHAR(30),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── Doctors / Nurses ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS providers (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT DEFAULT NULL,
  name             VARCHAR(120) NOT NULL,
  role             ENUM('doctor','nurse') NOT NULL,
  specialty        VARCHAR(100),
  bio              TEXT,
  avatar_initials  VARCHAR(3),
  is_active        TINYINT(1) DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Provider Availability ─────────────────────────────────
CREATE TABLE IF NOT EXISTS availability (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  provider_id  INT NOT NULL,
  avail_date   DATE NOT NULL,
  start_time   TIME NOT NULL,
  end_time     TIME NOT NULL,
  is_booked    TINYINT(1) DEFAULT 0,
  FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  UNIQUE KEY unique_slot (provider_id, avail_date, start_time)
);

-- ── Appointments ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS appointments (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  patient_id      INT NOT NULL,
  provider_id     INT NOT NULL,
  availability_id INT NOT NULL,
  appt_date       DATE NOT NULL,
  appt_time       TIME NOT NULL,
  reason          TEXT,
  status          ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  notes           TEXT,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id)      REFERENCES users(id)         ON DELETE CASCADE,
  FOREIGN KEY (provider_id)     REFERENCES providers(id)     ON DELETE CASCADE,
  FOREIGN KEY (availability_id) REFERENCES availability(id)  ON DELETE CASCADE
);

-- ── Consultations ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS consultations (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  patient_id  INT NOT NULL,
  provider_id INT,
  subject     VARCHAR(200),
  message     TEXT,
  reply       TEXT,
  status      ENUM('open','in_progress','resolved') DEFAULT 'open',
  priority    ENUM('low','medium','high') DEFAULT 'medium',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id)  REFERENCES users(id)     ON DELETE CASCADE,
  FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL
);

-- ── Feedback ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  patient_id  INT NOT NULL,
  provider_id INT,
  rating      TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment     TEXT,
  sentiment   ENUM('positive','neutral','negative'),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id)  REFERENCES users(id)     ON DELETE CASCADE,
  FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL
);

-- ── Health Records ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS health_records (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  patient_id  INT NOT NULL,
  provider_id INT,
  visit_date  DATE,
  diagnosis   VARCHAR(255),
  prescription TEXT,
  notes       TEXT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id)  REFERENCES users(id)     ON DELETE CASCADE,
  FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL
);

-- ── Notifications ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  title       VARCHAR(200),
  body        TEXT,
  is_read     TINYINT(1) DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
--  SEED DATA
-- ============================================================

-- ── Staff Users (Doctors & Nurses) ──────────────────────
-- Passwords below:
--   Doctors  → doctor123
--   Nurses   → nurse123
--   Admin    → staff123
--   Patient  → patient123

INSERT INTO users (name, email, password, role) VALUES
('Dr. Maria Santos',  'maria.santos@clinic.edu',  '$2b$10$BFwhbBNHSYw3vG5DstgSaeh41pYzXqVoKI/QRM7yozBkkX2VGoGYu', 'staff'),
('Dr. Jose Reyes',    'jose.reyes@clinic.edu',    '$2b$10$BFwhbBNHSYw3vG5DstgSaeh41pYzXqVoKI/QRM7yozBkkX2VGoGYu', 'staff'),
('Nurse Ana Lim',     'ana.lim@clinic.edu',       '$2b$10$H5UZDqaSJfZr9uxKNBKgFuAYohW4757fyHGdPs4hkgA5TXofAp0dy', 'staff'),
('Nurse Carlo Diaz',  'carlo.diaz@clinic.edu',    '$2b$10$H5UZDqaSJfZr9uxKNBKgFuAYohW4757fyHGdPs4hkgA5TXofAp0dy', 'staff'),
('Admin Staff',       'staff@clinic.edu',         '$2b$10$gb2YbrOIYwphJxgTiqOlz.xoVAI.S2namtMopo230g9MFqBmdKZja', 'staff');

-- ── Sample Patient ────────────────────────────────────────
INSERT INTO users (name, email, password, role, student_id) VALUES
('Juan dela Cruz', 'juan@university.edu', '$2b$10$f4MOOFQCupWuajIt.Vo/kuq5wCba0XLODHM6wp8lmSkAFu8C0rYZu', 'patient', '2021-00001');

-- ── Providers (linked to staff user accounts) ────────────
INSERT INTO providers (user_id, name, role, specialty, avatar_initials) VALUES
(1, 'Dr. Maria Santos', 'doctor', 'General Medicine',  'MS'),
(2, 'Dr. Jose Reyes',   'doctor', 'Internal Medicine', 'JR'),
(3, 'Nurse Ana Lim',    'nurse',  'Clinical Nurse',    'AL'),
(4, 'Nurse Carlo Diaz', 'nurse',  'Emergency Care',    'CD');

-- ── Sample Availability (next 7 days) ────────────────────
INSERT INTO availability (provider_id, avail_date, start_time, end_time) VALUES
-- Dr. Maria Santos (provider 1)
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', '08:30:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:30:00', '09:00:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', '09:30:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', '10:30:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', '09:30:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:30:00', '10:00:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', '14:30:00'),
(1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:30:00', '15:00:00'),
-- Dr. Jose Reyes (provider 2)
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', '10:30:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:30:00', '11:00:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00', '11:30:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '13:00:00', '13:30:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '13:30:00', '14:00:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', '09:30:00'),
(2, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '10:00:00', '10:30:00'),
-- Nurse Ana Lim (provider 3)
(3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', '08:30:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:30:00', '09:00:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', '11:30:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:30:00', '12:00:00'),
(3, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '08:00:00', '08:30:00'),
-- Nurse Carlo Diaz (provider 4)
(4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '15:00:00', '15:30:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '15:30:00', '16:00:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00', '14:30:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', '09:30:00'),
(4, DATE_ADD(CURDATE(), INTERVAL 4 DAY), '10:00:00', '10:30:00');
