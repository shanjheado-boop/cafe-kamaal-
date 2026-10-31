<?php
// config/db.php - Cafe Kamaal Database Connection

// Load environment variables if .env file exists
$envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (!empty($key) && getenv($key) === false) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
            }
        }
    }
}

// Database Credentials (with environment variable support for cloud deployment)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'cafe_kamaal_db');
define('APP_NAME', getenv('APP_NAME') ?: 'Cafe Kamaal Hotel Bill Calculator');

function get_db_connection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        // Ensure database exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        
        // Auto-initialize tables if not created yet
        ensure_schema($pdo);
        
        return $pdo;
    } catch (PDOException $e) {
        die("<div style='font-family:sans-serif;padding:30px;background:#fff1f2;border:1px solid #f43f5e;color:#881337;border-radius:8px;max-width:600px;margin:50px auto;'>"
            . "<h2>Database Connection Error</h2>"
            . "<p>Could not connect to MySQL server. Please ensure MySQL is running in XAMPP.</p>"
            . "<p><strong>Details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>"
            . "</div>");
    }
}

function ensure_schema($pdo) {
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        // Tables not found, run initial schema
        $sqlFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
        }
    }
}
