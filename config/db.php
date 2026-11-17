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

$host = cj_env('CJ_DB_HOST');
$user = cj_env('CJ_DB_USER');
$pass = cj_env('CJ_DB_PASS', '');
$dbname = cj_env('CJ_DB_NAME');
$port = (int) cj_env('CJ_DB_PORT', '3306');

if (strpos((string)$host, ':') !== false) {
    [$hostOnly, $portStr] = explode(':', (string)$host, 2);
    $host = $hostOnly;
    $port = (int)$portStr;
}

if ($isLocal) {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'if0_38635607_cjdb';
    $port = 3306;
} elseif ($host === null || $user === null || $dbname === null) {
    error_log('CJ database credentials are not configured. Set CJ_DB_* in Vercel Environment Variables.');
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>ChandraKala Jewellers — System Maintenance</title>';
    echo '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:20px;box-sizing:border-box;}';
    echo '.card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:32px;max-width:560px;text-align:center;box-shadow:0 10px 25px rgba(0,0,0,0.5);}';
    echo 'h1{color:#e2b342;margin-top:0;font-size:24px;}p{color:#94a3b8;line-height:1.6;font-size:15px;}</style></head>';
    echo '<body><div class="card"><h1>ChandraKala Jewellers</h1>';
    echo '<p>Our website is currently undergoing scheduled database maintenance as we migrate to our new cloud infrastructure.</p>';
    echo '<p>Please check back shortly, or reach us directly at <strong>+91 9427080359</strong> for immediate assistance.</p>';
    echo '</div></body></html>';
    exit;
}

// Set default PHP timezone
date_default_timezone_set('Asia/Kolkata');

$ssl = cj_env('CJ_DB_SSL', '0') === '1' || strpos((string)$host, 'tidbcloud') !== false || strpos((string)$host, 'aiven') !== false;

if ($ssl && extension_loaded('mysqli')) {
    $conn = mysqli_init();
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    @$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    @$conn->real_connect($host, $user, $pass, $dbname, $port, NULL, MYSQLI_CLIENT_SSL);
} else {
    $conn = @new mysqli($host, $user, $pass, $dbname, $port);
}

if ($conn->connect_error) {
    // If the database doesn't exist locally, try to auto-create it
    if ($host === 'localhost' && $conn->connect_errno === 1049) {
        $tempConn = @new mysqli($host, $user, $pass);
        if (!$tempConn->connect_error) {
            $tempConn->query("CREATE DATABASE IF NOT EXISTS `" . $tempConn->real_escape_string($dbname) . "`");
            $tempConn->close();
            $conn = @new mysqli($host, $user, $pass, $dbname, $port);
        }
    }
    if ($conn->connect_error) {
        if ($isLocal) {
            die("Database connection failed: " . $conn->connect_error . ". Please make sure MySQL is running in XAMPP and database '" . htmlspecialchars($dbname) . "' is imported.");
        }
        error_log('CJ database connection failed: ' . $conn->connect_error);
        http_response_code(503);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>ChandraKala Jewellers — System Maintenance</title>';
        echo '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:20px;box-sizing:border-box;}';
        echo '.card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:32px;max-width:560px;text-align:center;box-shadow:0 10px 25px rgba(0,0,0,0.5);}';
        echo 'h1{color:#e2b342;margin-top:0;font-size:24px;}p{color:#94a3b8;line-height:1.6;font-size:15px;}</style></head>';
        echo '<body><div class="card"><h1>ChandraKala Jewellers</h1>';
        echo '<p>Our website is currently undergoing scheduled database maintenance as we migrate to our new cloud infrastructure.</p>';
        echo '<p>Please check back shortly, or reach us directly at <strong>+91 9427080359</strong> for immediate assistance.</p>';
        echo '</div></body></html>';
        exit;
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
