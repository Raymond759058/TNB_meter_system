<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_role('admin');

$pdo = get_db_connection();

/* ── Handle Delete Action (Admin only) ───────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_reading') {
        $readingId = (int) ($_POST['reading_id'] ?? 0);

        if ($readingId > 0) {
            // Find reading to get photo path and meter details
            $stmt = $pdo->prepare('
                SELECT r.*, m.name AS meter_name 
                FROM readings r 
                JOIN meters m ON m.id = r.meter_id 
                WHERE r.id = ?
            ');
            $stmt->execute([$readingId]);
            $reading = $stmt->fetch();

            if ($reading) {
                // 1. Delete from database
                $del = $pdo->prepare('DELETE FROM readings WHERE id = ?');
                $del->execute([$readingId]);

                // 2. Remove physical photo file if present on disk
                if (!empty($reading['photo_path'])) {
                    $filename = basename($reading['photo_path']);
                    $fullPath = UPLOAD_DIR . $filename;
                    if (file_exists($fullPath) && is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }

                flash('success', "Reading record #{$readingId} ({$reading['meter_name']} — {$reading['reading_value']} kWh on {$reading['reading_date']}) deleted successfully.");
            } else {
                flash('error', 'Reading record not found.');
            }
        }
    }

    // Preserve filter parameters in redirect
    $queryArgs = [];
    if (!empty($_GET['meter_id'])) $queryArgs['meter_id'] = (int)$_GET['meter_id'];
    if (!empty($_GET['version']))  $queryArgs['version']  = $_GET['version'];
    $redirectUrl = '/admin/history.php' . ($queryArgs ? '?' . http_build_query($queryArgs) : '');

    header('Location: ' . $redirectUrl);
    exit;
}

/* ── Filter Parameters ───────────────────────────────────────────── */
$filterMeterId = isset($_GET['meter_id']) && $_GET['meter_id'] !== '' ? (int) $_GET['meter_id'] : null;
$filterVersion = isset($_GET['version']) && in_array($_GET['version'], ['v1', 'v2'], true) ? $_GET['version'] : null;

/* ── Fetch Meters for Dropdown Filter ────────────────────────────── */
$meters = get_meters(false);

/* ── Build Query ─────────────────────────────────────────────────── */
$whereConditions = [];
$queryParams = [];

if ($filterMeterId) {
    $whereConditions[] = 'r.meter_id = ?';
    $queryParams[] = $filterMeterId;
}

if ($filterVersion) {
    $whereConditions[] = 'r.app_version = ?';
    $queryParams[] = $filterVersion;
}

$whereSql = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$sql = "
    SELECT r.*, m.name AS meter_name, b.name AS building_name, u.name AS user_name, u.username
    FROM readings r
    JOIN meters m ON m.id = r.meter_id
    LEFT JOIN buildings b ON b.id = m.building_id
    JOIN users u ON u.id = r.user_id
    {$whereSql}
    ORDER BY r.reading_date DESC, r.reading_time DESC, r.id DESC
    LIMIT 100
";

$stmt = $pdo->prepare($sql);
$stmt->execute($queryParams);
$readings = $stmt->fetchAll();

// Total count
$totalCountSql = "SELECT COUNT(*) FROM readings r {$whereSql}";
$stmtTotal = $pdo->prepare($totalCountSql);
$stmtTotal->execute($queryParams);
$totalReadingsCount = (int) $stmtTotal->fetchColumn();

