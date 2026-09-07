<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_role('admin');

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_missing_check') {
    $settings = get_settings();
    $today = date('Y-m-d');
    $nowMinutes = (int) date('H') * 60 + (int) date('i');
    [$dh, $dm] = array_map('intval', explode(':', date('H:i', strtotime($settings['submission_deadline']))));
    $deadlineMinutes = $dh * 60 + $dm;

    if ($nowMinutes < $deadlineMinutes) {
        flash('error', 'Deadline is ' . date('H:i', strtotime($settings['submission_deadline'])) . ' — not reached yet (now ' . date('H:i') . '). No reminders sent.');
    } else {
        $raised = 0;
        foreach (get_meters() as $m) {
            $stmt = $pdo->prepare('SELECT id FROM readings WHERE meter_id = ? AND reading_date = ?');
            $stmt->execute([$m['id'], $today]);
            if (!$stmt->fetch()) {
                $message = sprintf('📋 No meter reading found on %s for %s.', format_date_human($today), $m['name']);
                if (raise_alert('missing', $m['id'], $today, $message)) {
                    $raised++;
                }
            }
        }
        flash('success', $raised > 0
            ? "Sent {$raised} missing-reading reminder(s)."
            : "All meters have today's reading submitted. No reminders needed.");
    }
    header('Location: /admin/alerts.php');
    exit;
}

$alerts = $pdo->query(
    'SELECT a.*, m.name AS meter_name FROM alerts a
     JOIN meters m ON m.id = a.meter_id
     ORDER BY a.created_at DESC'
)->fetchAll();

$pageTitle = 'Alerts';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>Missing-reading check</h2>
  <p class="hint">Current Malaysia time: <?= date('H:i') ?> on <?= date('d M Y') ?>.</p>
  <form method="post">
    <input type="hidden" name="action" value="run_missing_check">
    <button class="btn btn-amber" type="submit">📋 Check now &amp; send reminders</button>
  </form>
</div>

<div class="card">
  <div class="section-title">Alerts log <span class="badge badge-neutral"><?= count($alerts) ?></span></div>
  <?php if (!$alerts): ?>
    <p class="empty-note">No alerts yet.</p>
  <?php else: foreach ($alerts as $a): ?>
    <div class="alert-item">
      <div class="alert-icon <?= $a['type'] === 'overuse' ? 'overuse' : 'missing' ?>">
        <?= $a['type'] === 'overuse' ? '⚡' : '📋' ?>
      </div>
      <div class="alert-body">
        <div class="t1"><?= htmlspecialchars($a['message']) ?></div>
        <div class="t2"><?= htmlspecialchars($a['meter_name']) ?> · <?= date('d M Y', strtotime($a['alert_date'])) ?> · raised <?= date('d M Y H:i', strtotime($a['created_at'])) ?></div>
        <?php if ($a['whatsapp_sent']): ?><div class="wa-tag">✓ Simulated WhatsApp sent to Admin</div><?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
