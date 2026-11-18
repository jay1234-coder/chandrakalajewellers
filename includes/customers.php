<?php
/**
 * Optional customer accounts. Guests can still shop; login is never required.
 */
require_once __DIR__ . '/settings.php';

if (!function_exists('cj_ensure_customers_schema')) {
    function cj_ensure_customers_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $conn->query("CREATE TABLE IF NOT EXISTS customers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(160) NOT NULL DEFAULT '',
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_customer_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('cj_customer_issue_cookie')) {
    /**
     * Issue an HMAC-SHA256 signed stateless customer session cookie so customer logins
     * survive serverless Lambda restarts/cold starts on Vercel.
     * @param array<string,mixed> $row
     */
    function cj_customer_issue_cookie(array $row, int $lifetime = 2592000): void
    {
        if (headers_sent() || !function_exists('cj_auth_secret_key') || !function_exists('cj_session_cookie_params')) {
            return;
        }
        $payload = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'exp' => time() + $lifetime,
        ];
        $b64 = base64_encode((string) json_encode($payload));
        $sig = hash_hmac('sha256', 'cust:' . $b64, cj_auth_secret_key());
        $val = $b64 . '.' . $sig;

        $params = cj_session_cookie_params();
        setcookie('cj_cust_jwt', $val, [
            'expires' => time() + $lifetime,
            'path' => '/',
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['cj_cust_jwt'] = $val;
    }
}

if (!function_exists('cj_customer_clear_cookie')) {
    function cj_customer_clear_cookie(): void
    {
        if (headers_sent() || !function_exists('cj_session_cookie_params')) {
            return;
        }
        $params = cj_session_cookie_params();
        setcookie('cj_cust_jwt', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE['cj_cust_jwt']);
    }
}

if (!function_exists('cj_customer_restore_cookie')) {
    function cj_customer_restore_cookie(): bool
    {
        if (!function_exists('cj_auth_secret_key')) {
            return false;
        }
        $raw = (string) ($_COOKIE['cj_cust_jwt'] ?? '');
        if ($raw === '' || strpos($raw, '.') === false) {
            return false;
        }
        [$b64, $sig] = explode('.', $raw, 2);
        $expected = hash_hmac('sha256', 'cust:' . $b64, cj_auth_secret_key());
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        $json = base64_decode($b64, true);
        if (!$json) {
            return false;
        }
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['id']) || empty($data['exp']) || (int) $data['exp'] < time()) {
            return false;
        }
        $_SESSION['cj_customer_id'] = (int) $data['id'];
        $_SESSION['cj_customer_name'] = (string) ($data['name'] ?? '');
        $_SESSION['cj_customer_phone'] = (string) ($data['phone'] ?? '');
        $_SESSION['cj_customer_email'] = (string) ($data['email'] ?? '');
        return true;
    }
}

if (!function_exists('cj_customer_session')) {
    /** @return array{id:int,name:string,phone:string,email:string}|null */
    function cj_customer_session(): ?array
    {
        $id = (int) ($_SESSION['cj_customer_id'] ?? 0);
        if ($id <= 0) {
            cj_customer_restore_cookie();
            $id = (int) ($_SESSION['cj_customer_id'] ?? 0);
        }
        if ($id <= 0) {
            return null;
        }
        return [
            'id' => $id,
            'name' => (string) ($_SESSION['cj_customer_name'] ?? ''),
            'phone' => (string) ($_SESSION['cj_customer_phone'] ?? ''),
            'email' => (string) ($_SESSION['cj_customer_email'] ?? ''),
        ];
    }
}

if (!function_exists('cj_customer_login_set')) {
    /** @param array<string,mixed> $row */
    function cj_customer_login_set(array $row): void
    {
        $_SESSION['cj_customer_id'] = (int) ($row['id'] ?? 0);
        $_SESSION['cj_customer_name'] = (string) ($row['name'] ?? '');
        $_SESSION['cj_customer_phone'] = (string) ($row['phone'] ?? '');
        $_SESSION['cj_customer_email'] = (string) ($row['email'] ?? '');
        cj_customer_issue_cookie($row);
    }
}

if (!function_exists('cj_customer_logout')) {
    function cj_customer_logout(): void
    {
        unset(
            $_SESSION['cj_customer_id'],
            $_SESSION['cj_customer_name'],
            $_SESSION['cj_customer_phone'],
            $_SESSION['cj_customer_email']
        );
        cj_customer_clear_cookie();
    }
}

if (!function_exists('cj_normalize_customer_email')) {
    /**
     * Clean invisible spaces / Unicode whitespace / casing from email input.
     */
    function cj_normalize_customer_email(string $email): string
    {
        // Strip all whitespace including non-breaking space and zero-width chars
        $clean = preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u', '', $email) ?? trim($email);
        return mb_substr(strtolower($clean), 0, 160);
    }
}

if (!function_exists('cj_is_valid_customer_email')) {
    function cj_is_valid_customer_email(string $email): bool
    {
        if ($email === '') {
            return true;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            return true;
        }
        // Fallback for IDN / modern TLDs if PHP filter_var is overly strict
        return (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/u', $email);
    }
}

if (!function_exists('cj_normalize_customer_phone')) {
    function cj_normalize_customer_phone(string $phone): string
    {
        $n = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($n) === 11 && $n[0] === '0') {
            $n = substr($n, 1);
        }
        if (strlen($n) === 12 && str_starts_with($n, '91')) {
            $n = substr($n, 2);
        }
        return $n;
    }
}

