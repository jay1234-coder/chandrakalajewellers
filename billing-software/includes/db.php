<?php
/**
 * ChandraKala Jewellers — Billing Software (POS) Database Connection
 * Connected to centralized Cloud Database (TiDB / MySQL) with automatic table sync.
 */

// Include the centralized project DB configuration and security wrappers
$cjDbConfig = __DIR__ . '/../../config/db.php';

if (file_exists($cjDbConfig)) {
    require_once $cjDbConfig;
}

// If $conn is not set by config/db.php, fallback to local MySQL connection
if (!isset($conn) || !$conn || (is_object($conn) && !empty($conn->connect_error))) {
    $host = "localhost";
    $user = "root";
    $password = "";
    $database = "jewellery_management";

    $conn = @mysqli_connect($host, $user, $password, $database);
    if (!$conn) {
        // Fallback to project database name
        $database = "if0_38635607_cjdb";
        $conn = @mysqli_connect($host, $user, $password, $database);
    }
}

if (!$conn || (is_object($conn) && !empty($conn->connect_error))) {
    die("Database Connection Failed: " . (isset($connError) ? $connError : mysqli_connect_error()));
}

// Ensure UTF-8mb4 character set for Gujarati/Hindi customer names and descriptions
@mysqli_set_charset($conn, "utf8mb4");

// Auto-Sync / Auto-Migration: Ensure billing tables exist in database
if (!function_exists('cj_ensure_billing_tables')) {
    function cj_ensure_billing_tables($conn) {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $checkRes = @mysqli_query($conn, "SHOW TABLES LIKE 'bills'");
        if ($checkRes && mysqli_num_rows($checkRes) > 0) {
            return; // Tables already exist
        }

        // Schema file location
        $sqlPath = __DIR__ . '/../database/jewellery_management.sql';
        if (!file_exists($sqlPath)) {
            $sqlPath = __DIR__ . '/../jewellery_management.sql';
        }

        if (file_exists($sqlPath)) {
            $sql = file_get_contents($sqlPath);
            if (!empty($sql)) {
                // Execute multi-query dump to create all billing tables
                if (mysqli_multi_query($conn, $sql)) {
                    do {
                        if ($result = mysqli_store_result($conn)) {
                            mysqli_free_result($result);
                        }
                    } while (mysqli_more_results($conn) && mysqli_next_result($conn));
                }
            }
        }
    }
}

cj_ensure_billing_tables($conn);
?>
