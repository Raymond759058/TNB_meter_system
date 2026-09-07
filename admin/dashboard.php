<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_role('admin');

$pdo = get_db_connection();
$today = date('Y-m-d');
$settings = get_settings();

$meters = get_meters();
$meterRows = [];
foreach ($meters as $m) {
    $stmt = $pdo->prepare('SELECT * FROM readings WHERE meter_id = ? AND reading_date = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$m['id'], $today]);
    $meterRows[] = ['meter' => $m, 'today' => $stmt->fetch()];
}

$recentAlerts = $pdo->query(
    'SELECT a.*, m.name AS meter_name FROM alerts a
     JOIN meters m ON m.id = a.meter_id
     ORDER BY a.created_at DESC LIMIT 5'
)->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>Today — <?= date('d M Y') ?> (Malaysia time <?= date('H:i') ?>)</h2>
  <p class="hint">Submission deadline is <?= date('H:i', strtotime($settings['submission_deadline'])) ?>. Daily usage limit is <?= number_format($settings['daily_usage_limit'], 2) ?> kWh.</p>
  <?php foreach ($meterRows as $row): $m = $row['meter']; $r = $row['today']; ?>
    <div class="kv">
      <span><?= htmlspecialchars($m['name']) ?></span>
      <?php if ($r): ?>
        <b><span class="badge badge-green">Submitted · <?= number_format($r['reading_value'], 2) ?> kWh</span></b>
      <?php else: ?>
        <b><span class="badge badge-amber">Not submitted yet</span></b>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="section-title">Recent alerts</div>
  <?php if (!$recentAlerts): ?>
    <p class="empty-note">No alerts yet. They'll appear here the moment usage exceeds the limit, or a reading is missing past the deadline.</p>
  <?php else: foreach ($recentAlerts as $a): ?>
    <div class="alert-item">
      <div class="alert-icon <?= $a['type'] === 'overuse' ? 'overuse' : 'missing' ?>">
        <?= $a['type'] === 'overuse' ? '⚡' : '📋' ?>
      </div>
      <div class="alert-body">
        <div class="t1"><?= htmlspecialchars($a['message']) ?></div>
        <div class="t2"><?= htmlspecialchars($a['meter_name']) ?> · <?= date('d M Y', strtotime($a['alert_date'])) ?></div>
        <?php if ($a['whatsapp_sent']): ?><div class="wa-tag">✓ Simulated WhatsApp sent to Admin</div><?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>
  <div class="divider"></div>
  <a href="<?= url('/admin/alerts.php') ?>" class="btn btn-outline btn-sm">View all alerts</a>
</div>

<div class="card">
  <h2>Missing-reading check</h2>
  <p class="hint">In production this runs automatically via <code>cron/check-missing-readings.php</code> on a schedule after the deadline. Trigger it manually to test it now.</p>
  <form method="post" action="<?= url('/admin/alerts.php') ?>">
    <input type="hidden" name="action" value="run_missing_check">
    <button class="btn btn-amber" type="submit">📋 Check now &amp; send reminders</button>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
