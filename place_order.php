<?php
/**
 * Create a reserved order from checkout (JSON).
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/payments.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required.']);
    exit;
}

if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid checkout request. Please refresh and try again.']);
    exit;
}

$idsRaw = (string) ($_POST['ids'] ?? '');
$ids = array_map('intval', explode(',', $idsRaw));
$paymentMethod = cj_normalize_payment_method((string) ($_POST['payment_method'] ?? 'whatsapp'));

$result = cj_place_order(
    $conn,
    $ids,
    (string) ($_POST['name'] ?? ''),
    (string) ($_POST['phone'] ?? ''),
    (string) ($_POST['address'] ?? ''),
    'checkout',
    $paymentMethod,
    (string) ($_POST['pincode'] ?? ''),
    (string) ($_POST['email'] ?? ''),
    (int) (function_exists('cj_customer_session') && cj_customer_session() ? cj_customer_session()['id'] : 0)
);

if (empty($result['ok'])) {
    http_response_code(409);
}

echo json_encode($result);
