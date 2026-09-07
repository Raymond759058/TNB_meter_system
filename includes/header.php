<?php
/** Expects $pageTitle to be set by the including page. */
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> · <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
</head>
<body>
<div id="app">
  <div class="topbar">
    <div class="topbar-row1">
      <a href="<?= url('/index.php') ?>" class="brand" style="text-decoration:none;color:#fff;">
        <div class="brand-mark">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:17px;height:17px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        </div>
        <div>
          <div class="brand-name"><?= APP_NAME ?></div>
          <?php if ($user): ?>
            <div class="brand-sub"><?= htmlspecialchars($user['name']) ?> · <?= $user['role'] === 'admin' ? 'Admin' : 'Field User' ?></div>
          <?php endif; ?>
        </div>
      </a>
      <?php if ($user): ?>
        <a href="<?= url('/auth/logout.php') ?>" class="btn btn-outline btn-sm" style="width:auto;">Log out</a>
      <?php endif; ?>
    </div>
    <?php if ($user): ?>
    <div class="topbar-row2">
      <?php if ($user['role'] === 'admin'): ?>
        <a href="<?= url('/admin/dashboard.php') ?>" class="nav-link">Dashboard</a>
        <a href="<?= url('/admin/history.php') ?>" class="nav-link">History</a>
        <a href="<?= url('/admin/buildings.php') ?>" class="nav-link">Buildings</a>
        <a href="<?= url('/admin/meters.php') ?>" class="nav-link">Meters</a>
        <a href="<?= url('/admin/users.php') ?>" class="nav-link">Users</a>
        <a href="<?= url('/admin/alerts.php') ?>" class="nav-link">Alerts</a>
        <a href="<?= url('/admin/settings.php') ?>" class="nav-link">Settings</a>
      <?php else: ?>
        <a href="<?= url('/user/submit-v1.php') ?>" class="nav-link">Submit (V1)</a>
        <a href="<?= url('/user/submit-v2.php') ?>" class="nav-link">Submit (V2)</a>
        <a href="<?= url('/user/history.php') ?>" class="nav-link">History</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="screen">
    <?php $flashMsg = flash('success'); if ($flashMsg): ?>
      <div class="flash flash-success"><?= htmlspecialchars($flashMsg) ?></div>
    <?php endif; ?>
    <?php $flashErr = flash('error'); if ($flashErr): ?>
      <div class="flash flash-error"><?= htmlspecialchars($flashErr) ?></div>
    <?php endif; ?>
