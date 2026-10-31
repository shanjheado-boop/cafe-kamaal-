<?php
// register.php - Hosteller Registration with Single Alphabet Code
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = get_db_connection();

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

// Fetch already taken single alphabet codes
$taken_codes_stmt = $pdo->query("SELECT code FROM users WHERE code IS NOT NULL AND is_active = 1");
$taken_codes = array_map('strtoupper', $taken_codes_stmt->fetchAll(PDO::FETCH_COLUMN));

// Generate list of available single letters A to Z
$alphabet = range('A', 'Z');
$available_codes = array_values(array_diff($alphabet, $taken_codes));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = clean($_POST['full_name'] ?? '');
    $username = clean($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $room_no = clean($_POST['room_no'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));

    if (empty($full_name) || empty($username) || empty($password) || empty($code)) {
        $error = "Please fill in all required fields (Name, Username, Password, and Code).";
    } elseif (!is_valid_single_alphabet_code($code)) {
        $error = "Hosteller Code must be a single alphabet character (A to Z).";
    } elseif (in_array($code, $taken_codes)) {
        $error = "Code '{$code}' is already assigned to another hosteller. Please choose another code.";
    } else {
        // Check unique username
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->execute([$username]);
        if ($check_stmt->fetch()) {
            $error = "Username '{$username}' is already taken. Please pick another username.";
        } else {
            // Hash password
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, role, code, phone, room_no, plain_password_hint, is_active)
                VALUES (?, ?, ?, 'hosteller', ?, ?, ?, ?, 1)
            ");
            $insert_stmt->execute([$username, $hashed, $full_name, $code, $phone, $room_no, $password]);
            $new_user_id = $pdo->lastInsertId();

            // Auto-login
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['username'] = $username;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role'] = 'hosteller';
            $_SESSION['code'] = $code;

            log_audit($pdo, null, $new_user_id, 'CREATE', "Hosteller $full_name registered with Code [$code] and Room $room_no.");

            set_flash('success', "Registration successful! Welcome to Cafe Kamaal. Your Code is [$code].");
            header("Location: dashboard.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hosteller Registration - Cafe Kamaal Hotel Mess</title>
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

<div class="max-w-lg w-full bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">
    <!-- Header -->
    <div class="bg-gradient-to-r from-navy-900 to-slate-800 text-white p-6">
        <div class="flex items-center space-x-3">
            <span class="w-10 h-10 rounded-xl bg-brand-600 flex items-center justify-center text-white text-lg shadow">
                <i class="fa-solid fa-user-plus"></i>
            </span>
            <div>
                <h1 class="text-xl font-black">Register Hosteller Account</h1>
                <p class="text-xs text-slate-300">Join Cafe Kamaal Hostel Mess Billing System</p>
            </div>
        </div>
    </div>

    <!-- Form -->
    <form action="register.php" method="POST" class="p-6 sm:p-8 space-y-4">
        <?php if (!empty($error)): ?>
            <div class="bg-rose-50 border-l-4 border-rose-500 p-3 rounded-r-lg text-xs text-rose-700 font-semibold animate-fade-in flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-500"></i>
                <?= clean($error) ?>
            </div>
        <?php endif; ?>

        <!-- Full Name -->
        <div>
            <label for="full_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                Full Name <span class="text-rose-500">*</span>
            </label>
            <input type="text" name="full_name" id="full_name" required placeholder="e.g. Kamran Akmal"
                   value="<?= clean($_POST['full_name'] ?? '') ?>"
                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-brand-500 text-sm font-medium">
        </div>

        <!-- Single Alphabet Code Selector (Crucial Requirement) -->
        <div>
            <div class="flex items-center justify-between mb-1">
                <label for="code" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                    Assigned Single Alphabet Code (A-Z) <span class="text-rose-500">*</span>
                </label>
                <span class="text-[11px] text-brand-600 font-semibold"><?= count($available_codes) ?> letters free</span>
            </div>
            
            <div class="relative">
                <select name="code" id="code" required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-brand-500 text-sm font-bold bg-white cursor-pointer font-mono">
                    <option value="">-- Choose Your Single Letter Code --</option>
                    <?php foreach ($available_codes as $letter): ?>
                        <option value="<?= $letter ?>" <?= (isset($_POST['code']) && strtoupper($_POST['code']) === $letter) ? 'selected' : '' ?>>
                            Code [<?= $letter ?>] - Available
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="text-[11px] text-slate-500 mt-1">
                Each hosteller has a unique 1-letter code used as divider for meal splits.
            </p>
        </div>

        <!-- Username & Password -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="username" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Username <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="username" id="username" required placeholder="e.g. hosteller_g"
                       value="<?= clean($_POST['username'] ?? '') ?>"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-brand-500 text-sm font-medium">
            </div>
            <div>
                <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Password <span class="text-rose-500">*</span>
                </label>
                <input type="password" name="password" id="password" required placeholder="Create password"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-brand-500 text-sm font-medium">
            </div>
        </div>

        <!-- Room & Phone -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="room_no" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Hostel Room No
                </label>
                <input type="text" name="room_no" id="room_no" placeholder="e.g. Room 107"
                       value="<?= clean($_POST['room_no'] ?? '') ?>"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-brand-500 text-sm font-medium">
            </div>
            <div>
                <label for="phone" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Phone / WhatsApp
                </label>
                <input type="text" name="phone" id="phone" placeholder="e.g. +92 300 1234567"
                       value="<?= clean($_POST['phone'] ?? '') ?>"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-brand-500 text-sm font-medium">
            </div>
        </div>

        <button type="submit" class="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl text-sm shadow-md hover:shadow-lg transition flex items-center justify-center gap-2 mt-2">
            <i class="fa-solid fa-check"></i> Complete Registration
        </button>

        <div class="text-center text-xs text-slate-500 pt-2 border-t border-slate-100">
            Already have an account? <a href="index.php" class="text-brand-600 font-bold hover:underline">Log in here</a>
        </div>
    </form>
</div>

</body>
</html>
