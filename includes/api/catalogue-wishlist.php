<?php
/**
 * AJAX API endpoint: Manage Catalogue Design Wishlist / Likes.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__) . '/customers.php';
require_once dirname(__DIR__) . '/catalogue-wishlist.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Transparently decode JSON payload if submitted via fetch()
$rawInput = @file_get_contents('php://input');
if (!empty($rawInput)) {
    $jsonData = @json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $_POST = array_merge($jsonData, $_POST);
        $_REQUEST = array_merge($jsonData, $_REQUEST);
    }
}

$customer = cj_customer_session();
$action = (string)($_REQUEST['action'] ?? ($_POST['action'] ?? 'list'));

if ($action === 'list') {
    if (!$customer) {
        echo json_encode(['ok' => true, 'logged_in' => false, 'ids' => []]);
        exit;
    }
    $ids = cj_get_customer_catalogue_wishlist_ids($conn, (int)$customer['id']);
    echo json_encode(['ok' => true, 'logged_in' => true, 'ids' => $ids]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required.']);
    exit;
}

if (!$customer) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'requires_auth' => true,
        'message' => 'Please sign in or create an account to save catalogue designs to your personal wishlist.'
    ]);
    exit;
}

$customerId = (int)$customer['id'];
$catalogueId = (int)($_POST['catalogue_id'] ?? ($_POST['id'] ?? 0));

if ($catalogueId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid design ID.']);
    exit;
}

if ($action === 'toggle') {
    $liked = cj_toggle_customer_catalogue_wishlist($conn, $customerId, $catalogueId);
    echo json_encode([
        'ok' => true,
        'liked' => $liked,
        'id' => $catalogueId,
        'message' => $liked ? 'Saved to your Catalogue Images Wishlist!' : 'Removed from Wishlist.'
    ]);
    exit;
}

if ($action === 'remove') {
    $chk = $conn->prepare("DELETE FROM customer_catalogue_wishlist WHERE customer_id = ? AND catalogue_id = ?");
    if ($chk) {
        $chk->bind_param('ii', $customerId, $catalogueId);
        $chk->execute();
        $chk->close();
    }
    echo json_encode([
        'ok' => true,
        'liked' => false,
        'id' => $catalogueId,
        'message' => 'Design removed from your Wishlist.'
    ]);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
