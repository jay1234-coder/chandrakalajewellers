<?php
/**
 * Admin / Super-admin session helpers.
 */
require_once __DIR__ . '/pwa-schema.php';
require_once __DIR__ . '/activity-log.php';
require_once __DIR__ . '/login-rate-limit.php';

if (!function_exists('cj_auth_bootstrap')) {
    function cj_auth_bootstrap(mysqli $conn): void
    {
        cj_ensure_pwa_schema($conn);
        cj_ensure_activity_log_schema($conn);
        cj_ensure_login_rate_limit_schema($conn);
        if (function_exists('cj_ensure_settings_schema')) cj_ensure_settings_schema($conn);
        if (function_exists('cj_ensure_catalog_schema')) cj_ensure_catalog_schema($conn);
        if (function_exists('cj_ensure_customers_schema')) cj_ensure_customers_schema($conn);
        if (function_exists('cj_ensure_rates_schema')) cj_ensure_rates_schema($conn);
    }
}

if (!function_exists('cj_remember_lifetime_seconds')) {
    /** Persistent login duration (~30 days). */
    function cj_remember_lifetime_seconds(): int
    {
        return 60 * 60 * 24 * 30;
    }
}

if (!function_exists('cj_remember_cookie_name')) {
    function cj_remember_cookie_name(): string
    {
        return 'cj_remember';
    }
}

