<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/crypto.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    if (!empty($_COOKIE['cj_csrf']) && is_string($_COOKIE['cj_csrf']) && strlen($_COOKIE['cj_csrf']) >= 32) {
        $_SESSION['csrf_token'] = (string) $_COOKIE['cj_csrf'];
    } else {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
// Persist CSRF token in a secure cookie for ALL visitors (customers & admins)
// so serverless container switches on Vercel never reject valid form submissions.
if (empty($_COOKIE['cj_csrf']) || $_COOKIE['cj_csrf'] !== $_SESSION['csrf_token']) {
    if (!headers_sent() && function_exists('cj_session_cookie_params')) {
        $csrfParams = cj_session_cookie_params();
        setcookie('cj_csrf', $_SESSION['csrf_token'], [
            'expires' => time() + 604800,
            'path' => '/',
            'domain' => $csrfParams['domain'],
            'secure' => $csrfParams['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['cj_csrf'] = $_SESSION['csrf_token'];
    }
}

$isLocal = function_exists('cj_is_local_host') && cj_is_local_host();

$host = trim((string)(cj_env('CJ_DB_HOST') ?? ''));
$user = trim((string)(cj_env('CJ_DB_USER') ?? ''));
$pass = trim((string)(cj_env('CJ_DB_PASS', '') ?? ''));
$dbname = trim((string)(cj_env('CJ_DB_NAME') ?? ''));
$port = (int) trim((string)(cj_env('CJ_DB_PORT', '3306') ?? '3306'));

if (strpos((string)$host, ':') !== false) {
    [$hostOnly, $portStr] = explode(':', (string)$host, 2);
    $host = trim($hostOnly);
    $port = (int)trim($portStr);
}

if ($isLocal) {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'if0_38635607_cjdb';
    $port = 3306;
} elseif ($host === '' || $user === '' || $dbname === '') {
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

$conn = null;
$connError = null;

try {
    if ($ssl && extension_loaded('mysqli')) {
        $conn = mysqli_init();
        $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
        @$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        @$conn->real_connect($host, $user, $pass, $dbname, $port, NULL, MYSQLI_CLIENT_SSL);
    } else {
        $conn = @new mysqli($host, $user, $pass, $dbname, $port);
    }
} catch (Throwable $e) {
    $connError = $e->getMessage();
}

if ($conn === null || (is_object($conn) && $conn->connect_error) || $connError !== null) {
    $err = $connError ?? ($conn ? $conn->connect_error : 'Unknown database connection error');

    // If the database doesn't exist locally, try to auto-create it
    if ($host === 'localhost') {
        try {
            $tempConn = @new mysqli($host, $user, $pass);
            if ($tempConn && !$tempConn->connect_error) {
                $tempConn->query("CREATE DATABASE IF NOT EXISTS `" . $tempConn->real_escape_string($dbname) . "`");
                $tempConn->close();
                $conn = @new mysqli($host, $user, $pass, $dbname, $port);
                if ($conn && !$conn->connect_error) {
                    $connError = null;
                }
            }
        } catch (Throwable $ignore) {}
    }

    if ($connError !== null || $conn === null || (is_object($conn) && $conn->connect_error)) {
        if ($isLocal) {
            die("Database connection failed: " . htmlspecialchars($err) . ". Please make sure MySQL is running in XAMPP and database '" . htmlspecialchars($dbname) . "' is imported.");
        }
        error_log('CJ database connection failed: ' . $err);
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
// Ensure cloud MySQL instances never fail on strict only_full_group_by
@$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
$GLOBALS['conn'] = $conn;

if (!function_exists('cj_get_db')) {
    function cj_get_db(): ?mysqli {
        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            return $conn;
        }
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            return $GLOBALS['conn'];
        }
        return null;
    }
}

if (!function_exists('cj_format_ist')) {
    /**
     * Format a database or unix timestamp in Indian Standard Time (Asia/Kolkata).
     * Returns e.g. "17 Nov 2026, 07:13 PM IST".
     */
    function cj_format_ist($datetime, string $format = 'd M Y, h:i A', bool $withSuffix = true): string {
        if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
            return '—';
        }
        try {
            $ist = new DateTimeZone('Asia/Kolkata');
            if (is_numeric($datetime)) {
                $dt = new DateTime('@' . (int)$datetime);
                $dt->setTimezone($ist);
            } else {
                // DB session runs in +05:30, so naive SQL strings are already IST.
                $dt = new DateTime((string)$datetime, $ist);
                $dt->setTimezone($ist);
            }
            return $dt->format($format) . ($withSuffix ? ' IST' : '');
        } catch (Throwable $e) {
            return (string)$datetime;
        }
    }
}

if (!function_exists('cj_format_ist_iso')) {
    /** Format a datetime as ISO-8601 with +05:30 offset for Schema.org & Sitemaps. */
    function cj_format_ist_iso($datetime = null): string {
        try {
            $ist = new DateTimeZone('Asia/Kolkata');
            if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
                $dt = new DateTime('now', $ist);
            } elseif (is_numeric($datetime)) {
                $dt = new DateTime('@' . (int)$datetime);
                $dt->setTimezone($ist);
            } else {
                $dt = new DateTime((string)$datetime, $ist);
                $dt->setTimezone($ist);
            }
            return $dt->format('c');
        } catch (Throwable $e) {
            return date('c');
        }
    }
}

require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/catalog.php';
require_once __DIR__ . '/../includes/customers.php';
require_once __DIR__ . '/../includes/rates-schema.php';

// Run schema initialization only on local development or explicit setup request
if ($isLocal || !empty($_GET['cj_schema_setup'])) {
    cj_ensure_settings_schema($conn);
    cj_ensure_catalog_schema($conn);
    cj_ensure_customers_schema($conn);
    cj_ensure_rates_schema($conn);
}
