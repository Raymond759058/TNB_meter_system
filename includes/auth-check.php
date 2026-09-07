<?php
/**
 * Include at the very top of any protected page:
 *   require_once __DIR__ . '/../includes/auth-check.php';
 *   require_role('admin');   // or require_role('user'), or omit for "any logged-in user"
 */

require_once __DIR__ . '/../config/config.php';

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        header('Location: ' . url('/auth/login.php'));
        exit;
    }
    return $user;
}

function require_role(string $role): array {
    $user = require_login();
    if ($user['role'] !== $role) {
        header('Location: ' . url('/unauthorized.php'));
        exit;
    }
    return $user;
}
