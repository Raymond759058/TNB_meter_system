<?php
/**
 * Run this from the server's crontab shortly after the submission deadline
 * each day, e.g. for a 20:00 deadline:
 *
 *   5 20 * * * /usr/bin/php /path/to/tnb-meter-system/cron/check-missing-readings.php
 *
 * It raises a "missing reading" alert (and simulated WhatsApp send) for any
 * meter that has no reading recorded for today's Malaysia date.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = get_db_connection();
$today = date('Y-m-d');
$settings = get_settings();

$nowMinutes = (int) date('H') * 60 + (int) date('i');
[$dh, $dm] = array_map('intval', explode(':', date('H:i', strtotime($settings['submission_deadline']))));
$deadlineMinutes = $dh * 60 + $dm;

if ($nowMinutes < $deadlineMinutes) {
    echo "Deadline ({$settings['submission_deadline']}) not reached yet — nothing to do.\n";
    exit(0);
}

$raised = 0;
foreach (get_meters() as $m) {
    $stmt = $pdo->prepare('SELECT id FROM readings WHERE meter_id = ? AND reading_date = ?');
    $stmt->execute([$m['id'], $today]);
    if (!$stmt->fetch()) {
        $message = sprintf('No meter reading found on %s for %s.', format_date_human($today), $m['name']);
        if (raise_alert('missing', $m['id'], $today, $message)) {
            $raised++;
            echo "Alert raised: {$message}\n";
        }
    }
}

echo "Done. {$raised} reminder(s) raised.\n";
