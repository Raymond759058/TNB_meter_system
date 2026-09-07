<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_role('admin');

$pdo = get_db_connection();
$currentUser = current_user();

/* ── Handle POST actions ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* 1. Add New User */
    if ($action === 'add_user') {
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'] ?? '', ['admin', 'user'], true) ? $_POST['role'] : 'user';
        $whatsapp = trim($_POST['whatsapp_number'] ?? '');
        $assignedMeters = isset($_POST['meters']) && is_array($_POST['meters']) ? array_map('intval', $_POST['meters']) : [];

        if ($name === '' || $username === '') {
            flash('error', 'Please enter both full name and username.');
        } elseif (strlen($password) < 4) {
            flash('error', 'Password must be at least 4 characters.');
        } else {
            // Check if username already exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?)');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                flash('error', "Username '{$username}' is already taken. Please choose another.");
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('INSERT INTO users (name, username, password_hash, role, whatsapp_number) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $username, $hash, $role, $whatsapp ?: null]);
                $newUserId = (int) $pdo->lastInsertId();

                // Assign meters if field user
                if ($role === 'user' && !empty($assignedMeters)) {
                    $assignStmt = $pdo->prepare('INSERT IGNORE INTO user_meters (user_id, meter_id) VALUES (?, ?)');
                    foreach ($assignedMeters as $meterId) {
                        if ($meterId > 0) {
                            $assignStmt->execute([$newUserId, $meterId]);
                        }
                    }
                }

                flash('success', "User '{$name}' (@{$username}) created successfully.");
                header('Location: ' . url('/admin/users.php'));
                exit;
            }
        }
    }

    /* 2. Edit User */
    if ($action === 'edit_user') {
        $userId   = (int) ($_POST['user_id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = in_array($_POST['role'] ?? '', ['admin', 'user'], true) ? $_POST['role'] : 'user';
        $whatsapp = trim($_POST['whatsapp_number'] ?? '');
        $assignedMeters = isset($_POST['meters']) && is_array($_POST['meters']) ? array_map('intval', $_POST['meters']) : [];

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $existing = $stmt->fetch();

        if (!$existing) {
            flash('error', 'User not found.');
        } elseif ($name === '' || $username === '') {
            flash('error', 'Please enter both full name and username.');
        } else {
            // Prevent changing oneself from admin to user if you are the current admin
            if ($userId === (int) $currentUser['id'] && $role !== 'admin') {
                flash('error', 'You cannot remove your own administrator privileges while logged in.');
            } else {
                // Check if username taken by another user
                $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id != ?');
                $stmt->execute([$username, $userId]);
                if ($stmt->fetch()) {
                    flash('error', "Username '{$username}' is already taken by another account.");
                } else {
                    if ($password !== '') {
                        if (strlen($password) < 4) {
                            flash('error', 'Password must be at least 4 characters.');
                        } else {
                            $hash = password_hash($password, PASSWORD_BCRYPT);
                            $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, password_hash = ?, role = ?, whatsapp_number = ? WHERE id = ?');
                            $stmt->execute([$name, $username, $hash, $role, $whatsapp ?: null, $userId]);
                        }
                    } else {
                        $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, role = ?, whatsapp_number = ? WHERE id = ?');
                        $stmt->execute([$name, $username, $role, $whatsapp ?: null, $userId]);
                    }

                    // Update meter assignments
                    $pdo->prepare('DELETE FROM user_meters WHERE user_id = ?')->execute([$userId]);
                    if ($role === 'user' && !empty($assignedMeters)) {
                        $assignStmt = $pdo->prepare('INSERT IGNORE INTO user_meters (user_id, meter_id) VALUES (?, ?)');
                        foreach ($assignedMeters as $meterId) {
                            if ($meterId > 0) {
                                $assignStmt->execute([$userId, $meterId]);
                            }
                        }
                    }

                    // If editing current logged in user, update session name
                    if ($userId === (int) $currentUser['id']) {
                        $_SESSION['user']['name'] = $name;
                    }

                    flash('success', "User '{$name}' updated successfully.");
                    header('Location: ' . url('/admin/users.php'));
                    exit;
                }
            }
        }
    }

    /* 3. Delete User */
    if ($action === 'delete_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($userId === (int) $currentUser['id']) {
            flash('error', 'You cannot delete your own logged-in account.');
        } else {
            // Check if user has submitted readings
            $checkReadings = $pdo->prepare('SELECT COUNT(*) FROM readings WHERE user_id = ?');
            $checkReadings->execute([$userId]);
            $readingCount = (int) $checkReadings->fetchColumn();

            if ($readingCount > 0) {
                flash('error', "Cannot delete this user: they have {$readingCount} submitted reading(s). Deleting would break historical audit records.");
            } else {
                // Delete user_meters assignments and user
                $pdo->prepare('DELETE FROM user_meters WHERE user_id = ?')->execute([$userId]);
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                flash('success', 'User deleted successfully.');
            }
        }
    }

    header('Location: ' . url('/admin/users.php' . (isset($_GET['edit']) ? '?edit=' . (int)$_GET['edit'] : '')));
    exit;
}

