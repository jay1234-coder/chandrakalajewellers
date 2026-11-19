<?php
/**
 * Official Meta Instagram Graph API 1-Click Reels Sync & Remote Video URL Importer.
 * Admin-only + CSRF protected.
 * Supports both Meta Facebook Graph API tokens (EAA...) and Instagram Login tokens (IGAA...),
 * plus direct remote MP4 / Google Drive / Dropbox video URLs.
 */
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/settings.php';
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

function cj_ig_http_get(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'ChandraKalaJewellers/2.0',
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $err !== '') {
        return ['ok' => false, 'code' => $code, 'error' => 'Network error: ' . $err];
    }
    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'code' => $code, 'error' => 'Invalid API response (HTTP ' . $code . ')'];
    }
    if (!empty($json['error']['message'])) {
        return ['ok' => false, 'code' => $code, 'error' => (string)$json['error']['message']];
    }
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'data' => $json];
}

function cj_ig_is_direct_ig_token(string $token): bool
{
    return stripos($token, 'IG') === 0;
}

function cj_normalize_remote_video_url(string $url): string
{
    $url = trim($url);
    // Convert Google Drive share links to direct download URL so Cloudinary can fetch them
    if (preg_match('#drive\.google\.com/file/d/([a-zA-Z0-9_-]+)#i', $url, $m)) {
        return 'https://drive.google.com/uc?export=download&id=' . $m[1];
    }
    if (preg_match('#drive\.google\.com/open\?id=([a-zA-Z0-9_-]+)#i', $url, $m)) {
        return 'https://drive.google.com/uc?export=download&id=' . $m[1];
    }
    // Convert Dropbox share link dl=0 -> dl=1
    if (stripos($url, 'dropbox.com') !== false) {
        $url = preg_replace('/([?&])dl=0\b/', '$1dl=1', $url) ?? $url;
        if (strpos($url, 'dl=1') === false) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'dl=1';
        }
    }
    return $url;
}

$action = trim((string)($_POST['action'] ?? ''));

// 1. Save & Auto-Verify Meta Instagram Graph API Token
if ($action === 'save_config') {
    $token = trim((string)($_POST['ig_access_token'] ?? ''));
    $manualIgId = trim((string)($_POST['ig_user_id'] ?? ''));

    if ($token === '') {
        // Allow using already saved token if admin only updates IG ID
        $token = cj_setting('ig_access_token', '', $conn);
    }
    if ($token === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Please paste your Meta Instagram Access Token.']);
        exit;
    }

    $discoveredId = $manualIgId;
    $discoveredUser = '';

    if (cj_ig_is_direct_ig_token($token)) {
        // Instagram Login API token (IGAA...)
        $r = cj_ig_http_get('https://graph.instagram.com/v21.0/me?fields=id,username&access_token=' . rawurlencode($token));
        if (!$r['ok']) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Instagram token verification failed: ' . ($r['error'] ?? 'Invalid token')]);
            exit;
        }
        $discoveredId = (string)($r['data']['id'] ?? $manualIgId);
        $discoveredUser = (string)($r['data']['username'] ?? '');
    } else {
        // Facebook/Meta Graph API token (EAA...)
        if ($discoveredId !== '') {
            $r = cj_ig_http_get('https://graph.facebook.com/v21.0/' . rawurlencode($discoveredId) . '?fields=id,username&access_token=' . rawurlencode($token));
            if ($r['ok']) {
                $discoveredUser = (string)($r['data']['username'] ?? '');
            } else {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Could not access Instagram Account ID: ' . ($r['error'] ?? 'Check permissions')]);
                exit;
            }
        } else {
            // Auto-discover Instagram Business Account linked to Facebook Pages
            $r = cj_ig_http_get('https://graph.facebook.com/v21.0/me/accounts?fields=name,instagram_business_account{id,username}&access_token=' . rawurlencode($token));
            if (!$r['ok']) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Meta token verification failed: ' . ($r['error'] ?? 'Check token & pages_show_list / instagram_basic permissions')]);
                exit;
            }
            $pages = $r['data']['data'] ?? [];
            foreach ($pages as $pg) {
                if (!empty($pg['instagram_business_account']['id'])) {
                    $discoveredId = (string)$pg['instagram_business_account']['id'];
                    $discoveredUser = (string)($pg['instagram_business_account']['username'] ?? $pg['name'] ?? '');
                    break;
                }
            }
            if ($discoveredId === '') {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'error' => 'Token is valid, but no Instagram Business Account was found linked to your Facebook Page. Make sure @chandra.kalajewellers is linked to your Facebook Page (or paste your Instagram Business Account ID directly).',
                ]);
                exit;
            }
        }
    }

    cj_setting_set($conn, 'ig_access_token', $token);
    cj_setting_set($conn, 'ig_user_id', $discoveredId);
    cj_setting_set($conn, 'ig_username', $discoveredUser);

    echo json_encode([
        'ok' => true,
        'ig_user_id' => $discoveredId,
        'ig_username' => $discoveredUser,
        'message' => 'Connected to Instagram' . ($discoveredUser !== '' ? (' @' . $discoveredUser) : '') . ' (ID: ' . $discoveredId . ')!',
    ]);
    exit;
}

