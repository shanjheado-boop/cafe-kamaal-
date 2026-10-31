<?php
// print_bill.php - Clean Printable Invoice & Mess Bill Statement
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

require_login();

$pdo = get_db_connection();
$current_month = get_current_billing_month($pdo, $_GET['month_id'] ?? null);
$month_id = $current_month['id'] ?? 1;

$view = $_GET['view'] ?? 'monthly_bill';
$target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Total mess sum
$total_bill_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) AS total_amount, COUNT(*) AS total_meals FROM meals WHERE month_id = ?");
$total_bill_stmt->execute([$month_id]);
$month_summary = $total_bill_stmt->fetch();
$total_mess_bill = (float)$month_summary['total_amount'];
$total_meals_count = (int)$month_summary['total_meals'];

// All hostellers breakdown
$group_bill_stmt = $pdo->prepare("
    SELECT 
        u.id, u.code, u.full_name, u.room_no, u.phone, u.username, u.plain_password_hint,
        COALESCE(COUNT(me.id), 0) AS meals_attended,
        COALESCE(SUM(me.share_amount), 0) AS total_owed
    FROM users u
    LEFT JOIN meal_eaters me ON u.id = me.user_id
    LEFT JOIN meals m ON me.meal_id = m.id AND m.month_id = ?
    WHERE u.role = 'hosteller' AND u.is_active = 1
    GROUP BY u.id, u.code, u.full_name, u.room_no, u.phone, u.username, u.plain_password_hint
    ORDER BY u.code ASC
");
$group_bill_stmt->execute([$month_id]);
$hostellers = $group_bill_stmt->fetchAll();

// If target user specified
$single_hosteller = null;
$single_meals = [];
if ($target_user_id) {
    foreach ($hostellers as $h) {
        if ($h['id'] == $target_user_id) {
            $single_hosteller = $h;
            break;
        }
    }
    if ($single_hosteller) {
        $sm_stmt = $pdo->prepare("
            SELECT m.*, me.share_amount
            FROM meal_eaters me
            JOIN meals m ON me.meal_id = m.id
            WHERE me.user_id = ? AND m.month_id = ?
            ORDER BY m.meal_date ASC, m.meal_time ASC
        ");
        $sm_stmt->execute([$target_user_id, $month_id]);
        $single_meals = $sm_stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mess Bill Invoice - Cafe Kamaal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .print-border { border-color: #333 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 font-sans p-4 sm:p-8">

<!-- Floating Print & Back Actions (Hidden on print) -->
<div class="max-w-4xl mx-auto mb-6 flex items-center justify-between no-print">
    <a href="dashboard.php" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-xs font-bold hover:bg-slate-700 transition flex items-center gap-1.5 shadow">
        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>
    <div class="flex items-center gap-2">
        <button onclick="window.print()" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow">
            <i class="fa-solid fa-print"></i> Print Invoice / Save PDF
        </button>
    </div>
</div>

<div class="max-w-4xl mx-auto bg-white p-8 sm:p-12 rounded-2xl shadow-lg border border-slate-200">

    <!-- Invoice Header -->
    <div class="flex items-start justify-between border-b-2 border-slate-900 pb-6 mb-6">
        <div>
            <div class="flex items-center space-x-2">
                <span class="w-8 h-8 rounded-lg bg-orange-600 text-white flex items-center justify-center font-bold text-base">
                    <i class="fa-solid fa-utensils"></i>
                </span>
                <h1 class="text-2xl font-black tracking-tight text-slate-900">CAFE KAMAAL</h1>
            </div>
            <p class="text-xs text-slate-600 uppercase tracking-widest font-semibold mt-1">Hostel Mess & Hotel Meal Bill Calculator</p>
            <p class="text-xs text-slate-500 mt-0.5">Automated Single Alphabet Code Meal Split System</p>
        </div>
        <div class="text-right">
            <div class="text-xs uppercase tracking-wider text-slate-500 font-bold">Billing Cycle</div>
            <div class="text-base font-black text-slate-900"><?= clean($current_month['name'] ?? 'Current Mess') ?></div>
            <div class="text-xs text-slate-500">Date Generated: <?= date('M d, Y h:i A') ?></div>
        </div>
    </div>

    <!-- VIEW 1: SINGLE HOSTELLER PERSONAL STATEMENT -->
    <?php if ($single_hosteller): ?>
        <div class="mb-6 p-4 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
            <div>
                <span class="text-xs uppercase font-bold text-slate-500">Hosteller Statement</span>
                <h2 class="text-xl font-black text-slate-900 flex items-center gap-2">
                    <?= clean($single_hosteller['full_name']) ?>
                    <span class="px-2 py-0.5 bg-slate-800 text-white font-mono text-xs rounded font-bold">Code <?= clean($single_hosteller['code']) ?></span>
                </h2>
                <div class="text-xs text-slate-600 mt-0.5">Room: <?= clean($single_hosteller['room_no']) ?> &bull; Contact: <?= clean($single_hosteller['phone']) ?></div>
            </div>
            <div class="text-right">
                <span class="text-xs uppercase font-bold text-slate-500">Total Amount Payable</span>
                <div class="text-2xl font-black text-orange-600 font-mono"><?= format_currency($single_hosteller['total_owed']) ?></div>
            </div>
        </div>

        <table class="w-full text-left border-collapse text-xs mb-6">
            <thead>
                <tr class="border-b-2 border-slate-800 font-bold text-slate-800 uppercase tracking-wider">
                    <th class="py-2.5 px-3">Date</th>
                    <th class="py-2.5 px-3">Type</th>
                    <th class="py-2.5 px-3">Meal Item</th>
                    <th class="py-2.5 px-3 text-right">Total Price</th>
                    <th class="py-2.5 px-3 text-center">Dividers</th>
                    <th class="py-2.5 px-3 text-right">Charged Share</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php foreach ($single_meals as $m): ?>
                    <tr>
                        <td class="py-2.5 px-3 whitespace-nowrap"><?= format_date($m['meal_date']) ?></td>
                        <td class="py-2.5 px-3"><?= clean($m['meal_type']) ?></td>
                        <td class="py-2.5 px-3 font-medium"><?= clean($m['title']) ?></td>
                        <td class="py-2.5 px-3 text-right font-mono"><?= format_currency($m['total_price']) ?></td>
                        <td class="py-2.5 px-3 text-center font-mono">÷ <?= $m['split_count'] ?></td>
                        <td class="py-2.5 px-3 text-right font-black font-mono text-slate-900"><?= format_currency($m['share_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-black text-sm">
                    <td colspan="5" class="py-3 px-3 uppercase">Total Amount Due</td>
                    <td class="py-3 px-3 text-right font-mono text-orange-600"><?= format_currency($single_hosteller['total_owed']) ?></td>
                </tr>
            </tfoot>
        </table>

    <!-- VIEW 2: GROUP CREDENTIALS ROSTER -->
    <?php elseif ($view === 'credentials'): ?>
        <div class="mb-6">
            <h2 class="text-lg font-black text-slate-900 uppercase tracking-wide">Hostel Mess Group - Login Credentials & Assigned Codes</h2>
            <p class="text-xs text-slate-500">Authorized hosteller roster for Cafe Kamaal hotel meal calculation.</p>
        </div>

        <table class="w-full text-left border-collapse text-xs mb-6">
            <thead>
                <tr class="border-b-2 border-slate-800 font-bold text-slate-800 uppercase tracking-wider">
                    <th class="py-2.5 px-3">Code</th>
                    <th class="py-2.5 px-3">Hosteller Full Name</th>
                    <th class="py-2.5 px-3">Username</th>
                    <th class="py-2.5 px-3">Default Password</th>
                    <th class="py-2.5 px-3">Room #</th>
                    <th class="py-2.5 px-3">Phone</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php foreach ($hostellers as $h): ?>
                    <tr>
                        <td class="py-2.5 px-3 font-mono font-black text-sm text-slate-900">[<?= clean($h['code']) ?>]</td>
                        <td class="py-2.5 px-3 font-bold text-slate-800"><?= clean($h['full_name']) ?></td>
                        <td class="py-2.5 px-3 font-mono"><?= clean($h['username']) ?></td>
                        <td class="py-2.5 px-3 font-mono font-bold"><?= clean($h['plain_password_hint'] ?? 'password123') ?></td>
                        <td class="py-2.5 px-3"><?= clean($h['room_no'] ?? '-') ?></td>
                        <td class="py-2.5 px-3"><?= clean($h['phone'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <!-- VIEW 3: FULL MONTHLY MESS BILL SUMMARY -->
    <?php else: ?>
        <div class="mb-6">
            <h2 class="text-lg font-black text-slate-900 uppercase tracking-wide">Hosteller Monthly Mess Bill Summary Sheet</h2>
            <p class="text-xs text-slate-500">Total Group Meals: <strong><?= $total_meals_count ?> meals</strong> &bull; Total Mess Expenses: <strong><?= format_currency($total_mess_bill) ?></strong></p>
        </div>

        <table class="w-full text-left border-collapse text-xs mb-8">
            <thead>
                <tr class="border-b-2 border-slate-800 font-bold text-slate-800 uppercase tracking-wider">
                    <th class="py-2.5 px-3">Code</th>
                    <th class="py-2.5 px-3">Hosteller Name</th>
                    <th class="py-2.5 px-3">Room No</th>
                    <th class="py-2.5 px-3 text-center">Meals Attended</th>
                    <th class="py-2.5 px-3 text-center">% Share</th>
                    <th class="py-2.5 px-3 text-right">Calculated Bill Owed</th>
                    <th class="py-2.5 px-3 text-center">Hosteller Signature</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <?php foreach ($hostellers as $h): ?>
                    <?php $pct = ($total_mess_bill > 0) ? round(($h['total_owed'] / $total_mess_bill) * 100, 1) : 0; ?>
                    <tr>
                        <td class="py-3 px-3 font-mono font-black text-sm text-slate-900">[<?= clean($h['code']) ?>]</td>
                        <td class="py-3 px-3 font-bold text-slate-900"><?= clean($h['full_name']) ?></td>
                        <td class="py-3 px-3"><?= clean($h['room_no'] ?? '-') ?></td>
                        <td class="py-3 px-3 text-center font-mono"><?= $h['meals_attended'] ?></td>
                        <td class="py-3 px-3 text-center font-mono"><?= $pct ?>%</td>
                        <td class="py-3 px-3 text-right font-mono font-black text-sm text-slate-900"><?= format_currency($h['total_owed']) ?></td>
                        <td class="py-3 px-3 text-center text-slate-300">___________________</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-black text-sm bg-slate-50">
                    <td colspan="3" class="py-3 px-3 uppercase tracking-wider">Total Hotel Mess Bill</td>
                    <td class="py-3 px-3 text-center font-mono"><?= $total_meals_count ?></td>
                    <td class="py-3 px-3 text-center font-mono">100.0%</td>
                    <td class="py-3 px-3 text-right font-mono text-base text-orange-600"><?= format_currency($total_mess_bill) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <!-- Signature Block for Hostel Authority / Admin -->
    <div class="mt-12 pt-8 border-t border-slate-200 grid grid-cols-2 gap-8 text-xs text-slate-600">
        <div>
            <div class="border-b border-slate-400 w-48 mb-1"></div>
            <span class="font-bold">Mess Manager / Administrator Signature</span>
            <div class="text-[11px] text-slate-400">Cafe Kamaal Mess Office</div>
        </div>
        <div class="text-right flex flex-col items-end">
            <div class="border-b border-slate-400 w-48 mb-1"></div>
            <span class="font-bold">Hostel Warden / Head Verification</span>
            <div class="text-[11px] text-slate-400">Approved & Verified</div>
        </div>
    </div>

</div>

</body>
</html>
