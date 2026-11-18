<?php
/**
 * PWA / Push / messaging schema bootstrap + default seeds.
 */
if (!function_exists('cj_ensure_pwa_schema')) {
    function cj_ensure_pwa_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $conn->query("CREATE TABLE IF NOT EXISTS admin_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','superadmin') NOT NULL DEFAULT 'admin',
            display_name VARCHAR(120) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Persistent login tokens (selector + hashed validator). Cookie holds selector:validator.
        $conn->query("CREATE TABLE IF NOT EXISTS admin_remember_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            selector CHAR(32) NOT NULL,
            validator_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            UNIQUE KEY uq_selector (selector),
            KEY idx_user_id (user_id),
            KEY idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            endpoint TEXT NOT NULL,
            endpoint_hash CHAR(64) NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
            auth_key VARCHAR(255) NOT NULL,
            audience ENUM('customer','admin','superadmin','all') NOT NULL DEFAULT 'customer',
            user_agent VARCHAR(500) DEFAULT NULL,
            is_pwa TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_endpoint_hash (endpoint_hash),
            KEY idx_audience (audience)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS push_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(80) NOT NULL UNIQUE,
            title VARCHAR(160) NOT NULL,
            body VARCHAR(500) NOT NULL,
            audience ENUM('customer','admin','superadmin','all') NOT NULL DEFAULT 'customer',
            url_path VARCHAR(255) DEFAULT 'index1',
            is_active TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS push_queue (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(160) NOT NULL,
            body VARCHAR(500) NOT NULL,
            url_path VARCHAR(255) DEFAULT 'index1',
            audience ENUM('customer','admin','superadmin','all') NOT NULL DEFAULT 'customer',
            status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
            created_by VARCHAR(80) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME DEFAULT NULL,
            success_count INT UNSIGNED NOT NULL DEFAULT 0,
            fail_count INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS push_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue_id INT UNSIGNED DEFAULT NULL,
            title VARCHAR(160) NOT NULL,
            body VARCHAR(500) NOT NULL,
            audience VARCHAR(40) NOT NULL,
            success_count INT UNSIGNED NOT NULL DEFAULT 0,
            fail_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS internal_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            from_role ENUM('admin','superadmin','system') NOT NULL,
            to_role ENUM('admin','superadmin','customer','all') NOT NULL,
            subject VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS cron_meta (
            meta_key VARCHAR(80) PRIMARY KEY,
            meta_value VARCHAR(255) NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Dynamically migrate push_queue to support rich & scheduled notifications
        $cols = $conn->query("SHOW COLUMNS FROM push_queue");
        $existingCols = [];
        if ($cols) {
            while ($c = $cols->fetch_assoc()) {
                $existingCols[] = strtolower($c['Field']);
            }
        }
        if (!in_array('image_url', $existingCols, true)) {
            $conn->query("ALTER TABLE push_queue ADD COLUMN image_url VARCHAR(500) DEFAULT NULL AFTER url_path");
        }
        if (!in_array('scheduled_at', $existingCols, true)) {
            $conn->query("ALTER TABLE push_queue ADD COLUMN scheduled_at DATETIME DEFAULT NULL AFTER status");
        }
        if (!in_array('is_constant', $existingCols, true)) {
            $conn->query("ALTER TABLE push_queue ADD COLUMN is_constant TINYINT(1) NOT NULL DEFAULT 0 AFTER scheduled_at");
        }
        if (!in_array('constant_time', $existingCols, true)) {
            $conn->query("ALTER TABLE push_queue ADD COLUMN constant_time TIME DEFAULT NULL AFTER is_constant");
        }
        if (!in_array('last_constant_sent_at', $existingCols, true)) {
            $conn->query("ALTER TABLE push_queue ADD COLUMN last_constant_sent_at DATETIME DEFAULT NULL AFTER constant_time");
        }

        $existingSlugs = [];
        $slugRes = @$conn->query('SELECT slug FROM push_templates');
        if ($slugRes instanceof mysqli_result) {
            while ($sr = $slugRes->fetch_assoc()) {
                $existingSlugs[(string)$sr['slug']] = true;
            }
            $slugRes->free();
        }
        $templates = [
            ['welcome_pwa', 'Welcome to ChandraKala Jewellers', 'Thanks for installing our app. Browse gold, silver & 925 designs anytime.', 'customer', 'index1'],
            ['daily_customer', 'Fresh jewellery picks for you', 'Open ChandraKala Jewellers to check today\'s gold & silver designs.', 'customer', 'gallery'],
            ['daily_admin_rates', 'Daily reminder: update metal rates', 'Open the admin panel and update today\'s gold / silver / 925 rates.', 'admin', 'admin'],
            ['rates_updated', 'Today\'s metal rates updated', 'New gold & silver rates are live. Tap to view the shop.', 'customer', 'gallery'],
            ['new_product', 'New jewellery just added', 'A new piece is available at ChandraKala Jewellers. Take a look!', 'customer', 'gallery'],
            ['product_updated', 'A favourite design was updated', 'Product details changed — open the shop to see the latest.', 'customer', 'gallery'],
            ['engage_pwa', 'Your jewellery app misses you', 'Come back to ChandraKala Jewellers for live rates and new designs.', 'customer', 'index1'],
        ];
        $missingTemplates = array_values(array_filter($templates, static fn($t) => empty($existingSlugs[$t[0]])));
        if ($missingTemplates) {
            $stmt = $conn->prepare('INSERT IGNORE INTO push_templates (slug, title, body, audience, url_path) VALUES (?,?,?,?,?)');
            if ($stmt) {
                foreach ($missingTemplates as $t) {
                    $stmt->bind_param('sssss', $t[0], $t[1], $t[2], $t[3], $t[4]);
                    $stmt->execute();
                }
                $stmt->close();
            }
        }

        // Seed admin users if table empty (3 family accounts — both store admins share role=admin)
        $c = $conn->query('SELECT COUNT(*) AS c FROM admin_users');
        $count = (int)($c->fetch_assoc()['c'] ?? 0);
        if ($count === 0) {
            $adminHash = password_hash('mVsr@1617', PASSWORD_DEFAULT);
            $superHash = password_hash('Super@12345', PASSWORD_DEFAULT);
            // Precomputed bcrypt for Deven's store-admin login (plaintext not stored in repo)
            $devenHash = '$2y$10$.FQxfSgPwsLk3cibPVyuK.NvuotyhLEwzN22n.y.hINX4sliXZ1RC';
            $ins = $conn->prepare('INSERT INTO admin_users (username, password_hash, role, display_name) VALUES (?,?,?,?)');
            $u1 = 'admin'; $r1 = 'admin'; $d1 = 'Hasmukh Hiralal Soni';
            $ins->bind_param('ssss', $u1, $adminHash, $r1, $d1);
            $ins->execute();
            $u2 = 'deven'; $r2 = 'admin'; $d2 = 'Deven Hasmukhbhai Soni';
            $ins->bind_param('ssss', $u2, $devenHash, $r2, $d2);
            $ins->execute();
            $u3 = 'superadmin'; $r3 = 'superadmin'; $d3 = 'Soni Jaykumar Hasmukh';
            $ins->bind_param('ssss', $u3, $superHash, $r3, $d3);
            $ins->execute();
            $ins->close();
        }

        // One-time: set store admin password to mVsr@1617 (existing installs)
        $pwRev = 'admin_pw_mVsr1617';
        $metaRow = $conn->query("SELECT meta_value FROM cron_meta WHERE meta_key = 'admin_pw_rev' LIMIT 1");
        $currentRev = $metaRow ? (string)(($metaRow->fetch_assoc()['meta_value'] ?? '')) : '';
        if ($currentRev !== $pwRev) {
            $adminHash = password_hash('mVsr@1617', PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE username = 'admin' AND role = 'admin'");
            if ($upd) {
                $upd->bind_param('s', $adminHash);
                $upd->execute();
                $upd->close();
            }
            // Ensure admin row exists
            $exists = $conn->query("SELECT id FROM admin_users WHERE username = 'admin' LIMIT 1");
            if ($exists && $exists->num_rows === 0) {
                $ins = $conn->prepare('INSERT INTO admin_users (username, password_hash, role, display_name) VALUES (?,?,?,?)');
                $u = 'admin'; $r = 'admin'; $d = 'Hasmukh Hiralal Soni';
                $ins->bind_param('ssss', $u, $adminHash, $r, $d);
                $ins->execute();
                $ins->close();
            }
            $conn->query("INSERT INTO cron_meta (meta_key, meta_value) VALUES ('admin_pw_rev', '" . $conn->real_escape_string($pwRev) . "')
                ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
        }

        // One-time: name the 3 accounts + add Co-Admin (Deven) with own login for separate activity logs.
        // Deven uses role=admin (identical store-portal permissions). Passwords for admin/superadmin are left unchanged.
        $familyRev = 'family_accounts_v1';
        $famMeta = $conn->query("SELECT meta_value FROM cron_meta WHERE meta_key = 'family_accounts_rev' LIMIT 1");
        $famCurrent = $famMeta ? (string)(($famMeta->fetch_assoc()['meta_value'] ?? '')) : '';
        if ($famCurrent !== $familyRev) {
            $nameUpd = $conn->prepare('UPDATE admin_users SET display_name = ?, role = ? WHERE username = ?');
            if ($nameUpd) {
                $pairs = [
                    ['Hasmukh Hiralal Soni', 'admin', 'admin'],
                    ['Soni Jaykumar Hasmukh', 'superadmin', 'superadmin'],
                ];
                foreach ($pairs as $p) {
                    $nameUpd->bind_param('sss', $p[0], $p[1], $p[2]);
                    $nameUpd->execute();
                }
                $nameUpd->close();
            }

            $devenExists = $conn->query("SELECT id FROM admin_users WHERE username = 'deven' LIMIT 1");
            if ($devenExists && $devenExists->num_rows === 0) {
                $devenHash = '$2y$10$.FQxfSgPwsLk3cibPVyuK.NvuotyhLEwzN22n.y.hINX4sliXZ1RC';
                $ins = $conn->prepare('INSERT INTO admin_users (username, password_hash, role, display_name) VALUES (?,?,?,?)');
                if ($ins) {
                    $u = 'deven'; $r = 'admin'; $d = 'Deven Hasmukhbhai Soni';
                    $ins->bind_param('ssss', $u, $devenHash, $r, $d);
                    $ins->execute();
                    $ins->close();
                }
            } else {
                $fixDeven = $conn->prepare("UPDATE admin_users SET display_name = ?, role = 'admin' WHERE username = 'deven'");
                if ($fixDeven) {
                    $d = 'Deven Hasmukhbhai Soni';
                    $fixDeven->bind_param('s', $d);
                    $fixDeven->execute();
                    $fixDeven->close();
                }
            }

            $conn->query("INSERT INTO cron_meta (meta_key, meta_value) VALUES ('family_accounts_rev', '" . $conn->real_escape_string($familyRev) . "')
                ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
        }

        // Always ensure Co-Admin row exists (if an earlier migration marked complete but insert failed)
        $devenCheck = $conn->query("SELECT id FROM admin_users WHERE username = 'deven' LIMIT 1");
        if ($devenCheck && $devenCheck->num_rows === 0) {
            $devenHash = '$2y$10$.FQxfSgPwsLk3cibPVyuK.NvuotyhLEwzN22n.y.hINX4sliXZ1RC';
            $ins = $conn->prepare('INSERT INTO admin_users (username, password_hash, role, display_name) VALUES (?,?,?,?)');
            if ($ins) {
                $u = 'deven'; $r = 'admin'; $d = 'Deven Hasmukhbhai Soni';
                $ins->bind_param('ssss', $u, $devenHash, $r, $d);
                $ins->execute();
                $ins->close();
            }
        }
    }
}
