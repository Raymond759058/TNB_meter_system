<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_role('admin');

$pdo = get_db_connection();

/* ── Handle POST actions ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* Add new building */
    if ($action === 'add_building') {
        $name    = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $floors  = ($_POST['num_floors'] ?? '') !== '' ? (int) $_POST['num_floors'] : null;
        if ($name !== '') {
            $stmt = $pdo->prepare('INSERT INTO buildings (name, address, num_floors) VALUES (?, ?, ?)');
            $stmt->execute([$name, $address ?: null, $floors]);
            flash('success', "Building added: {$name}");
        }
    }

    /* Edit existing building */
    if ($action === 'edit_building') {
        $id      = (int) ($_POST['building_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $floors  = ($_POST['num_floors'] ?? '') !== '' ? (int) $_POST['num_floors'] : null;
        if ($id && $name !== '') {
            $stmt = $pdo->prepare('UPDATE buildings SET name = ?, address = ?, num_floors = ? WHERE id = ?');
            $stmt->execute([$name, $address ?: null, $floors, $id]);
            flash('success', "Building updated: {$name}");
        }
    }

    /* Toggle active / inactive */
    if ($action === 'toggle_building') {
        $id = (int) ($_POST['building_id'] ?? 0);
        if ($id) {
            $pdo->prepare('UPDATE buildings SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
            flash('success', 'Building status toggled.');
        }
    }

    /* Delete building (only if no meters linked) */
    if ($action === 'delete_building') {
        $id = (int) ($_POST['building_id'] ?? 0);
        if ($id) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM meters WHERE building_id = ?');
            $check->execute([$id]);
            if ((int) $check->fetchColumn() > 0) {
                flash('error', 'Cannot delete — meters are still assigned to this building. Remove or reassign them first.');
            } else {
                $pdo->prepare('DELETE FROM buildings WHERE id = ?')->execute([$id]);
                flash('success', 'Building deleted.');
            }
        }
    }

    /* Assign a meter to a building */
    if ($action === 'assign_meter') {
        $meterId    = (int) ($_POST['meter_id'] ?? 0);
        $buildingId = ($_POST['building_id'] ?? '') !== '' ? (int) $_POST['building_id'] : null;
        if ($meterId) {
            $stmt = $pdo->prepare('UPDATE meters SET building_id = ? WHERE id = ?');
            $stmt->execute([$buildingId, $meterId]);
            flash('success', 'Meter assignment updated.');
        }
    }

    header('Location: /admin/buildings.php');
    exit;
}

/* ── Fetch data ──────────────────────────────────────────────────── */
$buildings = $pdo->query('SELECT * FROM buildings ORDER BY name')->fetchAll();
$meters    = get_meters(false);

// Build a lookup: building_id → [meter names]
$metersByBuilding = [];
$unassignedMeters = [];
foreach ($meters as $m) {
    if ($m['building_id']) {
        $metersByBuilding[(int)$m['building_id']][] = $m;
    } else {
        $unassignedMeters[] = $m;
    }
}

// Editing?
$editing = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    foreach ($buildings as $b) {
        if ($b['id'] == $editId) { $editing = $b; break; }
    }
}

