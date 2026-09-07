<?php
require_once __DIR__ . '/../config/database.php';
$pdo = get_db_connection();

echo "Running readings schema and photo paths fix...\n";

// 1. Add index on meter_id so foreign key constraint is satisfied independently
$idxMeter = $pdo->query("SHOW INDEX FROM readings WHERE Key_name = 'idx_meter'")->fetchAll();
if (empty($idxMeter)) {
    $pdo->exec("ALTER TABLE readings ADD INDEX idx_meter (meter_id)");
    echo "  ✓ Added standalone index idx_meter on meter_id\n";
}

// 2. Drop uniq_meter_date
$indexes = $pdo->query("SHOW INDEX FROM readings WHERE Key_name = 'uniq_meter_date'")->fetchAll();
if (!empty($indexes)) {
    $pdo->exec("ALTER TABLE readings DROP INDEX uniq_meter_date");
    echo "  ✓ Dropped unique constraint uniq_meter_date from readings table\n";
} else {
    echo "  – uniq_meter_date already dropped\n";
}

// 3. Add composite index on (meter_id, reading_date) for fast history/deadline queries
$idxDate = $pdo->query("SHOW INDEX FROM readings WHERE Key_name = 'idx_meter_date'")->fetchAll();
if (empty($idxDate)) {
    $pdo->exec("ALTER TABLE readings ADD INDEX idx_meter_date (meter_id, reading_date)");
    echo "  ✓ Added composite index idx_meter_date (meter_id, reading_date)\n";
} else {
    echo "  – idx_meter_date already exists\n";
}

// 4. Fix any doubled folder paths in photo_path column
$affected = $pdo->exec("UPDATE readings SET photo_path = REPLACE(photo_path, '/tnb-meter-system/tnb-meter-system/', '/tnb-meter-system/') WHERE photo_path LIKE '%/tnb-meter-system/tnb-meter-system/%'");
echo "  ✓ Cleaned {$affected} reading photo path(s) in database\n";

echo "\nDone!\n";
