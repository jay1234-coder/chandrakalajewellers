<?php
/**
 * IP-based admin login lockout (survives dropping the session cookie).
 */

if (!function_exists('cj_ensure_login_rate_limit_schema')) {
    function cj_ensure_login_rate_limit_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $conn->query("CREATE TABLE IF NOT EXISTS admin_login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_hash CHAR(64) NOT NULL,
            portal VARCHAR(32) NOT NULL DEFAULT 'admin',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            first_attempt_at DATETIME NOT NULL,
            locked_until DATETIME DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ip_portal (ip_hash, portal),
            KEY idx_locked_until (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('cj_login_client_ip')) {
    function cj_login_client_ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}

if (!function_exists('cj_login_ip_hash')) {
    function cj_login_ip_hash(): string
    {
        return hash('sha256', cj_login_client_ip());
    }
}

if (!function_exists('cj_login_rate_limit_max_attempts')) {
    function cj_login_rate_limit_max_attempts(): int
    {
        return 5;
    }
}

if (!function_exists('cj_login_rate_limit_window_seconds')) {
    function cj_login_rate_limit_window_seconds(): int
    {
        return 900;
    }
}

if (!function_exists('cj_login_rate_limit_status')) {
    /**
     * @return array{ok:bool,wait:int}
     */
    function cj_login_rate_limit_status(mysqli $conn, string $portal): array
    {
        cj_ensure_login_rate_limit_schema($conn);
        $portal = preg_replace('/[^a-z0-9_-]/i', '', $portal) ?: 'admin';
        $ipHash = cj_login_ip_hash();
        $nowTs = time();

        $stmt = $conn->prepare(
            'SELECT attempts, first_attempt_at, locked_until
             FROM admin_login_attempts WHERE ip_hash = ? AND portal = ? LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => true, 'wait' => 0];
        }
        $stmt->bind_param('ss', $ipHash, $portal);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return ['ok' => true, 'wait' => 0];
        }

        $lockedUntilTs = !empty($row['locked_until']) ? strtotime((string) $row['locked_until']) : false;
        if ($lockedUntilTs !== false && $lockedUntilTs > $nowTs) {
            return ['ok' => false, 'wait' => $lockedUntilTs - $nowTs];
        }

        $firstTs = strtotime((string) $row['first_attempt_at']);
        $window = cj_login_rate_limit_window_seconds();
        if ($firstTs !== false && ($nowTs - $firstTs) > $window) {
            cj_login_rate_limit_clear($conn, $portal);
            return ['ok' => true, 'wait' => 0];
        }

        $attempts = (int) ($row['attempts'] ?? 0);
        if ($attempts >= cj_login_rate_limit_max_attempts()) {
            $lockUntil = date('Y-m-d H:i:s', $nowTs + $window);
            $upd = $conn->prepare(
                'UPDATE admin_login_attempts SET locked_until = ? WHERE ip_hash = ? AND portal = ?'
            );
            if ($upd) {
                $upd->bind_param('sss', $lockUntil, $ipHash, $portal);
                $upd->execute();
                $upd->close();
            }
            return ['ok' => false, 'wait' => $window];
        }

        return ['ok' => true, 'wait' => 0];
    }
}

if (!function_exists('cj_login_rate_limit_hit')) {
    function cj_login_rate_limit_hit(mysqli $conn, string $portal): void
    {
        cj_ensure_login_rate_limit_schema($conn);
        $portal = preg_replace('/[^a-z0-9_-]/i', '', $portal) ?: 'admin';
        $ipHash = cj_login_ip_hash();
        $now = date('Y-m-d H:i:s');
        $max = cj_login_rate_limit_max_attempts();
        $window = cj_login_rate_limit_window_seconds();
        $lockUntil = date('Y-m-d H:i:s', time() + $window);

        $stmt = $conn->prepare(
            'INSERT INTO admin_login_attempts (ip_hash, portal, attempts, first_attempt_at, locked_until)
             VALUES (?, ?, 1, ?, NULL)
             ON DUPLICATE KEY UPDATE
                attempts = IF(first_attempt_at < DATE_SUB(NOW(), INTERVAL ' . (int) $window . ' SECOND), 1, attempts + 1),
                first_attempt_at = IF(first_attempt_at < DATE_SUB(NOW(), INTERVAL ' . (int) $window . ' SECOND), VALUES(first_attempt_at), first_attempt_at),
                locked_until = IF(
                    IF(first_attempt_at < DATE_SUB(NOW(), INTERVAL ' . (int) $window . ' SECOND), 1, attempts + 1) >= ' . (int) $max . ',
                    ?,
                    NULL
                )'
        );
        if ($stmt) {
            $stmt->bind_param('ssss', $ipHash, $portal, $now, $lockUntil);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('cj_login_rate_limit_clear')) {
    function cj_login_rate_limit_clear(mysqli $conn, string $portal): void
    {
        cj_ensure_login_rate_limit_schema($conn);
        $portal = preg_replace('/[^a-z0-9_-]/i', '', $portal) ?: 'admin';
        $ipHash = cj_login_ip_hash();
        $stmt = $conn->prepare('DELETE FROM admin_login_attempts WHERE ip_hash = ? AND portal = ?');
        if ($stmt) {
            $stmt->bind_param('ss', $ipHash, $portal);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('cj_login_rate_limit_message')) {
    function cj_login_rate_limit_message(int $waitSeconds): string
    {
        $mins = max(1, (int) ceil($waitSeconds / 60));
        return 'Too many failed login attempts. Please try again in ' . $mins . ' minute(s).';
    }
}
