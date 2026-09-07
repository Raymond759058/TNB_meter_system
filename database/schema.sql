-- Meter Watch — TNB Electricity Meter Monitoring
-- Database schema + demo seed data
-- Import with: mysql -u root -p meter_watch < schema.sql

CREATE DATABASE IF NOT EXISTS meter_watch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE meter_watch;

-- ---------------------------------------------------------------------
-- Users (Admin + Field User accounts)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  whatsapp_number VARCHAR(30) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Meters (buildings / TNB meter points)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS meters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  location VARCHAR(150) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Which meters a given user is allowed to submit readings for
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_meters (
  user_id INT NOT NULL,
  meter_id INT NOT NULL,
  PRIMARY KEY (user_id, meter_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Daily meter readings
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS readings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  meter_id INT NOT NULL,
  user_id INT NOT NULL,
  reading_value DECIMAL(12,2) NOT NULL,
  photo_path VARCHAR(255) NOT NULL,
  reading_date DATE NOT NULL,               -- Malaysia (Asia/Kuala_Lumpur) date
  reading_time TIME NOT NULL,               -- Malaysia (Asia/Kuala_Lumpur) time
  app_version ENUM('v1','v2') NOT NULL DEFAULT 'v1',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_meter_date (meter_id, reading_date),
  FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Alerts raised (over-usage / missing reading), and simulated WhatsApp log
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('overuse','missing') NOT NULL,
  meter_id INT NOT NULL,
  alert_date DATE NOT NULL,
  message TEXT NOT NULL,
  whatsapp_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_alert (type, meter_id, alert_date),
  FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Single-row system settings
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  id INT PRIMARY KEY DEFAULT 1,
  daily_usage_limit DECIMAL(10,2) NOT NULL DEFAULT 80.00,
  submission_deadline TIME NOT NULL DEFAULT '20:00:00',
  admin_whatsapp_number VARCHAR(30) DEFAULT '+60123456789'
) ENGINE=InnoDB;

INSERT INTO settings (id, daily_usage_limit, submission_deadline, admin_whatsapp_number)
VALUES (1, 80.00, '20:00:00', '+60123456789')
ON DUPLICATE KEY UPDATE id = id;

-- ---------------------------------------------------------------------
-- Demo seed data
-- Default password for BOTH demo accounts: password123
-- (bcrypt hash below — change these accounts before real use)
-- ---------------------------------------------------------------------
INSERT INTO users (name, username, password_hash, role) VALUES
  ('System Admin', 'admin', '$2b$10$86XK.Hurlpd.sDWZW/ZhDOjTGYCpEnu3d0obdLbicNfbWbDnvUKqG', 'admin'),
  ('Farah Aiman',  'user1', '$2b$10$86XK.Hurlpd.sDWZW/ZhDOjTGYCpEnu3d0obdLbicNfbWbDnvUKqG', 'user')
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO meters (name, location) VALUES
  ('Block A — Main Meter', 'Ground Floor, Block A'),
  ('Block B — Main Meter', 'Ground Floor, Block B')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO user_meters (user_id, meter_id)
SELECT u.id, m.id FROM users u, meters m WHERE u.username = 'user1'
ON DUPLICATE KEY UPDATE user_id = user_id;
