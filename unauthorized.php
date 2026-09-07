<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth-check.php';
$pageTitle = 'Not authorized';
require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
  <h2>You don't have access to that page</h2>
  <p class="hint">This area is restricted to a different role. Use the navigation above to go back to your own dashboard.</p>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
