<?php
// meal_actions.php - Controller for Meal Operations (Add, Edit, Delete, Get)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = get_db_connection();

// GET request for fetching meal JSON (used by Edit modal)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get') {
    header('Content-Type: application/json');
    if (!is_logged_in()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $meal_id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM meals WHERE id = ?");
    $stmt->execute([$meal_id]);
    $meal = $stmt->fetch();

    if (!$meal) {
        echo json_encode(['success' => false, 'message' => 'Meal not found']);
        exit;
    }

    $eaters_stmt = $pdo->prepare("SELECT user_id, hosteller_code, share_amount FROM meal_eaters WHERE meal_id = ?");
    $eaters_stmt->execute([$meal_id]);
    $meal['eaters'] = $eaters_stmt->fetchAll();

    echo json_encode(['success' => true, 'meal' => $meal]);
    exit;
}

// POST requests require logged-in user and CSRF verification
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    set_flash('error', 'Invalid security token. Please try again.');
    header("Location: dashboard.php");
    exit;
}

$action = $_POST['action'] ?? '';
$current_user = get_logged_in_user();
$user_id = $current_user['id'];
$user_display = $current_user['full_name'] . ($current_user['code'] ? ' (Code ' . $current_user['code'] . ')' : '');

// -------------------------------------------------------------------------
// 1. CREATE MEAL ENTRY
// -------------------------------------------------------------------------
if ($action === 'create') {
    $month_id = (int)($_POST['month_id'] ?? 1);
    $title = clean($_POST['title'] ?? '');
    $meal_date = clean($_POST['meal_date'] ?? date('Y-m-d'));
    $meal_time = clean($_POST['meal_time'] ?? date('H:i:s'));
    $meal_type = clean($_POST['meal_type'] ?? 'Lunch');
    $total_price = (float)($_POST['total_price'] ?? 0);
    $eater_ids = isset($_POST['eater_ids']) && is_array($_POST['eater_ids']) ? array_map('intval', $_POST['eater_ids']) : [];

    if (empty($title)) {
        set_flash('error', 'Please provide a description or title for the meal.');
        header("Location: dashboard.php");
        exit;
    }

    if ($total_price <= 0) {
        set_flash('error', 'Total meal price must be greater than zero.');
        header("Location: dashboard.php");
        exit;
    }

    if (empty($eater_ids)) {
        set_flash('error', 'You must select at least one hosteller code who ate this meal.');
        header("Location: dashboard.php");
        exit;
    }

    $split_count = count($eater_ids);
    $per_person_price = round($total_price / $split_count, 2);
    $edit_summary = "Initial entry logged by " . $user_display;

    try {
        $pdo->beginTransaction();

        $insert_stmt = $pdo->prepare("
            INSERT INTO meals (
                month_id, meal_date, meal_time, meal_type, title, 
                total_price, split_count, per_person_price, 
                created_by, created_at, last_edited_by, last_edited_at, edit_summary
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), ?)
        ");
        $insert_stmt->execute([
            $month_id, $meal_date, $meal_time, $meal_type, $title,
            $total_price, $split_count, $per_person_price,
            $user_id, $user_id, $edit_summary
        ]);
        $meal_id = $pdo->lastInsertId();

        // Fetch hosteller codes to insert into meal_eaters
        $in_clause = implode(',', array_fill(0, count($eater_ids), '?'));
        $users_stmt = $pdo->prepare("SELECT id, code, full_name FROM users WHERE id IN ($in_clause)");
        $users_stmt->execute($eater_ids);
        $eaters_info = $users_stmt->fetchAll();

        $eater_insert = $pdo->prepare("INSERT INTO meal_eaters (meal_id, user_id, hosteller_code, share_amount) VALUES (?, ?, ?, ?)");
        $divider_codes = [];

        foreach ($eaters_info as $eater) {
            $eater_insert->execute([$meal_id, $eater['id'], $eater['code'] ?? '?', $per_person_price]);
            $divider_codes[] = $eater['code'] ?? $eater['full_name'];
        }

        // Log to audit trail
        $audit_desc = "$user_display created meal \"$title\" (Rs. " . number_format($total_price, 2) . ") split between " 
                    . implode(', ', $divider_codes) . " ($split_count eaters, Rs. " . number_format($per_person_price, 2) . " each).";
        log_audit($pdo, $meal_id, $user_id, 'CREATE', $audit_desc);

        $pdo->commit();
        set_flash('success', "Meal entry added successfully! Rs. " . number_format($total_price, 2) . " split across $split_count hostellers (Rs. " . number_format($per_person_price, 2) . " each).");
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', "Database error saving meal: " . $e->getMessage());
    }

    header("Location: dashboard.php#meals-section");
    exit;
}

