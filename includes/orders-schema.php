<?php
/**
 * Orders + unique-piece reservation schema.
 * Jewellery items are treated as qty 1. Checkout locks the row so two
 * customers cannot reserve the same piece.
 */
if (!function_exists('cj_ensure_orders_schema')) {
    function cj_ensure_orders_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $conn->query("CREATE TABLE IF NOT EXISTS orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_no VARCHAR(24) NOT NULL,
            customer_name VARCHAR(120) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            customer_address VARCHAR(500) NOT NULL DEFAULT '',
            customer_pincode VARCHAR(10) NOT NULL DEFAULT '',
            status ENUM('reserved','confirmed','cancelled','expired') NOT NULL DEFAULT 'reserved',
            source VARCHAR(32) NOT NULL DEFAULT 'checkout',
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            reserved_until DATETIME DEFAULT NULL,
            confirmed_at DATETIME DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            payment_status VARCHAR(16) NOT NULL DEFAULT 'unpaid',
            payment_method VARCHAR(16) NOT NULL DEFAULT 'whatsapp',
            razorpay_order_id VARCHAR(64) DEFAULT NULL,
            razorpay_payment_id VARCHAR(64) DEFAULT NULL,
            razorpay_signature VARCHAR(128) DEFAULT NULL,
            paid_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_order_no (order_no),
            KEY idx_status (status),
            KEY idx_reserved_until (reserved_until),
            KEY idx_phone (customer_phone),
            KEY idx_payment_status (payment_status),
            KEY idx_razorpay_order_id (razorpay_order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS order_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            metal_type VARCHAR(32) NOT NULL DEFAULT '',
            weight DECIMAL(10,3) NOT NULL DEFAULT 0,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            qty INT UNSIGNED NOT NULL DEFAULT 1,
            KEY idx_order_id (order_id),
            KEY idx_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $col = $conn->query("SHOW COLUMNS FROM jewellery_items LIKE 'reserved_order_id'");
        if ($col && $col->num_rows === 0) {
            $conn->query("ALTER TABLE jewellery_items
                ADD COLUMN reserved_order_id INT UNSIGNED DEFAULT NULL AFTER is_sold_out,
                ADD COLUMN reserved_until DATETIME DEFAULT NULL AFTER reserved_order_id");
        }

        $priceCol = $conn->query("SHOW COLUMNS FROM jewellery_items LIKE 'cached_price'");
        if ($priceCol && $priceCol->num_rows === 0) {
            $conn->query("ALTER TABLE jewellery_items
                ADD COLUMN cached_price DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER weight");
            $conn->query("ALTER TABLE jewellery_items ADD INDEX idx_metal_price (metal_type, cached_price, id)");
        }

        $orderAlters = [
            'customer_pincode' => "ADD COLUMN customer_pincode VARCHAR(10) NOT NULL DEFAULT '' AFTER customer_address",
            'customer_email' => "ADD COLUMN customer_email VARCHAR(160) NOT NULL DEFAULT '' AFTER customer_pincode",
            'customer_id' => "ADD COLUMN customer_id INT UNSIGNED DEFAULT NULL AFTER customer_email",
            'payment_status' => "ADD COLUMN payment_status VARCHAR(16) NOT NULL DEFAULT 'unpaid' AFTER cancelled_at",
            'payment_method' => "ADD COLUMN payment_method VARCHAR(16) NOT NULL DEFAULT 'whatsapp' AFTER payment_status",
            'razorpay_order_id' => "ADD COLUMN razorpay_order_id VARCHAR(64) DEFAULT NULL AFTER payment_method",
            'razorpay_payment_id' => "ADD COLUMN razorpay_payment_id VARCHAR(64) DEFAULT NULL AFTER razorpay_order_id",
            'razorpay_signature' => "ADD COLUMN razorpay_signature VARCHAR(128) DEFAULT NULL AFTER razorpay_payment_id",
            'paid_at' => "ADD COLUMN paid_at DATETIME DEFAULT NULL AFTER razorpay_signature",
            'gst_rate' => "ADD COLUMN gst_rate DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER subtotal",
            'gst_amount' => "ADD COLUMN gst_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gst_rate",
            'grand_total' => "ADD COLUMN grand_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER gst_amount",
            'refunded_at' => "ADD COLUMN refunded_at DATETIME DEFAULT NULL AFTER paid_at",
            'refund_id' => "ADD COLUMN refund_id VARCHAR(64) DEFAULT NULL AFTER refunded_at",
            'refund_reason' => "ADD COLUMN refund_reason VARCHAR(255) NOT NULL DEFAULT '' AFTER refund_id",
        ];
        foreach ($orderAlters as $column => $ddl) {
            $col = $conn->query("SHOW COLUMNS FROM orders LIKE '" . $conn->real_escape_string($column) . "'");
            if ($col && $col->num_rows === 0) {
                $conn->query("ALTER TABLE orders " . $ddl);
            }
        }

        $payIdx = $conn->query("SHOW INDEX FROM orders WHERE Key_name = 'idx_payment_status'");
        if ($payIdx && $payIdx->num_rows === 0) {
            $conn->query("ALTER TABLE orders ADD INDEX idx_payment_status (payment_status)");
        }
        $rzpIdx = $conn->query("SHOW INDEX FROM orders WHERE Key_name = 'idx_razorpay_order_id'");
        if ($rzpIdx && $rzpIdx->num_rows === 0) {
            $conn->query("ALTER TABLE orders ADD INDEX idx_razorpay_order_id (razorpay_order_id)");
        }
        $custIdx = $conn->query("SHOW INDEX FROM orders WHERE Key_name = 'idx_customer_id'");
        if ($custIdx && $custIdx->num_rows === 0) {
            $conn->query("ALTER TABLE orders ADD INDEX idx_customer_id (customer_id)");
        }

        $statusCol = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
        $statusRow = $statusCol ? $statusCol->fetch_assoc() : null;
        $type = strtolower((string) ($statusRow['Type'] ?? ''));
        if ($type !== '' && !str_contains($type, 'refunded')) {
            $conn->query("ALTER TABLE orders MODIFY COLUMN status ENUM('reserved','confirmed','cancelled','expired','refunded') NOT NULL DEFAULT 'reserved'");
        }
    }
}
