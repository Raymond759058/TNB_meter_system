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
    $cameraPhoto = trim($_POST['camera_photo_data'] ?? '');
    $hasUpload = !empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK;

    $meter = null;
    foreach ($meters as $m) { if ((int) $m['id'] === $meterId) { $meter = $m; break; } }

    if (!$meter) {
        flash('error', 'Please select a valid meter.');
    } elseif ($readingValue <= 0) {
        flash('error', 'Please enter the meter reading.');
    } elseif ($cameraPhoto === '' && !$hasUpload) {
        flash('error', 'Please take a photo with the camera or upload an image file.');
    } else {
        $photoPath = null;
        if ($cameraPhoto !== '') {
            $photoPath = save_meter_photo_from_dataurl($cameraPhoto, (string) $meterId, $today);
        } else {
            $photoPath = save_meter_photo_from_upload($_FILES['photo'], (string) $meterId, $today);
        }

        if (!$photoPath) {
            flash('error', 'That photo could not be saved. Please try another photo or upload a JPG/PNG under 5 MB.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO readings (meter_id, user_id, reading_value, photo_path, reading_date, reading_time, app_version)
                 VALUES (?, ?, ?, ?, ?, ?, "v1")'
            );
            $stmt->execute([$meterId, $user['id'], $readingValue, $photoPath, $today, date('H:i:s')]);

            check_over_usage($meterId, $meter['name'], $today, $readingValue);

            flash('success', "Reading saved for {$meter['name']}, " . date('d M Y H:i') . ' (Malaysia time).');
            header('Location: /user/submit-v1.php');
            exit;
        }
    }
}

$pageTitle = 'Submit reading (V1)';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>Version 1 — Submit meter reading</h2>
  <p class="hint">Take a photo directly with your camera or upload an image from your device, then enter the reading shown on the meter.</p>
</div>

<?php if (!$meters): ?>
  <div class="card"><p class="empty-note">No meters are assigned to you yet. Ask an Admin to assign one under Meters.</p></div>
<?php else: ?>
<div class="card">
  <form method="post" enctype="multipart/form-data" id="v1Form">
    <div class="field">
      <label>1. Select meter</label>
      <select name="meter_id" class="text-input" required>
        <?php foreach ($meters as $m): ?>
          <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label>2. Meter photo</label>

      <!-- Choice tabs: Camera vs Upload -->
      <div style="display:flex;gap:6px;margin-bottom:10px;">
        <button type="button" id="tabCamera" class="btn btn-primary btn-sm" style="flex:1;">📷 Take Photo</button>
        <button type="button" id="tabUpload" class="btn btn-outline btn-sm" style="flex:1;">📁 Upload File</button>
      </div>

      <!-- Camera Pane -->
      <div id="cameraPane">
        <div class="photo-box" id="photoBoxCamera">
          <div class="photo-empty">
            <p>📷 Open your camera to take a photo of the meter.</p>
          </div>
          <div style="padding:0 10px 10px;">
            <button type="button" class="btn btn-primary" id="openCameraBtn">📷 Open live camera</button>
          </div>
        </div>
        <p id="cameraError" style="color:var(--red);font-size:12px;margin-top:8px;display:none;"></p>
        <input type="hidden" name="camera_photo_data" id="cameraPhotoData" value="">
      </div>

      <!-- Upload Pane -->
      <div id="uploadPane" style="display:none;">
        <div class="photo-box" id="photoBoxUpload">
          <div class="photo-empty" id="uploadPrompt">
            <p>📁 Select an existing image file from your device.</p>
          </div>
          <div style="padding:0 10px 10px;">
            <input class="text-input" type="file" name="photo" id="fileInput" accept="image/*">
          </div>
          <!-- Instant Image Preview -->
          <div id="filePreviewBox" style="display:none;padding:10px;border-top:1px solid var(--line);">
            <p style="font-size:11.5px;color:var(--ink-soft);margin:0 0 6px;">Preview of selected file:</p>
            <img id="filePreviewImg" class="captured-img" src="" alt="Meter preview" style="max-height:220px;border-radius:8px;">
          </div>
        </div>
      </div>

      <p class="lock-note" style="margin-top:10px;">🧾 For this trial, a photo of any paper with a written reading is fine — it stands in for a real TNB meter.</p>
    </div>

    <div class="field">
      <label>3. Meter reading (kWh)</label>
      <div class="reading-input-wrap">
        <input class="text-input reading-input" type="number" step="0.01" name="reading_value" placeholder="0000" required>
        <span class="reading-unit">kWh</span>
      </div>
    </div>

    <button class="btn btn-primary" type="submit">✓ Submit reading</button>
  </form>
</div>
<?php endif; ?>

<script src="/assets/js/camera-v1.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
