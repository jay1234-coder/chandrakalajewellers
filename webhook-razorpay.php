<?php
/**
 * Razorpay webhook backup: payment.captured / order.paid → confirm stock.
 * Configure in Razorpay dashboard with CJ_RAZORPAY_WEBHOOK_SECRET.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/payments.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$raw = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? $_SERVER['REDIRECT_HTTP_X_RAZORPAY_SIGNATURE'] ?? '');
if (!cj_razorpay_verify_webhook_signature($raw, $signature)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$event = (string) ($payload['event'] ?? '');
$entity = $payload['payload']['payment']['entity'] ?? $payload['payload']['order']['entity'] ?? null;
if (!is_array($entity)) {
    echo json_encode(['ok' => true, 'ignored' => true]);
    exit;
}

if (!in_array($event, ['payment.captured', 'payment.authorized', 'order.paid'], true)) {
    echo json_encode(['ok' => true, 'ignored' => true]);
    exit;
}

$rzpOrderId = trim((string) ($entity['order_id'] ?? $entity['id'] ?? ''));
$rzpPaymentId = trim((string) ($entity['id'] ?? ''));
if ($event === 'order.paid') {
    $rzpOrderId = trim((string) ($entity['id'] ?? ''));
    $rzpPaymentId = '';
}

$notes = $entity['notes'] ?? [];
$orderNo = is_array($notes) ? preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($notes['order_no'] ?? '')) : '';

cj_ensure_orders_schema($conn);
$order = $rzpOrderId !== '' ? cj_fetch_order_by_razorpay_order_id($conn, $rzpOrderId) : null;
if (!$order && $orderNo !== '') {
    $packed = cj_fetch_order_by_no($conn, $orderNo);
    $order = $packed['order'] ?? null;
}

if (!$order) {
    echo json_encode(['ok' => true, 'ignored' => true]);
    exit;
}

if ($rzpPaymentId === '') {
    try {
        $list = cj_razorpay_api('GET', '/orders/' . rawurlencode($rzpOrderId) . '/payments');
        $items = $list['items'] ?? [];
        if (is_array($items) && isset($items[0]['id'])) {
            $rzpPaymentId = (string) $items[0]['id'];
        }
    } catch (Throwable $e) {
        error_log('webhook-razorpay payments list: ' . $e->getMessage());
    }
}

if ($rzpOrderId === '' || $rzpPaymentId === '') {
    echo json_encode(['ok' => true, 'incomplete' => true]);
    exit;
}

$ok = cj_mark_order_paid($conn, (int) $order['id'], $rzpOrderId, $rzpPaymentId, $signature);
echo json_encode(['ok' => $ok]);
