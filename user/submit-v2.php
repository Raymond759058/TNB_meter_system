<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
$user = require_role('user');

$pdo = get_db_connection();
$meters = get_meters_for_user($user['id'], $user['role']);
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meterId = (int) ($_POST['meter_id'] ?? 0);
    $readingValue = (float) ($_POST['reading_value'] ?? 0);
    $photoData = $_POST['live_photo_data'] ?? '';
    $meter = null;
    foreach ($meters as $m) { if ((int) $m['id'] === $meterId) { $meter = $m; break; } }

    if (!$meter) {
        flash('error', 'Please select a valid meter.');
    } elseif ($photoData === '') {
        // Server-side enforcement: a submission with no captured live photo is rejected outright,
        // even if someone bypasses the disabled button in the browser.
        flash('error', 'Submission not allowed: a live meter photo is required before you can submit.');
    } elseif ($readingValue <= 0) {
        flash('error', 'Please enter the meter reading.');
    } else {
        $photoPath = save_meter_photo_from_dataurl($photoData, (string) $meterId, $today);
        if (!$photoPath) {
            flash('error', 'The captured photo could not be saved. Please retake it.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO readings (meter_id, user_id, reading_value, photo_path, reading_date, reading_time, app_version)
                 VALUES (?, ?, ?, ?, ?, ?, "v2")'
            );
            $stmt->execute([$meterId, $user['id'], $readingValue, $photoPath, $today, date('H:i:s')]);

            check_over_usage($meterId, $meter['name'], $today, $readingValue);

            flash('success', "Reading saved for {$meter['name']}, " . date('d M Y H:i') . ' (Malaysia time) — live photo verified.');
            header('Location: ' . url('/user/submit-v2.php'));
            exit;
        }
    }
}

$pageTitle = 'Submit reading (V2)';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>Version 2 — Submit meter reading</h2>
  <p class="hint">Stronger evidence mode: the meter photo must be captured live, right now. There is no gallery picker, and Submit stays disabled until a live photo exists.</p>
</div>

<?php if (!$meters): ?>
  <div class="card"><p class="empty-note">No meters are assigned to you yet. Ask an Admin to assign one under Meters.</p></div>
<?php else: ?>
<div class="flow-strip" id="flowStrip">
  <span class="step done">Login</span><span class="arrow">→</span>
  <span class="step" id="stepMeter">Select meter</span><span class="arrow">→</span>
  <span class="step" id="stepPhoto">Capture live photo</span><span class="arrow">→</span>
  <span class="step" id="stepReading">Enter reading</span><span class="arrow">→</span>
  <span class="step">Submit</span>
</div>

<div class="card">
  <form method="post" id="v2Form">
    <div class="field">
      <label>1. Select meter</label>
      <select name="meter_id" id="meterSelect" class="text-input" required>
        <?php foreach ($meters as $m): ?>
          <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label>2. Capture live meter photo</label>
      <p class="lock-note">🔒 For this trial, point the camera at any paper with a written reading instead of a real TNB meter.</p>

      <div class="photo-box" id="photoBox">
        <div class="photo-empty">
          <p>📷 No gallery upload in Version 2 — the camera opens live and captures directly.</p>
        </div>
        <div style="padding:0 10px 10px;">
          <button type="button" class="btn btn-primary" id="openCameraBtn">📷 Open live camera</button>
        </div>
      </div>
      <p id="cameraError" style="color:var(--red);font-size:12px;margin-top:8px;display:none;"></p>

      <input type="hidden" name="live_photo_data" id="livePhotoData" value="">
    </div>

    <div class="field">
      <label>3. Enter meter reading (kWh)</label>
      <div class="reading-input-wrap">
        <input class="text-input reading-input" type="number" step="0.01" name="reading_value" id="readingInput" placeholder="0000" disabled required>
        <span class="reading-unit">kWh</span>
      </div>
      <p id="readingLockNote" style="font-size:11.5px;color:var(--ink-soft);margin-top:8px;">Capture the live photo first to unlock this field.</p>
    </div>

    <button class="btn btn-primary" type="submit" id="submitBtn" disabled>Live photo required to submit</button>
  </form>
</div>
<?php endif; ?>

<script src="<?= url('/assets/js/camera-v2.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
