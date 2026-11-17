<?php
/**
 * Ensure visitor analytics tables exist.
 */
if (!function_exists('cj_ensure_visitor_analytics_schema')) {
    function cj_ensure_visitor_analytics_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $conn->query("CREATE TABLE IF NOT EXISTS visitor_sessions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_key VARCHAR(64) NOT NULL,
            started_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            page_count INT UNSIGNED NOT NULL DEFAULT 0,
            landing_page VARCHAR(500) DEFAULT NULL,
            referrer VARCHAR(500) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            ip_hash VARCHAR(64) DEFAULT NULL,
            lat DECIMAL(10,7) DEFAULT NULL,
            lng DECIMAL(10,7) DEFAULT NULL,
            geo_accuracy FLOAT DEFAULT NULL,
            geo_label VARCHAR(255) DEFAULT NULL,
            geo_city VARCHAR(120) DEFAULT NULL,
            geo_region VARCHAR(120) DEFAULT NULL,
            geo_country VARCHAR(120) DEFAULT NULL,
            geo_status VARCHAR(32) DEFAULT 'unknown',
            UNIQUE KEY uq_session_key (session_key),
            KEY idx_last_seen (last_seen_at),
            KEY idx_started (started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS visitor_pageviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_key VARCHAR(64) NOT NULL,
            page_path VARCHAR(500) NOT NULL,
            page_title VARCHAR(255) DEFAULT NULL,
            viewed_at DATETIME NOT NULL,
            seconds_on_page INT UNSIGNED NOT NULL DEFAULT 0,
            KEY idx_session (session_key),
            KEY idx_viewed (viewed_at),
            KEY idx_path (page_path(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