// -------------------------------------------------------------------------
// 2. UPDATE / EDIT MEAL ENTRY
// -------------------------------------------------------------------------
if ($action === 'update') {
    $meal_id = (int)($_POST['meal_id'] ?? 0);
    $title = clean($_POST['title'] ?? '');
    $meal_date = clean($_POST['meal_date'] ?? date('Y-m-d'));
    $meal_time = clean($_POST['meal_time'] ?? date('H:i:s'));
    $meal_type = clean($_POST['meal_type'] ?? 'Lunch');
    $total_price = (float)($_POST['total_price'] ?? 0);
    $user_edit_summary = clean($_POST['edit_summary'] ?? '');
    $eater_ids = isset($_POST['eater_ids']) && is_array($_POST['eater_ids']) ? array_map('intval', $_POST['eater_ids']) : [];

    if (!$meal_id) {
        set_flash('error', 'Invalid meal ID specified.');
        header("Location: dashboard.php");
        exit;
    }

    if (empty($title) || $total_price <= 0 || empty($eater_ids)) {
        set_flash('error', 'Please fill in all required fields and select at least one hosteller code.');
        header("Location: dashboard.php");
        exit;
    }

    // Fetch previous meal state for audit comparison
    $check_stmt = $pdo->prepare("SELECT * FROM meals WHERE id = ?");
    $check_stmt->execute([$meal_id]);
    $prev_meal = $check_stmt->fetch();
    if (!$prev_meal) {
        set_flash('error', 'Meal entry not found.');
        header("Location: dashboard.php");
        exit;
    }

    $split_count = count($eater_ids);
    $per_person_price = round($total_price / $split_count, 2);

    $summary_prefix = "Edited by " . $user_display;
    $final_summary = !empty($user_edit_summary) ? ($summary_prefix . ": " . $user_edit_summary) : ($summary_prefix . " (Price: Rs. " . number_format($total_price, 2) . ", $split_count eaters)");

    try {
        $pdo->beginTransaction();

        $update_stmt = $pdo->prepare("
            UPDATE meals 
            SET title = ?, meal_date = ?, meal_time = ?, meal_type = ?, 
                total_price = ?, split_count = ?, per_person_price = ?, 
                last_edited_by = ?, last_edited_at = NOW(), edit_summary = ? 
            WHERE id = ?
        ");
        $update_stmt->execute([
            $title, $meal_date, $meal_time, $meal_type,
            $total_price, $split_count, $per_person_price,
            $user_id, $final_summary, $meal_id
        ]);

        // Remove old eaters and insert updated eaters
        $del_eaters = $pdo->prepare("DELETE FROM meal_eaters WHERE meal_id = ?");
        $del_eaters->execute([$meal_id]);

        $in_clause = implode(',', array_fill(0, count($eater_ids), '?'));
        $users_stmt = $pdo->prepare("SELECT id, code, full_name FROM users WHERE id IN ($in_clause)");
        $users_stmt->execute($eater_ids);
        $eaters_info = $users_stmt->fetchAll();

        $eater_insert = $pdo->prepare("INSERT INTO meal_eaters (meal_id, user_id, hosteller_code, share_amount) VALUES (?, ?, ?, ?)");
        $divider_codes = [];

        foreach ($eaters_info as $eater) {
            $eater_insert->execute([$meal_id, $eater['id'], $eater['code'] ?? '?', $per_person_price]);
            $divider_codes[] = $eater['code'] ?? $eater['full_name'];
        }

        // Log to audit trail
        $audit_desc = "$user_display modified meal #$meal_id \"$title\". New Price: Rs. " . number_format($total_price, 2) 
                    . ", Dividers: [" . implode(', ', $divider_codes) . "] ($split_count people, Rs. " . number_format($per_person_price, 2) . " each)."
                    . (!empty($user_edit_summary) ? " Note: " . $user_edit_summary : "");
        log_audit($pdo, $meal_id, $user_id, 'UPDATE', $audit_desc);

        $pdo->commit();
        set_flash('success', "Meal #$meal_id successfully updated by $user_display! All divider bills recalculated.");
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', "Database error updating meal: " . $e->getMessage());
    }

    header("Location: dashboard.php#meals-section");
    exit;
}

// -------------------------------------------------------------------------
// 3. DELETE MEAL ENTRY
// -------------------------------------------------------------------------
if ($action === 'delete') {
    $meal_id = (int)($_POST['meal_id'] ?? 0);
    if (!$meal_id) {
        set_flash('error', 'Invalid meal ID specified.');
        header("Location: dashboard.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM meals WHERE id = ?");
    $stmt->execute([$meal_id]);
    $meal = $stmt->fetch();

    if (!$meal) {
        set_flash('error', 'Meal entry not found.');
        header("Location: dashboard.php");
        exit;
    }

    // Only Admin or the creator/last editor can delete
    if (!is_admin() && $meal['created_by'] != $user_id && $meal['last_edited_by'] != $user_id) {
        set_flash('error', 'Permission denied. Only the entry creator or an administrator can delete meals.');
        header("Location: dashboard.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $del = $pdo->prepare("DELETE FROM meals WHERE id = ?");
        $del->execute([$meal_id]);

        $audit_desc = "$user_display deleted meal #$meal_id \"{$meal['title']}\" (worth Rs. " . number_format($meal['total_price'], 2) . ").";
        log_audit($pdo, $meal_id, $user_id, 'DELETE', $audit_desc);

        $pdo->commit();
        set_flash('success', "Meal entry #$meal_id has been removed and all hosteller balances recalculated.");
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', "Database error deleting meal: " . $e->getMessage());
    }

    header("Location: dashboard.php#meals-section");
    exit;
}

header("Location: dashboard.php");
exit;
