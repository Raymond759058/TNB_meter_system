<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

function current_user_exists(): bool {
    return isset($_SESSION['user']);
}

if (current_user_exists()) {
    $role = $_SESSION['user']['role'];
    header('Location: ' . url($role === 'admin' ? '/admin/dashboard.php' : '/user/submit-v1.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id'   => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
        ];
        header('Location: ' . url($user['role'] === 'admin' ? '/admin/dashboard.php' : '/user/submit-v1.php'));
        exit;
    }
    $error = 'Incorrect username or password.';
}

$pageTitle = 'Log in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Log in · <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
</head>
<body>
<div id="app">
  <div class="login-wrap">
    <div class="login-mark">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:30px;height:30px;color:#fff;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
    </div>
    <h1><?= APP_NAME ?></h1>
    <p class="sub">TNB electricity meter monitoring — daily readings, over-usage alerts, and missing-reading reminders.</p>
    <div class="card">
      <?php if ($error): ?>
        <div class="flash flash-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="field">
          <label>Username</label>
          <input class="text-input" type="text" name="username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Password</label>
          <input class="text-input" type="password" name="password" required>
        </div>
        <button class="btn btn-amber" type="submit">Log in</button>
      </form>
    </div>
    <p class="empty-note">Demo accounts — admin / user1, password: password123.<br>For this trial, submitted photos can be of any paper with a written reading, standing in for a real TNB meter.</p>
  </div>
</div>
</body>
</html>
