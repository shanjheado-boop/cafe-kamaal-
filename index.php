<?php
// index.php - Login Portal for Cafe Kamaal Hotel Bill Calculator
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = get_db_connection();

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['code'] = $user['code'];

            set_flash('success', "Welcome back, " . $user['full_name'] . ($user['code'] ? " (Code {$user['code']})" : "") . "!");
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cafe Kamaal Hotel Bill Calculator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
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
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">

<div class="max-w-4xl w-full grid grid-cols-1 lg:grid-cols-12 bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">

    <!-- Left Brand & Info Panel (5 cols) -->
    <div class="lg:col-span-5 bg-gradient-to-br from-navy-900 via-slate-800 to-navy-900 text-white p-8 flex flex-col justify-between">
        <div>
            <!-- Brand Badge -->
            <div class="flex items-center space-x-3 mb-6">
                <span class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-600 to-amber-400 flex items-center justify-center text-white text-xl shadow-lg">
                    <i class="fa-solid fa-utensils"></i>
                </span>
                <div>
                    <h1 class="text-xl font-black tracking-tight leading-none">Cafe Kamaal</h1>
                    <span class="text-xs text-amber-300 font-medium tracking-wide">Hotel Mess Bill Calculator</span>
                </div>
            </div>

            <h2 class="text-2xl font-black text-white leading-snug">
                Transparent Meal Bill Sharing for Hostellers
            </h2>
            <p class="text-slate-300 text-xs mt-3 leading-relaxed">
                Log meal prices and select single alphabet codes (A, B, C...) of hostellers who ate. The system automatically divides the meal cost equally among the eaters and tracks who created or edited each entry.
            </p>

            <!-- Feature Bullet List -->
            <div class="mt-6 space-y-3 text-xs text-slate-300">
                <div class="flex items-center space-x-2.5">
                    <span class="w-5 h-5 rounded-md bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px]">
                        <i class="fa-solid fa-check"></i>
                    </span>
                    <span>Single alphabet codes (A-Z) assigned to each hosteller</span>
                </div>
                <div class="flex items-center space-x-2.5">
                    <span class="w-5 h-5 rounded-md bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px]">
                        <i class="fa-solid fa-check"></i>
                    </span>
                    <span>Automated cost division ($Price \div N$ eaters)</span>
                </div>
                <div class="flex items-center space-x-2.5">
                    <span class="w-5 h-5 rounded-md bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px]">
                        <i class="fa-solid fa-check"></i>
                    </span>
                    <span>Shows the bill edited by the last person on login</span>
                </div>
                <div class="flex items-center space-x-2.5">
                    <span class="w-5 h-5 rounded-md bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px]">
                        <i class="fa-solid fa-check"></i>
                    </span>
                    <span>Admin group creation and credential sharing hub</span>
                </div>
            </div>
        </div>

        <div class="mt-8 pt-4 border-t border-slate-700/60 text-[11px] text-slate-400 flex items-center justify-between">
            <span>Cafe Kamaal Mess System</span>
            <span>PHP 8 & MySQL</span>
        </div>
    </div>

    <!-- Right Login Form & Quick Credentials (7 cols) -->
    <div class="lg:col-span-7 p-8 sm:p-10 flex flex-col justify-center">
        <div class="max-w-md w-full mx-auto">

            <div class="mb-6">
                <h3 class="text-2xl font-black text-slate-900">Sign In to Your Account</h3>
                <p class="text-xs text-slate-500 mt-1">Enter your assigned hosteller or admin credentials</p>
            </div>

            <!-- Flash Error -->
            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 border-l-4 border-rose-500 p-3 rounded-r-lg text-xs text-rose-700 font-semibold mb-4 animate-fade-in flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-500"></i>
                    <?= clean($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($flash_err = get_flash('error')): ?>
                <div class="bg-rose-50 border-l-4 border-rose-500 p-3 rounded-r-lg text-xs text-rose-700 font-semibold mb-4 animate-fade-in">
                    <?= clean($flash_err) ?>
                </div>
            <?php endif; ?>

            <?php if ($flash_succ = get_flash('success')): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-3 rounded-r-lg text-xs text-emerald-700 font-semibold mb-4 animate-fade-in">
                    <?= clean($flash_succ) ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="index.php" method="POST" class="space-y-4" id="loginForm">
                <div>
                    <label for="username" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Username
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                            <i class="fa-regular fa-user text-sm"></i>
                        </span>
                        <input type="text" name="username" id="username" required
                               placeholder="e.g. hosteller_a or admin"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm font-medium">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Password
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </span>
                        <input type="password" name="password" id="password" required
                               placeholder="Enter your password"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm font-medium">
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl text-sm shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In to Dashboard
                </button>
            </form>

            <!-- Registration Link -->
            <div class="mt-8 pt-6 border-t border-slate-200 text-center text-xs text-slate-500">
                Are you a new hosteller? 
                <a href="register.php" class="text-brand-600 hover:underline font-bold">Register Account</a> 
                or contact the Main Admin.
            </div>

        </div>
    </div>

</div>

</body>
</html>
