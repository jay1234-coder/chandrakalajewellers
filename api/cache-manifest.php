<?php
/**
 * List popular product / brand images for offline PWA precache.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/img-helper.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=300');

$urls = [
    'images/logo.png',
    'images/icons/icon-192.png',
    'images/icons/icon-512.png',
];

if (isset($conn) && !$conn->connect_error) {
    $q = $conn->query("SELECT image FROM jewellery_items WHERE image IS NOT NULL AND image != '' ORDER BY is_favourite DESC, id DESC LIMIT 80");
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $file = basename((string)$row['image']);
            if ($file === '') continue;
            if (function_exists('cj_img_url')) {
                $urls[] = cj_img_url($file, 400);
                $urls[] = cj_img_url($file, 800);
            } else {
                $urls[] = 'uploads/' . $file;
            }
        }
    }
}

$urls = array_values(array_unique(array_filter($urls)));
echo json_encode(['ok' => true, 'urls' => $urls, 'count' => count($urls)], JSON_UNESCAPED_SLASHES);
