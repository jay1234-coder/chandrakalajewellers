<?php
/**
 * Dynamic Product Types API for Admin Panel dropdown & Shop filtration.
 * GET  -> list all selectable product types
 * POST -> add a new product type dynamically via the "+" button
 */
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/catalog.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['loggedin']) && function_exists('cj_restore_auth_from_cookie')) {
    cj_restore_auth_from_cookie();
}
if (empty($_SESSION['loggedin'])) {
    cj_try_remember_login($conn, ['admin', 'superadmin']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'ok' => true,
        'types' => cj_get_product_types($conn),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Admin login required.']);
    exit;
}

if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Security token expired. Please refresh and try again.']);
    exit;
}

$rawName = (string)($_POST['name'] ?? '');
if ($rawName === '') {
    $json = json_decode((string)file_get_contents('php://input'), true);
    if (is_array($json)) {
        $rawName = (string)($json['name'] ?? '');
    }
}

$res = cj_add_product_type($conn, $rawName);
if (empty($res['ok'])) {
    http_response_code(400);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'added' => $res['name'],
    'types' => cj_get_product_types($conn),
], JSON_UNESCAPED_UNICODE);
