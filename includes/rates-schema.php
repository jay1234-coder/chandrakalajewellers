<?php
/**
 * Ensure rates / manual_rates tables exist with expected columns.
 */

if (!function_exists('cj_rates_has_column')) {
    function cj_rates_has_column(mysqli $conn, string $table, string $column): bool
    {
        $table = preg_replace('/[^a-z0-9_]/i', '', $table);
        $column = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('cj_ensure_rates_schema')) {
    function cj_ensure_rates_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $conn->query("CREATE TABLE IF NOT EXISTS rates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            gold_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
            silver_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
            silver_925_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
            making_charges_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
            apply_making_to_silver TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS manual_rates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            gold_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
            silver_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
            silver_925_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
            making_charges_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
            manual_making_charges_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
            manual_apply_making_to_silver TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $rateCols = [
            'gold_rate' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'silver_rate' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'silver_925_rate' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'copper_rate' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'making_charges_percent' => "DECIMAL(8,2) NOT NULL DEFAULT 0",
            'apply_making_to_silver' => "TINYINT(1) NOT NULL DEFAULT 0",
            'updated_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        ];
        foreach ($rateCols as $col => $def) {
            if (!cj_rates_has_column($conn, 'rates', $col)) {
                $conn->query("ALTER TABLE rates ADD COLUMN `{$col}` {$def}");
            }
        }

        $manualCols = [
            'gold_rate' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'silver_rate' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'silver_925_rate' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'copper_rate' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
            'making_charges_percent' => "DECIMAL(8,2) NOT NULL DEFAULT 0",
            'manual_making_charges_percent' => "DECIMAL(8,2) NOT NULL DEFAULT 0",
            'manual_apply_making_to_silver' => "TINYINT(1) NOT NULL DEFAULT 0",
            'updated_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        ];
        foreach ($manualCols as $col => $def) {
            if (!cj_rates_has_column($conn, 'manual_rates', $col)) {
                $conn->query("ALTER TABLE manual_rates ADD COLUMN `{$col}` {$def}");
            }
        }
    }
}
