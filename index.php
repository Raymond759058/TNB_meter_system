<?php
require_once __DIR__ . '/config/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: /tnb-meter-system/auth/login.php');
    exit;
}

header('Location: ' . ($_SESSION['user']['role'] === 'admin' ? '/admin/dashboard.php' : '/user/submit-v1.php'));
exit;
