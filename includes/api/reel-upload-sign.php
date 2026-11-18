<?php
/**
 * Admin Direct-to-Cloudinary Signed Video Upload Endpoint.
 * Bypasses Vercel's 4.5MB serverless body limit so 50MB–100MB HD jewellery reels
 * upload straight from the browser to Cloudinary with real-time progress bar.
 */
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/reels.php';

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
    echo json_encode(['ok' => false, 'error' => 'Admin login required.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired. Please refresh and try again.']);
    exit;
}

cj_ensure_reels_schema($conn);
$action = trim((string)($_POST['action'] ?? 'sign'));

// Step 1: Issue a 1-time Cloudinary SHA-1 upload signature
if ($action === 'sign') {
    $cfg = cj_cloudinary_config();
    if (empty($cfg['cloud_name']) || empty($cfg['api_key']) || empty($cfg['api_secret'])) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Cloudinary is not configured.']);
        exit;
    }

    $folder = 'chandrakala/reels';
    $timestamp = time();
    $publicId = 'reel_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));

    $params = [
        'folder' => $folder,
        'public_id' => $publicId,
        'timestamp' => $timestamp,
    ];
    ksort($params);
    $toSign = '';
    foreach ($params as $k => $v) {
        $toSign .= $k . '=' . $v . '&';
    }
    $signature = sha1(rtrim($toSign, '&') . $cfg['api_secret']);

    echo json_encode([
        'ok' => true,
        'cloud_name' => $cfg['cloud_name'],
        'api_key' => $cfg['api_key'],
        'timestamp' => $timestamp,
        'folder' => $folder,
        'public_id' => $publicId,
        'signature' => $signature,
        'upload_url' => 'https://api.cloudinary.com/v1_1/' . rawurlencode($cfg['cloud_name']) . '/video/upload',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// Step 2: Save completed Cloudinary video upload into TiDB with IST timestamp
if ($action === 'save') {
    $publicId = trim((string)($_POST['public_id'] ?? ''));
    $videoUrl = trim((string)($_POST['video_url'] ?? ''));
    $adminNote = mb_substr(trim((string)($_POST['admin_note'] ?? '')), 0, 200);
    $width = max(0, (int)($_POST['width'] ?? 0));
    $height = max(0, (int)($_POST['height'] ?? 0));
    $duration = max(0.0, (float)($_POST['duration'] ?? 0));

    if ($videoUrl === '' || !preg_match('#^https?://#i', $videoUrl)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Valid video URL is required.']);
        exit;
    }

    $posterUrl = '';
    if ($publicId !== '') {
        $cfg = cj_cloudinary_config();
        $posterUrl = 'https://res.cloudinary.com/' . rawurlencode($cfg['cloud_name'])
            . '/video/upload/so_0.5,f_auto,q_auto,w_540/' . ltrim($publicId, '/') . '.jpg';
    }

    // Store exact IST time via MySQL NOW() (session time_zone is +05:30 in config/db.php)
    $stmt = $conn->prepare(
        "INSERT INTO video_reels (public_id, video_url, poster_url, admin_note, width, height, duration, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())"
    );
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database error preparing insert.']);
        exit;
    }
    $stmt->bind_param('ssssiid', $publicId, $videoUrl, $posterUrl, $adminNote, $width, $height, $duration);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to save reel: ' . $err]);
        exit;
    }
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    // Ping IndexNow for instant Bing/ChatGPT/Yandex indexing of the new reel
    if (file_exists(dirname(__DIR__, 2) . '/includes/indexnow-helper.php')) {
        require_once dirname(__DIR__, 2) . '/includes/indexnow-helper.php';
        if (function_exists('cj_auto_ping_indexnow')) {
            @cj_auto_ping_indexnow(['https://chandrakalajewellers.in/reels', 'https://chandrakalajewellers.in/showcase?v=' . $newId], true);
        }
    }

    $saved = cj_get_reel_by_id($conn, $newId);
    echo json_encode([
        'ok' => true,
        'id' => $newId,
        'reel' => $saved,
        'uploaded_ist' => function_exists('cj_format_ist') ? cj_format_ist($saved['created_at'] ?? null) : date('d M Y, h:i A') . ' IST',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