if (!function_exists('cj_customer_register')) {
    /**
     * @return array{ok:bool,message:string,customer?:array}
     */
    function cj_customer_register(mysqli $conn, string $name, string $phone, string $password, string $email = ''): array
    {
        cj_ensure_customers_schema($conn);
        $name = mb_substr(trim($name), 0, 120);
        $phone = cj_normalize_customer_phone($phone);
        $email = cj_normalize_customer_email($email);
        $password = (string) $password;
        if ($name === '' || strlen($phone) !== 10) {
            return ['ok' => false, 'message' => 'Enter your name and a valid 10-digit mobile number.'];
        }
        if (strlen($password) < 6) {
            return ['ok' => false, 'message' => 'Password must be at least 6 characters.'];
        }
        if ($email !== '' && !cj_is_valid_customer_email($email)) {
            return ['ok' => false, 'message' => 'Please enter a valid email address (e.g. name@gmail.com), or leave it blank.'];
        }
        // Check duplicate phone explicitly (works even if table was migrated without UNIQUE index)
        $chk = $conn->prepare('SELECT id FROM customers WHERE phone = ? LIMIT 1');
        if ($chk) {
            $chk->bind_param('s', $phone);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($exists) {
                return ['ok' => false, 'message' => 'This mobile number already has an account. Please sign in instead.'];
            }
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            'INSERT INTO customers (name, phone, email, password_hash) VALUES (?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Could not create account. Please try again.'];
        }
        $stmt->bind_param('ssss', $name, $phone, $email, $hash);
        if (!$stmt->execute()) {
            $err = (string) $stmt->error;
            $stmt->close();
            if (stripos($err, 'duplicate') !== false) {
                return ['ok' => false, 'message' => 'This mobile number already has an account. Please sign in instead.'];
            }
            return ['ok' => false, 'message' => 'Could not create account. Please try again.'];
        }
        $id = (int) $stmt->insert_id;
        $stmt->close();
        $row = ['id' => $id, 'name' => $name, 'phone' => $phone, 'email' => $email];
        cj_customer_login_set($row);
        return ['ok' => true, 'message' => 'Account created successfully!', 'customer' => $row];
    }
}

if (!function_exists('cj_customer_login')) {
    /**
     * Supports signing in with either a 10-digit Mobile Number OR Email ID.
     * @return array{ok:bool,message:string,customer?:array}
     */
    function cj_customer_login(mysqli $conn, string $identifier, string $password): array
    {
        cj_ensure_customers_schema($conn);
        $rawId = trim($identifier);
        if ($rawId === '' || $password === '') {
            return ['ok' => false, 'message' => 'Enter your mobile number (or email) and password.'];
        }

        $isEmailLogin = strpos($rawId, '@') !== false;
        if ($isEmailLogin) {
            $email = cj_normalize_customer_email($rawId);
            if (!cj_is_valid_customer_email($email)) {
                return ['ok' => false, 'message' => 'Please enter a valid email address or 10-digit mobile number.'];
            }
            $stmt = $conn->prepare('SELECT id, name, phone, email, password_hash FROM customers WHERE LOWER(email) = ? LIMIT 1');
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Could not sign in.'];
            }
            $stmt->bind_param('s', $email);
        } else {
            $phone = cj_normalize_customer_phone($rawId);
            if (strlen($phone) !== 10) {
                return ['ok' => false, 'message' => 'Enter a valid 10-digit mobile number or email address.'];
            }
            $stmt = $conn->prepare('SELECT id, name, phone, email, password_hash FROM customers WHERE phone = ? LIMIT 1');
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Could not sign in.'];
            }
            $stmt->bind_param('s', $phone);
        }

        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            return ['ok' => false, 'message' => 'Mobile/email or password is incorrect.'];
        }
        unset($row['password_hash']);
        cj_customer_login_set($row);
        return ['ok' => true, 'message' => 'Signed in successfully.', 'customer' => $row];
    }
}

if (!function_exists('cj_customer_orders')) {
    /**
     * @return list<array{order:array<string,mixed>,items:list<array<string,mixed>>}>
     */
    function cj_customer_orders(mysqli $conn, int $customerId, string $phone): array
    {
        if ($customerId <= 0 && $phone === '') {
            return [];
        }
        $phone = cj_normalize_customer_phone($phone);
        $sql = 'SELECT * FROM orders WHERE customer_id = ?';
        $types = 'i';
        $params = [$customerId];
        if ($phone !== '') {
            $sql .= ' OR customer_phone = ? OR customer_phone = ?';
            $types .= 'ss';
            $params[] = $phone;
            $params[] = '91' . $phone;
        }
        $sql .= ' ORDER BY id DESC LIMIT 40';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $orders = [];
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
        if ($orders === []) {
            return [];
        }
        $oids = array_map(static fn($o) => (int) $o['id'], $orders);
        $in = implode(',', $oids);
        $itemsBy = [];
        $ir = $conn->query("SELECT * FROM order_items WHERE order_id IN ($in)");
        if ($ir) {
            while ($it = $ir->fetch_assoc()) {
                $itemsBy[(int) $it['order_id']][] = $it;
            }
        }
        $out = [];
        foreach ($orders as $order) {
            $out[] = [
                'order' => $order,
                'items' => $itemsBy[(int) $order['id']] ?? [],
            ];
        }
        return $out;
    }
}
