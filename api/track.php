<?php
/**
 * Public visitor analytics collector.
 * POST JSON: { sid, event, page, title, referrer, lat, lng, accuracy, duration, seconds_on_page }
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/visitor-analytics-schema.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

cj_ensure_visitor_analytics_schema($conn);

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'json']);
    exit;
}

$sid = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($data['sid'] ?? ''));
if ($sid === '' || strlen($sid) < 16 || strlen($sid) > 64) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'sid']);
    exit;
}

$event = (string)($data['event'] ?? 'heartbeat');
$allowed = ['start', 'page', 'heartbeat', 'geo', 'end'];
if (!in_array($event, $allowed, true)) {
    $event = 'heartbeat';
}

$page = substr(trim((string)($data['page'] ?? '/')), 0, 500);
$title = substr(trim((string)($data['title'] ?? '')), 0, 255);
$referrer = substr(trim((string)($data['referrer'] ?? '')), 0, 500);
$ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
$duration = max(0, min(86400, (int)($data['duration'] ?? 0)));
$secondsOnPage = max(0, min(7200, (int)($data['seconds_on_page'] ?? 0)));

$lat = isset($data['lat']) && $data['lat'] !== null && $data['lat'] !== '' ? (float)$data['lat'] : null;
$lng = isset($data['lng']) && $data['lng'] !== null && $data['lng'] !== '' ? (float)$data['lng'] : null;
$accuracy = isset($data['accuracy']) && $data['accuracy'] !== null && $data['accuracy'] !== '' ? (float)$data['accuracy'] : null;
$geoStatus = substr(trim((string)($data['geo_status'] ?? '')), 0, 32);

// Ignore admin / API noise
$lowerPage = strtolower($page);
if (strpos($lowerPage, 'admin') !== false || strpos($lowerPage, 'track.php') !== false || strpos($lowerPage, 'api/') !== false) {
    echo json_encode(['ok' => true, 'ignored' => true]);
    exit;
}

$ip = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}
$ipHash = $ip !== '' ? hash('sha256', $ip . '|cj-analytics-salt') : null;

$now = date('Y-m-d H:i:s');

function cj_track_reverse_geo(float $lat, float $lng): array
{
    $out = ['label' => null, 'city' => null, 'region' => null, 'country' => null];
    $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' . rawurlencode((string)$lat) . '&lon=' . rawurlencode((string)$lng) . '&zoom=12&addressdetails=1';
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 3,
            'header' => "User-Agent: ChandraKalaJewellersAnalytics/1.0\r\nAccept: application/json\r\n",
        ],
    ]);
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) {
        return $out;
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return $out;
    }
    $addr = $decoded['address'] ?? [];
    $out['city'] = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['suburb'] ?? null;
    $out['region'] = $addr['state'] ?? $addr['county'] ?? null;
    $out['country'] = $addr['country'] ?? null;
    $parts = array_filter([$out['city'], $out['region'], $out['country']]);
    $out['label'] = $parts ? implode(', ', $parts) : ($decoded['display_name'] ?? null);
    if ($out['label']) {
        $out['label'] = substr((string)$out['label'], 0, 255);
    }
    return $out;
}

// Upsert session
$stmt = $conn->prepare('SELECT id, geo_label, lat, lng, page_count, duration_seconds, geo_status FROM visitor_sessions WHERE session_key = ? LIMIT 1');
$stmt->bind_param('s', $sid);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    $pc = ($event === 'page' || $event === 'start') ? 1 : 0;
    $gs = $geoStatus !== '' ? $geoStatus : 'unknown';
    $land = $page !== '' ? $page : '/';
    $ref = $referrer;
    $ins = $conn->prepare('INSERT INTO visitor_sessions
        (session_key, started_at, last_seen_at, duration_seconds, page_count, landing_page, referrer, user_agent, ip_hash, geo_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $ins->bind_param('sssiiissss', $sid, $now, $now, $duration, $pc, $land, $ref, $ua, $ipHash, $gs);
    $ins->execute();
    $ins->close();
} else {
    $newDuration = max((int)$existing['duration_seconds'], $duration);
    $pageCount = (int)$existing['page_count'];
    if ($event === 'page') {
        $pageCount++;
    }
    $upd = $conn->prepare('UPDATE visitor_sessions SET last_seen_at = ?, duration_seconds = GREATEST(duration_seconds, ?), page_count = ?, user_agent = IF(user_agent IS NULL OR user_agent = \'\', ?, user_agent) WHERE session_key = ?');
    $upd->bind_param('siiss', $now, $newDuration, $pageCount, $ua, $sid);
    $upd->execute();
    $upd->close();
}

if ($event === 'page' || $event === 'start') {
    $pv = $conn->prepare('INSERT INTO visitor_pageviews (session_key, page_path, page_title, viewed_at, seconds_on_page) VALUES (?, ?, ?, ?, 0)');
    $pv->bind_param('ssss', $sid, $page, $title, $now);
    $pv->execute();
    $pv->close();
}

if ($event === 'heartbeat' || $event === 'end') {
    if ($secondsOnPage > 0 && $page !== '') {
        $find = $conn->prepare('SELECT id FROM visitor_pageviews WHERE session_key = ? AND page_path = ? ORDER BY viewed_at DESC LIMIT 1');
        $find->bind_param('ss', $sid, $page);
        $find->execute();
        $pvRow = $find->get_result()->fetch_assoc();
        $find->close();
        if ($pvRow) {
            $pid = (int)$pvRow['id'];
            $updPv = $conn->prepare('UPDATE visitor_pageviews SET seconds_on_page = GREATEST(seconds_on_page, ?) WHERE id = ?');
            $updPv->bind_param('ii', $secondsOnPage, $pid);
            $updPv->execute();
            $updPv->close();
        }
    }
}

if ($event === 'geo') {
    if ($lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
        $needGeo = true;
        $check = $conn->prepare('SELECT geo_label, lat FROM visitor_sessions WHERE session_key = ? LIMIT 1');
        $check->bind_param('s', $sid);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();
        $label = null;
        $city = null;
        $region = null;
        $country = null;
        if ($row && !empty($row['geo_label']) && $row['lat'] !== null) {
            $needGeo = false;
            // still update coords
        }
        if ($needGeo || empty($row['geo_label'])) {
            $rev = cj_track_reverse_geo($lat, $lng);
            $label = $rev['label'];
            $city = $rev['city'];
            $region = $rev['region'];
            $country = $rev['country'];
        }
        $gs = 'granted';
        if ($label) {
            $g = $conn->prepare('UPDATE visitor_sessions SET lat = ?, lng = ?, geo_accuracy = ?, geo_label = COALESCE(?, geo_label), geo_city = COALESCE(?, geo_city), geo_region = COALESCE(?, geo_region), geo_country = COALESCE(?, geo_country), geo_status = ?, last_seen_at = ? WHERE session_key = ?');
            $g->bind_param('dddsssssss', $lat, $lng, $accuracy, $label, $city, $region, $country, $gs, $now, $sid);
            $g->execute();
            $g->close();
        } else {
            $g = $conn->prepare('UPDATE visitor_sessions SET lat = ?, lng = ?, geo_accuracy = ?, geo_status = ?, last_seen_at = ? WHERE session_key = ?');
            $g->bind_param('dddsss', $lat, $lng, $accuracy, $gs, $now, $sid);
            $g->execute();
            $g->close();
        }
    } elseif ($geoStatus !== '') {
        $g = $conn->prepare('UPDATE visitor_sessions SET geo_status = ?, last_seen_at = ? WHERE session_key = ?');
        $g->bind_param('sss', $geoStatus, $now, $sid);
        $g->execute();
        $g->close();
    }
}

echo json_encode(['ok' => true]);
