<?php
/**
 * Lightweight beacon for abandoned-cart admin list (product IDs only).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

cj_ensure_catalog_schema($conn);
$raw = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($raw)) {
    echo json_encode(['ok' => false]);
    exit;
}

$ids = [];
foreach ((array) ($raw['ids'] ?? []) as $id) {
    $id = (int) $id;
    if ($id > 0) {
        $ids[$id] = $id;
    }
}
$ids = array_values($ids);
$session = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) ($raw['session'] ?? '')) ?? '';
if ($session === '' || strlen($session) > 64) {
    $session = bin2hex(random_bytes(8));
}

if ($ids === []) {
    $stmt = $conn->prepare('DELETE FROM abandoned_carts WHERE session_key = ?');
    if ($stmt) {
        $stmt->bind_param('s', $session);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['ok' => true]);
    exit;
}

$csv = implode(',', $ids);
$count = count($ids);
$stmt = $conn->prepare(
    'INSERT INTO abandoned_carts (session_key, product_ids, item_count)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE product_ids = VALUES(product_ids), item_count = VALUES(item_count), last_seen_at = CURRENT_TIMESTAMP'
);
if ($stmt) {
    $stmt->bind_param('ssi', $session, $csv, $count);
    $stmt->execute();
    $stmt->close();
}
echo json_encode(['ok' => true]);
