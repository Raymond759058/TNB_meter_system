<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_role('admin');

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_meter') {
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        if ($name !== '') {
            $stmt = $pdo->prepare('INSERT INTO meters (name, location) VALUES (?, ?)');
            $stmt->execute([$name, $location]);
            flash('success', "Meter added: {$name}");
        }
    }

    if ($action === 'assign_meter') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $meterId = (int) ($_POST['meter_id'] ?? 0);
        if ($userId && $meterId) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO user_meters (user_id, meter_id) VALUES (?, ?)');
            $stmt->execute([$userId, $meterId]);
            flash('success', 'Meter assigned.');
        }
    }

    header('Location: ' . url('/admin/meters.php'));
    exit;
}

$meters = get_meters(false);
$users = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY name")->fetchAll();

// Fetch buildings for display next to meters
$buildingsLookup = [];
foreach ($pdo->query('SELECT id, name FROM buildings ORDER BY name')->fetchAll() as $b) {
    $buildingsLookup[$b['id']] = $b['name'];
}

$assignments = [];
foreach ($pdo->query('SELECT user_id, meter_id FROM user_meters')->fetchAll() as $row) {
    $assignments[$row['user_id']][] = $row['meter_id'];
}

$pageTitle = 'Meters';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <h2>Meters</h2>
  <p class="hint">Buildings / TNB meter points available in the system.</p>
  <?php foreach ($meters as $m): ?>
    <div class="kv">
      <span>
        <?= htmlspecialchars($m['name']) ?><?= $m['location'] ? ' — ' . htmlspecialchars($m['location']) : '' ?>
        <?php if (!empty($m['building_id']) && isset($buildingsLookup[$m['building_id']])): ?>
          <span class="badge badge-neutral" style="margin-left:4px;font-size:10px;"><?= htmlspecialchars($buildingsLookup[$m['building_id']]) ?></span>
        <?php endif; ?>
      </span>
      <b><?= $m['is_active'] ? 'Active' : 'Inactive' ?></b>
    </div>
  <?php endforeach; ?>
  <div class="divider"></div>
  <form method="post">
    <input type="hidden" name="action" value="add_meter">
    <div class="field">
      <label>Meter name</label>
      <input class="text-input" name="name" placeholder="e.g. Block C — Sub Meter" required>
    </div>
    <div class="field">
      <label>Location (optional)</label>
      <input class="text-input" name="location" placeholder="e.g. Level 2, Block C">
    </div>
    <button class="btn btn-outline" type="submit">Add meter</button>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">
    <h2 style="margin:0;">Assign meters to Field Users</h2>
    <a href="<?= url('/admin/users.php') ?>" class="btn btn-outline btn-sm" style="padding:4px 8px;font-size:11.5px;">Manage users →</a>
  </div>
  <p class="hint">A user only sees meters assigned to them when submitting a reading.</p>
  <?php foreach ($users as $u): ?>
    <div style="margin-bottom:14px;">
      <div class="kv"><span><b><?= htmlspecialchars($u['name']) ?></b></span><span></span></div>
      <div style="font-size:12px;color:var(--ink-soft);margin-bottom:8px;">
        <?php
          $assigned = $assignments[$u['id']] ?? [];
          $names = array_filter($meters, fn($m) => in_array($m['id'], $assigned, true));
          echo $names ? htmlspecialchars(implode(', ', array_column($names, 'name'))) : 'No meters assigned yet.';
        ?>
      </div>
      <form method="post" class="row">
        <input type="hidden" name="action" value="assign_meter">
        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
        <select name="meter_id" class="text-input">
          <?php foreach ($meters as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline btn-sm" type="submit">Assign</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
