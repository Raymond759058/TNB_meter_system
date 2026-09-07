<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
$user = require_role('user');

$pdo = get_db_connection();
$meters = get_meters_for_user($user['id'], $user['role']);
$meterIds = array_column($meters, 'id');

$readings = [];
if ($meterIds) {
    $placeholders = implode(',', array_fill(0, count($meterIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT r.*, m.name AS meter_name FROM readings r
         JOIN meters m ON m.id = r.meter_id
         WHERE r.meter_id IN ($placeholders)
         ORDER BY r.reading_date DESC, r.reading_time DESC, r.id DESC LIMIT 50"
    );
    $stmt->execute($meterIds);
    $readings = $stmt->fetchAll();
}

$pageTitle = 'History';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <div class="section-title">Your recent submissions</div>
  <?php if (!$readings): ?>
    <p class="empty-note">No readings submitted yet.</p>
  <?php else: foreach ($readings as $r): ?>
    <?php $photoUrl = get_photo_url($r['photo_path']); ?>
    <div class="hist-row">
      <img class="hist-thumb" src="<?= htmlspecialchars($photoUrl) ?>" alt="Meter reading photo" onclick="window.open(this.src,'_blank')" title="Click to view full photo" style="cursor:pointer;object-fit:cover;">
      <div class="hist-main">
        <div class="r1"><?= number_format($r['reading_value'], 2) ?> kWh</div>
        <div class="r2"><?= htmlspecialchars($r['meter_name']) ?> · <?= date('d M Y', strtotime($r['reading_date'])) ?> <?= substr($r['reading_time'], 0, 5) ?> MY</div>
      </div>
      <span class="badge <?= $r['app_version'] === 'v2' ? 'badge-amber' : 'badge-neutral' ?>">
        <?= strtoupper($r['app_version']) ?>
      </span>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
