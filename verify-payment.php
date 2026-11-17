<?php
/**
 * Verify Razorpay Checkout.js response and confirm the shop order.
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

$sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
$postedToken = (string) ($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid payment request. Please refresh and try again.']);
    exit;
}

$orderNo = preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($_POST['order_no'] ?? '')) ?? '';
$rzpOrderId = trim((string) ($_POST['razorpay_order_id'] ?? ''));
$rzpPaymentId = trim((string) ($_POST['razorpay_payment_id'] ?? ''));
$rzpSignature = trim((string) ($_POST['razorpay_signature'] ?? ''));

if ($orderNo === '' || $rzpOrderId === '' || $rzpPaymentId === '' || $rzpSignature === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Incomplete payment details.']);
    exit;
}

if (!cj_razorpay_verify_checkout_signature($rzpOrderId, $rzpPaymentId, $rzpSignature)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Payment signature could not be verified.']);
    exit;
}

cj_ensure_orders_schema($conn);
cj_expire_reservations($conn);

$packed = cj_fetch_order_by_no($conn, $orderNo);
if (!$packed) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Order not found.']);
    exit;
}

$order = $packed['order'];
$storedRzp = trim((string) ($order['razorpay_order_id'] ?? ''));
if ($storedRzp !== '' && !hash_equals($storedRzp, $rzpOrderId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Payment does not match this order.']);
    exit;
}

try {
    $payment = cj_razorpay_api('GET', '/payments/' . rawurlencode($rzpPaymentId));
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'Could not confirm payment with the gateway. Please wait a moment and try again.']);
    exit;
}

$payStatus = (string) ($payment['status'] ?? '');
$payAmount = (int) ($payment['amount'] ?? 0);
$payOrderId = (string) ($payment['order_id'] ?? '');
$expectedPaise = cj_razorpay_amount_paise((float) ($order['subtotal'] ?? 0));

if (!in_array($payStatus, ['captured', 'authorized'], true)
    || $payAmount !== $expectedPaise
    || !hash_equals($rzpOrderId, $payOrderId)
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Payment could not be matched to this order.']);
    exit;
}

if (!cj_mark_order_paid($conn, (int) $order['id'], $rzpOrderId, $rzpPaymentId, $rzpSignature)) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'message' => 'Payment received but the order could not be confirmed. Please contact the shop with your order number.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Payment confirmed.',
    'order_no' => $orderNo,
    'redirect' => 'success?ref=' . rawurlencode($orderNo),
]);
