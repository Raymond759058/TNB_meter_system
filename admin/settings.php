<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_role('admin');

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $limit = (float) ($_POST['daily_usage_limit'] ?? 80);
    $deadline = $_POST['submission_deadline'] ?? '20:00';
    $phone = trim($_POST['admin_whatsapp_number'] ?? '');

    $stmt = $pdo->prepare(
        'UPDATE settings SET daily_usage_limit = ?, submission_deadline = ?, admin_whatsapp_number = ? WHERE id = 1'
    );
    $stmt->execute([$limit, $deadline, $phone]);

    flash('success', 'Settings saved.');
    header('Location: ' . url('/admin/settings.php'));
    exit;
}

$settings = get_settings();
$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>Alert rules</h2>
  <p class="hint">These thresholds drive the two automatic WhatsApp alerts.</p>
  <form method="post">
    <div class="field">
      <label>Daily usage limit (kWh)</label>
      <input class="text-input" type="number" step="0.01" name="daily_usage_limit" value="<?= htmlspecialchars($settings['daily_usage_limit']) ?>" required>
    </div>
    <div class="field">
      <label>Reading submission deadline (Malaysia time)</label>
      <input class="text-input" type="time" name="submission_deadline" value="<?= date('H:i', strtotime($settings['submission_deadline'])) ?>" required>
    </div>
    <div class="field">
      <label>Admin WhatsApp number</label>
      <input class="text-input" type="text" name="admin_whatsapp_number" value="<?= htmlspecialchars($settings['admin_whatsapp_number']) ?>" placeholder="+60123456789" required>
    </div>
    <button class="btn btn-primary" type="submit">Save settings</button>
  </form>
</div>

<div class="card" style="background:#F7F9FC;">
  <h2>How WhatsApp sending works here</h2>
  <p class="hint" style="margin-bottom:0;">Alerts are simulated: they're written to the Alerts log and marked "sent", but no real WhatsApp message leaves the server yet. Real delivery needs a WhatsApp Business API credential (Meta Cloud API or Twilio) — wire it up inside <code>send_whatsapp_message()</code> in <code>includes/functions.php</code>.</p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
