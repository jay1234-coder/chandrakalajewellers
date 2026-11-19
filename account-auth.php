<?php
/**
 * Optional customer login / register (JSON). Never required to checkout.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/customers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!cj_feature('feat_customer_accounts')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Customer accounts are turned off.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required.']);
    exit;
}

// Transparently decode JSON payload if submitted via fetch()
$rawInput = @file_get_contents('php://input');
if (!empty($rawInput)) {
    $jsonData = @json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $_POST = array_merge($jsonData, $_POST);
    }
}

if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Session expired. Please refresh and try again.']);
    exit;
}

$action = (string) ($_POST['action'] ?? '');
if ($action === 'logout') {
    cj_customer_logout();
    echo json_encode(['ok' => true, 'message' => 'Signed out.', 'logged_in' => false]);
    exit;
}

if ($action === 'login') {
    $result = cj_customer_login($conn, (string) ($_POST['phone'] ?? ''), (string) ($_POST['password'] ?? ''));
    $result['logged_in'] = !empty($result['ok']);
    echo json_encode($result);
    exit;
}

if ($action === 'register') {
    $result = cj_customer_register(
        $conn,
        (string) ($_POST['name'] ?? ''),
        (string) ($_POST['phone'] ?? ''),
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['email'] ?? '')
    );
    $result['logged_in'] = !empty($result['ok']);
    echo json_encode($result);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
