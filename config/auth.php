<?php
// config/auth.php - Authentication and Session Management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
        'code'      => $_SESSION['code'] ?? null,
    ];
}

function is_admin() {
    return is_logged_in() && ($_SESSION['role'] === 'admin');
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = "Please log in to access the system.";
        header("Location: index.php");
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        $_SESSION['flash_error'] = "Access denied. Administrator privileges required.";
        header("Location: dashboard.php");
        exit;
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
