<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/pwa-schema.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

cj_ensure_pwa_schema($conn);

$role = $_SESSION['role'] ?? 'customer';
$audiencePref = ['customer', 'all'];
if ($role === 'admin') {
    $audiencePref = ['admin', 'customer', 'all'];
}
if ($role === 'superadmin') {
    $audiencePref = ['superadmin', 'admin', 'customer', 'all'];
}

$in = "'" . implode("','", $audiencePref) . "'";
$row = $conn->query("SELECT title, body, url_path, image_url, audience FROM push_queue WHERE status = 'sent' AND audience IN ({$in}) ORDER BY sent_at DESC, id DESC LIMIT 1")->fetch_assoc();

if (!$row) {
    $row = [
        'title' => 'ChandraKala Jewellers',
        'body' => 'Open the app for live rates and new jewellery designs.',
        'url_path' => 'index1',
        'image_url' => null,
    ];
}

$url = $row['url_path'] ?: 'index1';
if (!preg_match('#^https?://#i', $url)) {
    $url = './' . ltrim($url, './');
}

echo json_encode([
    'ok' => true,
    'title' => $row['title'],
    'body' => $row['body'],
    'url' => $url,
    'image' => $row['image_url'],
]);
