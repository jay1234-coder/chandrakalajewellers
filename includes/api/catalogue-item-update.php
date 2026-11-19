<?php
/**
 * Update Catalogue Item Name & Category API Endpoint
 * Allows instant inline renaming and category updates for custom catalogue designs.
 */
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/activity-log.php';
require_once dirname(__DIR__, 2) . '/includes/indexnow-helper.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['loggedin']) && function_exists('cj_restore_auth_from_cookie')) {
    cj_restore_auth_from_cookie();
}
if (empty($_SESSION['loggedin'])) {
    cj_try_remember_login($conn, ['admin', 'superadmin']);
}

if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Admin session expired. Please re-login.']);
    exit;
}

if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF verification failed.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$category = trim((string)($_POST['category'] ?? ''));
$description = isset($_POST['description']) ? trim((string)$_POST['description']) : null;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid product ID.']);
    exit;
}

if ($name === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Product name cannot be empty.']);
    exit;
}

// Check columns in products
$hasCat = ($conn->query("SHOW COLUMNS FROM products LIKE 'category'")?->num_rows ?? 0) > 0;

if ($hasCat) {
    if ($description !== null) {
        $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $category, $description, $id);
    } else {
        $stmt = $conn->prepare("UPDATE products SET name = ?, category = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $category, $id);
    }
} else {
    if ($description !== null) {
        $stmt = $conn->prepare("UPDATE products SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $description, $id);
    } else {
        $stmt = $conn->prepare("UPDATE products SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $id);
    }
}

if (!$stmt || !$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database update error: ' . ($stmt ? $stmt->error : $conn->error)]);
    exit;
}
$stmt->close();

cj_log_activity($conn, 'edit_catalogue_design', "Renamed design #{$id} to '{$name}' [{$category}]");

// Trigger auto-indexing for updated design
if (function_exists('cj_trigger_auto_indexing')) {
    cj_trigger_auto_indexing($conn, [
        'https://chandrakalajewellers.in/Catalogue',
        'https://chandrakalajewellers.in/Catalogue?id=' . $id
    ], 'catalogue_item_update');
}

echo json_encode([
    'ok' => true,
    'id' => $id,
    'name' => $name,
    'category' => $category,
], JSON_UNESCAPED_UNICODE);
