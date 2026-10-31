<?php
// admin.php - Administrator Control Panel
// Main Administer: Register hostellers, assign single alphabet codes, share credentials
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_admin();

$page_title = 'Admin Mess Manager';
require_once __DIR__ . '/includes/header.php';

// Handle POST actions for Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_action = $_POST['admin_action'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        set_flash('error', 'Invalid security token.');
        header("Location: admin.php");
        exit;
    }

    // 1. Admin Add New Hosteller
    if ($admin_action === 'add_hosteller') {
        $full_name = clean($_POST['full_name'] ?? '');
        $username = clean($_POST['username'] ?? '');
        $password = $_POST['password'] ?? 'password123';
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $room_no = clean($_POST['room_no'] ?? '');
        $phone = clean($_POST['phone'] ?? '');

        if (empty($full_name) || empty($username) || empty($code) || empty($password)) {
            set_flash('error', 'Name, Username, Code, and Password are required.');
        } elseif (!is_valid_single_alphabet_code($code)) {
            set_flash('error', 'Code must be a single alphabet character (A to Z).');
        } else {
            // Check if code or username is already taken
            $check = $pdo->prepare("SELECT id FROM users WHERE code = ? OR username = ?");
            $check->execute([$code, $username]);
            if ($check->fetch()) {
                set_flash('error', "Either Code '{$code}' or Username '{$username}' is already in use.");
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password, full_name, role, code, phone, room_no, plain_password_hint, is_active)
                    VALUES (?, ?, ?, 'hosteller', ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$username, $hash, $full_name, $code, $phone, $room_no, $password]);
                $new_id = $pdo->lastInsertId();

                log_audit($pdo, null, $current_user['id'], 'CREATE', "Admin created new hosteller $full_name with Code [$code] (Username: $username).");
                set_flash('success', "Hosteller $full_name added with Code [$code] successfully!");
            }
        }
        header("Location: admin.php");
        exit;
    }

    // 2. Admin Reset Hosteller Password / Edit
    if ($admin_action === 'edit_hosteller') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $full_name = clean($_POST['full_name'] ?? '');
        $room_no = clean($_POST['room_no'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $new_pass = $_POST['new_password'] ?? '';

        if ($user_id && !empty($full_name)) {
            if (!empty($new_pass)) {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, room_no = ?, phone = ?, password = ?, plain_password_hint = ? WHERE id = ?");
                $stmt->execute([$full_name, $room_no, $phone, $hash, $new_pass, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, room_no = ?, phone = ? WHERE id = ?");
                $stmt->execute([$full_name, $room_no, $phone, $user_id]);
            }
            log_audit($pdo, null, $current_user['id'], 'UPDATE', "Admin updated hosteller ID #$user_id ($full_name).");
            set_flash('success', "Hosteller details updated successfully.");
        }
        header("Location: admin.php");
        exit;
    }

    // 3. Admin Create New Billing Month
    if ($admin_action === 'create_month') {
        $name = clean($_POST['month_name'] ?? '');
        $start_date = clean($_POST['start_date'] ?? date('Y-m-01'));
        $end_date = clean($_POST['end_date'] ?? date('Y-m-t'));
        $notes = clean($_POST['notes'] ?? '');
        $is_current = isset($_POST['set_active']) ? 1 : 0;

        if (!empty($name)) {
            if ($is_current) {
                $pdo->exec("UPDATE billing_months SET is_current = 0");
            }
            $stmt = $pdo->prepare("INSERT INTO billing_months (name, start_date, end_date, is_current, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $start_date, $end_date, $is_current, $notes]);
            set_flash('success', "New billing cycle \"$name\" created successfully.");
        }
        header("Location: admin.php");
        exit;
    }

    // 4. Admin Remove Member (Permanent Delete or Deactivate)
    if ($admin_action === 'remove_hosteller') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $removal_mode = $_POST['removal_mode'] ?? 'hard_delete';

        if (!$user_id) {
            set_flash('error', 'Invalid hosteller ID specified.');
            header("Location: admin.php");
            exit;
        }

        // Verify target exists and is a hosteller (cannot delete admin)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $target_user = $stmt->fetch();

        if (!$target_user || $target_user['role'] === 'admin') {
            set_flash('error', 'Cannot remove an administrator account or non-existent user.');
            header("Location: admin.php");
            exit;
        }

        $h_name = $target_user['full_name'];
        $h_code = $target_user['code'] ?? 'None';

        try {
            $pdo->beginTransaction();

            if ($removal_mode === 'deactivate') {
                // Soft removal: suspend login and free code so someone else can use it
                $stmt = $pdo->prepare("UPDATE users SET is_active = 0, code = NULL WHERE id = ?");
                $stmt->execute([$user_id]);

                log_audit($pdo, null, $current_user['id'], 'UPDATE', "Admin deactivated member $h_name (former Code [$h_code]). Account suspended.");
                $pdo->commit();
                set_flash('success', "Member $h_name (Code [$h_code]) has been deactivated. Historical records are preserved and Code [$h_code] is now available.");
            } else {
                // Complete permanent removal:
                // 1. Fetch meals where this member was in divider
                $eater_meals_stmt = $pdo->prepare("SELECT DISTINCT meal_id FROM meal_eaters WHERE user_id = ?");
                $eater_meals_stmt->execute([$user_id]);
                $affected_meal_ids = $eater_meals_stmt->fetchAll(PDO::FETCH_COLUMN);

                // 2. Remove member from meal_eaters
                $del_eaters = $pdo->prepare("DELETE FROM meal_eaters WHERE user_id = ?");
                $del_eaters->execute([$user_id]);

                // 3. For each affected meal, recalculate split among remaining eaters or remove empty meal
                foreach ($affected_meal_ids as $m_id) {
                    $rem_stmt = $pdo->prepare("SELECT * FROM meal_eaters WHERE meal_id = ?");
                    $rem_stmt->execute([$m_id]);
                    $remaining_eaters = $rem_stmt->fetchAll();
                    $rem_count = count($remaining_eaters);

                    if ($rem_count > 0) {
                        $m_stmt = $pdo->prepare("SELECT total_price FROM meals WHERE id = ?");
                        $m_stmt->execute([$m_id]);
                        $total_price = (float)$m_stmt->fetchColumn();

                        $new_per_person = round($total_price / $rem_count, 2);

                        $up_meal = $pdo->prepare("UPDATE meals SET split_count = ?, per_person_price = ?, last_edited_by = ?, last_edited_at = NOW(), edit_summary = CONCAT('Auto-recalculated after member ', ?, ' was removed') WHERE id = ?");
                        $up_meal->execute([$rem_count, $new_per_person, $current_user['id'], $h_name, $m_id]);

                        $up_eaters = $pdo->prepare("UPDATE meal_eaters SET share_amount = ? WHERE meal_id = ?");
                        $up_eaters->execute([$new_per_person, $m_id]);
                    } else {
                        // Empty meal left, remove it
                        $del_meal = $pdo->prepare("DELETE FROM meals WHERE id = ?");
                        $del_meal->execute([$m_id]);
                    }
                }

                // 4. Reassign meal author & editor references to current admin to prevent foreign key errors
                $up_author = $pdo->prepare("UPDATE meals SET created_by = ? WHERE created_by = ?");
                $up_author->execute([$current_user['id'], $user_id]);

                $up_editor = $pdo->prepare("UPDATE meals SET last_edited_by = ? WHERE last_edited_by = ?");
                $up_editor->execute([$current_user['id'], $user_id]);

                // 5. Delete member from users table
                $del_user = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $del_user->execute([$user_id]);

                log_audit($pdo, null, $current_user['id'], 'DELETE', "Admin completely removed member $h_name (Code [$h_code]). All affected meals recalculated and code freed.");
                $pdo->commit();
                set_flash('success', "Member $h_name (Code [$h_code]) has been permanently removed! All affected meal splits were recalculated and Code [$h_code] is now available.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', "Database error removing member: " . $e->getMessage());
        }

        header("Location: admin.php");
        exit;
    }

    // 5. Admin Reactivate Member
    if ($admin_action === 'reactivate_hosteller') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_code = strtoupper(trim($_POST['code'] ?? ''));

        if ($user_id && !empty($new_code)) {
            // Check if code taken
            $check = $pdo->prepare("SELECT id FROM users WHERE code = ? AND is_active = 1 AND id != ?");
            $check->execute([$new_code, $user_id]);
            if ($check->fetch()) {
                set_flash('error', "Code [$new_code] is already assigned to another hosteller.");
            } else {
                $stmt = $pdo->prepare("UPDATE users SET is_active = 1, code = ? WHERE id = ?");
                $stmt->execute([$new_code, $user_id]);
                log_audit($pdo, null, $current_user['id'], 'UPDATE', "Admin reactivated member ID #$user_id with Code [$new_code].");
                set_flash('success', "Member reactivated successfully with Code [$new_code].");
            }
        }
        header("Location: admin.php");
        exit;
    }

    // 6. Admin Reset All (Start New Calculation)
    if ($admin_action === 'reset_all') {
        $reset_scope = $_POST['reset_scope'] ?? 'current_month';
        $confirm = trim($_POST['confirmation'] ?? '');

        if (strtoupper($confirm) !== 'RESET') {
            set_flash('error', 'Reset cancelled. You must type RESET in capital letters to confirm.');
            header("Location: admin.php");
            exit;
        }

        try {
            $pdo->beginTransaction();

            if ($reset_scope === 'current_month') {
                $month_id = (int)($_POST['month_id'] ?? ($current_month['id'] ?? 1));
                // Delete all meals in the current billing month (cascades to meal_eaters)
                $del = $pdo->prepare("DELETE FROM meals WHERE month_id = ?");
                $del->execute([$month_id]);

                log_audit($pdo, null, $current_user['id'], 'DELETE', "Admin reset all meal entries for billing month #$month_id to start new calculation.");
                $pdo->commit();
                set_flash('success', "All meal entries for the current cycle have been reset to Rs. 0.00! Hosteller group members are preserved and you can now log new meals.");
            } elseif ($reset_scope === 'all_cycles') {
                // Delete all meals across all cycles
                $pdo->exec("DELETE FROM meal_eaters");
                $pdo->exec("DELETE FROM meals");

                log_audit($pdo, null, $current_user['id'], 'DELETE', "Admin reset all hotel meal calculations across all months. All balances set to Rs. 0.00.");
                $pdo->commit();
                set_flash('success', "All hotel meal calculations have been reset to Rs. 0.00! Hosteller codes (A-Z) and accounts remain ready for new calculations.");
            } elseif ($reset_scope === 'new_month_cycle') {
                $new_cycle_name = clean($_POST['new_cycle_name'] ?? '');
                if (empty($new_cycle_name)) {
                    $new_cycle_name = 'Mess Cycle ' . date('F Y');
                }
                $start = clean($_POST['new_cycle_start'] ?? date('Y-m-01'));
                $end = clean($_POST['new_cycle_end'] ?? date('Y-m-t'));

                $pdo->exec("UPDATE billing_months SET is_current = 0");
                $stmt = $pdo->prepare("INSERT INTO billing_months (name, start_date, end_date, is_current, notes) VALUES (?, ?, ?, 1, 'Fresh new calculation cycle')");
                $stmt->execute([$new_cycle_name, $start, $end]);

                log_audit($pdo, null, $current_user['id'], 'CREATE', "Admin started fresh new calculation cycle: \"$new_cycle_name\".");
                $pdo->commit();
                set_flash('success', "Fresh calculation cycle \"$new_cycle_name\" created and set as active with Rs. 0.00 starting balance!");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', "Database error during reset: " . $e->getMessage());
        }

        header("Location: admin.php");
        exit;
    }
}

// Fetch all hostellers
$hostellers_stmt = $pdo->query("SELECT * FROM users WHERE role = 'hosteller' ORDER BY code ASC, full_name ASC");
$all_hostellers = $hostellers_stmt->fetchAll();

// Determine next available code
$used_codes = array_map('strtoupper', array_column($all_hostellers, 'code'));
$alphabet = range('A', 'Z');
$available_codes = array_values(array_diff($alphabet, $used_codes));
$next_suggested_code = $available_codes[0] ?? 'Z';

// Fetch Recent Audit Logs
$audit_stmt = $pdo->query("
    SELECT a.*, u.full_name, u.code, u.role
    FROM audit_trail a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC LIMIT 25
");
$recent_audits = $audit_stmt->fetchAll();

// Generate WhatsApp credentials share text
$share_text = "🍽️ *Cafe Kamaal - Hostel Mess Bill Calculator*\n";
$share_text .= "Hostel Block Monthly Mess Portal\n\n";
$share_text .= "📋 *Group Hosteller Login Credentials:*\n";
foreach ($all_hostellers as $idx => $h) {
    $num = $idx + 1;
    $share_text .= "{$num}. *{$h['full_name']}* [Code *{$h['code']}*]\n";
    $share_text .= "   • Username: `{$h['username']}`\n";
    $share_text .= "   • Password: `" . ($h['plain_password_hint'] ?? 'password123') . "`\n";
    $share_text .= "   • Room: {$h['room_no']}\n\n";
}
$share_text .= "🔗 Login to log meals, edit entries, and view live bill calculations.\n";
?>

<div class="space-y-8">

    <!-- Admin Top Banner -->
    <div class="bg-gradient-to-r from-navy-900 via-slate-800 to-navy-900 text-white rounded-3xl p-6 sm:p-8 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="px-2.5 py-0.5 rounded-full bg-brand-500 text-white text-[11px] font-black uppercase tracking-wider">
                    Administer Control Center
                </span>
                <span class="text-xs text-slate-300">Authorized: <?= clean($current_user['full_name']) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black">Group Hostellers & Credential Distribution</h1>
            <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-2xl leading-relaxed">
                As the main administrator, register and manage the group of hostellers, assign unique single alphabet codes (A-Z), and share login credentials with your hostel group so everyone can log, edit, and inspect meal bills transparently.
            </p>
        </div>

        <div class="flex items-center gap-3 shrink-0 flex-wrap">
            <button onclick="openResetAllModal()" class="px-4 py-2.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold shadow-lg transition flex items-center gap-2">
                <i class="fa-solid fa-rotate-left"></i> Reset All / New Calculation
            </button>
            <button onclick="copyToClipboard(<?= htmlspecialchars(json_encode($share_text)) ?>, 'WhatsApp credentials message copied! You can now paste it into your hostel WhatsApp group.')"
                    class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg transition flex items-center gap-2">
                <i class="fa-brands fa-whatsapp text-base"></i> Copy WhatsApp Credentials
            </button>
            <a href="print_bill.php?view=credentials" target="_blank" class="px-4 py-2.5 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                <i class="fa-solid fa-print"></i> Print Roster Sheet
            </a>
        </div>
    </div>

    <!-- Grid: Register New Hosteller + WhatsApp Sharing Preview -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Form: Register New Hosteller to Group (5 cols) -->
        <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center space-x-3 mb-5 border-b border-slate-100 pb-4">
                <span class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-user-plus"></i>
                </span>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Register Group Hosteller</h3>
                    <p class="text-xs text-slate-500">Assign single alphabet code and create credentials</p>
                </div>
            </div>

            <form action="admin.php" method="POST" class="space-y-4">
                <input type="hidden" name="admin_action" value="add_hosteller">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Hosteller Full Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="full_name" required placeholder="e.g. Tariq Mehmood"
                           class="w-full px-3.5 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-brand-500 text-xs font-medium">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Single Alphabet Code (A-Z) <span class="text-rose-500">*</span>
                        </label>
                        <select name="code" required class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-brand-500 text-xs font-bold font-mono bg-white">
                            <?php foreach ($available_codes as $ac): ?>
                                <option value="<?= $ac ?>" <?= ($ac === $next_suggested_code) ? 'selected' : '' ?>>
                                    Code [<?= $ac ?>] <?= ($ac === $next_suggested_code) ? '(Suggested)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Hostel Room #
                        </label>
                        <input type="text" name="room_no" placeholder="e.g. Room 204"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-brand-500 text-xs">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Username <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="username" required placeholder="e.g. hosteller_g"
                               value="hosteller_<?= strtolower($next_suggested_code) ?>"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-brand-500 text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Login Password <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="password" required value="password123"
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-brand-500 text-xs font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Phone / WhatsApp
                    </label>
                    <input type="text" name="phone" placeholder="e.g. +92 300 9876543"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-brand-500 text-xs">
                </div>

                <button type="submit" class="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl text-xs shadow transition flex items-center justify-center gap-2 mt-2">
                    <i class="fa-solid fa-plus"></i> Add Hosteller & Assign Code
                </button>
            </form>
        </div>

        <!-- Credentials Distribution & Quick Copy Hub (7 cols) -->
        <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
                    <div class="flex items-center space-x-2.5">
                        <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-bold">
                            <i class="fa-brands fa-whatsapp"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Group Credential Sharing Card</h3>
                            <p class="text-[11px] text-slate-500">Ready to copy and broadcast to your hostel WhatsApp group</p>
                        </div>
                    </div>
                    <button onclick="copyToClipboard(<?= htmlspecialchars(json_encode($share_text)) ?>, 'WhatsApp message copied! Paste it in your hostel group.')" 
                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i class="fa-regular fa-copy"></i> Copy Text
                    </button>
                </div>

                <div class="bg-slate-900 text-slate-100 rounded-xl p-4 font-mono text-xs leading-relaxed max-h-72 overflow-y-auto whitespace-pre-wrap border border-slate-800 shadow-inner">
<?= clean($share_text) ?>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500 flex items-center justify-between">
                <span>Total Hostellers in Group: <strong><?= count($all_hostellers) ?></strong></span>
                <span class="text-emerald-600 font-semibold"><i class="fa-solid fa-circle-check"></i> Individual logins enabled</span>
            </div>
        </div>

    </div>

    <!-- Table: Active Group Hosteller Roster with Edit Modal Trigger -->
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-address-book text-brand-600"></i>
                    Hosteller Group Roster & Assigned Codes
                </h3>
                <p class="text-xs text-slate-500">Each hosteller has an independent login so their name & code appear on any entry they create or edit.</p>
            </div>
            <span class="text-xs bg-slate-200/80 text-slate-700 px-2.5 py-1 rounded-md font-bold">
                <?= count($all_hostellers) ?> Hostellers Registered
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-100/70 border-b border-slate-200 text-slate-600 text-xs uppercase font-bold tracking-wider">
                        <th class="py-3 px-4">Code</th>
                        <th class="py-3 px-4">Full Name</th>
                        <th class="py-3 px-4">Username</th>
                        <th class="py-3 px-4">Password Hint</th>
                        <th class="py-3 px-4">Room No</th>
                        <th class="py-3 px-4">Phone</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($all_hostellers as $h): ?>
                        <tr class="hover:bg-slate-50 transition <?= empty($h['is_active']) ? 'opacity-60 bg-slate-100/50' : '' ?>">
                            <td class="py-3 px-4">
                                <?php if (!empty($h['code'])): ?>
                                    <span class="w-8 h-8 rounded-lg bg-navy-900 text-white font-mono font-black text-sm flex items-center justify-center shadow-sm">
                                        <?= clean($h['code']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="w-8 h-8 rounded-lg bg-slate-300 text-slate-600 font-mono text-xs flex items-center justify-center">
                                        -
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900"><?= clean($h['full_name']) ?></div>
                                <div class="text-[11px] text-slate-400">Created: <?= format_date($h['created_at']) ?></div>
                            </td>
                            <td class="py-3 px-4 font-mono text-xs text-brand-700 font-bold">
                                <?= clean($h['username']) ?>
                            </td>
                            <td class="py-3 px-4 font-mono text-xs text-slate-600">
                                <span class="bg-slate-100 px-2 py-1 rounded border border-slate-200">
                                    <?= clean($h['plain_password_hint'] ?? '••••••••') ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-700">
                                <?= clean($h['room_no'] ?? '-') ?>
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-700">
                                <?= clean($h['phone'] ?? '-') ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <?php if (!empty($h['is_active'])): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-200 text-slate-600 border border-slate-300">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center space-x-1.5">
                                    <a href="my_expenses.php?user_id=<?= $h['id'] ?>" title="Inspect Personal Bill" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition border border-slate-200">
                                        <i class="fa-solid fa-receipt text-xs"></i>
                                    </a>
                                    <button type="button" 
                                            onclick="openEditMemberModal(<?= $h['id'] ?>, '<?= clean(addslashes($h['full_name'])) ?>', '<?= clean(addslashes($h['room_no'] ?? '')) ?>', '<?= clean(addslashes($h['phone'] ?? '')) ?>')"
                                            title="Edit Hosteller Details" 
                                            class="w-8 h-8 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 flex items-center justify-center transition">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>
                                    <button type="button" 
                                            onclick="openRemoveModal(<?= $h['id'] ?>, '<?= clean(addslashes($h['full_name'])) ?>', '<?= clean(addslashes($h['code'] ?? '')) ?>')"
                                            title="Remove Hosteller Member" 
                                            class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 flex items-center justify-center transition shadow-sm">
                                        <i class="fa-solid fa-user-xmark text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Audit Trail Table: Full Transparency of Entries & Edits -->
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-timeline text-purple-600"></i>
                    Audit Trail & History Log (Who Entered / Edited What)
                </h3>
                <p class="text-xs text-slate-500">Every single meal addition, modification, or divider recalculation is tracked here.</p>
            </div>
            <span class="text-xs text-slate-400">Last 25 Events</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-100/70 border-b border-slate-200 text-slate-600 text-xs uppercase font-bold tracking-wider">
                        <th class="py-3 px-4">Timestamp</th>
                        <th class="py-3 px-4">Action</th>
                        <th class="py-3 px-4">User Details</th>
                        <th class="py-3 px-4">Activity Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($recent_audits as $audit): ?>
                        <tr class="hover:bg-slate-50 transition text-xs">
                            <td class="py-3 px-4 whitespace-nowrap text-slate-500 font-mono">
                                <?= format_datetime($audit['created_at']) ?>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php
                                $badge_class = match($audit['action']) {
                                    'CREATE' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                    'UPDATE' => 'bg-amber-100 text-amber-800 border-amber-300',
                                    'DELETE' => 'bg-rose-100 text-rose-800 border-rose-300',
                                    default  => 'bg-slate-100 text-slate-800 border-slate-300'
                                };
                                ?>
                                <span class="px-2 py-0.5 rounded font-mono font-bold text-[11px] border <?= $badge_class ?>">
                                    <?= clean($audit['action']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap font-bold text-slate-800">
                                <?= clean($audit['full_name'] ?? 'System') ?>
                                <?php if (!empty($audit['code'])): ?>
                                    <span class="w-4 h-4 rounded bg-slate-800 text-white font-mono text-[10px] inline-flex items-center justify-center ml-1">
                                        <?= clean($audit['code']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-slate-700 leading-relaxed">
                                <?= clean($audit['description']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Section: Start New Calculation & Reset Mess Bills -->
    <section id="reset-section" class="bg-gradient-to-br from-rose-50 via-white to-amber-50 rounded-2xl border-2 border-rose-200 shadow-sm p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-rose-100 pb-4 mb-5">
            <div class="flex items-center space-x-3">
                <span class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-lg font-bold shadow-sm">
                    <i class="fa-solid fa-rotate-left"></i>
                </span>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Start New Calculation & Reset Mess Bills</h3>
                    <p class="text-xs text-slate-500">Reset meal expenses to calculate a new monthly mess cycle or clear past records</p>
                </div>
            </div>
            <button type="button" onclick="openResetAllModal()" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition shadow-md flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-arrow-rotate-right"></i> Reset All Meals / New Cycle
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <div class="bg-white rounded-xl p-4 border border-rose-200 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-bold text-slate-900 text-sm">1. Reset Current Cycle</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">Rs. 0.00</span>
                    </div>
                    <p class="text-slate-500 leading-relaxed mt-1">
                        Clears all meals in <strong><?= clean($current_month['name'] ?? 'Current Month') ?></strong>. All hosteller balances reset to <strong>Rs. 0.00</strong> while keeping registered members and codes (A-Z) intact.
                    </p>
                </div>
                <button type="button" onclick="openResetAllModal('current_month')" class="mt-4 w-full py-2 bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-700 rounded-lg font-bold border border-slate-200 hover:border-rose-300 transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-rotate-left text-xs"></i> Reset Current Cycle
                </button>
            </div>

            <div class="bg-white rounded-xl p-4 border border-brand-200 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-bold text-slate-900 text-sm">2. Start New Month Cycle</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-brand-100 text-brand-800">Fresh Start</span>
                    </div>
                    <p class="text-slate-500 leading-relaxed mt-1">
                        Creates a brand new monthly billing period (e.g. November 2026), sets starting balance to <strong>Rs. 0.00</strong>, and preserves the previous month in the archives.
                    </p>
                </div>
                <button type="button" onclick="openResetAllModal('new_month_cycle')" class="mt-4 w-full py-2 bg-brand-50 hover:bg-brand-100 text-brand-700 rounded-lg font-bold border border-brand-200 transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-plus text-xs"></i> + Start Next Month
                </button>
            </div>

            <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-bold text-slate-900 text-sm">3. Clear All Meals</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">Wipe History</span>
                    </div>
                    <p class="text-slate-500 leading-relaxed mt-1">
                        Clears all meal logs across all cycles. All member balances become <strong>Rs. 0.00</strong>. Codes, names, and passwords remain ready for new entries.
                    </p>
                </div>
                <button type="button" onclick="openResetAllModal('all_cycles')" class="mt-4 w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold border border-slate-200 transition flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-trash text-xs"></i> Clear All Meals
                </button>
            </div>
        </div>
    </section>

</div>

<!-- Modal: Remove Hosteller Member -->
<div id="removeMemberModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full border border-slate-200 overflow-hidden transform transition-all animate-fade-in">
        <div class="bg-gradient-to-r from-rose-700 to-rose-600 text-white p-5 flex items-center justify-between">
            <div class="flex items-center space-x-2.5">
                <span class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-white text-base shadow">
                    <i class="fa-solid fa-user-xmark"></i>
                </span>
                <div>
                    <h3 class="text-base font-bold">Remove Group Hosteller</h3>
                    <p class="text-xs text-rose-100">Permanent delete or account suspension</p>
                </div>
            </div>
            <button type="button" onclick="closeRemoveModal()" class="text-rose-200 hover:text-white transition text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="admin.php" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="admin_action" value="remove_hosteller">
            <input type="hidden" name="user_id" id="removeUserId" value="">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 leading-relaxed">
                You are about to remove <strong id="removeUserName" class="text-rose-950 font-bold"></strong> 
                <span id="removeUserCode" class="inline-block px-1.5 py-0.2 bg-slate-800 text-white rounded font-mono font-bold text-[11px] ml-1"></span> from the hostel mess group.
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Select Removal Mode:
                </label>
                <div class="space-y-2.5">
                    <label class="flex items-start p-3 rounded-xl border-2 border-slate-200 hover:border-rose-400 cursor-pointer transition select-none group has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50/40">
                        <input type="radio" name="removal_mode" value="hard_delete" checked class="mt-0.5 text-rose-600 focus:ring-rose-500">
                        <div class="ml-3 text-xs">
                            <span class="font-bold text-slate-900 block group-hover:text-rose-700">Permanent Delete & Free Code</span>
                            <span class="text-slate-500">Deletes the user, frees up their single alphabet code for new hostellers, and automatically recalculates splits on any shared meals.</span>
                        </div>
                    </label>

                    <label class="flex items-start p-3 rounded-xl border-2 border-slate-200 hover:border-amber-400 cursor-pointer transition select-none group has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50/40">
                        <input type="radio" name="removal_mode" value="deactivate" class="mt-0.5 text-amber-600 focus:ring-amber-500">
                        <div class="ml-3 text-xs">
                            <span class="font-bold text-slate-900 block group-hover:text-amber-700">Deactivate / Suspend Account Only</span>
                            <span class="text-slate-500">Blocks login and hides them from future meal dividers, while safely preserving their historical mess bill entries.</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeRemoveModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold shadow-md hover:shadow transition flex items-center gap-1.5">
                    <i class="fa-solid fa-trash"></i> Confirm Removal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Hosteller Member -->
<div id="editMemberModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full border border-slate-200 overflow-hidden transform transition-all animate-fade-in">
        <div class="bg-gradient-to-r from-navy-900 to-slate-800 text-white p-5 flex items-center justify-between">
            <div class="flex items-center space-x-2.5">
                <span class="w-9 h-9 rounded-xl bg-brand-600 flex items-center justify-center text-white text-base shadow">
                    <i class="fa-solid fa-user-pen"></i>
                </span>
                <div>
                    <h3 class="text-base font-bold">Edit Hosteller Details</h3>
                    <p class="text-xs text-slate-300">Update room, contact, or reset password</p>
                </div>
            </div>
            <button type="button" onclick="closeEditMemberModal()" class="text-slate-400 hover:text-white transition text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="admin.php" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="admin_action" value="edit_hosteller">
            <input type="hidden" name="user_id" id="editUserId" value="">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Full Name</label>
                <input type="text" name="full_name" id="editFullName" required class="w-full px-3 py-2 border rounded-lg text-xs font-medium focus:ring-2 focus:ring-brand-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Hostel Room No</label>
                    <input type="text" name="room_no" id="editRoomNo" class="w-full px-3 py-2 border rounded-lg text-xs focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Phone / WhatsApp</label>
                    <input type="text" name="phone" id="editPhone" class="w-full px-3 py-2 border rounded-lg text-xs focus:ring-2 focus:ring-brand-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Reset Password (Optional)
                </label>
                <input type="text" name="new_password" id="editNewPassword" placeholder="Leave blank to keep current password" class="w-full px-3 py-2 border rounded-lg text-xs font-mono focus:ring-2 focus:ring-brand-500">
                <span class="text-[10px] text-slate-400 mt-0.5 block">Changing password also updates the admin credential sharing card.</span>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeEditMemberModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-xs font-bold shadow-md hover:shadow transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reset All / New Calculation -->
<div id="resetAllModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full border border-slate-200 overflow-hidden transform transition-all animate-fade-in">
        <div class="bg-gradient-to-r from-rose-700 via-rose-600 to-orange-600 text-white p-5 flex items-center justify-between">
            <div class="flex items-center space-x-2.5">
                <span class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center text-white text-base shadow">
                    <i class="fa-solid fa-rotate-left"></i>
                </span>
                <div>
                    <h3 class="text-base font-bold">Reset Calculation / Start New Cycle</h3>
                    <p class="text-xs text-rose-100">Set meal balances to Rs. 0.00 for fresh billing</p>
                </div>
            </div>
            <button type="button" onclick="closeResetAllModal()" class="text-rose-200 hover:text-white transition text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="admin.php" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="admin_action" value="reset_all">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="month_id" value="<?= $current_month['id'] ?? 1 ?>">

            <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 leading-relaxed">
                <strong>⚠️ Note:</strong> Resetting clears meals so hostellers can start a new calculation. Hosteller profiles and assigned codes (A-Z) will remain safe.
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Choose Reset Method:
                </label>
                <div class="space-y-2.5">
                    <label class="flex items-start p-3 rounded-xl border-2 border-slate-200 hover:border-rose-400 cursor-pointer transition select-none group has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50/40">
                        <input type="radio" name="reset_scope" id="scope_current" value="current_month" checked onchange="toggleCycleInputs()" class="mt-0.5 text-rose-600 focus:ring-rose-500">
                        <div class="ml-3 text-xs">
                            <span class="font-bold text-slate-900 block group-hover:text-rose-700">Reset Current Cycle (Rs. 0.00)</span>
                            <span class="text-slate-500">Deletes all meals in <strong><?= clean($current_month['name'] ?? 'Current Month') ?></strong>. Balances reset to Rs. 0.00.</span>
                        </div>
                    </label>

                    <label class="flex items-start p-3 rounded-xl border-2 border-slate-200 hover:border-brand-400 cursor-pointer transition select-none group has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/40">
                        <input type="radio" name="reset_scope" id="scope_new_cycle" value="new_month_cycle" onchange="toggleCycleInputs()" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                        <div class="ml-3 text-xs">
                            <span class="font-bold text-slate-900 block group-hover:text-brand-700">Start Fresh New Monthly Cycle</span>
                            <span class="text-slate-500">Archives current records and activates a new month with clean Rs. 0.00 starting balance.</span>
                        </div>
                    </label>

                    <label class="flex items-start p-3 rounded-xl border-2 border-slate-200 hover:border-slate-400 cursor-pointer transition select-none group has-[:checked]:border-slate-800 has-[:checked]:bg-slate-50">
                        <input type="radio" name="reset_scope" id="scope_all" value="all_cycles" onchange="toggleCycleInputs()" class="mt-0.5 text-slate-800 focus:ring-slate-800">
                        <div class="ml-3 text-xs">
                            <span class="font-bold text-slate-900 block">Clear All Historical Meals Across All Cycles</span>
                            <span class="text-slate-500">Wipes all meal records everywhere. All member balances become Rs. 0.00.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Extra inputs if starting new cycle -->
            <div id="newCycleFields" class="hidden p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase">New Cycle Name</label>
                    <input type="text" name="new_cycle_name" placeholder="e.g. November 2026 Mess Bill" class="w-full px-3 py-1.5 border rounded-lg text-xs font-medium">
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase">Start Date</label>
                        <input type="date" name="new_cycle_start" value="<?= date('Y-m-01', strtotime('+1 month')) ?>" class="w-full px-2 py-1.5 border rounded-lg text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase">End Date</label>
                        <input type="date" name="new_cycle_end" value="<?= date('Y-m-t', strtotime('+1 month')) ?>" class="w-full px-2 py-1.5 border rounded-lg text-xs">
                    </div>
                </div>
            </div>

            <!-- Confirmation input -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Type <span class="text-rose-600 font-mono font-black">RESET</span> to confirm: <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="confirmation" id="resetConfirmationInput" required placeholder="Type RESET in capital letters"
                       class="w-full px-3 py-2 border-2 border-slate-300 focus:border-rose-500 focus:ring-rose-500 rounded-lg text-xs font-mono font-bold uppercase tracking-wider">
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeResetAllModal()" class="px-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold shadow-md hover:shadow transition flex items-center gap-1.5">
                    <i class="fa-solid fa-rotate-left"></i> Confirm Reset & Start New
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRemoveModal(id, name, code) {
    document.getElementById('removeUserId').value = id;
    document.getElementById('removeUserName').textContent = name;
    document.getElementById('removeUserCode').textContent = code ? `Code [${code}]` : '';
    document.getElementById('removeMemberModal').classList.remove('hidden');
}

function closeRemoveModal() {
    document.getElementById('removeMemberModal').classList.add('hidden');
}

function openEditMemberModal(id, name, room, phone) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editFullName').value = name;
    document.getElementById('editRoomNo').value = room || '';
    document.getElementById('editPhone').value = phone || '';
    document.getElementById('editNewPassword').value = '';
    document.getElementById('editMemberModal').classList.remove('hidden');
}

function closeEditMemberModal() {
    document.getElementById('editMemberModal').classList.add('hidden');
}

function openResetAllModal(scope = 'current_month') {
    const modal = document.getElementById('resetAllModal');
    if (!modal) return;
    
    if (scope === 'new_month_cycle') {
        document.getElementById('scope_new_cycle').checked = true;
    } else if (scope === 'all_cycles') {
        document.getElementById('scope_all').checked = true;
    } else {
        document.getElementById('scope_current').checked = true;
    }
    toggleCycleInputs();
    document.getElementById('resetConfirmationInput').value = '';
    modal.classList.remove('hidden');
    document.getElementById('resetConfirmationInput').focus();
}

function closeResetAllModal() {
    const modal = document.getElementById('resetAllModal');
    if (modal) modal.classList.add('hidden');
}

function toggleCycleInputs() {
    const isNewCycle = document.getElementById('scope_new_cycle').checked;
    const fields = document.getElementById('newCycleFields');
    if (fields) {
        if (isNewCycle) {
            fields.classList.remove('hidden');
        } else {
            fields.classList.add('hidden');
        }
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
