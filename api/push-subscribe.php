<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/pwa-schema.php';
require_once __DIR__ . '/../includes/vapid.php';

if (function_exists('cj_is_local_host') && cj_is_local_host()) {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

cj_ensure_pwa_schema($conn);
$vapid = cj_vapid_config();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pub = (string)($vapid['publicKey'] ?? '');
    echo json_encode([
        'ok' => $pub !== '',
        'publicKey' => $pub,
        'ready' => $pub !== '' && strlen($pub) > 80,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$data = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$action = (string)($data['action'] ?? 'subscribe');
$endpoint = trim((string)($data['endpoint'] ?? ''));
if ($endpoint === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'endpoint']);
    exit;
}

$hash = hash('sha256', $endpoint);
$audience = (string)($data['audience'] ?? 'customer');
if (!in_array($audience, ['customer', 'admin', 'superadmin', 'all'], true)) {
    $audience = 'customer';
}
// Only allow elevating audience when logged in as that role
if ($audience === 'admin' || $audience === 'superadmin') {
    $role = $_SESSION['role'] ?? '';
    if ($audience === 'admin' && !in_array($role, ['admin', 'superadmin'], true)) {
        $audience = 'customer';
    }
    if ($audience === 'superadmin' && $role !== 'superadmin') {
        $audience = 'customer';
    }
}

if ($action === 'unsubscribe') {
    $stmt = $conn->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = ?');
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit;
}

$p256dh = substr((string)($data['keys']['p256dh'] ?? ''), 0, 255);
$auth = substr((string)($data['keys']['auth'] ?? ''), 0, 255);
$ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
$isPwa = !empty($data['is_pwa']) ? 1 : 0;

if ($p256dh === '' || $auth === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'keys']);
    exit;
}

$stmt = $conn->prepare('INSERT INTO push_subscriptions (endpoint, endpoint_hash, p256dh, auth_key, audience, user_agent, is_pwa, last_seen_at)
    VALUES (?,?,?,?,?,?,?,NOW())
    ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh), auth_key=VALUES(auth_key), audience=VALUES(audience), user_agent=VALUES(user_agent), is_pwa=VALUES(is_pwa), last_seen_at=NOW()');
$stmt->bind_param('ssssssi', $endpoint, $hash, $p256dh, $auth, $audience, $ua, $isPwa);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

// Welcome only when a new row is inserted (affected_rows = 1). Updates return 2 on MySQL.
if ($isPwa && $audience === 'customer' && $affected === 1) {
    require_once __DIR__ . '/../includes/push.php';
    cj_queue_template($conn, 'welcome_pwa', 'system');
}

echo json_encode(['ok' => true]);
