<?php
/**
 * Public Video Reels Engagement & Impressions Tracking API.
 * Handles: impression, view, click, whatsapp, call, share, like (toggle), comment/review, order/booking.
 */
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/reels.php';
require_once dirname(__DIR__, 2) . '/includes/customers.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    cj_ensure_reels_schema($conn);
    $getAct = (string)($_GET['action'] ?? '');

    if ($getAct === 'comments') {
        $rid = (int)($_GET['reel_id'] ?? 0);
        if ($rid <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'comments' => []]);
            exit;
        }
        $list = cj_get_reel_comments($conn, $rid, 40);
        $formatted = array_map(static function ($c) {
            return [
                'id' => (int)$c['id'],
                'customer_name' => (string)$c['customer_name'],
                'rating' => (int)$c['rating'],
                'comment' => (string)$c['comment'],
                'time_ist' => function_exists('cj_format_ist') ? cj_format_ist($c['created_at'] ?? '') : (string)($c['created_at'] ?? ''),
            ];
        }, $list);
        echo json_encode(['ok' => true, 'comments' => $formatted], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($getAct === 'ad_comments') {
        $itemKey = trim((string)($_GET['item_key'] ?? ''));
        if (!preg_match('/^(?:shop|cat):\d+$/', $itemKey)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'comments' => []]);
            exit;
        }
        $st = $conn->prepare("SELECT id, customer_name, rating, comment, created_at FROM reel_ad_comments WHERE item_key = ? ORDER BY id DESC LIMIT 40");
        $out = [];
        if ($st) {
            $st->bind_param('s', $itemKey);
            $st->execute();
            $res = $st->get_result();
            while ($c = $res->fetch_assoc()) {
                $out[] = [
                    'id' => (int)$c['id'],
                    'customer_name' => (string)$c['customer_name'],
                    'rating' => (int)$c['rating'],
                    'comment' => (string)$c['comment'],
                    'time_ist' => function_exists('cj_format_ist') ? cj_format_ist($c['created_at'] ?? '') : (string)($c['created_at'] ?? ''),
                ];
            }
            $st->close();
        }
        echo json_encode(['ok' => true, 'comments' => $out], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($getAct === 'more_feed') {
        $visitorHash = cj_reel_visitor_hash();
        $exclRaw = trim((string)($_GET['exclude'] ?? ''));
        $excl = $exclRaw !== '' ? array_values(array_filter(explode(',', $exclRaw))) : [];
        $slides = cj_get_sponsored_ad_slides($conn, $visitorHash, 16, $excl);
        // If visitor scrolled through all 200+ items, reset exclusions so it NEVER ends!
        if (empty($slides)) {
            $slides = cj_get_sponsored_ad_slides($conn, $visitorHash, 16, []);
        }
        foreach ($slides as &$sl) {
            $sl['views_fmt'] = cj_format_reel_count((int)$sl['display_views']);
            $sl['wa_url'] = 'https://wa.me/919427080359?text=' . rawurlencode((string)($sl['wa_text'] ?? ''));
        }
        unset($sl);
        echo json_encode(['ok' => true, 'slides' => $slides], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

cj_ensure_reels_schema($conn);

// Support both application/json (sendBeacon) and form-urlencoded/multipart
$rawBody = (string)file_get_contents('php://input');
$payload = $_POST;
if (empty($payload) && $rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    } else {
        parse_str($rawBody, $parsed);
        if (is_array($parsed)) {
            $payload = $parsed;
        }
    }
}

$reelId = (int)($payload['reel_id'] ?? 0);
$itemKey = trim((string)($payload['item_key'] ?? ''));
$action = strtolower(trim((string)($payload['action'] ?? '')));
$visitorHash = cj_reel_visitor_hash();

// 0. Sponsored Gallery / Catalogue Photo Reel Ad Interactions (item_key = "shop:12" or "cat:298")
if ($itemKey !== '' && preg_match('/^(?:shop|cat):\d+$/', $itemKey)) {
    $conn->query("INSERT IGNORE INTO reel_ad_stats (item_key) VALUES ('" . $conn->real_escape_string($itemKey) . "')");

    if ($action === 'impression') {
        $st = $conn->prepare("UPDATE reel_ad_stats SET impressions = impressions + 1 WHERE item_key = ?");
        if ($st) { $st->bind_param('s', $itemKey); $st->execute(); $st->close(); }
        $r = $conn->query("SELECT impressions FROM reel_ad_stats WHERE item_key = '" . $conn->real_escape_string($itemKey) . "'")->fetch_assoc();
        $imp = (int)($r['impressions'] ?? 1);
        echo json_encode(['ok' => true, 'display_views' => $imp, 'display_views_fmt' => cj_format_reel_count($imp)]);
        exit;
    }

    if ($action === 'click') {
        $st = $conn->prepare("UPDATE reel_ad_stats SET clicks = clicks + 1 WHERE item_key = ?");
        if ($st) { $st->bind_param('s', $itemKey); $st->execute(); $st->close(); }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'whatsapp' || $action === 'share') {
        $st = $conn->prepare("UPDATE reel_ad_stats SET whatsapp_clicks = whatsapp_clicks + 1 WHERE item_key = ?");
        if ($st) { $st->bind_param('s', $itemKey); $st->execute(); $st->close(); }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'like') {
        $chk = $conn->prepare("SELECT id FROM reel_ad_likes WHERE item_key = ? AND visitor_hash = ? LIMIT 1");
        $already = false;
        if ($chk) {
            $chk->bind_param('ss', $itemKey, $visitorHash);
            $chk->execute();
            $already = (bool)$chk->get_result()->fetch_assoc();
            $chk->close();
        }
        if ($already) {
            $del = $conn->prepare("DELETE FROM reel_ad_likes WHERE item_key = ? AND visitor_hash = ?");
            if ($del) { $del->bind_param('ss', $itemKey, $visitorHash); $del->execute(); $del->close(); }
            $liked = false;
        } else {
            $ins = $conn->prepare("INSERT IGNORE INTO reel_ad_likes (item_key, visitor_hash, created_at) VALUES (?, ?, NOW())");
            if ($ins) { $ins->bind_param('ss', $itemKey, $visitorHash); $ins->execute(); $ins->close(); }
            $liked = true;
        }
        $esc = $conn->real_escape_string($itemKey);
        $conn->query("UPDATE reel_ad_stats SET likes_count = (SELECT COUNT(*) FROM reel_ad_likes WHERE item_key = '{$esc}') WHERE item_key = '{$esc}'");
        $r = $conn->query("SELECT likes_count FROM reel_ad_stats WHERE item_key = '{$esc}'")->fetch_assoc();
        $cnt = (int)($r['likes_count'] ?? 0);
        echo json_encode(['ok' => true, 'liked' => $liked, 'likes_count' => $cnt, 'likes_fmt' => cj_format_reel_count($cnt)]);
        exit;
    }

    if ($action === 'comment') {
        $sess = function_exists('cj_customer_session') ? cj_customer_session() : null;
        $name = mb_substr(trim((string)($payload['name'] ?? ($sess['name'] ?? ''))), 0, 120);
        $comment = mb_substr(trim((string)($payload['comment'] ?? '')), 0, 800);
        $rating = max(0, min(5, (int)($payload['rating'] ?? 0)));
        if ($name === '' || mb_strlen($comment, 'UTF-8') < 2) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Please enter your name and comment.']);
            exit;
        }
        $ins = $conn->prepare("INSERT INTO reel_ad_comments (item_key, customer_name, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($ins) {
            $ins->bind_param('ssis', $itemKey, $name, $rating, $comment);
            $ins->execute();
            $cid = (int)$ins->insert_id;
            $ins->close();
        } else {
            $cid = 0;
        }
        $esc = $conn->real_escape_string($itemKey);
        $conn->query("UPDATE reel_ad_stats SET comments_count = (SELECT COUNT(*) FROM reel_ad_comments WHERE item_key = '{$esc}') WHERE item_key = '{$esc}'");
        $r = $conn->query("SELECT comments_count FROM reel_ad_stats WHERE item_key = '{$esc}'")->fetch_assoc();
        echo json_encode([
            'ok' => true,
            'comment' => [
                'id' => $cid,
                'customer_name' => $name,
                'rating' => $rating,
                'comment' => $comment,
                'time_ist' => function_exists('cj_format_ist') ? cj_format_ist(date('Y-m-d H:i:s')) : date('d M Y, h:i A') . ' IST',
            ],
            'comments_count' => (int)($r['comments_count'] ?? 1),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unsupported ad action.']);
    exit;
}

if ($reelId <= 0 || $action === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid reel_id or action.']);
    exit;
}

// 1. Trackable Analytics Events (impression, view, watch, click, whatsapp, call, directions, share)
$allowedEvents = ['impression', 'view', 'watch', 'click', 'whatsapp', 'call', 'directions', 'share'];
if (in_array($action, $allowedEvents, true)) {
    $watchMs = max(0, min(300000, (int)($payload['watch_ms'] ?? 0)));

    // Deduplicate impressions & views per visitor within 6 hours to keep counts genuine
    $shouldCount = true;
    if ($action === 'impression' || $action === 'view') {
        $chk = $conn->prepare(
            "SELECT id FROM video_reel_events
             WHERE reel_id = ? AND event_type = ? AND visitor_hash = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)
             LIMIT 1"
        );
        if ($chk) {
            $chk->bind_param('iss', $reelId, $action, $visitorHash);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $shouldCount = false;
            }
            $chk->close();
        }
    }

    if ($shouldCount) {
        $ev = $conn->prepare("INSERT INTO video_reel_events (reel_id, event_type, visitor_hash, watch_ms, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($ev) {
            $ev->bind_param('issi', $reelId, $action, $visitorHash, $watchMs);
            $ev->execute();
            $ev->close();
        }

        if ($action === 'impression') {
            $conn->query("UPDATE video_reels SET impressions = impressions + 1, views = GREATEST(views, impressions) WHERE id = {$reelId}");
        } elseif ($action === 'view') {
            $conn->query("UPDATE video_reels SET views = views + 1, impressions = GREATEST(impressions, views) WHERE id = {$reelId}");
        } elseif ($action === 'watch' && $watchMs >= 500) {
            $conn->query("UPDATE video_reels SET total_watch_ms = total_watch_ms + {$watchMs}, watch_sessions = watch_sessions + 1 WHERE id = {$reelId}");
        } elseif ($action === 'click') {
            $conn->query("UPDATE video_reels SET clicks = clicks + 1 WHERE id = {$reelId}");
        } elseif ($action === 'whatsapp') {
            $conn->query("UPDATE video_reels SET whatsapp_clicks = whatsapp_clicks + 1 WHERE id = {$reelId}");
        }
    }

    $reel = cj_get_reel_by_id($conn, $reelId);
    echo json_encode([
        'ok' => true,
        'counted' => $shouldCount,
        'display_views' => $reel ? (int)$reel['display_views'] : 0,
        'display_views_fmt' => $reel ? cj_format_reel_count((int)$reel['display_views']) : '0',
    ]);
    exit;
}

// 2. Toggle Like / Unlike
if ($action === 'like') {
    $already = cj_reel_is_liked_by_visitor($conn, $reelId, $visitorHash);
    if ($already) {
        $del = $conn->prepare("DELETE FROM video_reel_likes WHERE reel_id = ? AND visitor_hash = ?");
        if ($del) {
            $del->bind_param('is', $reelId, $visitorHash);
            $del->execute();
            $del->close();
        }
        $conn->query("UPDATE video_reels SET likes_count = GREATEST(0, likes_count - 1) WHERE id = {$reelId}");
        $liked = false;
    } else {
        $ins = $conn->prepare("INSERT IGNORE INTO video_reel_likes (reel_id, visitor_hash, created_at) VALUES (?, ?, NOW())");
        if ($ins) {
            $ins->bind_param('is', $reelId, $visitorHash);
            $ins->execute();
            $ins->close();
        }
        $conn->query("UPDATE video_reels SET likes_count = (SELECT COUNT(*) FROM video_reel_likes WHERE reel_id = {$reelId}) WHERE id = {$reelId}");
        $liked = true;
    }

    $reel = cj_get_reel_by_id($conn, $reelId);
    $likesCount = $reel ? (int)$reel['likes_count'] : 0;
    echo json_encode([
        'ok' => true,
        'liked' => $liked,
        'likes_count' => $likesCount,
        'likes_fmt' => cj_format_reel_count($likesCount),
    ]);
    exit;
}

// 3. Post Comment / Showroom Review on a Reel
if ($action === 'comment') {
    $sess = function_exists('cj_customer_session') ? cj_customer_session() : null;
    $name = mb_substr(trim((string)($payload['name'] ?? ($sess['name'] ?? ''))), 0, 120);
    $phone = mb_substr(trim((string)($payload['phone'] ?? ($sess['phone'] ?? ''))), 0, 20);
    $comment = mb_substr(trim((string)($payload['comment'] ?? '')), 0, 800);
    $rating = max(0, min(5, (int)($payload['rating'] ?? 0)));

    if ($name === '' || mb_strlen($comment, 'UTF-8') < 2) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Please enter your name and comment.']);
        exit;
    }

    $ins = $conn->prepare(
        "INSERT INTO video_reel_comments (reel_id, customer_name, customer_phone, rating, comment, is_approved, created_at)
         VALUES (?, ?, ?, ?, ?, 1, NOW())"
    );
    if (!$ins) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not save comment.']);
        exit;
    }
    $ins->bind_param('issis', $reelId, $name, $phone, $rating, $comment);
    $ins->execute();
    $cid = (int)$ins->insert_id;
    $ins->close();

    $conn->query("UPDATE video_reels SET comments_count = (SELECT COUNT(*) FROM video_reel_comments WHERE reel_id = {$reelId} AND is_approved = 1) WHERE id = {$reelId}");
    $reel = cj_get_reel_by_id($conn, $reelId);

    echo json_encode([
        'ok' => true,
        'comment' => [
            'id' => $cid,
            'customer_name' => $name,
            'rating' => $rating,
            'comment' => $comment,
            'time_ist' => function_exists('cj_format_ist') ? cj_format_ist(date('Y-m-d H:i:s')) : date('d M Y, h:i A') . ' IST',
        ],
        'comments_count' => $reel ? (int)$reel['comments_count'] : 1,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Submit Order / Custom Design Inquiry / Private Showroom Viewing Booking
if ($action === 'order') {
    $sess = function_exists('cj_customer_session') ? cj_customer_session() : null;
    $name = mb_substr(trim((string)($payload['name'] ?? ($sess['name'] ?? ''))), 0, 120);
    $rawPhone = (string)($payload['phone'] ?? ($sess['phone'] ?? ''));
    $phone = function_exists('cj_normalize_customer_phone') ? cj_normalize_customer_phone($rawPhone) : preg_replace('/\D+/', '', $rawPhone);
    $preferredTime = mb_substr(trim((string)($payload['preferred_time'] ?? '')), 0, 80);
    $message = mb_substr(trim((string)($payload['message'] ?? '')), 0, 800);

    if ($name === '' || strlen((string)$phone) < 10) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Please enter your name and valid 10-digit mobile number.']);
        exit;
    }

    $ins = $conn->prepare(
        "INSERT INTO video_reel_orders (reel_id, customer_name, customer_phone, preferred_time, message, status, created_at)
         VALUES (?, ?, ?, ?, ?, 'new', NOW())"
    );
    if (!$ins) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not save inquiry.']);
        exit;
    }
    $ins->bind_param('issss', $reelId, $name, $phone, $preferredTime, $message);
    $ins->execute();
    $orderId = (int)$ins->insert_id;
    $ins->close();

    $ev = $conn->prepare("INSERT INTO video_reel_events (reel_id, event_type, visitor_hash, created_at) VALUES (?, 'order', ?, NOW())");
    if ($ev) {
        $ev->bind_param('is', $reelId, $visitorHash);
        $ev->execute();
        $ev->close();
    }
    $conn->query("UPDATE video_reels SET order_clicks = order_clicks + 1 WHERE id = {$reelId}");

    // Notify Admin immediately when a customer submits a Reel Order / Showroom Visit request
    try {
        if (file_exists(dirname(__DIR__) . '/push.php')) {
            require_once dirname(__DIR__) . '/push.php';
            $qid = cj_queue_push(
                $conn,
                "New Reel Order Inquiry (#{$reelId})",
                "{$name} ({$phone}) requested Reel #{$reelId}. Open Admin Reels to reply.",
                'admin',
                'admin/reels.php?tab=orders',
                'system'
            );
            if ($qid > 0) @cj_dispatch_push_queue($conn, $qid);
        }
    } catch (Throwable $ignorePush) {}

    $reelLink = 'https://chandrakalajewellers.in/showcase?v=' . $reelId;
    $waText = "Namaste ChandraKala Jewellers! I want to order / enquire about Reel #{$reelId}.\n"
        . "Name: {$name}\nMobile: {$phone}"
        . ($preferredTime !== '' ? "\nPreferred Visit/Call Time: {$preferredTime}" : '')
        . ($message !== '' ? "\nNote: {$message}" : '')
        . "\nReel Link: {$reelLink}";

    echo json_encode([
        'ok' => true,
        'order_id' => $orderId,
        'message' => 'Your request has been received! Our Khedbrahma showroom team will call or WhatsApp you shortly.',
        'whatsapp_url' => 'https://wa.me/919427080359?text=' . rawurlencode($waText),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unsupported action.']);