$pageTitle = 'Buildings';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Buildings list ─────────────────────────────────────────────── -->
<div class="card">
  <h2>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;vertical-align:-3px;margin-right:4px;color:var(--sky);"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/></svg>
    Buildings
  </h2>
  <p class="hint">Physical buildings that group one or more TNB meter points.</p>

  <?php if (!$buildings): ?>
    <p class="empty-note">No buildings added yet. Use the form below to create one.</p>
  <?php else: ?>
    <?php foreach ($buildings as $b): ?>
      <div class="building-row" style="padding:12px 0;border-bottom:1px solid var(--line);">
        <div class="kv" style="padding:0 0 2px;">
          <span style="font-weight:700;color:var(--ink);font-size:13.5px;">
            <?= htmlspecialchars($b['name']) ?>
          </span>
          <span style="display:flex;gap:6px;align-items:center;">
            <span class="badge <?= $b['is_active'] ? 'badge-green' : 'badge-red' ?>">
              <?= $b['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
          </span>
        </div>
        <?php if ($b['address']): ?>
          <div style="font-size:12px;color:var(--ink-soft);margin-bottom:2px;">
            📍 <?= htmlspecialchars($b['address']) ?>
          </div>
        <?php endif; ?>
        <?php if ($b['num_floors']): ?>
          <div style="font-size:12px;color:var(--ink-soft);margin-bottom:2px;">
            🏢 <?= (int)$b['num_floors'] ?> floor<?= $b['num_floors'] > 1 ? 's' : '' ?>
          </div>
        <?php endif; ?>

        <!-- Meters in this building -->
        <?php $linked = $metersByBuilding[(int)$b['id']] ?? []; ?>
        <?php if ($linked): ?>
          <div style="font-size:11.5px;color:var(--ink-soft);margin-top:4px;">
            ⚡ Meters:
            <?php foreach ($linked as $i => $lm): ?>
              <span class="badge badge-neutral" style="margin:2px 0;"><?= htmlspecialchars($lm['name']) ?></span>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="font-size:11.5px;color:var(--ink-soft);margin-top:4px;font-style:italic;">No meters assigned.</div>
        <?php endif; ?>

        <!-- Action buttons -->
        <div style="display:flex;gap:6px;margin-top:8px;">
          <a href="/admin/buildings.php?edit=<?= $b['id'] ?>" class="btn btn-outline btn-sm">✏️ Edit</a>
          <form method="post" style="margin:0;">
            <input type="hidden" name="action" value="toggle_building">
            <input type="hidden" name="building_id" value="<?= $b['id'] ?>">
            <button class="btn btn-outline btn-sm" type="submit">
              <?= $b['is_active'] ? '⏸ Deactivate' : '▶ Activate' ?>
            </button>
          </form>
          <?php if (empty($linked)): ?>
            <form method="post" style="margin:0;" onsubmit="return confirm('Delete this building?')">
              <input type="hidden" name="action" value="delete_building">
              <input type="hidden" name="building_id" value="<?= $b['id'] ?>">
              <button class="btn btn-danger-outline btn-sm" type="submit">🗑 Delete</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ── Add / Edit building form ──────────────────────────────────── -->
<div class="card">
  <h2><?= $editing ? '✏️ Edit Building' : '➕ Add Building' ?></h2>
  <p class="hint"><?= $editing ? 'Update the building details below.' : 'Register a new physical building in the system.' ?></p>
  <form method="post">
    <input type="hidden" name="action" value="<?= $editing ? 'edit_building' : 'add_building' ?>">
    <?php if ($editing): ?>
      <input type="hidden" name="building_id" value="<?= $editing['id'] ?>">
    <?php endif; ?>
    <div class="field">
      <label>Building name *</label>
      <input class="text-input" name="name" placeholder="e.g. Block C" required
             value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
    </div>
    <div class="field">
      <label>Address (optional)</label>
      <input class="text-input" name="address" placeholder="e.g. Jalan Utama 3, Kuala Lumpur"
             value="<?= htmlspecialchars($editing['address'] ?? '') ?>">
    </div>
    <div class="field">
      <label>Number of floors (optional)</label>
      <input class="text-input" type="number" name="num_floors" min="1" max="200" placeholder="e.g. 5"
             value="<?= htmlspecialchars($editing['num_floors'] ?? '') ?>">
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-primary" type="submit" style="flex:1;">
        <?= $editing ? 'Update building' : 'Add building' ?>
      </button>
      <?php if ($editing): ?>
        <a href="/admin/buildings.php" class="btn btn-outline" style="flex:0 0 auto;">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- ── Assign meters to buildings ────────────────────────────────── -->
<div class="card">
  <h2>🔗 Assign Meters to Buildings</h2>
  <p class="hint">Link each meter to the building it physically belongs to.</p>
  <?php foreach ($meters as $m): ?>
    <form method="post" style="margin-bottom:10px;">
      <input type="hidden" name="action" value="assign_meter">
      <input type="hidden" name="meter_id" value="<?= $m['id'] ?>">
      <div class="row" style="align-items:center;">
        <div style="flex:1;font-size:13px;font-weight:600;color:var(--ink);">
          <?= htmlspecialchars($m['name']) ?>
          <?php if ($m['building_id']): ?>
            <?php
              $currentBuilding = '';
              foreach ($buildings as $b) {
                  if ($b['id'] == $m['building_id']) { $currentBuilding = $b['name']; break; }
              }
            ?>
            <span class="badge badge-green" style="margin-left:4px;"><?= htmlspecialchars($currentBuilding) ?></span>
          <?php else: ?>
            <span class="badge badge-amber" style="margin-left:4px;">Unassigned</span>
          <?php endif; ?>
        </div>
        <select name="building_id" class="text-input" style="width:auto;min-width:140px;">
          <option value="">— None —</option>
          <?php foreach ($buildings as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $m['building_id'] == $b['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline btn-sm" type="submit">Save</button>
      </div>
    </form>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
