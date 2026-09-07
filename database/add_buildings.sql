-- Migration: Add buildings table and link meters to buildings
-- Run with: mysql -u root -p meter_watch < add_buildings.sql

USE meter_watch;

-- ---------------------------------------------------------------------
-- Buildings (groups of meters by physical building)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS buildings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  address VARCHAR(255) DEFAULT NULL,
  num_floors INT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Add building_id FK column to meters table
ALTER TABLE meters
  ADD COLUMN building_id INT DEFAULT NULL AFTER location,
  ADD CONSTRAINT fk_meters_building FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE SET NULL;

-- Seed demo buildings
INSERT INTO buildings (name, address, num_floors) VALUES
  ('Block A', 'Jalan Utama 1, Kuala Lumpur', 5),
  ('Block B', 'Jalan Utama 2, Kuala Lumpur', 3)
ON DUPLICATE KEY UPDATE name = name;

-- Assign existing demo meters to demo buildings
UPDATE meters SET building_id = (SELECT id FROM buildings WHERE name = 'Block A' LIMIT 1) WHERE name LIKE 'Block A%';
UPDATE meters SET building_id = (SELECT id FROM buildings WHERE name = 'Block B' LIMIT 1) WHERE name LIKE 'Block B%';
