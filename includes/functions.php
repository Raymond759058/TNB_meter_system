<?php
/**
 * Shared helper functions used across admin/, user/ and cron/.
 */

require_once __DIR__ . '/../config/database.php';

function get_settings(): array {
    $pdo = get_db_connection();
    $row = $pdo->query('SELECT * FROM settings WHERE id = 1')->fetch();
    return $row ?: [
        'daily_usage_limit' => 80.00,
        'submission_deadline' => '20:00:00',
        'admin_whatsapp_number' => '',
    ];
}

function get_meters(bool $activeOnly = true): array {
    $pdo = get_db_connection();
    $sql = 'SELECT * FROM meters' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name';
    return $pdo->query($sql)->fetchAll();
}

function get_meters_for_user(int $userId, string $role): array {
    $pdo = get_db_connection();
    if ($role === 'admin') {
        return get_meters();
    }
    $stmt = $pdo->prepare(
        'SELECT m.* FROM meters m
         JOIN user_meters um ON um.meter_id = m.id
         WHERE um.user_id = ? AND m.is_active = 1
         ORDER BY m.name'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Simulated WhatsApp delivery.
 *
 * Real delivery requires a server-side call to a provider such as the
 * Meta WhatsApp Business Cloud API or Twilio, using a private API
 * credential. That call belongs here — replace the body of this
 * function with a cURL/HTTP request to your provider. Until then, every
 * "send" is written to the alerts table and treated as delivered so the
 * rest of the app (dashboards, logs) works end-to-end.
 *
 * @return bool true if the HTTP call to the provider would be considered successful
 */
function send_whatsapp_message(string $toNumber, string $message): bool {
    // --- Replace this block with a real WhatsApp Business API call ---
    //
    // Example (Meta Cloud API, pseudo-code):
    //   $ch = curl_init('https://graph.facebook.com/v20.0/<PHONE_NUMBER_ID>/messages');
    //   curl_setopt($ch, CURLOPT_POST, true);
    //   curl_setopt($ch, CURLOPT_HTTPHEADER, [
    //       'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
    //       'Content-Type: application/json',
    //   ]);
    //   curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    //       'messaging_product' => 'whatsapp',
    //       'to' => $toNumber,
    //       'type' => 'text',
    //       'text' => ['body' => $message],
    //   ]));
    //   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //   $response = curl_exec($ch);
    //   $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    //   curl_close($ch);
    //   return $ok;
    // -------------------------------------------------------------------

    error_log("[SIMULATED WHATSAPP] to {$toNumber}: {$message}");
    return true; // treated as sent for demo purposes
}

/**
 * Raise an over-usage or missing-reading alert, avoiding duplicates for the
 * same meter/date/type, and simulate the WhatsApp send.
 */
function raise_alert(string $type, int $meterId, string $date, string $message): bool {
    $pdo = get_db_connection();

    $check = $pdo->prepare('SELECT id FROM alerts WHERE type = ? AND meter_id = ? AND alert_date = ?');
    $check->execute([$type, $meterId, $date]);
    if ($check->fetch()) {
        return false; // already raised, do not duplicate
    }

    $settings = get_settings();
    $sent = send_whatsapp_message($settings['admin_whatsapp_number'], $message);

    $stmt = $pdo->prepare(
        'INSERT INTO alerts (type, meter_id, alert_date, message, whatsapp_sent) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$type, $meterId, $date, $message, $sent ? 1 : 0]);
    return true;
}

/**
 * Compare a newly submitted reading against the previous day's reading for
 * the same meter, and raise an over-usage alert if the daily limit set by
 * Admin is exceeded.
 */
function check_over_usage(int $meterId, string $meterName, string $date, float $readingValue): void {
    $pdo = get_db_connection();
    $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));

    $stmt = $pdo->prepare('SELECT reading_value FROM readings WHERE meter_id = ? AND reading_date = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$meterId, $prevDate]);
    $prev = $stmt->fetch();
    if (!$prev) {
        return; // nothing to compare against yet
    }

    $usage = $readingValue - (float) $prev['reading_value'];
    if ($usage <= 0) {
        return;
    }

    $settings = get_settings();
    $limit = (float) $settings['daily_usage_limit'];
    if ($usage > $limit) {
        $over = $usage - $limit;
        $message = sprintf(
            '⚡ Over-usage on %s: %.2f kWh used on %s — exceeds the %.2f kWh limit by %.2f kWh.',
            $meterName, $usage, format_date_human($date), $limit, $over
        );
        raise_alert('overuse', $meterId, $date, $message);
    }
}

function format_date_human(string $isoDate): string {
    return date('d M Y', strtotime($isoDate));
}

function get_photo_url(?string $photoPath): string {
    if (!$photoPath) {
        return '';
    }
    return str_replace('/tnb-meter-system/tnb-meter-system/', '/tnb-meter-system/', $photoPath);
}

/** Save an uploaded/captured photo (base64 data URL or $_FILES entry) and return its stored path. */
function save_meter_photo_from_dataurl(string $dataUrl, string $meterId, string $date): ?string {
    if (!preg_match('/^data:image\/(png|jpe?g);base64,(.+)$/', $dataUrl, $m)) {
        return null;
    }
    $ext = $m[1] === 'png' ? 'png' : 'jpg';
    $binary = base64_decode($m[2]);
    if ($binary === false) {
        return null;
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $filename = sprintf('meter_%s_%s_%s.%s', $meterId, $date, bin2hex(random_bytes(4)), $ext);
    $fullPath = UPLOAD_DIR . $filename;
    if (file_put_contents($fullPath, $binary) === false) {
        return null;
    }
    return UPLOAD_URL . $filename;
}

function save_meter_photo_from_upload(array $file, string $meterId, string $date): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return null;
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return null;
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $filename = sprintf('meter_%s_%s_%s.%s', $meterId, $date, bin2hex(random_bytes(4)), $allowed[$mime]);
    $fullPath = UPLOAD_DIR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return null;
    }
    return UPLOAD_URL . $filename;
}

function flash(string $key, ?string $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}
