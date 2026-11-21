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
@mysqli_query($conn, "SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Auto-Sync / Auto-Migration: Ensure billing tables exist in database
if (!function_exists('cj_ensure_billing_tables')) {
    function cj_ensure_billing_tables($conn) {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $checkRes = @mysqli_query($conn, "SHOW TABLES LIKE 'bills'");
        $billsExist = ($checkRes && mysqli_num_rows($checkRes) > 0);

        // Ensure customer table has required POS columns and nullable password_hash
        $custCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'customers'");
        if ($custCheck && mysqli_num_rows($custCheck) > 0) {
            $colsRes = @mysqli_query($conn, "DESCRIBE customers");
            $cols = [];
            $nullStatus = [];
            if ($colsRes) {
                while ($c = mysqli_fetch_assoc($colsRes)) {
                    $cols[] = $c['Field'];
                    $nullStatus[$c['Field']] = $c['Null'] ?? 'YES';
                }
            }
            if (!in_array('mobile', $cols)) { @mysqli_query($conn, "ALTER TABLE customers ADD COLUMN mobile VARCHAR(20) NULL"); }
            if (!in_array('address', $cols)) { @mysqli_query($conn, "ALTER TABLE customers ADD COLUMN address TEXT NULL"); }
            if (!in_array('gst_no', $cols)) { @mysqli_query($conn, "ALTER TABLE customers ADD COLUMN gst_no VARCHAR(100) NULL"); }
            if (!in_array('pan_no', $cols)) { @mysqli_query($conn, "ALTER TABLE customers ADD COLUMN pan_no VARCHAR(100) NULL"); }
            if (in_array('password_hash', $cols) && ($nullStatus['password_hash'] ?? '') === 'NO') {
                @mysqli_query($conn, "ALTER TABLE customers MODIFY COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL");
            }
            if (in_array('phone', $cols) && ($nullStatus['phone'] ?? '') === 'NO') {
                @mysqli_query($conn, "ALTER TABLE customers MODIFY COLUMN phone VARCHAR(20) NULL DEFAULT NULL");
            }
        }

        // Ensure bills table has AUTO_INCREMENT on id (TiDB / MySQL)
        $checkBills = @mysqli_query($conn, "SHOW COLUMNS FROM bills WHERE Field = 'id'");
        if (!$checkBills || mysqli_num_rows($checkBills) === 0) {
            @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `bills` (
              `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `bill_no` varchar(50) DEFAULT NULL,
              `bill_date` date DEFAULT NULL,
              `customer_name` varchar(255) DEFAULT NULL,
              `mobile` varchar(20) DEFAULT NULL,
              `address` text DEFAULT NULL,
              `item_type` varchar(50) DEFAULT NULL,
              `purity` varchar(20) DEFAULT NULL,
              `hsn_code` varchar(50) DEFAULT NULL,
              `quantity` decimal(10,3) DEFAULT NULL,
              `rate` decimal(12,2) DEFAULT NULL,
              `making_percent` decimal(5,2) DEFAULT NULL,
              `gold_value` decimal(12,2) DEFAULT NULL,
              `making_amount` decimal(12,2) DEFAULT NULL,
              `subtotal` decimal(12,2) DEFAULT NULL,
              `cgst` decimal(12,2) DEFAULT NULL,
              `sgst` decimal(12,2) DEFAULT NULL,
              `net_amount` decimal(12,2) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `bill_type` enum('SALE','LABOUR') NOT NULL DEFAULT 'SALE',
              `status` varchar(20) DEFAULT 'ACTIVE',
              `cancel_reason` varchar(255) DEFAULT NULL,
              `gst_no` varchar(100) DEFAULT NULL,
              `state_code` varchar(50) DEFAULT NULL,
              `pan_no` varchar(100) DEFAULT NULL,
              `delivery_option` varchar(50) NOT NULL DEFAULT 'At Shop',
              `gold_rate` decimal(12,2) DEFAULT NULL,
              `silver_rate` decimal(10,2) NOT NULL DEFAULT 1000.00,
              `gold_credit` decimal(10,3) DEFAULT 0.000,
              `discount` decimal(12,2) DEFAULT 0.00,
              `cash_received` decimal(12,2) DEFAULT 0.00,
              `bank_received` decimal(12,2) DEFAULT 0.00,
              `old_gold_amount` decimal(12,2) DEFAULT 0.00,
              `balance_amount` decimal(12,2) DEFAULT 0.00
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $bCol = mysqli_fetch_assoc($checkBills);
            if (empty($bCol['Extra']) || stripos($bCol['Extra'], 'auto_increment') === false) {
                @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `_bills_ai` (
                  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `bill_no` varchar(50) DEFAULT NULL,
                  `bill_date` date DEFAULT NULL,
                  `customer_name` varchar(255) DEFAULT NULL,
                  `mobile` varchar(20) DEFAULT NULL,
                  `address` text DEFAULT NULL,
                  `item_type` varchar(50) DEFAULT NULL,
                  `purity` varchar(20) DEFAULT NULL,
                  `hsn_code` varchar(50) DEFAULT NULL,
                  `quantity` decimal(10,3) DEFAULT NULL,
                  `rate` decimal(12,2) DEFAULT NULL,
                  `making_percent` decimal(5,2) DEFAULT NULL,
                  `gold_value` decimal(12,2) DEFAULT NULL,
                  `making_amount` decimal(12,2) DEFAULT NULL,
                  `subtotal` decimal(12,2) DEFAULT NULL,
                  `cgst` decimal(12,2) DEFAULT NULL,
                  `sgst` decimal(12,2) DEFAULT NULL,
                  `net_amount` decimal(12,2) DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `bill_type` enum('SALE','LABOUR') NOT NULL DEFAULT 'SALE',
                  `status` varchar(20) DEFAULT 'ACTIVE',
                  `cancel_reason` varchar(255) DEFAULT NULL,
                  `gst_no` varchar(100) DEFAULT NULL,
                  `state_code` varchar(50) DEFAULT NULL,
                  `pan_no` varchar(100) DEFAULT NULL,
                  `delivery_option` varchar(50) NOT NULL DEFAULT 'At Shop',
                  `gold_rate` decimal(12,2) DEFAULT NULL,
                  `silver_rate` decimal(10,2) NOT NULL DEFAULT 1000.00,
                  `gold_credit` decimal(10,3) DEFAULT 0.000,
                  `discount` decimal(12,2) DEFAULT 0.00,
                  `cash_received` decimal(12,2) DEFAULT 0.00,
                  `bank_received` decimal(12,2) DEFAULT 0.00,
                  `old_gold_amount` decimal(12,2) DEFAULT 0.00,
                  `balance_amount` decimal(12,2) DEFAULT 0.00
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                @mysqli_query($conn, "INSERT INTO `_bills_ai` SELECT * FROM `bills`");
                @mysqli_query($conn, "RENAME TABLE `bills` TO `_bills_old`, `_bills_ai` TO `bills`");
                @mysqli_query($conn, "DROP TABLE IF EXISTS `_bills_old`");
            }
        }

        // Ensure bill_items table has AUTO_INCREMENT on id (TiDB / MySQL)
        $checkItems = @mysqli_query($conn, "SHOW COLUMNS FROM bill_items WHERE Field = 'id'");
        if (!$checkItems || mysqli_num_rows($checkItems) === 0) {
            @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `bill_items` (
              `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
              `bill_id` int NOT NULL,
              `description` varchar(255) DEFAULT NULL,
              `hsn_code` varchar(50) DEFAULT NULL,
              `quantity` decimal(10,3) DEFAULT NULL,
              `rate` decimal(12,2) DEFAULT NULL,
              `making_percent` decimal(5,2) DEFAULT NULL,
              `item_metal_type` varchar(20) DEFAULT 'gold',
              `purity_title` varchar(50) DEFAULT NULL,
              `gold_value` decimal(12,2) DEFAULT NULL,
              `making_amount` decimal(12,2) DEFAULT NULL,
              `total_amount` decimal(12,2) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $iCol = mysqli_fetch_assoc($checkItems);
            if (empty($iCol['Extra']) || stripos($iCol['Extra'], 'auto_increment') === false) {
                @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `_bill_items_ai` (
                  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `bill_id` int NOT NULL,
                  `description` varchar(255) DEFAULT NULL,
                  `hsn_code` varchar(50) DEFAULT NULL,
                  `quantity` decimal(10,3) DEFAULT NULL,
                  `rate` decimal(12,2) DEFAULT NULL,
                  `making_percent` decimal(5,2) DEFAULT NULL,
                  `item_metal_type` varchar(20) DEFAULT 'gold',
                  `purity_title` varchar(50) DEFAULT NULL,
                  `gold_value` decimal(12,2) DEFAULT NULL,
                  `making_amount` decimal(12,2) DEFAULT NULL,
                  `total_amount` decimal(12,2) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                @mysqli_query($conn, "INSERT INTO `_bill_items_ai` SELECT * FROM `bill_items`");
                @mysqli_query($conn, "RENAME TABLE `bill_items` TO `_bill_items_old`, `_bill_items_ai` TO `bill_items`");
                @mysqli_query($conn, "DROP TABLE IF EXISTS `_bill_items_old`");
            }
        }

        // Ensure master tables exist
        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `company_settings` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `shop_name` varchar(255) DEFAULT 'Chandrakala Jewellers',
          `address` text DEFAULT NULL,
          `gst_no` varchar(100) DEFAULT NULL,
          `pan_no` varchar(100) DEFAULT NULL,
          `state_code` varchar(20) DEFAULT '24',
          `bank_name` varchar(255) DEFAULT NULL,
          `account_no` varchar(255) DEFAULT NULL,
          `ifsc` varchar(100) DEFAULT NULL,
          `logo` varchar(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `hsn_master` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `hsn_code` varchar(50) DEFAULT NULL,
          `description` varchar(255) DEFAULT NULL,
          `gst_rate` decimal(5,2) DEFAULT 3.00
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `purity_master` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `purity_name` varchar(50) DEFAULT NULL,
          `metal_type` varchar(20) DEFAULT NULL,
          `purity_value` varchar(20) DEFAULT NULL,
          `rate` decimal(12,2) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `rate_master` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `purity_id` int NOT NULL,
          `rate` decimal(12,2) NOT NULL,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `settings` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `setting_name` varchar(100) DEFAULT NULL,
          `setting_value` varchar(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `settings_backup` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `backup_folder` varchar(500) DEFAULT '../backups/',
          `auto_backup` tinyint(1) DEFAULT 0,
          `last_backup` datetime DEFAULT NULL,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `settings_business` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `shop_name` varchar(255) DEFAULT 'Chandrakala Jewellers',
          `owner_name` varchar(255) DEFAULT 'Jay Soni',
          `address` text DEFAULT NULL,
          `mobile` varchar(20) DEFAULT '9427080359',
          `email` varchar(100) DEFAULT NULL,
          `gst_no` varchar(30) DEFAULT NULL,
          `pan_no` varchar(20) DEFAULT NULL,
          `logo` varchar(255) DEFAULT NULL,
          `invoice_footer` text DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `settings_notifications` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `backup_reminder` tinyint(1) DEFAULT 1,
          `pending_payment_alert` tinyint(1) DEFAULT 1,
          `low_storage_alert` tinyint(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `settings_printing` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `paper_size` enum('A4','A5') DEFAULT 'A5',
          `show_logo` tinyint(1) DEFAULT 1,
          `show_gst` tinyint(1) DEFAULT 1,
          `show_qr` tinyint(1) DEFAULT 1,
          `show_signature` tinyint(1) DEFAULT 1,
          `default_print_mode` enum('preview','direct') DEFAULT 'preview'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
