<?php
/**
 * Daily reminders cron.
 * Call: /cron/daily-push.php?key=YOUR_CRON_SECRET
 * Set secret in config/cron-secret.php or use default below and change it.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/push.php';

header('Content-Type: application/json; charset=utf-8');

$secretFile = __DIR__ . '/../config/cron-secret.php';
$secret = 'cj-change-this-cron-secret';
if (is_file($secretFile)) {
    $loaded = include $secretFile;
    if (is_string($loaded) && $loaded !== '') $secret = $loaded;
}

$key = (string)($_GET['key'] ?? '');
if (!hash_equals($secret, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

cj_ensure_pwa_schema($conn);

$today = date('Y-m-d');
$row = $conn->query("SELECT meta_value FROM cron_meta WHERE meta_key = 'daily_push_date' LIMIT 1");
$last = $row ? ($row->fetch_assoc()['meta_value'] ?? '') : '';
if ($last === $today) {
    echo json_encode(['ok' => true, 'skipped' => true, 'reason' => 'already_ran']);
    exit;
}

$ids = [];
$ids[] = cj_queue_template($conn, 'daily_admin_rates', 'cron');
$ids[] = cj_queue_template($conn, 'daily_customer', 'cron');
$ids[] = cj_queue_template($conn, 'engage_pwa', 'cron');

$total = ['ok' => 0, 'fail' => 0];
foreach ($ids as $id) {
    if ($id) {
        $res = cj_dispatch_push_queue($conn, $id);
        $total['ok'] += $res['ok'];
        $total['fail'] += $res['fail'];
    }
}

$stmt = $conn->prepare("INSERT INTO cron_meta (meta_key, meta_value) VALUES ('daily_push_date', ?) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
$stmt->bind_param('s', $today);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true, 'sent' => $total]);
