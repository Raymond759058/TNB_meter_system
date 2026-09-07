<?php
require_once __DIR__ . '/config/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: ' . url('/auth/login.php'));
    exit;
}

$role = $_SESSION['user']['role'];
header('Location: ' . url($role === 'admin' ? '/admin/dashboard.php' : '/user/submit-v1.php'));
exit;
