<?php
/**
 * App-wide configuration.
 */

// All dates/times recorded for readings and alerts use Malaysia time.
date_default_timezone_set('Asia/Kuala_Lumpur');

define('APP_NAME', 'Meter Watch');

// Where meter photos are saved (must be writable by the web server).
define('UPLOAD_DIR', __DIR__ . '/../uploads/meter_photos/');

// Detect base path dynamically (e.g. '/tnb-meter-system' under XAMPP htdocs, or '' if at root)
if (PHP_SAPI === 'cli') {
    $appBase = '/tnb-meter-system';
} else {
    $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
    $appBase = rtrim(preg_replace('#/(admin|user|auth|cron|config|database)$#', '', $scriptDir), '/');
    if ($appBase === '.') {
        $appBase = '/tnb-meter-system';
    }
}
define('APP_BASE', $appBase);
define('UPLOAD_URL', ($appBase !== '' ? $appBase : '') . '/uploads/meter_photos/');

// Max upload size for a meter photo, in bytes (5 MB).
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

session_start();
