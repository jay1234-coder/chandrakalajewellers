<?php
/**
 * IndexNow notifier — tells Bing / Yandex / Seznam / Naver that URLs changed.
 * Visit once after deploy (while logged into admin) or call with ?key=...
 *
 * Usage: /indexnow.php?token=YOUR_ADMIN_SESSION or set INDEXNOW_RUN=1 once.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/seo.php';

header('Content-Type: application/json; charset=UTF-8');

$indexNowKey = 'b7e4c91a2f8d4e6ab3c5d0e9f1a2b3c4';
$host = 'chandrakalajewellers.in';
$keyLocation = 'https://' . $host . '/' . $indexNowKey . '.txt';

// Allow only admin session OR one-time query token matching the key
$allowed = (!empty($_SESSION['loggedin']) && $_SESSION['loggedin'] === true)
    || (isset($_GET['key']) && hash_equals($indexNowKey, (string)$_GET['key']));

if (!$allowed) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden. Log in to admin or pass ?key=indexnow-key']);
    exit;
}

$urls = [
    'https://' . $host . '/index1',
    'https://' . $host . '/top-jewellers-in-gujarat',
    'https://' . $host . '/jewellers-in-sabarkantha',
    'https://' . $host . '/jewellers-in-khedbrahma',
    'https://' . $host . '/mcx',
    'https://' . $host . '/gold-rate-khedbrahma',
    'https://' . $host . '/about',
    'https://' . $host . '/gallery',
    'https://' . $host . '/Catalogue',
    'https://' . $host . '/contact',
    'https://' . $host . '/follow-us',
    'https://' . $host . '/blog',
    'https://' . $host . '/llms.txt',
    'https://' . $host . '/llms-full.txt',
    'https://' . $host . '/ai.txt',
    'https://' . $host . '/agents.txt',
    'https://' . $host . '/sitemap.xml',
];

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $pq = $conn->query('SELECT id FROM jewellery_items ORDER BY id DESC LIMIT 100');
    if ($pq) {
        while ($pr = $pq->fetch_assoc()) {
            $urls[] = 'https://' . $host . '/product?id=' . (int)$pr['id'];
        }
    }
}

$payload = json_encode([
    'host' => $host,
    'key' => $indexNowKey,
    'keyLocation' => $keyLocation,
    'urlList' => $urls,
], JSON_UNESCAPED_SLASHES);

$endpoints = [
    'https://api.indexnow.org/indexnow',
    'https://www.bing.com/indexnow',
    'https://yandex.com/indexnow',
];

$results = [];
foreach ($endpoints as $endpoint) {
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $results[] = [
        'endpoint' => $endpoint,
        'http' => $code,
        'error' => $err ?: null,
        'body' => is_string($body) ? substr($body, 0, 200) : null,
    ];
}

echo json_encode([
    'ok' => true,
    'submitted' => count($urls),
    'urls' => $urls,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
