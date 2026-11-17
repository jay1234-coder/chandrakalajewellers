<?php
/**
 * Admin / Super-admin activity logger.
 */

if (!function_exists('cj_ensure_activity_log_schema')) {
    function cj_ensure_activity_log_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $conn->query("CREATE TABLE IF NOT EXISTS admin_activity_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED DEFAULT NULL,
            username VARCHAR(80) NOT NULL DEFAULT 'unknown',
            role VARCHAR(40) NOT NULL DEFAULT 'admin',
            action VARCHAR(120) NOT NULL,
            details TEXT DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_created (created_at),
            KEY idx_username (username),
            KEY idx_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('cj_log_activity')) {
    function cj_log_activity(mysqli $conn, string $action, string $details = ''): void
    {
        try {
            cj_ensure_activity_log_schema($conn);
            // Prefer display name so Hasmukh vs Deven are obvious in the activity log
            $username = trim((string)($_SESSION['display_name'] ?? ''));
            if ($username === '') {
                $username = trim((string)($_SESSION['username'] ?? 'unknown'));
            }
            if ($username === '') {
                $username = 'unknown';
            }
            $role = (string)($_SESSION['role'] ?? 'admin');
            $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
            $action = substr(trim($action), 0, 120);
            $details = substr(trim($details), 0, 4000);

            $stmt = $conn->prepare(
                'INSERT INTO admin_activity_log (user_id, username, role, action, details, ip, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                return;
            }
            $stmt->bind_param('issssss', $uid, $username, $role, $action, $details, $ip, $ua);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            // never break admin flows
        }
    }
}

if (!function_exists('cj_fetch_activity_logs')) {
    /**
     * @return list<array<string,mixed>>
     */
    function cj_fetch_activity_logs(mysqli $conn, int $limit = 100): array
    {
        cj_ensure_activity_log_schema($conn);
        $limit = max(1, min(500, $limit));
        $rows = [];
        $r = $conn->query(
            "SELECT id, user_id, username, role, action, details, ip, created_at
             FROM admin_activity_log
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}
