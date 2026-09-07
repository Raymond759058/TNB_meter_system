<?php
/**
 * Run the buildings migration via PDO (avoids MySQL CLI auth plugin issues).
 * Execute: php database/run_buildings_migration.php
 */
require_once __DIR__ . '/../config/database.php';

$pdo = get_db_connection();

echo "Running buildings migration...\n";

// 1. Create buildings table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS buildings (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(150) NOT NULL,
      address VARCHAR(255) DEFAULT NULL,
      num_floors INT DEFAULT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
");
echo "  ✓ buildings table created\n";

// 2. Add building_id column to meters (if it doesn't exist)
$cols = $pdo->query("SHOW COLUMNS FROM meters LIKE 'building_id'")->fetchAll();
if (empty($cols)) {
    $pdo->exec("ALTER TABLE meters ADD COLUMN building_id INT DEFAULT NULL");
    $pdo->exec("ALTER TABLE meters ADD CONSTRAINT fk_meters_building FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE SET NULL");
    echo "  ✓ building_id column added to meters\n";
} else {
    echo "  – building_id column already exists in meters\n";
}

// 3. Seed demo buildings
$pdo->exec("
    INSERT INTO buildings (name, address, num_floors) VALUES
      ('Block A', 'Jalan Utama 1, Kuala Lumpur', 5),
      ('Block B', 'Jalan Utama 2, Kuala Lumpur', 3)
    ON DUPLICATE KEY UPDATE name = name
");
echo "  ✓ demo buildings seeded\n";

// 4. Link existing demo meters to their buildings
$pdo->exec("UPDATE meters SET building_id = (SELECT id FROM buildings WHERE name = 'Block A' LIMIT 1) WHERE name LIKE 'Block A%'");
$pdo->exec("UPDATE meters SET building_id = (SELECT id FROM buildings WHERE name = 'Block B' LIMIT 1) WHERE name LIKE 'Block B%'");
echo "  ✓ demo meters linked to buildings\n";

echo "\nDone! Buildings feature is ready.\n";
