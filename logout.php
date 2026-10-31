<?php
// logout.php - End session and redirect
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

session_start();
set_flash('success', 'You have been safely signed out. See you at Cafe Kamaal!');
header("Location: index.php");
exit;