// 2. Fetch Latest Reels from Connected Instagram Business Account
if ($action === 'fetch_reels') {
    $token = cj_setting('ig_access_token', '', $conn);
    $igId = cj_setting('ig_user_id', '', $conn);
    if ($token === '' || $igId === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Please save & connect your Instagram Access Token first.']);
        exit;
    }

    $fields = 'id,caption,media_type,media_product_type,media_url,thumbnail_url,permalink,timestamp';
    $base = cj_ig_is_direct_ig_token($token)
        ? 'https://graph.instagram.com/v21.0/me/media'
        : ('https://graph.facebook.com/v21.0/' . rawurlencode($igId) . '/media');
    $url = $base . '?fields=' . rawurlencode($fields) . '&limit=50&access_token=' . rawurlencode($token);

    $r = cj_ig_http_get($url);
    if (!$r['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Instagram API error: ' . ($r['error'] ?? 'Could not fetch media')]);
        exit;
    }

    // Load already-imported IG media IDs so we never duplicate
    $importedMap = [];
    if ($iq = @$conn->query("SELECT id, ig_media_id FROM video_reels WHERE ig_media_id != ''")) {
        while ($row = $iq->fetch_assoc()) {
            $importedMap[(string)$row['ig_media_id']] = (int)$row['id'];
        }
    }

    $reels = [];
    foreach (($r['data']['data'] ?? []) as $item) {
        $type = strtoupper((string)($item['media_type'] ?? ''));
        $mediaUrl = trim((string)($item['media_url'] ?? ''));
        if ($type !== 'VIDEO' || $mediaUrl === '') {
            continue;
        }
        $mid = (string)($item['id'] ?? '');
        $caption = trim((string)($item['caption'] ?? ''));
        $shortCaption = mb_substr(preg_replace('/\s+/', ' ', $caption) ?? $caption, 0, 140);
        $ts = (string)($item['timestamp'] ?? '');
        $istDate = ($ts !== '' && function_exists('cj_format_ist')) ? cj_format_ist($ts, 'd M Y, h:i A') : $ts;

        $reels[] = [
            'ig_media_id' => $mid,
            'media_url' => $mediaUrl,
            'thumbnail_url' => (string)($item['thumbnail_url'] ?? ''),
            'permalink' => (string)($item['permalink'] ?? ''),
            'caption' => $shortCaption,
            'posted_ist' => $istDate,
            'imported' => isset($importedMap[$mid]),
            'local_reel_id' => $importedMap[$mid] ?? 0,
        ];
    }

    echo json_encode([
        'ok' => true,
        'count' => count($reels),
        'reels' => $reels,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Import One Reel (from Instagram media_url OR any pasted Direct Video URL) directly into Cloudinary + TiDB
if ($action === 'import_one') {
    $rawUrl = trim((string)($_POST['video_url'] ?? ''));
    $igMediaId = mb_substr(trim((string)($_POST['ig_media_id'] ?? '')), 0, 80);
    $adminNote = mb_substr(trim((string)($_POST['admin_note'] ?? '')), 0, 200);

    if ($rawUrl === '' || !preg_match('#^https?://#i', $rawUrl)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Please enter a valid video URL (https://...).']);
        exit;
    }

    if (preg_match('#instagram\.com/(?:reel|p|tv)/#i', $rawUrl) && $igMediaId === '') {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'error' => 'To import Instagram reels directly, use the "1-Click Instagram Sync" tab above (connects your account via Meta free API) — or paste a direct .mp4 / Google Drive / Dropbox video link here.',
        ]);
        exit;
    }

    // Prevent duplicate import if ig_media_id already exists
    if ($igMediaId !== '') {
        $chk = $conn->prepare("SELECT id FROM video_reels WHERE ig_media_id = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param('s', $igMediaId);
            $chk->execute();
            $ex = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($ex) {
                echo json_encode(['ok' => true, 'already_imported' => true, 'id' => (int)$ex['id'], 'message' => 'Already imported as Reel #' . (int)$ex['id']]);
                exit;
            }
        }
    }

    $fetchUrl = cj_normalize_remote_video_url($rawUrl);
    $publicIdSeed = $igMediaId !== '' ? ('ig_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $igMediaId)) : null;
    $cld = cj_cloudinary_upload_remote_video($fetchUrl, $publicIdSeed, 'chandrakala/reels');
    if (empty($cld['ok'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $cld['error'] ?? 'Could not import video into Cloudinary.']);
        exit;
    }

    $cfg = cj_cloudinary_config();
    $publicId = (string)$cld['public_id'];
    $secureUrl = (string)$cld['secure_url'];
    $posterUrl = 'https://res.cloudinary.com/' . rawurlencode($cfg['cloud_name'])
        . '/video/upload/so_0.5,f_auto,q_auto,w_540/' . ltrim($publicId, '/') . '.jpg';
    $w = (int)($cld['width'] ?? 0);
    $h = (int)($cld['height'] ?? 0);
    $dur = (float)($cld['duration'] ?? 0);

    $stmt = $conn->prepare(
        "INSERT INTO video_reels (public_id, ig_media_id, video_url, poster_url, admin_note, width, height, duration, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())"
    );
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database error saving imported reel.']);
        exit;
    }
    $stmt->bind_param('sssssiid', $publicId, $igMediaId, $secureUrl, $posterUrl, $adminNote, $w, $h, $dur);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed saving reel: ' . $err]);
        exit;
    }
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    // Auto-ping IndexNow & rebuild sitemap for instant search indexing
    if (file_exists(dirname(__DIR__, 2) . '/includes/indexnow-helper.php')) {
        require_once dirname(__DIR__, 2) . '/includes/indexnow-helper.php';
        if (function_exists('cj_trigger_auto_indexing')) {
            @cj_trigger_auto_indexing($conn, [
                'https://chandrakalajewellers.in/reels',
                'https://chandrakalajewellers.in/showcase?v=' . $newId
            ], 'reel_ig_import');
        }
    }

    echo json_encode([
        'ok' => true,
        'id' => $newId,
        'uploaded_ist' => function_exists('cj_format_ist') ? cj_format_ist(date('Y-m-d H:i:s')) : date('d M Y, h:i A') . ' IST',
        'message' => 'Imported permanently to Cloudinary as Reel #' . $newId . '!',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
