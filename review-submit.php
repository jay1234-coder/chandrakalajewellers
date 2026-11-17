<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/catalog.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: gallery');
    exit;
}

$sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
$postedToken = (string) ($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
    $_SESSION['review_flash'] = 'Please refresh and try again.';
    header('Location: gallery');
    exit;
}

if (trim((string) ($_POST['website'] ?? '')) !== '') {
    header('Location: gallery');
    exit;
}

if (!cj_feature('feat_reviews')) {
    $_SESSION['review_flash'] = 'Reviews are turned off.';
    header('Location: product?id=' . (int) ($_POST['product_id'] ?? 0));
    exit;
}

$id = (int) ($_POST['product_id'] ?? 0);
$result = cj_submit_review(
    $conn,
    $id,
    (string) ($_POST['customer_name'] ?? ''),
    (int) ($_POST['rating'] ?? 5),
    (string) ($_POST['comment'] ?? '')
);
$_SESSION['review_flash'] = $result['message'];
header('Location: product?id=' . $id);
exit;
