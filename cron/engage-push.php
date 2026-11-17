<?php
/**
 * Juicy customer engagement push — every ~2 hours.
 *
 * Call from an external cron (cron-job.org / EasyCron / cPanel):
 *   https://chandrakalajewellers.in/cron/engage-push.php?key=YOUR_CRON_SECRET
 * Interval: every 2 hours
 *
 * Skips automatically if last send was < 110 minutes ago.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/push.php';

header('Content-Type: application/json; charset=utf-8');

$secretFile = __DIR__ . '/../config/cron-secret.php';
$secret = 'cj-change-this-cron-secret';
if (is_file($secretFile)) {
    $loaded = include $secretFile;
    if (is_string($loaded) && $loaded !== '') {
        $secret = $loaded;
    }
}

$key = (string)($_GET['key'] ?? '');
if (!hash_equals($secret, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

cj_ensure_pwa_schema($conn);

$metaKey = 'engage_push_last_ts';
$row = $conn->query("SELECT meta_value FROM cron_meta WHERE meta_key = '{$metaKey}' LIMIT 1");
$lastTs = (int)($row && ($row->fetch_assoc()['meta_value'] ?? 0));
$now = time();
$minGap = 110 * 60; // ~2 hours with small buffer

if ($lastTs > 0 && ($now - $lastTs) < $minGap) {
    echo json_encode([
        'ok' => true,
        'skipped' => true,
        'reason' => 'too_soon',
        'retry_in_sec' => $minGap - ($now - $lastTs),
    ]);
    exit;
}

// Quiet hours (India): 22:00–08:00 — skip night spam
$hour = (int)date('G');
if ($hour >= 22 || $hour < 8) {
    echo json_encode(['ok' => true, 'skipped' => true, 'reason' => 'quiet_hours']);
    exit;
}

$juicy = [
    ['🔥 Gold just got hotter', 'Today\'s 916 rate is live in Khedbrahma. Peek before FOMO hits.', 'gold-rate-khedbrahma'],
    ['Psst… sparkle check?', 'Someone nearby is browsing gold. Your turn — open ChandraKala.', 'gallery?metal_type=gold'],
    ['✨ 30-second jewellery break', 'New designs waiting. Tap open like Zomato… but for gold.', 'gallery'],
    ['Silver looking pricey?', 'Ours isn\'t. Live silver & 925 rates inside — one tap.', 'gallery?metal_type=silver'],
    ['💍 Wedding season energy', 'Custom catalogue designs ready. WhatsApp us when you fall in love.', 'Catalogue'],
    ['Your cart misses you', 'One gold piece could fix that. Browse fresh picks now.', 'gallery?metal_type=gold'],
    ['925 silver drop ✨', 'Lightweight. Shiny. WhatsApp-ready. Open the shop.', 'gallery?metal_type=silver_925'],
    ['Live rate. Zero regret.', 'Khedbrahma shoppers check 916 gold here first. You in?', 'index1'],
    ['Civil Road is open till 8', 'ChandraKala calling — come see (or shop online) before closing.', 'contact'],
    ['Don\'t tell your FOMO', 'Fresh jewellery just for you. Open the app & smile.', 'gallery'],
    ['Hallmark gold near you', 'Trusted family jewellers on Civil Road. Tap for today\'s picks.', 'jewellers-in-khedbrahma'],
    ['Hungry for shine?', 'Zomato feeds hunger. We feed sparkle. Open ChandraKala 😉', 'index1'],
];

$idxKey = 'engage_push_idx';
$idxRow = $conn->query("SELECT meta_value FROM cron_meta WHERE meta_key = '{$idxKey}' LIMIT 1");
$idx = (int)($idxRow && ($idxRow->fetch_assoc()['meta_value'] ?? 0));
$pick = $juicy[$idx % count($juicy)];
$nextIdx = ($idx + 1) % count($juicy);

$id = cj_queue_push(
    $conn,
    $pick[0],
    $pick[1],
    'customer',
    $pick[2],
    'engage-cron'
);

$total = ['ok' => 0, 'fail' => 0];
if ($id) {
    $total = cj_dispatch_push_queue($conn, $id);
}

$ts = (string)$now;
$stmt = $conn->prepare("INSERT INTO cron_meta (meta_key, meta_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
$stmt->bind_param('ss', $metaKey, $ts);
$stmt->execute();
$idxStr = (string)$nextIdx;
$stmt->bind_param('ss', $idxKey, $idxStr);
$stmt->execute();
$stmt->close();

echo json_encode([
    'ok' => true,
    'sent' => $total,
    'message' => ['title' => $pick[0], 'body' => $pick[1], 'url' => $pick[2]],
    'next_index' => $nextIdx,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
