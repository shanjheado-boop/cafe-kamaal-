<?php
// dashboard.php - Main Dashboard & Real-Time Bill Calculator
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_login();

$page_title = 'Dashboard & Hotel Bill';
require_once __DIR__ . '/includes/header.php';

$user = get_logged_in_user();
$month_id = $current_month['id'] ?? 1;

// 1. Fetch the Most Recently Edited Meal (Requirement: Show bill edited by last person upon login)
$last_edited_meal = get_last_edited_meal($pdo, $month_id);

// 2. Compute Total Mess Bill for the Month
$total_bill_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) AS total_amount, COUNT(*) AS total_meals FROM meals WHERE month_id = ?");
$total_bill_stmt->execute([$month_id]);
$month_summary = $total_bill_stmt->fetch();
$total_mess_bill = (float)$month_summary['total_amount'];
$total_meals_count = (int)$month_summary['total_meals'];

// 3. Compute Logged-in Hosteller's Personal Expense for this month
$my_expense = 0.00;
$my_meals_count = 0;
if ($user['role'] === 'hosteller') {
    $my_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(me.share_amount), 0) AS my_total, COUNT(me.id) AS my_count
        FROM meal_eaters me
        JOIN meals m ON me.meal_id = m.id
        WHERE m.month_id = ? AND me.user_id = ?
    ");
    $my_stmt->execute([$month_id, $user['id']]);
    $my_row = $my_stmt->fetch();
    $my_expense = (float)$my_row['my_total'];
    $my_meals_count = (int)$my_row['my_count'];
}

