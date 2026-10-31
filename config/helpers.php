<?php
// config/helpers.php - General Helper Utilities

require_once __DIR__ . '/db.php';

function clean($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function format_currency($amount) {
    return 'Rs. ' . number_format((float)$amount, 2);
}

function format_date($date_str, $format = 'M d, Y') {
    if (empty($date_str)) return '-';
    $time = strtotime($date_str);
    return date($format, $time);
}

function format_datetime($datetime_str, $format = 'M d, Y h:i A') {
    if (empty($datetime_str)) return '-';
    $time = strtotime($datetime_str);
    return date($format, $time);
}

function time_ago($datetime_str) {
    if (empty($datetime_str)) return 'recently';
    $time = strtotime($datetime_str);
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    return floor($diff / 86400) . ' days ago';
}

function set_flash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_' . $type] = $message;
}

function get_flash($type) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

/**
 * Validate that a code is a single alphabet character (A-Z)
 */
function is_valid_single_alphabet_code($code) {
    return (bool)preg_match('/^[A-Za-z]$/', trim($code));
}

/**
 * Log action into audit_trail
 */
function log_audit($pdo, $meal_id, $user_id, $action, $description) {
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_trail (meal_id, action, user_id, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$meal_id, $action, $user_id, $description]);
    } catch (Exception $e) {
        // Suppress audit log failures so primary action succeeds
    }
}

/**
 * Get active or selected billing month
 */
function get_current_billing_month($pdo, $selected_id = null) {
    if ($selected_id) {
        $stmt = $pdo->prepare("SELECT * FROM billing_months WHERE id = ?");
        $stmt->execute([$selected_id]);
        $row = $stmt->fetch();
        if ($row) return $row;
    }
    
    // Default to current active month
    $stmt = $pdo->query("SELECT * FROM billing_months WHERE is_current = 1 ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch();
    if ($row) return $row;

    // Fallback to latest
    $stmt = $pdo->query("SELECT * FROM billing_months ORDER BY id DESC LIMIT 1");
    return $stmt->fetch();
}

/**
 * Fetch the single last edited/created meal to fulfill requirement:
 * "Whenever a hosteller login the system then the system should show the bill which is edited by the last person"
 */
function get_last_edited_meal($pdo, $month_id = null) {
    $sql = "SELECT m.*, 
                   u_creator.full_name AS creator_name, u_creator.code AS creator_code,
                   u_editor.full_name AS editor_name, u_editor.code AS editor_code
            FROM meals m
            LEFT JOIN users u_creator ON m.created_by = u_creator.id
            LEFT JOIN users u_editor ON m.last_edited_by = u_editor.id ";
    
    $params = [];
    if ($month_id) {
        $sql .= "WHERE m.month_id = ? ";
        $params[] = $month_id;
    }
    $sql .= "ORDER BY m.last_edited_at DESC, m.id DESC LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $meal = $stmt->fetch();
    
    if (!$meal) {
        return null;
    }
    
    // Fetch dividers (hostellers who ate that meal)
    $stmt2 = $pdo->prepare("SELECT me.*, u.full_name 
                            FROM meal_eaters me 
                            JOIN users u ON me.user_id = u.id 
                            WHERE me.meal_id = ? 
                            ORDER BY me.hosteller_code ASC");
    $stmt2->execute([$meal['id']]);
    $meal['eaters'] = $stmt2->fetchAll();
    
    return $meal;
}

/**
 * Fetch all active hostellers
 */
function get_all_hostellers($pdo) {
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'hosteller' AND is_active = 1 ORDER BY code ASC, full_name ASC");
    return $stmt->fetchAll();
}
