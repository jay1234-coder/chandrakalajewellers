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

if (!function_exists('cj_customer_session')) {
    /** @return array{id:int,name:string,phone:string,email:string}|null */
    function cj_customer_session(): ?array
    {
        $id = (int) ($_SESSION['cj_customer_id'] ?? 0);
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
        $email = mb_substr(trim($email), 0, 160);
        $password = (string) $password;
        if ($name === '' || strlen($phone) !== 10) {
            return ['ok' => false, 'message' => 'Enter your name and a 10-digit mobile number.'];
        }
        if (strlen($password) < 6) {
            return ['ok' => false, 'message' => 'Password must be at least 6 characters.'];
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please enter a valid email, or leave it blank.'];
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            'INSERT INTO customers (name, phone, email, password_hash) VALUES (?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Could not create account.'];
        }
        $stmt->bind_param('ssss', $name, $phone, $email, $hash);
        if (!$stmt->execute()) {
            $stmt->close();
            return ['ok' => false, 'message' => 'This mobile number already has an account. Sign in instead.'];
        }
        $id = (int) $stmt->insert_id;
        $stmt->close();
        $row = ['id' => $id, 'name' => $name, 'phone' => $phone, 'email' => $email];
        cj_customer_login_set($row);
        return ['ok' => true, 'message' => 'Account created.', 'customer' => $row];
    }
}

if (!function_exists('cj_customer_login')) {
    /**
     * @return array{ok:bool,message:string,customer?:array}
     */
    function cj_customer_login(mysqli $conn, string $phone, string $password): array
    {
        cj_ensure_customers_schema($conn);
        $phone = cj_normalize_customer_phone($phone);
        if (strlen($phone) !== 10 || $password === '') {
            return ['ok' => false, 'message' => 'Enter your mobile number and password.'];
        }
        $stmt = $conn->prepare('SELECT id, name, phone, email, password_hash FROM customers WHERE phone = ? LIMIT 1');
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Could not sign in.'];
        }
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            return ['ok' => false, 'message' => 'Mobile number or password is incorrect.'];
        }
        unset($row['password_hash']);
        cj_customer_login_set($row);
        return ['ok' => true, 'message' => 'Signed in.', 'customer' => $row];
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