// 4. Compute Breakdown for ALL Active Hostellers (Group Bill Calculation)
$group_bill_stmt = $pdo->prepare("
    SELECT 
        u.id, u.code, u.full_name, u.room_no, u.phone,
        COALESCE(COUNT(me.id), 0) AS meals_attended,
        COALESCE(SUM(me.share_amount), 0) AS total_owed
    FROM users u
    LEFT JOIN meal_eaters me ON u.id = me.user_id
    LEFT JOIN meals m ON me.meal_id = m.id AND m.month_id = ?
    WHERE u.role = 'hosteller' AND u.is_active = 1
    GROUP BY u.id, u.code, u.full_name, u.room_no, u.phone
    ORDER BY u.code ASC
");
$group_bill_stmt->execute([$month_id]);
$group_roster = $group_bill_stmt->fetchAll();

// 5. Fetch all meals for this month with eater codes
$meals_stmt = $pdo->prepare("
    SELECT m.*, 
           u_creator.full_name AS creator_name, u_creator.code AS creator_code,
           u_editor.full_name AS editor_name, u_editor.code AS editor_code
    FROM meals m
    LEFT JOIN users u_creator ON m.created_by = u_creator.id
    LEFT JOIN users u_editor ON m.last_edited_by = u_editor.id
    WHERE m.month_id = ?
    ORDER BY m.meal_date DESC, m.meal_time DESC, m.id DESC
");
$meals_stmt->execute([$month_id]);
$all_meals = $meals_stmt->fetchAll();

// Attach eaters to each meal
if (!empty($all_meals)) {
    $meal_ids = array_column($all_meals, 'id');
    $in_clause = implode(',', array_fill(0, count($meal_ids), '?'));
    $eaters_map_stmt = $pdo->prepare("
        SELECT me.meal_id, me.user_id, me.hosteller_code, me.share_amount, u.full_name
        FROM meal_eaters me
        JOIN users u ON me.user_id = u.id
        WHERE me.meal_id IN ($in_clause)
        ORDER BY me.hosteller_code ASC
    ");
    $eaters_map_stmt->execute($meal_ids);
    $all_eaters = $eaters_map_stmt->fetchAll();

    $meal_eaters_grouped = [];
    foreach ($all_eaters as $eater) {
        $meal_eaters_grouped[$eater['meal_id']][] = $eater;
    }

    foreach ($all_meals as &$m) {
        $m['eaters'] = $meal_eaters_grouped[$m['id']] ?? [];
    }
    unset($m);
}
?>

<div class="space-y-6">

    <!-- ========================================================================= -->
    <!-- 1. LAST EDITED BILL BANNER (Mandatory Requirement)                         -->
    <!-- "Whenever a hosteller login the system then the system should show the     -->
    <!--  bill which is edited by the last person"                                  -->
    <!-- ========================================================================= -->
    <?php if ($last_edited_meal): ?>
        <section class="bg-gradient-to-r from-amber-500 via-brand-600 to-orange-600 rounded-2xl p-0.5 shadow-lg overflow-hidden animate-fade-in">
            <div class="bg-white/95 backdrop-blur-sm rounded-[15px] p-5 sm:p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-amber-100 pb-4">
                    <div class="flex items-start space-x-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-600 text-xl shrink-0 shadow-sm">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="bg-amber-100 text-amber-800 text-[11px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                                    Last Edited Meal Entry
                                </span>
                                <span class="text-xs text-slate-500">
                                    <i class="fa-regular fa-clock mr-1"></i> <?= time_ago($last_edited_meal['last_edited_at']) ?> (<?= format_datetime($last_edited_meal['last_edited_at']) ?>)
                                </span>
                            </div>
                            <h2 class="text-lg sm:text-xl font-black text-slate-900 mt-1">
                                <?= clean($last_edited_meal['title']) ?>
                            </h2>
                            <p class="text-xs text-slate-600 mt-0.5">
                                Last modified by: <strong class="text-slate-900 font-bold"><?= clean($last_edited_meal['editor_name'] ?? 'Admin') ?></strong>
                                <?php if (!empty($last_edited_meal['editor_code'])): ?>
                                    <span class="inline-block px-1.5 py-0.2 bg-slate-800 text-white font-mono text-[10px] rounded font-bold">Code <?= clean($last_edited_meal['editor_code']) ?></span>
                                <?php endif; ?>
                                &bull; <span class="italic text-slate-500">"<?= clean($last_edited_meal['edit_summary'] ?? 'Updated entry') ?>"</span>
                            </p>
                        </div>
                    </div>

                    <!-- Price & Quick Edit -->
                    <div class="flex items-center gap-4 bg-amber-50/80 border border-amber-200/80 rounded-xl p-3 shrink-0">
                        <div class="text-right">
                            <div class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold">Total Price</div>
                            <div class="text-xl sm:text-2xl font-black text-amber-700 font-mono">
                                <?= format_currency($last_edited_meal['total_price']) ?>
                            </div>
                        </div>
                        <div class="h-8 w-px bg-amber-200"></div>
                        <div>
                            <div class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold">Divided Cost</div>
                            <div class="text-sm font-bold text-slate-800">
                                <?= format_currency($last_edited_meal['per_person_price']) ?> <span class="text-xs font-normal text-slate-500">/ person</span>
                            </div>
                        </div>
                        <button onclick="openEditMealModal(<?= $last_edited_meal['id'] ?>)" 
                                class="ml-2 px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold transition shadow-sm flex items-center gap-1.5">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </button>
                    </div>
                </div>

                <!-- Dividers Badges -->
                <div class="mt-3 pt-1 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-slate-600">Dividers (<?= $last_edited_meal['split_count'] ?> hostellers):</span>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <?php foreach ($last_edited_meal['eaters'] as $eater): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-800 border border-slate-300 font-medium <?= ($user['id'] == $eater['user_id']) ? 'ring-2 ring-emerald-500 bg-emerald-50' : '' ?>">
                                    <span class="w-4 h-4 rounded bg-slate-800 text-white font-mono text-[10px] flex items-center justify-center font-bold mr-1.5">
                                        <?= clean($eater['hosteller_code']) ?>
                                    </span>
                                    <span><?= clean($eater['full_name']) ?></span>
                                    <span class="ml-1 text-[10px] text-slate-500 font-mono">(<?= format_currency($eater['share_amount']) ?>)</span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-500">
                        Meal Type: <span class="font-semibold text-slate-700"><?= clean($last_edited_meal['meal_type']) ?></span> &bull; 
                        Date: <span class="font-semibold text-slate-700"><?= format_date($last_edited_meal['meal_date']) ?></span>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ========================================================================= -->
    <!-- 2. REAL-TIME STATS / KPI CARDS                                             -->
    <!-- ========================================================================= -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Group Mess Bill -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div>
                <div class="text-xs uppercase font-bold text-slate-500 tracking-wider">Total Group Mess Bill</div>
                <div class="text-2xl font-black text-slate-900 font-mono mt-0.5"><?= format_currency($total_mess_bill) ?></div>
                <div class="text-xs text-slate-500 mt-0.5"><?= $total_meals_count ?> meals in <?= clean($current_month['name'] ?? 'Current Month') ?></div>
            </div>
        </div>

        <!-- Card 2: Current Hosteller Expense -->
        <?php if ($user['role'] === 'hosteller'): ?>
            <div class="bg-white rounded-2xl p-5 border border-emerald-200 bg-gradient-to-br from-white to-emerald-50/50 shadow-sm flex items-center space-x-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <div>
                    <div class="text-xs uppercase font-bold text-emerald-800 tracking-wider flex items-center gap-1.5">
                        My Total Expense
                        <span class="w-5 h-5 rounded bg-emerald-700 text-white font-mono text-[11px] flex items-center justify-center font-bold">
                            <?= clean($user['code']) ?>
                        </span>
                    </div>
                    <div class="text-2xl font-black text-emerald-700 font-mono mt-0.5"><?= format_currency($my_expense) ?></div>
                    <div class="text-xs text-slate-600 mt-0.5">
                        Ate in <strong><?= $my_meals_count ?></strong> of <?= $total_meals_count ?> meals (<?= $total_mess_bill > 0 ? round(($my_expense / $total_mess_bill) * 100, 1) : 0 ?>% of total)
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl p-5 border border-purple-200 bg-gradient-to-br from-white to-purple-50/40 shadow-sm flex items-center space-x-4">
                <div class="w-12 h-12 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-crown"></i>
                </div>
                <div>
                    <div class="text-xs uppercase font-bold text-purple-800 tracking-wider">Manager Overview</div>
                    <div class="text-2xl font-black text-purple-900 font-mono mt-0.5"><?= count($group_roster) ?> Members</div>
                    <div class="text-xs text-slate-500 mt-0.5">Administering Mess Group</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Card 3: Total Active Hostellers in Group -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <div class="text-xs uppercase font-bold text-slate-500 tracking-wider">Active Hosteller Codes</div>
                <div class="text-2xl font-black text-slate-900 mt-0.5"><?= count($group_roster) ?> Codes</div>
                <div class="text-xs text-slate-500 mt-0.5 font-mono">
                    <?= implode(', ', array_map(fn($r) => '[' . $r['code'] . ']', $group_roster)) ?>
                </div>
            </div>
        </div>

        <!-- Card 4: Quick Action Card -->
        <div class="bg-gradient-to-br from-navy-900 to-slate-800 text-white rounded-2xl p-5 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs uppercase font-bold text-slate-400 tracking-wider">Hotel Bill Splitter</span>
                <span class="text-xs bg-brand-500 text-white font-bold px-2 py-0.5 rounded">Active</span>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button onclick="openAddMealModal()" class="flex-1 py-2 px-3 bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold rounded-lg transition text-center shadow flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-plus"></i> Add Meal Entry
                </button>
                <?php if (is_admin()): ?>
                    <a href="admin.php#reset-section" title="Reset All & Calculate New" class="py-2 px-2.5 bg-rose-600/80 hover:bg-rose-600 text-white text-xs font-bold rounded-lg transition text-center shadow flex items-center justify-center gap-1">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                <?php endif; ?>
                <a href="print_bill.php" target="_blank" title="Print Mess Bill Sheet" class="py-2 px-3 bg-slate-700 hover:bg-slate-600 text-slate-200 hover:text-white text-xs font-semibold rounded-lg transition text-center">
                    <i class="fa-solid fa-print"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 3. INDIVIDUAL HOSTELLER BILL BREAKDOWN (Divider Summary Table)              -->
    <!-- ========================================================================= -->
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-users-viewfinder text-brand-600"></i>
                    Hosteller Monthly Expense Summary (Code-Wise Breakdown)
                </h3>
                <p class="text-xs text-slate-500">Each hosteller's share is automatically calculated whenever their code is included as a divider in a meal.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs bg-slate-200/80 text-slate-700 px-2.5 py-1 rounded-md font-mono font-bold">
                    Total: <?= format_currency($total_mess_bill) ?>
                </span>
                <a href="print_bill.php" target="_blank" class="text-xs bg-white border border-slate-300 hover:border-slate-400 px-3 py-1 rounded-md font-semibold text-slate-700 transition flex items-center gap-1">
                    <i class="fa-solid fa-file-pdf text-rose-500"></i> Printable Bill
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-100/70 border-b border-slate-200 text-slate-600 text-xs uppercase font-bold tracking-wider">
                        <th class="py-3 px-4">Code</th>
                        <th class="py-3 px-4">Hosteller Name</th>
                        <th class="py-3 px-4">Room / Contact</th>
                        <th class="py-3 px-4 text-center">Meals Attended</th>
                        <th class="py-3 px-4 text-center">% of Total Mess</th>
                        <th class="py-3 px-4 text-right">Total Amount Owed</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($group_roster)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">No hostellers registered yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($group_roster as $h): ?>
                            <?php 
                            $is_current_hosteller = ($user['id'] == $h['id']);
                            $percentage = ($total_mess_bill > 0) ? round(($h['total_owed'] / $total_mess_bill) * 100, 1) : 0;
                            ?>
                            <tr class="hover:bg-slate-50/80 transition <?= $is_current_hosteller ? 'bg-amber-50/60 font-semibold' : '' ?>">
                                <td class="py-3 px-4">
                                    <span class="w-8 h-8 rounded-lg bg-navy-900 text-white font-mono font-black text-sm flex items-center justify-center shadow-sm">
                                        <?= clean($h['code']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800 flex items-center gap-2">
                                        <?= clean($h['full_name']) ?>
                                        <?php if ($is_current_hosteller): ?>
                                            <span class="text-[10px] bg-brand-600 text-white px-2 py-0.5 rounded-full uppercase tracking-wider font-bold">You</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-slate-400">Code [<?= clean($h['code']) ?>]</div>
                                </td>
                                <td class="py-3 px-4 text-xs text-slate-600">
                                    <div><?= clean($h['room_no'] ?? 'N/A') ?></div>
                                    <div class="text-slate-400"><?= clean($h['phone'] ?? '-') ?></div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold <?= $h['meals_attended'] > 0 ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-slate-100 text-slate-500' ?>">
                                        <?= $h['meals_attended'] ?> meals
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="w-16 bg-slate-200 rounded-full h-2 overflow-hidden">
                                            <div class="bg-brand-500 h-2 rounded-full" style="width: <?= min(100, $percentage) ?>%"></div>
                                        </div>
                                        <span class="text-xs font-mono text-slate-600"><?= $percentage ?>%</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="text-base font-black font-mono text-slate-900">
                                        <?= format_currency($h['total_owed']) ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                        Unsettled
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-100 border-t-2 border-slate-300 font-bold text-slate-900 text-sm">
                        <td colspan="3" class="py-3 px-4 uppercase text-xs tracking-wider">Total Mess Bill</td>
                        <td class="py-3 px-4 text-center font-mono"><?= $total_meals_count ?> meals</td>
                        <td class="py-3 px-4 text-center font-mono">100.0%</td>
                        <td class="py-3 px-4 text-right font-mono text-base font-black text-brand-700"><?= format_currency($total_mess_bill) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 4. MEAL ENTRIES LOG & LIVE SPLIT DETAILS                                  -->
    <!-- ========================================================================= -->
    <section id="meals-section" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-amber-500"></i>
                    Monthly Meal Entries & Divider Split Log
                </h3>
                <p class="text-xs text-slate-500">All meal expenses entered by hostellers or admin. Click Edit to adjust price or dividers.</p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="openAddMealModal()" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-xs font-bold transition shadow flex items-center gap-1.5">
                    <i class="fa-solid fa-plus"></i> Add Meal Entry
                </button>
            </div>
        </div>

        <?php if (empty($all_meals)): ?>
            <div class="p-12 text-center text-slate-400">
                <i class="fa-solid fa-utensils text-4xl mb-3 text-slate-300"></i>
                <p class="text-base font-semibold text-slate-600">No meal entries logged yet for this billing cycle.</p>
                <p class="text-xs text-slate-400 mt-1">Click "Add Meal Entry" to log breakfast, lunch, or dinner and split cost among codes.</p>
                <button onclick="openAddMealModal()" class="mt-4 px-4 py-2 bg-brand-600 text-white rounded-lg text-xs font-bold">
                    + Add First Meal
                </button>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-100/70 border-b border-slate-200 text-slate-600 text-xs uppercase font-bold tracking-wider">
                            <th class="py-3 px-4">Date & Time</th>
                            <th class="py-3 px-4">Meal Type</th>
                            <th class="py-3 px-4">Description / Menu</th>
                            <th class="py-3 px-4 text-right">Meal Price</th>
                            <th class="py-3 px-4">Dividers (Hosteller Codes)</th>
                            <th class="py-3 px-4 text-right">Per Person</th>
                            <th class="py-3 px-4">Audit Details (Who Entered / Edited)</th>
                            <th class="py-3 px-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($all_meals as $meal): ?>
                            <?php 
                            $is_user_in_divider = false;
                            if ($user['role'] === 'hosteller') {
                                foreach ($meal['eaters'] as $e) {
                                    if ($e['user_id'] == $user['id']) {
                                        $is_user_in_divider = true;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <tr class="hover:bg-slate-50 transition <?= $is_user_in_divider ? 'border-l-4 border-l-brand-500' : '' ?>">
                                <!-- Date & Time -->
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs text-slate-700">
                                    <div class="font-bold text-slate-900"><?= format_date($meal['meal_date']) ?></div>
                                    <div class="text-slate-400 font-mono"><?= date('h:i A', strtotime($meal['meal_time'])) ?></div>
                                </td>

                                <!-- Meal Type -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <?php
                                    $type_color = match($meal['meal_type']) {
                                        'Breakfast' => 'bg-amber-100 text-amber-800 border-amber-300',
                                        'Lunch' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                        'Dinner' => 'bg-indigo-100 text-indigo-800 border-indigo-300',
                                        'Special Mess' => 'bg-purple-100 text-purple-800 border-purple-300',
                                        default => 'bg-slate-100 text-slate-800 border-slate-300'
                                    };
                                    ?>
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold border <?= $type_color ?>">
                                        <?= clean($meal['meal_type']) ?>
                                    </span>
                                </td>

                                <!-- Description -->
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900"><?= clean($meal['title']) ?></div>
                                    <?php if ($is_user_in_divider): ?>
                                        <span class="text-[10px] bg-emerald-50 text-emerald-700 font-semibold px-1.5 py-0.2 rounded border border-emerald-200">
                                            ✓ You ate this meal
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Total Price -->
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="text-base font-black font-mono text-slate-900">
                                        <?= format_currency($meal['total_price']) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono">Total</div>
                                </td>

                                <!-- Divider Codes -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <?php foreach ($meal['eaters'] as $eater): ?>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-slate-800 text-white font-mono text-xs font-bold" title="<?= clean($eater['full_name']) ?> (<?= format_currency($eater['share_amount']) ?>)">
                                                <?= clean($eater['hosteller_code']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <span class="text-xs text-slate-500 font-medium ml-1">(<?= $meal['split_count'] ?> eaters)</span>
                                    </div>
                                </td>

                                <!-- Per Person Price -->
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="text-sm font-black font-mono text-brand-700">
                                        <?= format_currency($meal['per_person_price']) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400">per person</div>
                                </td>

                                <!-- Audit Details (Who created & edited) -->
                                <td class="py-3.5 px-4 text-xs text-slate-600 max-w-xs">
                                    <div>
                                        <span class="text-slate-400">Created by:</span> 
                                        <strong><?= clean($meal['creator_name'] ?? 'Admin') ?></strong>
                                        <?php if (!empty($meal['creator_code'])): ?>
                                            <span class="font-mono text-[10px] bg-slate-200 px-1 rounded">[<?= clean($meal['creator_code']) ?>]</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-0.5">
                                        <span class="text-slate-400">Last edited:</span> 
                                        <strong><?= clean($meal['editor_name'] ?? 'Admin') ?></strong>
                                        <?php if (!empty($meal['editor_code'])): ?>
                                            <span class="font-mono text-[10px] bg-slate-200 px-1 rounded">[<?= clean($meal['editor_code']) ?>]</span>
                                        <?php endif; ?>
                                        <span class="text-[11px] text-slate-400 block"><?= time_ago($meal['last_edited_at']) ?></span>
                                    </div>
                                    <?php if (!empty($meal['edit_summary'])): ?>
                                        <div class="text-[11px] text-slate-500 italic mt-0.5 truncate" title="<?= clean($meal['edit_summary']) ?>">
                                            "<?= clean($meal['edit_summary']) ?>"
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center space-x-1.5">
                                        <button onclick="openEditMealModal(<?= $meal['id'] ?>)" 
                                                title="Edit Entry" 
                                                class="w-8 h-8 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 flex items-center justify-center transition">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>

                                        <?php if (is_admin() || $meal['created_by'] == $user['id'] || $meal['last_edited_by'] == $user['id']): ?>
                                            <form action="meal_actions.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete meal: <?= addslashes($meal['title']) ?>? All eater divider shares will be reversed.')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="meal_id" value="<?= $meal['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <button type="submit" title="Delete Meal" class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 flex items-center justify-center transition">
                                                    <i class="fa-solid fa-trash text-xs"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
