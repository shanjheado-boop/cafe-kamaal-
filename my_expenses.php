<?php
// my_expenses.php - Individual Hosteller Expense Statement
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_login();

$page_title = 'My Expenses';
require_once __DIR__ . '/includes/header.php';

$logged_user = get_logged_in_user();
$month_id = $current_month['id'] ?? 1;

// Target hosteller ID (Admin can inspect any hosteller; hostellers only view their own)
$target_user_id = $logged_user['id'];
if (is_admin() && isset($_GET['user_id'])) {
    $target_user_id = (int)$_GET['user_id'];
}

// Fetch hosteller info
$h_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$h_stmt->execute([$target_user_id]);
$hosteller = $h_stmt->fetch();

if (!$hosteller) {
    echo "<div class='p-6 text-center text-rose-600 font-bold'>Hosteller not found.</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// 1. Fetch all meals where this hosteller's code was in the divider
$meals_stmt = $pdo->prepare("
    SELECT m.*, me.share_amount,
           u_creator.full_name AS creator_name, u_creator.code AS creator_code,
           u_editor.full_name AS editor_name, u_editor.code AS editor_code
    FROM meal_eaters me
    JOIN meals m ON me.meal_id = m.id
    LEFT JOIN users u_creator ON m.created_by = u_creator.id
    LEFT JOIN users u_editor ON m.last_edited_by = u_editor.id
    WHERE me.user_id = ? AND m.month_id = ?
    ORDER BY m.meal_date DESC, m.meal_time DESC
");
$meals_stmt->execute([$target_user_id, $month_id]);
$my_meals = $meals_stmt->fetchAll();

// Attach all eaters for these meals so user can see who else ate with them
if (!empty($my_meals)) {
    $meal_ids = array_column($my_meals, 'id');
    $in_clause = implode(',', array_fill(0, count($meal_ids), '?'));
    $e_stmt = $pdo->prepare("SELECT meal_id, hosteller_code, share_amount FROM meal_eaters WHERE meal_id IN ($in_clause) ORDER BY hosteller_code ASC");
    $e_stmt->execute($meal_ids);
    $all_eaters = $e_stmt->fetchAll();

    $meal_eaters_map = [];
    foreach ($all_eaters as $e) {
        $meal_eaters_map[$e['meal_id']][] = $e;
    }

    foreach ($my_meals as &$m) {
        $m['all_dividers'] = $meal_eaters_map[$m['id']] ?? [];
    }
    unset($m);
}

// 2. Compute category aggregates (Breakfast, Lunch, Dinner, etc.)
$total_expense = 0;
$cat_breakdown = ['Breakfast' => 0, 'Lunch' => 0, 'Dinner' => 0, 'Tea & Snacks' => 0, 'Special Mess' => 0, 'Other' => 0];

foreach ($my_meals as $m) {
    $total_expense += (float)$m['share_amount'];
    $type = $m['meal_type'] ?? 'Other';
    if (!isset($cat_breakdown[$type])) $cat_breakdown[$type] = 0;
    $cat_breakdown[$type] += (float)$m['share_amount'];
}

// Total group mess bill for % calculation
$total_mess_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM meals WHERE month_id = ?");
$total_mess_stmt->execute([$month_id]);
$total_mess_bill = (float)$total_mess_stmt->fetchColumn();
$percent_of_mess = ($total_mess_bill > 0) ? round(($total_expense / $total_mess_bill) * 100, 1) : 0;
?>

<div class="space-y-6">

    <!-- Top Profile & Summary Card -->
    <section class="bg-gradient-to-r from-navy-900 via-slate-800 to-navy-900 text-white rounded-3xl p-6 sm:p-8 shadow-xl">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center space-x-4">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 flex items-center justify-center font-mono font-black text-3xl text-white shadow-lg">
                    <?= clean($hosteller['code'] ?? '★') ?>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl sm:text-2xl font-black text-white"><?= clean($hosteller['full_name']) ?></h2>
                        <span class="text-xs bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 font-mono font-bold px-2 py-0.5 rounded">
                            Code [<?= clean($hosteller['code'] ?? 'Admin') ?>]
                        </span>
                    </div>
                    <p class="text-xs text-slate-300 mt-1">
                        Hostel Room: <strong><?= clean($hosteller['room_no'] ?? 'N/A') ?></strong> &bull; 
                        Username: <span class="font-mono"><?= clean($hosteller['username']) ?></span> &bull; 
                        Billing Cycle: <strong class="text-brand-300"><?= clean($current_month['name'] ?? 'Active') ?></strong>
                    </p>
                </div>
            </div>

            <!-- Big Expense Total -->
            <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 sm:p-5 border border-white/10 text-right min-w-[200px]">
                <div class="text-xs uppercase tracking-wider text-slate-300 font-bold">My Total Meal Bill</div>
                <div class="text-3xl sm:text-4xl font-black text-emerald-400 font-mono mt-0.5">
                    <?= format_currency($total_expense) ?>
                </div>
                <div class="text-xs text-slate-300 mt-1">
                    <?= count($my_meals) ?> meals attended (<?= $percent_of_mess ?>% of total mess)
                </div>
            </div>
        </div>

        <!-- Category Mini Pills -->
        <div class="mt-6 pt-5 border-t border-slate-700/60 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            <?php foreach ($cat_breakdown as $cat => $amount): ?>
                <?php if ($amount > 0 || in_array($cat, ['Breakfast', 'Lunch', 'Dinner'])): ?>
                    <div class="bg-slate-800/80 rounded-xl p-3 border border-slate-700">
                        <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider"><?= clean($cat) ?></div>
                        <div class="text-sm font-black font-mono text-white mt-0.5"><?= format_currency($amount) ?></div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Detailed Meals Where Logged-in Hosteller Ate -->
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-brand-600"></i>
                    Meals Attended & Charged to Code [<?= clean($hosteller['code'] ?? 'You') ?>]
                </h3>
                <p class="text-xs text-slate-500">List of every meal where your single alphabet code was included as a divider.</p>
            </div>
            <div>
                <a href="print_bill.php?user_id=<?= $hosteller['id'] ?>" target="_blank" class="px-3 py-1.5 bg-white border border-slate-300 hover:border-slate-400 text-slate-700 rounded-lg text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                    <i class="fa-solid fa-print text-brand-600"></i> Print Personal Statement
                </a>
            </div>
        </div>

        <?php if (empty($my_meals)): ?>
            <div class="p-12 text-center text-slate-400">
                <i class="fa-solid fa-utensils text-4xl mb-3 text-slate-300"></i>
                <p class="text-base font-semibold text-slate-600">No meals have been billed to your code yet this month.</p>
                <p class="text-xs text-slate-400 mt-1">When adding a meal, make sure to select Code [<?= clean($hosteller['code']) ?>] to divide the bill!</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-100/70 border-b border-slate-200 text-slate-600 text-xs uppercase font-bold tracking-wider">
                            <th class="py-3 px-4">Date & Time</th>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4">Meal Description</th>
                            <th class="py-3 px-4 text-right">Total Meal Cost</th>
                            <th class="py-3 px-4">All Dividers</th>
                            <th class="py-3 px-4 text-right">Your Share (Charged)</th>
                            <th class="py-3 px-4">Entered / Edited By</th>
                            <th class="py-3 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($my_meals as $m): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3.5 px-4 whitespace-nowrap text-xs">
                                    <div class="font-bold text-slate-800"><?= format_date($m['meal_date']) ?></div>
                                    <div class="text-slate-400 font-mono"><?= date('h:i A', strtotime($m['meal_time'])) ?></div>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-700 border">
                                        <?= clean($m['meal_type']) ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900"><?= clean($m['title']) ?></div>
                                </td>

                                <td class="py-3.5 px-4 text-right whitespace-nowrap font-mono text-slate-600 text-xs">
                                    <?= format_currency($m['total_price']) ?>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-1 flex-wrap">
                                        <?php foreach ($m['all_dividers'] as $d): ?>
                                            <span class="w-5 h-5 rounded <?= ($d['hosteller_code'] === $hosteller['code']) ? 'bg-emerald-600 ring-2 ring-emerald-300' : 'bg-slate-700' ?> text-white font-mono text-[10px] font-bold flex items-center justify-center" title="Code <?= clean($d['hosteller_code']) ?>">
                                                <?= clean($d['hosteller_code']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <span class="text-xs text-slate-500 font-mono ml-1">(÷ <?= $m['split_count'] ?>)</span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="text-sm font-black font-mono text-emerald-700">
                                        <?= format_currency($m['share_amount']) ?>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-xs text-slate-500">
                                    <div>By: <strong><?= clean($m['creator_name'] ?? 'Admin') ?></strong></div>
                                    <div class="text-[11px] text-slate-400">Edited by: <?= clean($m['editor_name'] ?? 'Admin') ?> (<?= time_ago($m['last_edited_at']) ?>)</div>
                                </td>

                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <button onclick="openEditMealModal(<?= $m['id'] ?>)" class="w-8 h-8 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 flex items-center justify-center transition" title="Edit Meal Entry">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 border-t-2 border-slate-300 font-bold text-slate-900">
                            <td colspan="5" class="py-3 px-4 uppercase text-xs tracking-wider">Total Amount Billed to Code [<?= clean($hosteller['code']) ?>]</td>
                            <td class="py-3 px-4 text-right font-mono text-base font-black text-emerald-700"><?= format_currency($total_expense) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
