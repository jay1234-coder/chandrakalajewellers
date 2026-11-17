<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/env.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLocal = function_exists('cj_is_local_host') && cj_is_local_host();

$host = cj_env('CJ_DB_HOST') ?: cj_env('POSTGRES_HOST') ?: cj_env('DB_HOST');
$user = cj_env('CJ_DB_USER') ?: cj_env('POSTGRES_USER') ?: cj_env('DB_USER');
$pass = cj_env('CJ_DB_PASS') ?? cj_env('POSTGRES_PASSWORD') ?? cj_env('DB_PASS', '');
$dbname = cj_env('CJ_DB_NAME') ?: cj_env('POSTGRES_DATABASE') ?: cj_env('DB_NAME');

if ($isLocal) {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'if0_38635607_cjdb';
} elseif ($host === null || $user === null || $dbname === null || $pass === null || $pass === '') {
    error_log('CJ database credentials are not configured. Set CJ_DB_* in config/.env (use a rotated password).');
    http_response_code(500);
    die('<div style="font-family:sans-serif;padding:30px;text-align:center;"><h2>Database Configuration Notice</h2><p>Database credentials are not configured. Set <code>CJ_DB_HOST</code>, <code>CJ_DB_USER</code>, <code>CJ_DB_PASS</code>, and <code>CJ_DB_NAME</code> in Vercel Environment Variables.</p></div>');
}

// Set default PHP timezone
date_default_timezone_set('Asia/Kolkata');

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    // If the database doesn't exist locally, try to auto-create it
    if ($host === 'localhost' && $conn->connect_errno === 1049) {
        $tempConn = new mysqli($host, $user, $pass);
        if (!$tempConn->connect_error) {
            $tempConn->query("CREATE DATABASE IF NOT EXISTS `" . $tempConn->real_escape_string($dbname) . "`");
            $tempConn->close();
            $conn = new mysqli($host, $user, $pass, $dbname);
        }
    }
    if ($conn->connect_error) {
        if ($isLocal) {
            die("Database connection failed: " . $conn->connect_error . ". Please make sure MySQL is running in XAMPP and database '" . htmlspecialchars($dbname) . "' is imported.");
        }
        error_log('CJ database connection failed: ' . $conn->connect_error);
        http_response_code(500);
        die('<div style="font-family:sans-serif;padding:30px;text-align:center;"><h2>Database Connection Error</h2><p>' . htmlspecialchars($conn->connect_error) . '</p><p>Please check your MySQL database host, port, user, and password in Vercel Environment Variables.</p></div>');
    }
}

// Set MySQL session timezone to IST
$conn->query("SET time_zone = '+05:30'");

require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/catalog.php';
require_once __DIR__ . '/../includes/customers.php';
require_once __DIR__ . '/../includes/rates-schema.php';
cj_ensure_settings_schema($conn);
cj_ensure_catalog_schema($conn);
cj_ensure_customers_schema($conn);
cj_ensure_rates_schema($conn);