if (!function_exists('cj_remember_cookie_options')) {
    /**
     * HttpOnly Secure(SameSite=Lax) cookie options matching session cookie policy.
     * @return array{expires:int,path:string,secure:bool,httponly:bool,samesite:string}
     */
    function cj_remember_cookie_options(int $expires): array
    {
        $secure = function_exists('cj_is_https') ? cj_is_https() : false;
        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (!function_exists('cj_clear_remember_cookie')) {
    function cj_clear_remember_cookie(): void
    {
        if (headers_sent()) {
            return;
        }
        $name = cj_remember_cookie_name();
        setcookie($name, '', cj_remember_cookie_options(time() - 3600));
        unset($_COOKIE[$name]);
    }
}

if (!function_exists('cj_set_remember_cookie')) {
    function cj_set_remember_cookie(string $selector, string $validator): void
    {
        if (headers_sent()) {
            return;
        }
        $value = $selector . ':' . $validator;
        $expires = time() + cj_remember_lifetime_seconds();
        setcookie(cj_remember_cookie_name(), $value, cj_remember_cookie_options($expires));
        $_COOKIE[cj_remember_cookie_name()] = $value;
    }
}

if (!function_exists('cj_parse_remember_cookie')) {
    /** @return array{0:string,1:string}|null [selector, validator] */
    function cj_parse_remember_cookie(): ?array
    {
        $raw = (string) ($_COOKIE[cj_remember_cookie_name()] ?? '');
        if ($raw === '' || strpos($raw, ':') === false) {
            return null;
        }
        [$selector, $validator] = explode(':', $raw, 2);
        $selector = preg_replace('/[^a-f0-9]/i', '', $selector) ?? '';
        $validator = preg_replace('/[^a-f0-9]/i', '', $validator) ?? '';
        if (strlen($selector) !== 32 || strlen($validator) !== 64) {
            return null;
        }
        return [$selector, $validator];
    }
}

if (!function_exists('cj_delete_remember_token_by_selector')) {
    function cj_delete_remember_token_by_selector(mysqli $conn, string $selector): void
    {
        $stmt = $conn->prepare('DELETE FROM admin_remember_tokens WHERE selector = ?');
        if ($stmt) {
            $stmt->bind_param('s', $selector);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('cj_invalidate_remember_tokens')) {
    /**
     * Clear remember cookie and remove matching DB token(s).
     * When $userId > 0, also drop all tokens for that user.
     */
    function cj_invalidate_remember_tokens(mysqli $conn, int $userId = 0): void
    {
        $parsed = cj_parse_remember_cookie();
        if ($parsed) {
            cj_delete_remember_token_by_selector($conn, $parsed[0]);
        }
        if ($userId > 0) {
            $stmt = $conn->prepare('DELETE FROM admin_remember_tokens WHERE user_id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();
            }
        }
        $conn->query('DELETE FROM admin_remember_tokens WHERE expires_at < NOW()');
        cj_clear_remember_cookie();
    }
}

if (!function_exists('cj_issue_remember_token')) {
    /** Create selector/validator pair, store hashed validator, set HttpOnly cookie. */
    function cj_issue_remember_token(mysqli $conn, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        cj_ensure_pwa_schema($conn);

        // Replace any prior remember cookie for this browser (leave other devices alone).
        $existing = cj_parse_remember_cookie();
        if ($existing) {
            cj_delete_remember_token_by_selector($conn, $existing[0]);
        }

        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $validatorHash = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + cj_remember_lifetime_seconds());
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

        $stmt = $conn->prepare(
            'INSERT INTO admin_remember_tokens (user_id, selector, validator_hash, expires_at, user_agent)
             VALUES (?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('issss', $userId, $selector, $validatorHash, $expiresAt, $ua);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return false;
        }
        cj_set_remember_cookie($selector, $validator);
        return true;
    }
}

if (!function_exists('cj_try_remember_login')) {
    /**
     * If session expired but remember cookie is valid, restore login and rotate token.
     * @param list<string>|null $allowedRoles When set, only restore if role is in the list (wrong portal = leave cookie alone).
     */
    function cj_try_remember_login(mysqli $conn, ?array $allowedRoles = null): bool
    {
        if (!empty($_SESSION['loggedin'])) {
            return true;
        }
        $parsed = cj_parse_remember_cookie();
        if (!$parsed) {
            return false;
        }
        [$selector, $validator] = $parsed;
        cj_ensure_pwa_schema($conn);

        $stmt = $conn->prepare(
            'SELECT t.id AS token_id, t.user_id, t.validator_hash, t.expires_at,
                    u.username, u.role, u.display_name, u.id
             FROM admin_remember_tokens t
             INNER JOIN admin_users u ON u.id = t.user_id
             WHERE t.selector = ? LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $selector);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            cj_clear_remember_cookie();
            return false;
        }

        $expiresTs = strtotime((string) $row['expires_at']);
        if ($expiresTs === false || $expiresTs < time()) {
            cj_delete_remember_token_by_selector($conn, $selector);
            cj_clear_remember_cookie();
            return false;
        }

        $expected = (string) $row['validator_hash'];
        $actual = hash('sha256', $validator);
        if (!hash_equals($expected, $actual)) {
            // Possible theft / reuse — invalidate this selector
            cj_delete_remember_token_by_selector($conn, $selector);
            cj_clear_remember_cookie();
            return false;
        }

        $role = (string) ($row['role'] ?? '');
        if ($allowedRoles !== null && !in_array($role, $allowedRoles, true)) {
            // Valid token for another portal — do not consume; leave cookie for the correct portal.
            return false;
        }

        // Rotate: delete used token, issue a fresh one
        cj_delete_remember_token_by_selector($conn, $selector);

        $user = [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'role' => $role,
            'display_name' => (string) ($row['display_name'] ?? ''),
        ];
        cj_finish_login_session($user);
        cj_issue_remember_token($conn, (int) $user['id']);
        $conn->query('UPDATE admin_users SET last_login_at = NOW() WHERE id = ' . (int) $user['id']);
        cj_log_activity($conn, 'login', 'Remember-me auto login as ' . $role);
        return true;
    }
}

if (!function_exists('cj_script_is_in_admin_dir')) {
    function cj_script_is_in_admin_dir(): bool
    {
        $uri = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? ''));
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $cwd = str_replace('\\', '/', getcwd());
        if (basename($cwd) === 'admin') {
            return true;
        }
        if (preg_match('#/admin(?:/|$|\.php)#', $uri) || preg_match('#/admin(?:/|$|\.php)#', $script)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('cj_admin_home_href')) {
    /**
     * Dashboard URL that works both from /admin/* pages and from the site root.
     * Never use a bare "admin" or "superadmin" href from inside /admin/ — that
     * becomes /admin/admin and 404s.
     */
    function cj_admin_home_href(?string $role = null, bool $logout = false): string
    {
        $role = $role ?? (string) ($_SESSION['role'] ?? 'admin');
        $inAdmin = cj_script_is_in_admin_dir();
        if ($role === 'superadmin') {
            $path = $inAdmin ? 'superadmin.php' : 'admin/superadmin.php';
        } else {
            $path = $inAdmin ? 'index.php' : 'admin/index.php';
        }
        return $logout ? $path . '?logout' : $path;
    }
}

if (!function_exists('cj_require_role')) {
    function cj_require_role(array $roles, string $redirect = 'admin'): void
    {
        if (empty($_SESSION['loggedin'])) {
            header('Location: /secret');
            exit;
        }
        $role = $_SESSION['role'] ?? 'admin';
        // Legacy password login without role → treat as admin
        if (empty($_SESSION['role']) && !empty($_SESSION['loggedin'])) {
            $role = 'admin';
            $_SESSION['role'] = 'admin';
        }
        if (!in_array($role, $roles, true)) {
            header('Location: /secret');
            exit;
        }
    }
}

if (!function_exists('cj_finish_login_session')) {
    /** Mark session authenticated and ensure the session cookie is sent. */
    function cj_finish_login_session(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['loggedin'] = true;
        $_SESSION['role'] = (string) ($user['role'] ?? 'admin');
        $_SESSION['username'] = (string) ($user['username'] ?? '');
        $_SESSION['display_name'] = (string) (($user['display_name'] ?? '') ?: ($user['username'] ?? 'Admin'));
        $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
        $_SESSION['just_logged_in'] = true;
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        if (function_exists('cj_persist_session_cookie')) {
            cj_persist_session_cookie();
        }
    }
}

if (!function_exists('cj_try_user_login')) {
    function cj_try_user_login(mysqli $conn, string $username, string $password): bool
    {
        cj_ensure_pwa_schema($conn);
        $stmt = $conn->prepare('SELECT id, username, password_hash, role, display_name FROM admin_users WHERE username = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        cj_finish_login_session($user);
        $conn->query('UPDATE admin_users SET last_login_at = NOW() WHERE id = ' . (int) $user['id']);
        cj_log_activity($conn, 'login', 'Signed in as ' . $user['role']);
        return true;
    }
}

if (!function_exists('cj_clear_login_session')) {
    /** Drop auth keys but keep CSRF / flash-capable session. */
    function cj_clear_login_session(): void
    {
        unset(
            $_SESSION['loggedin'],
            $_SESSION['role'],
            $_SESSION['username'],
            $_SESSION['display_name'],
            $_SESSION['user_id'],
            $_SESSION['just_logged_in']
        );
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        if (function_exists('cj_persist_session_cookie')) {
            cj_persist_session_cookie();
        }
    }
}
