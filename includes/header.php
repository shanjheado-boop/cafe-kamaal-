<?php
// includes/header.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

$pdo = get_db_connection();
$current_user = get_logged_in_user();

// Fetch available billing months
$all_months_stmt = $pdo->query("SELECT * FROM billing_months ORDER BY start_date DESC");
$all_billing_months = $all_months_stmt->fetchAll();

// Determine selected month from GET parameter or session or default
$selected_month_id = isset($_GET['month_id']) ? (int)$_GET['month_id'] : ($_SESSION['active_month_id'] ?? null);
$current_month = get_current_billing_month($pdo, $selected_month_id);
if ($current_month) {
    $_SESSION['active_month_id'] = $current_month['id'];
}

$page_title = $page_title ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($page_title) ?> - <?= APP_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
                        },
                        navy: {
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Progressive Web App (PWA) & Mobile Install Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Cafe Kamaal">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/icons/icon-192.png">
    <link rel="icon" type="image/svg+xml" href="assets/icons/icon.svg">
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col font-sans antialiased">

<!-- PWA Mobile App Install Banner (Prompt for Android / iOS) -->
<div id="pwaInstallBanner" class="hidden bg-gradient-to-r from-navy-900 via-slate-800 to-navy-900 text-white px-4 py-2.5 border-b border-brand-500/40 shadow-md">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-3 text-xs">
        <div class="flex items-center space-x-2.5">
            <span class="w-7 h-7 rounded-lg bg-brand-600 flex items-center justify-center text-white shrink-0 shadow">
                <i class="fa-solid fa-mobile-screen"></i>
            </span>
            <div>
                <span class="font-bold text-white">Install Cafe Kamaal App</span>
                <span class="hidden sm:inline text-slate-300"> &bull; Install on your mobile home screen for quick daily meal logging</span>
            </div>
        </div>
        <div class="flex items-center space-x-2 shrink-0">
            <button id="pwaInstallBtn" onclick="triggerPWAInstall()" class="px-3 py-1.5 bg-brand-600 hover:bg-brand-500 text-white rounded-lg font-bold shadow transition flex items-center gap-1.5">
                <i class="fa-solid fa-download"></i> Install App
            </button>
            <button onclick="dismissPWAInstall()" class="text-slate-400 hover:text-white p-1">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>
</div>

<!-- Navigation Bar -->
<header class="bg-navy-900 text-white shadow-lg sticky top-0 z-40 border-b border-slate-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Brand -->
            <div class="flex items-center space-x-3">
                <a href="dashboard.php" class="flex items-center space-x-2 group">
                    <span class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brand-600 to-amber-400 flex items-center justify-center text-white shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-utensils text-lg"></i>
                    </span>
                    <div>
                        <div class="text-base font-bold tracking-tight text-white flex items-center gap-1.5">
                            Cafe Kamaal
                            <span class="text-xs px-2 py-0.5 rounded bg-brand-500/20 text-brand-300 border border-brand-500/30 font-medium">Hotel Mess</span>
                        </div>
                        <div class="text-xs text-slate-400 font-normal">Monthly Bill Calculator</div>
                    </div>
                </a>
            </div>

            <!-- Month Selector Pill -->
            <div class="hidden md:flex items-center">
                <?php if ($current_month): ?>
                    <div class="flex items-center bg-slate-800/80 border border-slate-700 rounded-lg px-3 py-1.5 text-xs text-slate-200">
                        <i class="fa-regular fa-calendar-alt text-brand-400 mr-2"></i>
                        <span class="text-slate-400 mr-1.5">Cycle:</span>
                        <select onchange="window.location.href='?month_id=' + this.value" class="bg-transparent font-semibold text-white focus:outline-none cursor-pointer">
                            <?php foreach ($all_billing_months as $bm): ?>
                                <option value="<?= $bm['id'] ?>" class="bg-slate-800 text-white" <?= ($current_month && $bm['id'] == $current_month['id']) ? 'selected' : '' ?>>
                                    <?= clean($bm['name']) ?> <?= $bm['is_current'] ? '★' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Navigation Links -->
            <?php if (is_logged_in()): ?>
                <nav class="hidden md:flex items-center space-x-1 lg:space-x-2 text-sm font-medium">
                    <a href="dashboard.php" class="px-3 py-2 rounded-lg transition <?= ($page_title === 'Dashboard') ? 'bg-slate-800 text-white font-semibold' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-chart-pie mr-1.5 text-brand-400"></i> Dashboard
                    </a>
                    <a href="dashboard.php#meals-section" class="px-3 py-2 rounded-lg transition text-slate-300 hover:text-white hover:bg-slate-800/60">
                        <i class="fa-solid fa-receipt mr-1.5 text-amber-400"></i> Meal Log
                    </a>
                    <a href="my_expenses.php" class="px-3 py-2 rounded-lg transition <?= ($page_title === 'My Expenses') ? 'bg-slate-800 text-white font-semibold' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' ?>">
                        <i class="fa-solid fa-wallet mr-1.5 text-emerald-400"></i> My Expenses
                    </a>
                    <?php if (is_admin()): ?>
                        <a href="admin.php" class="px-3 py-2 rounded-lg transition <?= (strpos($page_title, 'Admin') !== false) ? 'bg-brand-600 text-white font-semibold' : 'text-brand-300 hover:text-white hover:bg-brand-600/40' ?>">
                            <i class="fa-solid fa-user-shield mr-1.5"></i> Admin Hub
                        </a>
                    <?php endif; ?>
                </nav>

                <!-- User Profile & Single Letter Code Pill -->
                <div class="flex items-center space-x-3">
                    <div class="flex items-center bg-slate-800 border border-slate-700/80 rounded-full pl-2 pr-3 py-1">
                        <?php if ($current_user['role'] === 'admin'): ?>
                            <span class="w-7 h-7 rounded-full bg-brand-600 text-white font-bold text-xs flex items-center justify-center mr-2 shadow-inner">
                                <i class="fa-solid fa-crown text-[10px]"></i>
                            </span>
                        <?php else: ?>
                            <span class="w-7 h-7 rounded-full bg-emerald-600 text-white font-black text-sm flex items-center justify-center mr-2 shadow-inner" title="Your Single Alphabet Code">
                                <?= clean($current_user['code'] ?? '?') ?>
                            </span>
                        <?php endif; ?>
                        <div class="text-left hidden sm:block">
                            <div class="text-xs font-semibold text-white leading-tight flex items-center gap-1">
                                <?= clean($current_user['full_name']) ?>
                                <?php if (!empty($current_user['code'])): ?>
                                    <span class="text-[10px] bg-emerald-500/20 text-emerald-300 px-1 py-0.2 rounded font-mono font-bold">Code <?= clean($current_user['code']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[10px] text-slate-400 capitalize"><?= clean($current_user['role']) ?></div>
                        </div>
                    </div>

                    <a href="logout.php" title="Sign Out" class="w-9 h-9 rounded-lg bg-slate-800 hover:bg-rose-900/40 text-slate-300 hover:text-rose-400 border border-slate-700 flex items-center justify-center transition" onclick="return confirm('Do you want to log out of Cafe Kamaal?')">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="flex items-center space-x-2">
                    <a href="index.php" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-semibold shadow transition">
                        <i class="fa-solid fa-lock mr-1.5"></i> Sign In
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Flash Notifications -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
    <?php if ($flash_err = get_flash('error')): ?>
        <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-lg shadow-sm flex items-start space-x-3 mb-4 animate-fade-in">
            <i class="fa-solid fa-circle-exclamation text-rose-500 text-lg mt-0.5"></i>
            <div class="flex-1">
                <h4 class="text-sm font-bold text-rose-800">Notice</h4>
                <p class="text-sm text-rose-700 mt-0.5"><?= clean($flash_err) ?></p>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-600"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <?php if ($flash_succ = get_flash('success')): ?>
        <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg shadow-sm flex items-start space-x-3 mb-4 animate-fade-in">
            <i class="fa-solid fa-circle-check text-emerald-500 text-lg mt-0.5"></i>
            <div class="flex-1">
                <h4 class="text-sm font-bold text-emerald-800">Success</h4>
                <p class="text-sm text-emerald-700 mt-0.5"><?= clean($flash_succ) ?></p>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-600"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>
</div>

<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
