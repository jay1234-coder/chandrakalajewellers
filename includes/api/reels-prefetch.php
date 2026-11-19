<?php
/**
 * ChandraKala Jewellers — Background Reels Prefetch API
 * Dispatched automatically via api/index.php at /api/reels-prefetch
 * Serves lightweight metadata for the top-ranked reels so client-side browsers
 * can pre-buffer the first 2 reels in the background while visitors browse the site.
 */
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/reels.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=180, s-maxage=300, stale-while-revalidate=600');
header('Access-Control-Allow-Origin: *');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

try {
    cj_ensure_reels_schema($conn);
    $visitorHash = function_exists('cj_reel_visitor_hash') ? cj_reel_visitor_hash() : '';
    $limit = isset($_GET['limit']) ? max(1, min(10, (int)$_GET['limit'])) : 2;

    $reels = function_exists('cj_get_prefetch_reels')
        ? cj_get_prefetch_reels($conn, $visitorHash, $limit)
        : [];

    echo json_encode([
        'ok' => true,
        'count' => count($reels),
        'reels' => $reels,
        'ts' => time(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Prefetch service unavailable',
    ]);
}
exit;
