<?php
/**
 * Database connection.
 * Edit the four constants below to match your environment, then every
 * other file connects through get_db_connection().
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'synergy1_raymondtanzijian_meter_watch');
define('DB_USER', 'synergy1_yenping');
define('DB_PASS', 'R.zb0ZwEuGZ}*fW2');
define('DB_PORT', '3306');

function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Database connection failed. Check config/database.php credentials. (' . $e->getMessage() . ')');
        }
    }
    return $pdo;
}