$pageTitle = 'Submission History';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
    <div>
      <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;vertical-align:-3px;margin-right:4px;color:var(--sky);"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Reading History
      </h2>
      <p class="hint" style="margin-bottom:8px;">All daily meter submissions logged across all meters and field users. Admins can review and delete entries.</p>
    </div>
    <span class="badge badge-neutral" style="font-size:12px;padding:5px 10px;"><?= $totalReadingsCount ?> record<?= $totalReadingsCount === 1 ? '' : 's' ?></span>
  </div>

  <!-- Filter Form -->
  <form method="get" style="margin-top:10px;padding:12px;background:#F7F9FC;border:1px solid var(--line);border-radius:10px;">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
      <div style="flex:1;min-width:140px;">
        <label style="font-size:11px;margin-bottom:4px;">Filter by Meter</label>
        <select name="meter_id" class="text-input" style="padding:7px 10px;font-size:13px;">
          <option value="">— All meters —</option>
          <?php foreach ($meters as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $filterMeterId === (int)$m['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($m['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="flex:0 0 110px;">
        <label style="font-size:11px;margin-bottom:4px;">Version</label>
        <select name="version" class="text-input" style="padding:7px 10px;font-size:13px;">
          <option value="">All</option>
          <option value="v1" <?= $filterVersion === 'v1' ? 'selected' : '' ?>>V1</option>
          <option value="v2" <?= $filterVersion === 'v2' ? 'selected' : '' ?>>V2</option>
        </select>
      </div>

      <div style="display:flex;gap:4px;">
        <button class="btn btn-primary btn-sm" type="submit" style="padding:8px 14px;">Filter</button>
        <?php if ($filterMeterId || $filterVersion): ?>
          <a href="/admin/history.php" class="btn btn-outline btn-sm" style="padding:8px 12px;">Reset</a>
        <?php endif; ?>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <div class="section-title">Submissions Log</div>

  <?php if (empty($readings)): ?>
    <p class="empty-note">No reading submissions found matching the criteria.</p>
  <?php else: ?>
    <?php foreach ($readings as $r): ?>
      <?php 
        $photoUrl = get_photo_url($r['photo_path']); 
        $rId = (int) $r['id'];
      ?>
      <div class="hist-row" style="padding:12px 0;align-items:flex-start;">
        <!-- Clickable Photo Thumbnail -->
        <img class="hist-thumb" 
             src="<?= htmlspecialchars($photoUrl) ?>" 
             alt="Meter photo" 
             onclick="window.open(this.src,'_blank')" 
             title="Click to open full photo"
             style="width:52px;height:52px;border-radius:9px;cursor:pointer;object-fit:cover;border:1px solid var(--line);">

        <!-- Details -->
        <div class="hist-main" style="margin-left:12px;">
          <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <span style="font-family:var(--mono);font-weight:700;font-size:15px;color:var(--navy);">
              <?= number_format($r['reading_value'], 2) ?> kWh
            </span>
            <span class="badge <?= $r['app_version'] === 'v2' ? 'badge-amber' : 'badge-neutral' ?>" style="font-size:10px;">
              <?= strtoupper($r['app_version']) ?>
            </span>
          </div>

          <div style="font-size:12.5px;font-weight:600;color:var(--ink);margin-top:3px;">
            <?= htmlspecialchars($r['meter_name']) ?>
            <?php if (!empty($r['building_name'])): ?>
              <span class="badge badge-neutral" style="font-size:10px;padding:1px 6px;margin-left:4px;">
                🏢 <?= htmlspecialchars($r['building_name']) ?>
              </span>
            <?php endif; ?>
          </div>

          <div style="font-size:11.5px;color:var(--ink-soft);margin-top:2px;">
            👤 <?= htmlspecialchars($r['user_name']) ?> (@<?= htmlspecialchars($r['username']) ?>)
            · 📅 <?= date('d M Y', strtotime($r['reading_date'])) ?> at <?= substr($r['reading_time'], 0, 5) ?>
          </div>
        </div>

        <!-- Admin Delete Button -->
        <div style="margin-left:auto;padding-left:8px;display:flex;align-items:center;">
          <form method="post" onsubmit="return confirm('Are you sure you want to delete this reading for <?= htmlspecialchars(addslashes($r['meter_name'])) ?> (<?= number_format($r['reading_value'], 2) ?> kWh)?\n\nThis cannot be undone.');" style="margin:0;">
            <input type="hidden" name="action" value="delete_reading">
            <input type="hidden" name="reading_id" value="<?= $rId ?>">
            <button class="btn btn-danger-outline btn-sm" type="submit" style="padding:5px 8px;font-size:11px;white-space:nowrap;">
              🗑 Delete
            </button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