/* ── Fetch Data ──────────────────────────────────────────────────── */
// Users with reading count
$users = $pdo->query('
    SELECT u.*,
           (SELECT COUNT(*) FROM readings r WHERE r.user_id = u.id) AS reading_count
    FROM users u
    ORDER BY CASE WHEN u.role = "admin" THEN 0 ELSE 1 END, u.name ASC
')->fetchAll();

// All meters with building names
$meters = $pdo->query('
    SELECT m.*, b.name AS building_name
    FROM meters m
    LEFT JOIN buildings b ON b.id = m.building_id
    ORDER BY m.name ASC
')->fetchAll();

// User meter assignments lookup: user_id => [meter_id, ...]
$userMeters = [];
foreach ($pdo->query('SELECT user_id, meter_id FROM user_meters')->fetchAll() as $row) {
    $userMeters[(int)$row['user_id']][] = (int)$row['meter_id'];
}

// Meter lookup: meter_id => meter row
$metersLookup = [];
foreach ($meters as $m) {
    $metersLookup[(int)$m['id']] = $m;
}

// Editing state
$editingUser = null;
$editAssignedMeters = [];
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    foreach ($users as $u) {
        if ((int)$u['id'] === $editId) {
            $editingUser = $u;
            $editAssignedMeters = $userMeters[$editId] ?? [];
            break;
        }
    }
}

// Summary counts
$totalUsers = count($users);
$adminCount = 0;
$fieldUserCount = 0;
foreach ($users as $u) {
    if ($u['role'] === 'admin') $adminCount++;
    else $fieldUserCount++;
}

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Users Overview & List ─────────────────────────────────────── -->
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;">
    <div>
      <h2>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;vertical-align:-3px;margin-right:4px;color:var(--sky);"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Users
      </h2>
      <p class="hint" style="margin-bottom:8px;">Manage administrator and field user accounts, credentials, and meter assignments.</p>
    </div>
    <a href="#user-form" class="btn btn-amber btn-sm" style="white-space:nowrap;">+ Add user</a>
  </div>

  <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
    <span class="badge badge-neutral"><?= $totalUsers ?> total user<?= $totalUsers === 1 ? '' : 's' ?></span>
    <span class="badge badge-amber"><?= $adminCount ?> Admin<?= $adminCount === 1 ? '' : 's' ?></span>
    <span class="badge badge-green"><?= $fieldUserCount ?> Field User<?= $fieldUserCount === 1 ? '' : 's' ?></span>
  </div>

  <div class="divider"></div>

  <?php if (empty($users)): ?>
    <p class="empty-note">No users found.</p>
  <?php else: ?>
    <?php foreach ($users as $u): ?>
      <?php
        $uId = (int) $u['id'];
        $isSelf = ($uId === (int) $currentUser['id']);
        $assigned = $userMeters[$uId] ?? [];
        $readingCnt = (int) $u['reading_count'];
      ?>
      <div style="padding:14px 0;border-bottom:1px solid var(--line);">
        <div class="kv" style="align-items:center;padding:0 0 4px;">
          <div>
            <span style="font-weight:700;color:var(--ink);font-size:14px;"><?= htmlspecialchars($u['name']) ?></span>
            <span style="font-family:var(--mono);font-size:12px;color:var(--ink-soft);margin-left:4px;">@<?= htmlspecialchars($u['username']) ?></span>
            <?php if ($isSelf): ?>
              <span class="badge badge-neutral" style="font-size:10px;padding:2px 6px;">You</span>
            <?php endif; ?>
          </div>
          <div>
            <?php if ($u['role'] === 'admin'): ?>
              <span class="badge badge-amber">Admin</span>
            <?php else: ?>
              <span class="badge badge-green">Field User</span>
            <?php endif; ?>
          </div>
        </div>

        <div style="font-size:12px;color:var(--ink-soft);margin-bottom:4px;display:flex;gap:12px;flex-wrap:wrap;">
          <?php if (!empty($u['whatsapp_number'])): ?>
            <span>💬 <?= htmlspecialchars($u['whatsapp_number']) ?></span>
          <?php else: ?>
            <span style="font-style:italic;">No WhatsApp number</span>
          <?php endif; ?>
          <span>📋 <?= $readingCnt ?> reading<?= $readingCnt === 1 ? '' : 's' ?> submitted</span>
          <span>📅 Joined <?= date('d M Y', strtotime($u['created_at'])) ?></span>
        </div>

        <!-- Meter assignments -->
        <div style="font-size:12px;margin:6px 0;">
          <?php if ($u['role'] === 'admin'): ?>
            <span style="color:var(--ink-soft);font-size:11.5px;">⚡ Access to all meters (Admin privileges)</span>
          <?php elseif (!empty($assigned)): ?>
            <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
              <span style="color:var(--ink-soft);font-size:11.5px;margin-right:2px;">⚡ Assigned meters:</span>
              <?php foreach ($assigned as $mId): ?>
                <?php if (isset($metersLookup[$mId])): ?>
                  <span class="badge badge-neutral" style="font-size:10.5px;">
                    <?= htmlspecialchars($metersLookup[$mId]['name']) ?>
                  </span>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <span style="color:var(--red);font-size:11.5px;">⚠️ No meters assigned (cannot submit readings)</span>
          <?php endif; ?>
        </div>

        <!-- Actions -->
        <div style="display:flex;gap:6px;margin-top:8px;">
          <a href="<?= url('/admin/users.php?edit=' . $uId) ?>#user-form" class="btn btn-outline btn-sm">✏️ Edit</a>
          <?php if (!$isSelf): ?>
            <form method="post" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete user <?= htmlspecialchars(addslashes($u['name'])) ?>?');">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="user_id" value="<?= $uId ?>">
              <button class="btn btn-danger-outline btn-sm" type="submit">🗑 Delete</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ── Add / Edit User Form ──────────────────────────────────────── -->
<div class="card" id="user-form">
  <h2><?= $editingUser ? '✏️ Edit User: ' . htmlspecialchars($editingUser['name']) : '➕ Add New User' ?></h2>
  <p class="hint">
    <?= $editingUser
      ? 'Update profile information, change role, or reset password below.'
      : 'Create a new login for an administrator or field user.' ?>
  </p>

  <form method="post">
    <input type="hidden" name="action" value="<?= $editingUser ? 'edit_user' : 'add_user' ?>">
    <?php if ($editingUser): ?>
      <input type="hidden" name="user_id" value="<?= (int)$editingUser['id'] ?>">
    <?php endif; ?>

    <div class="field">
      <label>Full Name *</label>
      <input class="text-input" type="text" name="name" placeholder="e.g. Ahmad Razak" required
             value="<?= htmlspecialchars($editingUser['name'] ?? '') ?>">
    </div>

    <div class="grid2">
      <div class="field">
        <label>Username *</label>
        <input class="text-input" type="text" name="username" placeholder="e.g. ahmad" required
               value="<?= htmlspecialchars($editingUser['username'] ?? '') ?>">
      </div>

      <div class="field">
        <label>Role *</label>
        <select class="text-input" name="role" id="user-role-select" onchange="toggleMeterSection()">
          <option value="user" <?= ($editingUser['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>Field User</option>
          <option value="admin" <?= ($editingUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
        </select>
      </div>
    </div>

    <div class="field">
      <label>
        <?= $editingUser ? 'New Password (leave blank to keep current)' : 'Password *' ?>
      </label>
      <input class="text-input" type="password" name="password"
             placeholder="<?= $editingUser ? 'Enter new password only if changing' : 'At least 4 characters' ?>"
             <?= $editingUser ? '' : 'required' ?>>
    </div>

    <div class="field">
      <label>WhatsApp Number (optional)</label>
      <input class="text-input" type="text" name="whatsapp_number" placeholder="e.g. +60123456789"
             value="<?= htmlspecialchars($editingUser['whatsapp_number'] ?? '') ?>">
      <span style="font-size:11px;color:var(--ink-soft);margin-top:3px;display:block;">Used for simulated or real WhatsApp reminders and notifications.</span>
    </div>

    <!-- Assigned meters section for field users -->
    <div class="field" id="meter-assignment-section" style="margin-top:16px;">
      <label style="margin-bottom:8px;">Assign Meters (for Field Users)</label>
      <p class="hint" style="font-size:11.5px;margin-bottom:8px;">Select which TNB meter points this user can view and submit daily readings for.</p>
      
      <?php if (empty($meters)): ?>
        <p class="empty-note" style="padding:8px 0;">No meters available in the system yet. Add meters first in the Meters tab.</p>
      <?php else: ?>
        <div style="background:#F7F9FC;border:1px solid var(--line);border-radius:9px;padding:10px 12px;max-height:180px;overflow-y:auto;">
          <?php foreach ($meters as $m): ?>
            <?php
              $mId = (int)$m['id'];
              $isChecked = in_array($mId, $editAssignedMeters, true);
            ?>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;color:var(--ink);cursor:pointer;padding:4px 0;margin:0;">
              <input type="checkbox" name="meters[]" value="<?= $mId ?>" <?= $isChecked ? 'checked' : '' ?> style="cursor:pointer;accent-color:var(--navy);">
              <span>
                <?= htmlspecialchars($m['name']) ?>
                <?php if (!empty($m['building_name'])): ?>
                  <span class="badge badge-neutral" style="font-size:9.5px;padding:1px 5px;"><?= htmlspecialchars($m['building_name']) ?></span>
                <?php endif; ?>
                <?php if (!$m['is_active']): ?>
                  <span class="badge badge-red" style="font-size:9.5px;padding:1px 5px;">Inactive</span>
                <?php endif; ?>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div style="display:flex;gap:8px;margin-top:16px;">
      <button class="btn btn-primary" type="submit" style="flex:1;">
        <?= $editingUser ? 'Save changes' : 'Create user' ?>
      </button>
      <?php if ($editingUser): ?>
        <a href="<?= url('/admin/users.php') ?>" class="btn btn-outline" style="flex:0 0 auto;">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<script>
function toggleMeterSection() {
  const roleSelect = document.getElementById('user-role-select');
  const meterSection = document.getElementById('meter-assignment-section');
  if (roleSelect && meterSection) {
    if (roleSelect.value === 'admin') {
      meterSection.style.display = 'none';
    } else {
      meterSection.style.display = 'block';
    }
  }
}
// Initialize on page load
toggleMeterSection();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
