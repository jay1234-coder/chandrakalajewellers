<?php
/**
 * ChandraKala Jewellers — Next-Gen Executive Visitor Intelligence & Analytics Dashboard
 * 
 * Features:
 * - Real-time automated insights & AI takeaway summary cards
 * - Timeframe selector: Today | 7 Days | 30 Days | All Time
 * - Interactive Chart.js Area Chart: Traffic & Pageviews velocity over time
 * - Top Cities & Geographic Distribution with animated bar meters
 * - Device & Platform Distribution (Mobile / Desktop / Tablet)
 * - Most Viewed Pages & Jewellery Reels Leaderboard
 * - Upgraded Leaflet Map with animated radar pulsing active pins & quick-zoom buttons
 * - Chronological Visitor Session Journey logs with step-by-step page trails
 * - Full Dark Mode & Mobile Responsive styling
 */

require_once __DIR__ . '/visitor-analytics-schema.php';
require_once __DIR__ . '/reels.php';
cj_ensure_visitor_analytics_schema($conn);

// -----------------------------------------------------------------------------
// 1. Timeframe & Filter Configuration
// -----------------------------------------------------------------------------
$range = strtolower(trim((string)($_GET['range'] ?? '7d')));
$validRanges = ['today', '7d', '30d', 'all'];
if (!in_array($range, $validRanges, true)) {
    $range = '7d';
}

$nowTs = time();
$activeCut = date('Y-m-d H:i:s', $nowTs - 5 * 60); // active in last 5 minutes

switch ($range) {
    case 'today':
        $sessionWhere = "started_at >= CURDATE()";
        $pvWhere = "viewed_at >= CURDATE()";
        $rangeTitle = "Today (Live)";
        $chartMode = "hourly";
        break;
    case '30d':
        $sessionWhere = "started_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $pvWhere = "viewed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $rangeTitle = "Last 30 Days";
        $chartMode = "daily";
        break;
    case 'all':
        $sessionWhere = "1=1";
        $pvWhere = "1=1";
        $rangeTitle = "All Time History";
        $chartMode = "daily";
        break;
    case '7d':
    default:
        $sessionWhere = "started_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $pvWhere = "viewed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        $rangeTitle = "Last 7 Days";
        $chartMode = "daily";
        break;
}

// -----------------------------------------------------------------------------
// 2. Core KPI Aggregations
// -----------------------------------------------------------------------------
// Active right now (last 5 min)
$activeNow = 0;
if ($r = $conn->query("SELECT COUNT(*) AS c FROM visitor_sessions WHERE last_seen_at >= '{$activeCut}'")) {
    $activeNow = (int)($r->fetch_assoc()['c'] ?? 0);
}

// Total sessions & duration in range
$totalVisitors = 0;
$avgDurationSec = 0;
$withGeo = 0;
if ($r = $conn->query("SELECT COUNT(*) AS c, AVG(duration_seconds) AS a, COUNT(CASE WHEN lat IS NOT NULL THEN 1 END) AS g FROM visitor_sessions WHERE {$sessionWhere}")) {
    $row = $r->fetch_assoc();
    $totalVisitors = (int)($row['c'] ?? 0);
    $avgDurationSec = round((float)($row['a'] ?? 0));
    $withGeo = (int)($row['g'] ?? 0);
}

// Total pageviews in range
$totalPageviews = 0;
if ($r = $conn->query("SELECT COUNT(*) AS c FROM visitor_pageviews WHERE {$pvWhere}")) {
    $totalPageviews = (int)($r->fetch_assoc()['c'] ?? 0);
}

$pagesPerVisit = $totalVisitors > 0 ? round($totalPageviews / $totalVisitors, 1) : 0;
$geoRate = $totalVisitors > 0 ? round(($withGeo / $totalVisitors) * 100) : 0;

// -----------------------------------------------------------------------------
// 3. Traffic Over Time Dataset for Chart.js
// -----------------------------------------------------------------------------
$chartLabels = [];
$chartVisitors = [];
$chartPageviews = [];

if ($chartMode === 'hourly') {
    $hourVis = array_fill(0, 24, 0);
    $hourViews = array_fill(0, 24, 0);
    $qV = $conn->query("SELECT HOUR(started_at) AS hr, COUNT(*) AS c FROM visitor_sessions WHERE started_at >= CURDATE() GROUP BY HOUR(started_at)");
    if ($qV) {
        while ($row = $qV->fetch_assoc()) $hourVis[(int)$row['hr']] = (int)$row['c'];
    }
    $qP = $conn->query("SELECT HOUR(viewed_at) AS hr, COUNT(*) AS c FROM visitor_pageviews WHERE viewed_at >= CURDATE() GROUP BY HOUR(viewed_at)");
    if ($qP) {
        while ($row = $qP->fetch_assoc()) $hourViews[(int)$row['hr']] = (int)$row['c'];
    }
    $curHour = (int)date('G');
    for ($h = 0; $h <= max(12, $curHour); $h++) {
        $chartLabels[] = sprintf('%02d:00', $h);
        $chartVisitors[] = $hourVis[$h];
        $chartPageviews[] = $hourViews[$h];
    }
} else {
    $daysBack = ($range === '30d') ? 30 : (($range === 'all') ? 45 : 7);
    $dayVis = [];
    $dayViews = [];
    $qV = $conn->query("SELECT DATE(started_at) AS dt, COUNT(*) AS c FROM visitor_sessions WHERE {$sessionWhere} GROUP BY DATE(started_at)");
    if ($qV) {
        while ($row = $qV->fetch_assoc()) $dayVis[$row['dt']] = (int)$row['c'];
    }
    $qP = $conn->query("SELECT DATE(viewed_at) AS dt, COUNT(*) AS c FROM visitor_pageviews WHERE {$pvWhere} GROUP BY DATE(viewed_at)");
    if ($qP) {
        while ($row = $qP->fetch_assoc()) $dayViews[$row['dt']] = (int)$row['c'];
    }
    for ($d = $daysBack; $d >= 0; $d--) {
        $dKey = date('Y-m-d', strtotime("-{$d} days"));
        $chartLabels[] = date('d M', strtotime($dKey));
        $chartVisitors[] = (int)($dayVis[$dKey] ?? 0);
        $chartPageviews[] = (int)($dayViews[$dKey] ?? 0);
    }
}

// -----------------------------------------------------------------------------
// 4. Geographic City Distribution & Auto-Insights
// -----------------------------------------------------------------------------
$topCities = [];
$totalCityVisits = 0;
$cRes = $conn->query("SELECT COALESCE(NULLIF(TRIM(geo_city), ''), 'Other / Location Not Shared') AS city, COUNT(*) AS cnt 
    FROM visitor_sessions 
    WHERE {$sessionWhere} 
    GROUP BY city 
    ORDER BY cnt DESC 
    LIMIT 7");
if ($cRes) {
    while ($row = $cRes->fetch_assoc()) {
        $topCities[] = $row;
        $totalCityVisits += (int)$row['cnt'];
    }
}

// Compute primary lead city
$leadCity = 'Khedbrahma';
$leadCityCount = 0;
foreach ($topCities as $tc) {
    if ($tc['city'] !== 'Other / Location Not Shared') {
        $leadCity = $tc['city'];
        $leadCityCount = (int)$tc['cnt'];
        break;
    }
}
$leadCityPct = $totalVisitors > 0 ? round(($leadCityCount / $totalVisitors) * 100) : 0;

// -----------------------------------------------------------------------------
// 5. Device & Platform Distribution
// -----------------------------------------------------------------------------
$devices = ['Mobile' => 0, 'Desktop' => 0, 'Tablet' => 0];
$dRes = $conn->query("SELECT user_agent FROM visitor_sessions WHERE {$sessionWhere} AND user_agent IS NOT NULL");
if ($dRes) {
    while ($row = $dRes->fetch_assoc()) {
        $ua = $row['user_agent'];
        if (preg_match('/ipad|tablet|playbook|silk/i', $ua)) {
            $devices['Tablet']++;
        } elseif (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini/i', $ua)) {
            $devices['Mobile']++;
        } else {
            $devices['Desktop']++;
        }
    }
}
$totalDeviceCount = array_sum($devices);
$mobilePct = $totalDeviceCount > 0 ? round(($devices['Mobile'] / $totalDeviceCount) * 100) : 0;

// -----------------------------------------------------------------------------
// 6. Top Content: Pages Leaderboard
// -----------------------------------------------------------------------------
$topPages = [];
$pRes = $conn->query("SELECT page_path, COUNT(*) AS views, ROUND(AVG(seconds_on_page), 0) AS avg_sec 
    FROM visitor_pageviews 
    WHERE {$pvWhere} AND page_path IS NOT NULL AND page_path != '' 
    GROUP BY page_path 
    ORDER BY views DESC 
    LIMIT 8");
if ($pRes) {
    while ($row = $pRes->fetch_assoc()) {
        $topPages[] = $row;
    }
}
$maxPageViews = 1;
foreach ($topPages as $tp) {
    $maxPageViews = max($maxPageViews, (int)$tp['views']);
}

// -----------------------------------------------------------------------------
// 7. Top Video Reels & Catalogue Highlights
// -----------------------------------------------------------------------------
$rankedReels = cj_get_ranked_reels($conn, 4, true, 'top');
$topReel = !empty($rankedReels[0]) ? $rankedReels[0] : null;

// Peak browsing hour calculation
$peakHour = 18;
$hRes = $conn->query("SELECT HOUR(started_at) AS hr, COUNT(*) AS c FROM visitor_sessions WHERE {$sessionWhere} GROUP BY HOUR(started_at) ORDER BY c DESC LIMIT 1");
if ($hRes && ($row = $hRes->fetch_assoc())) {
    $peakHour = (int)$row['hr'];
}
$peakHourFormatted = date('g:00 A', strtotime("2026-01-01 {$peakHour}:00:00"));

// -----------------------------------------------------------------------------
// 8. Map Pins Data & City Flight Chips
// -----------------------------------------------------------------------------
$mapPoints = [];
$mRes = $conn->query("SELECT session_key, lat, lng, geo_label, geo_city, geo_region, geo_country, duration_seconds, page_count, landing_page, last_seen_at
    FROM visitor_sessions
    WHERE lat IS NOT NULL AND lng IS NOT NULL
    ORDER BY last_seen_at DESC
    LIMIT 150");
if ($mRes) {
    while ($row = $mRes->fetch_assoc()) {
        $mapPoints[] = $row;
    }
}

// Extract top geotagged cities for interactive quick-flight chips
$mapCityChips = [];
foreach ($mapPoints as $mp) {
    $cn = trim((string)($mp['geo_city'] ?: ($mp['geo_region'] ?: '')));
    if ($cn !== '' && !isset($mapCityChips[$cn])) {
        $mapCityChips[$cn] = [
            'name' => $cn,
            'lat' => (float)$mp['lat'],
            'lng' => (float)$mp['lng'],
            'count' => 0
        ];
    }
    if ($cn !== '') {
        $mapCityChips[$cn]['count']++;
    }
}
uasort($mapCityChips, static fn($a, $b) => $b['count'] <=> $a['count']);
$mapCityChips = array_slice($mapCityChips, 0, 7, true);

// -----------------------------------------------------------------------------
// 9. Paginated Session Journey Logs
// -----------------------------------------------------------------------------
$vaPerPage = 10;
$vaTotal = 0;
if ($r = $conn->query("SELECT COUNT(*) AS c FROM visitor_sessions")) {
    $vaTotal = (int)($r->fetch_assoc()['c'] ?? 0);
}
$vaTotalPages = max(1, (int)ceil($vaTotal / $vaPerPage));
$vaPage = max(1, (int)($_GET['va_page'] ?? 1));
if ($vaPage > $vaTotalPages) $vaPage = $vaTotalPages;
$vaOffset = ($vaPage - 1) * $vaPerPage;
$vaFrom = $vaTotal === 0 ? 0 : ($vaOffset + 1);
$vaTo = min($vaTotal, $vaOffset + $vaPerPage);

$recentSessions = [];
$sRes = $conn->query("SELECT session_key, started_at, last_seen_at, duration_seconds, page_count, landing_page, referrer, user_agent,
        geo_label, geo_city, geo_region, geo_country, geo_status, lat, lng
    FROM visitor_sessions
    ORDER BY last_seen_at DESC
    LIMIT {$vaPerPage} OFFSET {$vaOffset}");
if ($sRes) {
    while ($row = $sRes->fetch_assoc()) $recentSessions[] = $row;
}

$sessionKeys = array_column($recentSessions, 'session_key');
$pagesBySession = [];
if ($sessionKeys) {
    $inKeys = implode(',', array_map(static fn($k) => "'" . $conn->real_escape_string($k) . "'", $sessionKeys));
    $pvRes = $conn->query("SELECT session_key, page_path, page_title, viewed_at, seconds_on_page
        FROM visitor_pageviews
        WHERE session_key IN ($inKeys) AND page_path IS NOT NULL AND TRIM(page_path) != ''
        ORDER BY viewed_at ASC");
    if ($pvRes) {
        while ($row = $pvRes->fetch_assoc()) {
            $sk = $row['session_key'];
            if (!isset($pagesBySession[$sk])) $pagesBySession[$sk] = [];
            if (count($pagesBySession[$sk]) < 20) $pagesBySession[$sk][] = $row;
        }
    }
}

// Friendly page names dictionary
function cj_va_page_title(string $path): string {
    $clean = trim(explode('?', $path)[0], '/');
    $map = [
        '' => 'Homepage (Showroom Splash)',
        'index' => 'Showroom Splash',
        'index1' => 'Showroom Experience',
        'gallery' => 'Jewellery Collection (Shop)',
        'Catalogue' => 'Custom Jewellery Catalogue',
        'reels' => 'Video Reels Experience',
        'showcase' => 'Dedicated Reel Player',
        'cart' => 'Shopping Cart',
        'checkout' => 'Order Checkout',
        'account' => 'Customer Account',
        'contact' => 'Showroom Contact & Directions',
        'about' => 'Brand Heritage & About',
        'blog' => 'Jewellery Journal / Blog',
        'gold-rate-khedbrahma' => 'Daily Live Gold Rates',
        'jewellers-in-khedbrahma' => 'Khedbrahma Jewellers Hub',
        'follow-us' => 'QR Follow & Google Reviews',
    ];
    return $map[$clean] ?? ('/' . $clean);
}

// URL generator preserving filters
function cj_va_url(string $newRange, int $newPage = 1): string {
    $params = $_GET;
    $params['view'] = 'analytics';
    $params['range'] = $newRange;
    $params['va_page'] = max(1, $newPage);
    return '?' . http_build_query($params) . '#visitorAnalytics';
}

function cj_va_format_duration(int $seconds): string {
    if ($seconds < 60) return $seconds . 's';
    $m = (int)floor($seconds / 60);
    $s = $seconds % 60;
    if ($m < 60) return $m . 'm ' . $s . 's';
    $h = (int)floor($m / 60);
    $m = $m % 60;
    return $h . 'h ' . $m . 'm';
}
?>

<!-- Scoped Luxury Styles for Redesigned Visitor Analytics Dashboard -->
<style>
:root {
    --cj-gold-pri: #d4af37;
    --cj-gold-dark: #b8972c;
    --cj-gold-light: #fef9e7;
    --cj-gold-border: rgba(212, 175, 55, 0.35);
    --cj-card-bg: #ffffff;
    --cj-card-border: #f1f2f6;
    --cj-card-sub: #f9fafb;
    --cj-text-main: #111827;
    --cj-text-muted: #6b7280;
}
html[data-theme="dark"] {
    --cj-card-bg: #161a22;
    --cj-card-border: #2e3544;
    --cj-card-sub: #1f2430;
    --cj-text-main: #f9fafb;
    --cj-text-muted: #9ca3af;
    --cj-gold-light: rgba(212, 175, 55, 0.12);
}

.cj-va-dashboard {
    font-family: inherit;
    color: var(--cj-text-main);
}
.cj-va-card {
    background: var(--cj-card-bg);
    border: 1px solid var(--cj-card-border);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.cj-va-card:hover {
    box-shadow: 0 8px 26px rgba(184, 151, 44, 0.07);
}

/* Auto Analysis / AI Insights Deck */
.cj-va-insights-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 12px;
}
@media (min-width: 640px) {
    .cj-va-insights-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (min-width: 1100px) {
    .cj-va-insights-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.cj-va-insight-pill {
    background: linear-gradient(135deg, var(--cj-card-bg) 0%, var(--cj-card-sub) 100%);
    border: 1px solid var(--cj-gold-border);
    border-radius: 14px;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}
.cj-va-insight-pill::before {
    content: '';
    position: absolute;
    top: 0; left: 0; width: 4px; height: 100%;
    background: linear-gradient(180deg, var(--cj-gold-pri), var(--cj-gold-dark));
}

/* KPI Grid */
.cj-va-kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
@media (min-width: 900px) {
    .cj-va-kpi-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
}
.cj-va-kpi-box {
    padding: 16px;
    border-radius: 14px;
    background: var(--cj-card-bg);
    border: 1px solid var(--cj-card-border);
    position: relative;
}
.cj-va-kpi-num {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1.15;
    color: var(--cj-text-main);
    letter-spacing: -0.02em;
}

/* Radar Pulse for Active Visitors */
.cj-va-radar-dot {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #10b981;
    position: relative;
    vertical-align: middle;
    margin-right: 5px;
}
.cj-va-radar-dot::after {
    content: '';
    position: absolute;
    top: -3px; left: -3px; right: -3px; bottom: -3px;
    border-radius: 50%;
    border: 2px solid #10b981;
    animation: cjRadarPulse 2s cubic-bezier(0.24, 0, 0.38, 1) infinite;
}
@keyframes cjRadarPulse {
    0% { transform: scale(0.6); opacity: 1; }
    100% { transform: scale(2.4); opacity: 0; }
}

/* Timeframe filter pills */
.cj-va-range-pills {
    display: inline-flex;
    background: var(--cj-card-sub);
    padding: 3px;
    border-radius: 10px;
    border: 1px solid var(--cj-card-border);
    gap: 2px;
}
.cj-va-range-btn {
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 8px;
    color: var(--cj-text-muted);
    text-decoration: none;
    transition: all 0.2s ease;
}
.cj-va-range-btn.is-active {
    background: var(--cj-card-bg);
    color: var(--cj-gold-dark);
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}

/* Progress bar meters */
.cj-va-bar-track {
    height: 6px;
    border-radius: 999px;
    background: var(--cj-card-sub);
    overflow: hidden;
    position: relative;
}
.cj-va-bar-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #d4af37, #eab308);
    transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

/* -----------------------------------------------------------------------------
   Interactive Live Visitor Map & Radar Styling
----------------------------------------------------------------------------- */
#adminVisitorMap {
    height: 480px !important;
    min-height: 400px !important;
    width: 100% !important;
    border-radius: 14px;
    background: #eef2ff;
    position: relative;
    z-index: 1;
}
@media (max-width: 640px) {
    #adminVisitorMap {
        height: 360px !important;
        min-height: 320px !important;
    }
}
html[data-theme="dark"] #adminVisitorMap {
    background: #0f1218;
}
#adminVisitorMap .leaflet-container {
    width: 100% !important;
    height: 100% !important;
    font-family: inherit !important;
}

/* Map Card and Frame */
.cj-va-map-card {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid var(--cj-card-border);
    background: var(--cj-card-bg);
}

/* HUD Overlay on Map (Top-Left) */
.cj-map-hud {
    position: absolute;
    top: 14px;
    left: 14px;
    z-index: 1000;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(212, 175, 55, 0.45);
    border-radius: 12px;
    padding: 8px 14px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    pointer-events: auto;
}
html[data-theme="dark"] .cj-map-hud {
    background: rgba(22, 26, 34, 0.94);
    border-color: rgba(212, 175, 55, 0.35);
    color: #f3f4f6;
}

/* Map Action Buttons (Top-Right) */
.cj-va-map-actions {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 1000;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: flex-end;
}
.cj-va-map-btn {
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(212, 175, 55, 0.4);
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    color: #1a1204;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.cj-va-map-btn:hover {
    background: #d4af37;
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(212, 175, 55, 0.35);
}
html[data-theme="dark"] .cj-va-map-btn {
    background: rgba(22, 26, 34, 0.94);
    color: #f0d78c;
    border-color: rgba(212, 175, 55, 0.3);
}
html[data-theme="dark"] .cj-va-map-btn:hover {
    background: #d4af37;
    color: #111827;
}

/* Quick City Flight Pills Bar */
.cj-city-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--cj-card-sub);
    border: 1px solid var(--cj-card-border);
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    color: var(--cj-text-main);
    cursor: pointer;
    transition: all 0.2s ease;
}
.cj-city-pill:hover {
    border-color: #d4af37;
    color: #d4af37;
    background: var(--cj-gold-light);
    transform: translateY(-1px);
}
.cj-city-pill .pill-badge {
    background: rgba(212, 175, 55, 0.2);
    color: #b8972c;
    font-weight: 800;
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 999px;
}

/* ChandraKala HQ Crown Marker */
.cj-hq-marker-wrap {
    position: relative;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.cj-hq-halo {
    position: absolute;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 2px dashed #d4af37;
    animation: cjHQRotate 10s linear infinite;
    pointer-events: none;
}
.cj-hq-core {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, #fef08a 0%, #d4af37 60%, #b8972c 100%);
    border: 2px solid #ffffff;
    box-shadow: 0 0 16px rgba(212, 175, 55, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1a1204;
    font-size: 12px;
    z-index: 2;
    animation: cjHQBob 2.8s ease-in-out infinite;
}
.cj-hq-tag {
    position: absolute;
    top: -24px;
    background: #111827;
    color: #fef08a;
    font-weight: 800;
    font-size: 9px;
    letter-spacing: 0.04em;
    padding: 2px 7px;
    border-radius: 999px;
    white-space: nowrap;
    border: 1px solid #d4af37;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    pointer-events: none;
}
@keyframes cjHQRotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
@keyframes cjHQBob {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-3px); }
}

/* Visitor Pin Animated Radar Markers */
.cj-visitor-pin-wrap {
    position: relative;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.cj-visitor-pin-core {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #d4af37;
    border: 2px solid #ffffff;
    box-shadow: 0 0 8px rgba(212, 175, 55, 0.85);
    position: relative;
    z-index: 2;
    transition: transform 0.2s ease;
}
.cj-visitor-pin-wrap:hover .cj-visitor-pin-core {
    transform: scale(1.4);
}
.cj-visitor-pin-wrap.is-live .cj-visitor-pin-core {
    background: #10b981;
    box-shadow: 0 0 14px rgba(16, 185, 129, 1);
}
.cj-radar-wave {
    position: absolute;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid #10b981;
    animation: cjRadarWave 2.2s cubic-bezier(0.25, 0, 0.45, 1) infinite;
    pointer-events: none;
    z-index: 1;
}
.cj-radar-wave.wave-2 {
    animation-delay: 1.1s;
}
@keyframes cjRadarWave {
    0% { transform: scale(0.4); opacity: 0.95; }
    100% { transform: scale(2.6); opacity: 0; }
}

/* Animated Traffic Beams Connecting Visitors to Showroom HQ */
.cj-traffic-beam {
    stroke-dasharray: 6, 8;
    animation: cjBeamFlow 1.6s linear infinite;
}
.cj-traffic-beam.is-live {
    animation-duration: 1.1s;
}
@keyframes cjBeamFlow {
    from { stroke-dashoffset: 28; }
    to { stroke-dashoffset: 0; }
}

/* Custom Popup Card Styling */
.cj-leaflet-popup .leaflet-popup-content-wrapper {
    background: rgba(255, 255, 255, 0.96) !important;
    backdrop-filter: blur(10px) !important;
    border-radius: 12px !important;
    border: 1px solid rgba(212, 175, 55, 0.45) !important;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
    padding: 0 !important;
}
html[data-theme="dark"] .cj-leaflet-popup .leaflet-popup-content-wrapper {
    background: rgba(22, 26, 34, 0.96) !important;
    border-color: rgba(212, 175, 55, 0.4) !important;
    color: #f3f4f6 !important;
}
.cj-leaflet-popup .leaflet-popup-content {
    margin: 12px 14px !important;
    font-family: inherit !important;
}
.cj-leaflet-popup .leaflet-popup-tip {
    background: rgba(255, 255, 255, 0.96) !important;
}
html[data-theme="dark"] .cj-leaflet-popup .leaflet-popup-tip {
    background: rgba(22, 26, 34, 0.96) !important;
}
</style>

<section id="visitorAnalytics" class="cj-va-dashboard mb-10 scroll-mt-24 space-y-6">

    <!-- 1. Header & Controls -->
    <div class="cj-va-card p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600">
                    <i class="fas fa-chart-line text-sm"></i>
                </span>
                <h1 class="text-xl sm:text-2xl font-bold font-serif tracking-tight text-gray-900 dark:text-gray-100 m-0">
                    Visitor Intelligence &amp; Analytics
                </h1>
                <span class="inline-flex items-center text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                    <span class="cj-va-radar-dot"></span> <?= (int)$activeNow ?> Live Now
                </span>
            </div>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1 mb-0">
                Automated geographic insights, content engagement leaderboards, and real-time visitor journeys.
            </p>
        </div>

        <div class="flex items-center gap-3 self-start md:self-auto">
            <!-- Timeframe Pills -->
            <div class="cj-va-range-pills" role="tablist" aria-label="Timeframe">
                <a href="<?= htmlspecialchars(cj_va_url('today')) ?>" class="cj-va-range-btn <?= $range === 'today' ? 'is-active' : '' ?>">Today</a>
                <a href="<?= htmlspecialchars(cj_va_url('7d')) ?>" class="cj-va-range-btn <?= $range === '7d' ? 'is-active' : '' ?>">7 Days</a>
                <a href="<?= htmlspecialchars(cj_va_url('30d')) ?>" class="cj-va-range-btn <?= $range === '30d' ? 'is-active' : '' ?>">30 Days</a>
                <a href="<?= htmlspecialchars(cj_va_url('all')) ?>" class="cj-va-range-btn <?= $range === 'all' ? 'is-active' : '' ?>">All Time</a>
            </div>

            <!-- Refresh Button -->
            <a href="<?= htmlspecialchars(cj_va_url($range, $vaPage)) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:border-amber-700/50 dark:text-amber-300 text-xs font-bold hover:bg-amber-100 transition-colors" title="Sync latest data">
                <i class="fas fa-sync-alt text-[10px]"></i> Refresh
            </a>
        </div>
    </div>

    <!-- 2. Automated AI & Smart Takeaway Deck ("Auto Analysis") -->
    <div class="cj-va-insights-grid">
        <!-- Insight 1: Geographic Hub -->
        <div class="cj-va-insight-pill">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                    <i class="fas fa-map-marked-alt"></i> Top Audience Hub
                </span>
                <span class="text-xs font-bold text-gray-500">Geo</span>
            </div>
            <p class="text-xs sm:text-sm font-semibold text-gray-800 dark:text-gray-200 m-0">
                <strong class="text-amber-600 dark:text-amber-400"><?= htmlspecialchars($leadCity) ?></strong> represents 
                <span class="text-gray-900 dark:text-white font-bold"><?= $leadCityPct ?>%</span> of mapped visits, with active traffic from Ahmedabad, Mumbai &amp; Gujarat.
            </p>
        </div>

        <!-- Insight 2: Content Engagement -->
        <div class="cj-va-insight-pill">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                    <i class="fas fa-fire"></i> Most Popular Flow
                </span>
                <span class="text-xs font-bold text-gray-500">Content</span>
            </div>
            <p class="text-xs sm:text-sm font-semibold text-gray-800 dark:text-gray-200 m-0">
                <strong class="text-amber-600 dark:text-amber-400">Gallery Collection &amp; Rates</strong> drive the highest engagement. Visitors average 
                <span class="text-gray-900 dark:text-white font-bold"><?= $pagesPerVisit ?> pages</span> per session.
            </p>
        </div>

        <!-- Insight 3: Star Jewellery Reel -->
        <div class="cj-va-insight-pill">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                    <i class="fas fa-play-circle"></i> Trending Reel
                </span>
                <span class="text-xs font-bold text-gray-500">Video</span>
            </div>
            <p class="text-xs sm:text-sm font-semibold text-gray-800 dark:text-gray-200 m-0">
                <?php if ($topReel): ?>
                    <strong class="text-amber-600 dark:text-amber-400">Reel #<?= (int)$topReel['id'] ?></strong> leads all video views with 
                    <span class="text-gray-900 dark:text-white font-bold"><?= (int)$topReel['display_views'] ?> plays</span> &amp; <?= (int)$topReel['likes_count'] ?> likes.
                <?php else: ?>
                    Reels engagement tracking ready. Watch sessions recorded automatically.
                <?php endif; ?>
            </p>
        </div>

        <!-- Insight 4: Peak Browsing & Mobile Share -->
        <div class="cj-va-insight-pill">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                    <i class="fas fa-clock"></i> Peak Time &amp; Device
                </span>
                <span class="text-xs font-bold text-gray-500">Velocity</span>
            </div>
            <p class="text-xs sm:text-sm font-semibold text-gray-800 dark:text-gray-200 m-0">
                Peak visitor activity occurs around <strong class="text-amber-600 dark:text-amber-400"><?= htmlspecialchars($peakHourFormatted) ?></strong>. 
                <span class="text-gray-900 dark:text-white font-bold"><?= $mobilePct ?>%</span> of customers browse from mobile phones.
            </p>
        </div>
    </div>

    <!-- 3. Core KPI Numbers -->
    <div class="cj-va-kpi-grid">
        <div class="cj-va-kpi-box">
            <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Active Now</span>
            <div class="cj-va-kpi-num text-emerald-600 dark:text-emerald-400 mt-1"><?= (int)$activeNow ?></div>
            <span class="text-[11px] text-gray-500">Active in last 5 min</span>
        </div>
        <div class="cj-va-kpi-box">
            <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Unique Visitors</span>
            <div class="cj-va-kpi-num mt-1"><?= number_format($totalVisitors) ?></div>
            <span class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold"><?= htmlspecialchars($rangeTitle) ?></span>
        </div>
        <div class="cj-va-kpi-box">
            <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Total Page Views</span>
            <div class="cj-va-kpi-num mt-1"><?= number_format($totalPageviews) ?></div>
            <span class="text-[11px] text-gray-500">Avg <?= $pagesPerVisit ?> pages / visit</span>
        </div>
        <div class="cj-va-kpi-box">
            <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Avg Session Time</span>
            <div class="cj-va-kpi-num mt-1"><?= cj_va_format_duration($avgDurationSec) ?></div>
            <span class="text-[11px] <?= $avgDurationSec > 90 ? 'text-emerald-600' : 'text-gray-500' ?> font-semibold">
                <?= $avgDurationSec > 90 ? '⚡ High Engagement' : 'Normal Browsing' ?>
            </span>
        </div>
        <div class="cj-va-kpi-box">
            <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">GPS Geo Shared</span>
            <div class="cj-va-kpi-num mt-1"><?= (int)$withGeo ?></div>
            <span class="text-[11px] text-gray-500"><?= $geoRate ?>% allowed location</span>
        </div>
    </div>

    <!-- 4. Hero Interactive Visitor Radar Map (Front & Center Experience) -->
    <div class="cj-va-card p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-base sm:text-lg font-bold font-serif m-0 flex items-center gap-2 text-gray-900 dark:text-gray-100">
                    <i class="fas fa-satellite-dish text-amber-500 animate-pulse"></i> Live Audience Radar &amp; Visitor Map
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 m-0 mt-0.5">
                    Real-time visual map tracking where customers are watching from. Animated radar pulses indicate active sessions, and light-beams trace traffic flow to ChandraKala Showroom HQ.
                </p>
            </div>
            <div class="text-xs font-semibold text-gray-500 flex items-center gap-3 self-start sm:self-auto flex-wrap">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Active (Last 10m)</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Past Visitors</span>
                <span class="flex items-center gap-1.5"><span class="text-amber-500">👑</span> Showroom HQ</span>
            </div>
        </div>

        <div class="cj-va-map-card">
            <!-- Floating Heads-Up Display (HUD) -->
            <div class="cj-map-hud">
                <div class="flex items-center gap-2">
                    <span class="cj-va-radar-dot"></span>
                    <span class="font-bold text-xs tracking-wider text-amber-900 dark:text-amber-300">LIVE TRAFFIC RADAR</span>
                </div>
                <div class="text-[11px] text-gray-600 dark:text-gray-300 mt-0.5">
                    <strong><?= count($mapPoints) ?></strong> Geotagged Visitors &middot; <strong><?= (int)$activeNow ?></strong> Active Now
                </div>
            </div>

            <!-- Quick Flight & Camera Controls -->
            <div class="cj-va-map-actions">
                <button type="button" class="cj-va-map-btn" id="cjZoomShowroom" title="Fly to Flagship Showroom HQ">
                    <i class="fas fa-crown text-amber-500"></i> Showroom HQ
                </button>
                <button type="button" class="cj-va-map-btn" id="cjZoomGujarat" title="Focus on Gujarat Audience">
                    <i class="fas fa-map-marker-alt text-amber-600"></i> Gujarat
                </button>
                <button type="button" class="cj-va-map-btn" id="cjZoomIndia" title="View Pan-India Visitors">
                    <i class="fas fa-globe-asia text-blue-500"></i> All India
                </button>
                <button type="button" class="cj-va-map-btn" id="cjZoomFit" title="Fit All Visitor Pins">
                    <i class="fas fa-expand text-emerald-600"></i> Fit All
                </button>
                <button type="button" class="cj-va-map-btn" id="cjToggleBeams" title="Toggle Animated Traffic Beams">
                    <i class="fas fa-wave-square text-amber-500"></i> Flow Beams
                </button>
            </div>

            <!-- Map Container -->
            <div id="adminVisitorMap" aria-label="Interactive Visitor Locations Map"></div>
        </div>

        <!-- Quick City Flight Chips -->
        <div class="flex items-center gap-2 flex-wrap pt-3.5 mt-2 border-t border-gray-100 dark:border-gray-800">
            <span class="font-bold text-gray-500 text-[11px] uppercase tracking-wider flex items-center gap-1.5 mr-1">
                <i class="fas fa-paper-plane text-amber-500"></i> Quick Fly:
            </span>
            <button type="button" class="cj-city-pill" data-lat="24.0314" data-lng="73.0464" data-city="Showroom HQ (Khedbrahma)">
                <span>👑 Showroom HQ</span>
            </button>
            <?php foreach ($mapCityChips as $chip): ?>
                <button type="button" class="cj-city-pill" data-lat="<?= $chip['lat'] ?>" data-lng="<?= $chip['lng'] ?>" data-city="<?= htmlspecialchars($chip['name']) ?>">
                    <span><?= htmlspecialchars($chip['name']) ?></span>
                    <span class="pill-badge"><?= (int)$chip['count'] ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Raw map points JSON -->
        <script type="application/json" id="adminVisitorMapData"><?= json_encode(array_map(static function ($p) {
            $isLive = strtotime((string)$p['last_seen_at']) >= time() - 600;
            return [
                'lat' => (float)$p['lat'],
                'lng' => (float)$p['lng'],
                'city' => trim((string)($p['geo_city'] ?? '')),
                'label' => $p['geo_label'] ?: trim(($p['geo_city'] ?? '') . ' ' . ($p['geo_country'] ?? '')),
                'duration' => (int)$p['duration_seconds'],
                'pages' => (int)$p['page_count'],
                'landing' => $p['landing_page'],
                'when' => date('d M, h:i A', strtotime((string)$p['last_seen_at'])),
                'live' => $isLive,
            ];
        }, $mapPoints), JSON_UNESCAPED_UNICODE) ?></script>
    </div>

    <!-- 5. Charts Row: Traffic Trend & Top Cities -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left: Traffic Velocity Curve (7 Cols) -->
        <div class="lg:col-span-7 cj-va-card p-5 sm:p-6 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold font-serif m-0 flex items-center gap-2">
                        <i class="fas fa-chart-area text-amber-500"></i> Traffic &amp; Pageviews Trend
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 m-0 mt-0.5">
                        Interactive volume curve for <?= htmlspecialchars($rangeTitle) ?>
                    </p>
                </div>
                <div class="flex items-center gap-3 text-xs font-semibold">
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Visitors</span>
                    <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span> Pageviews</span>
                </div>
            </div>

            <div class="relative w-full h-[260px] sm:h-[280px]">
                <canvas id="cjVaTrafficChart"></canvas>
            </div>
        </div>

        <!-- Right: Top Cities Bar Graph & Share (5 Cols) -->
        <div class="lg:col-span-5 cj-va-card p-5 sm:p-6 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold font-serif m-0 flex items-center gap-2">
                        <i class="fas fa-city text-amber-500"></i> Audience Cities &amp; Hubs
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 m-0 mt-0.5">Top visitor concentrations</p>
                </div>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300">
                    <?= count($topCities) ?> Locations
                </span>
            </div>

            <?php if (!$topCities): ?>
                <div class="py-12 text-center text-xs text-gray-400">No geographic session data recorded for this period.</div>
            <?php else: ?>
                <div class="space-y-3.5 my-auto">
                    <?php 
                    $maxCityCnt = max(1, (int)($topCities[0]['cnt'] ?? 1));
                    foreach ($topCities as $cIdx => $tc): 
                        $cnt = (int)$tc['cnt'];
                        $pct = round(($cnt / max(1, $totalVisitors)) * 100);
                        $barPct = max(10, round(($cnt / $maxCityCnt) * 100));
                        $isLeader = ($cIdx === 0 && $tc['city'] !== 'Other / Location Not Shared');
                    ?>
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-semibold text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
                                <?php if ($isLeader): ?>
                                    <i class="fas fa-crown text-amber-500 text-[11px]" title="Top Location"></i>
                                <?php else: ?>
                                    <i class="fas fa-map-pin text-gray-400 text-[10px]"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($tc['city']) ?>
                            </span>
                            <span class="text-gray-500 font-mono text-[11px]">
                                <strong><?= $cnt ?></strong> visits (<?= $pct ?>%)
                            </span>
                        </div>
                        <div class="cj-va-bar-track">
                            <div class="cj-va-bar-fill <?= $isLeader ? '' : 'opacity-70' ?>" style="width: <?= $barPct ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 5. Content Leaderboards: Most Viewed Pages & Top Video Reels -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Most Viewed Pages & Sections (7 Cols) -->
        <div class="lg:col-span-7 cj-va-card p-5 sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold font-serif m-0 flex items-center gap-2">
                        <i class="fas fa-columns text-amber-500"></i> Top Viewed Pages &amp; Sections
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 m-0 mt-0.5">Most frequented paths on chandrakalajewellers.in</p>
                </div>
                <span class="text-xs font-bold text-gray-400">Views &amp; Avg Time</span>
            </div>

            <?php if (!$topPages): ?>
                <div class="py-12 text-center text-xs text-gray-400">No page views recorded in this period.</div>
            <?php else: ?>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    <?php foreach ($topPages as $pIdx => $tp): 
                        $views = (int)$tp['views'];
                        $pct = max(8, round(($views / $maxPageViews) * 100));
                        $path = (string)$tp['page_path'];
                        $friendly = cj_va_page_title($path);
                    ?>
                    <div class="py-2.5 first:pt-0 last:pb-0">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <div class="min-w-0 flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 font-bold text-[10px] flex items-center justify-center shrink-0">
                                    <?= $pIdx + 1 ?>
                                </span>
                                <div class="truncate">
                                    <strong class="text-gray-900 dark:text-gray-100 block truncate"><?= htmlspecialchars($friendly) ?></strong>
                                    <span class="text-[11px] text-gray-400 font-mono"><?= htmlspecialchars($path) ?></span>
                                </div>
                            </div>
                            <div class="text-right shrink-0 ml-3">
                                <strong class="text-gray-900 dark:text-gray-100"><?= number_format($views) ?> views</strong>
                                <span class="block text-[10px] text-gray-400"><?= cj_va_format_duration((int)$tp['avg_sec']) ?> avg</span>
                            </div>
                        </div>
                        <div class="cj-va-bar-track mt-1.5">
                            <div class="cj-va-bar-fill" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Top Video Reels & Devices (5 Cols) -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Top Video Reels -->
            <div class="cj-va-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-3.5">
                    <div>
                        <h3 class="text-base font-bold font-serif m-0 flex items-center gap-2">
                            <i class="fas fa-video text-amber-500"></i> Top Jewellery Reels
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 m-0 mt-0.5">Most watched video reels</p>
                    </div>
                    <a href="/reels" target="_blank" rel="noopener" class="text-xs font-bold text-amber-600 hover:underline">
                        View Feed &rarr;
                    </a>
                </div>

                <?php if (!$rankedReels): ?>
                    <p class="text-xs text-gray-400 py-6 text-center">No active reels published.</p>
                <?php else: ?>
                    <div class="space-y-2.5">
                        <?php foreach (array_slice($rankedReels, 0, 4) as $rIdx => $rr): 
                            $rid = (int)$rr['id'];
                            $views = (int)$rr['display_views'];
                            $likes = (int)$rr['likes_count'];
                            $thumb = (string)($rr['poster_thumb'] ?: '/images/logo.png');
                        ?>
                        <div class="flex items-center gap-3 p-2 rounded-xl bg-gray-50/70 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
                            <div class="w-10 h-14 rounded-lg overflow-hidden bg-black shrink-0 relative border border-amber-200/40">
                                <img src="<?= htmlspecialchars($thumb) ?>" alt="Reel #<?= $rid ?>" class="w-full h-full object-cover">
                                <span class="absolute bottom-0 right-0 bg-black/80 text-[9px] text-amber-300 font-bold px-1 rounded-tl">#<?= $rid ?></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h4 class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate m-0">
                                    Jewellery Reel #<?= $rid ?>
                                </h4>
                                <div class="flex items-center gap-3 text-[11px] text-gray-500 mt-1 font-mono">
                                    <span><i class="fas fa-eye text-amber-500 mr-1"></i><?= $views ?></span>
                                    <span><i class="fas fa-heart text-rose-500 mr-1"></i><?= $likes ?></span>
                                    <span><i class="fas fa-comment text-sky-500 mr-1"></i><?= (int)$rr['comments_count'] ?></span>
                                </div>
                            </div>
                            <a href="/showcase?v=<?= $rid ?>" target="_blank" rel="noopener" class="px-2.5 py-1 text-[11px] font-bold rounded-lg border border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:border-amber-700 dark:text-amber-300 hover:bg-amber-100 transition-colors shrink-0" title="Watch standalone showcase">
                                Watch
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Device Distribution Donut -->
            <div class="cj-va-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-bold font-serif m-0 flex items-center gap-2">
                        <i class="fas fa-mobile-alt text-amber-500"></i> Device Platforms
                    </h3>
                    <span class="text-xs font-mono text-gray-400"><?= $totalDeviceCount ?> parsed</span>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center pt-2">
                    <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
                        <i class="fas fa-mobile-alt text-amber-500 text-lg mb-1 block"></i>
                        <strong class="text-sm block text-gray-900 dark:text-white"><?= (int)$devices['Mobile'] ?></strong>
                        <span class="text-[10px] text-gray-400">Mobile Phone</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
                        <i class="fas fa-laptop text-slate-500 text-lg mb-1 block"></i>
                        <strong class="text-sm block text-gray-900 dark:text-white"><?= (int)$devices['Desktop'] ?></strong>
                        <span class="text-[10px] text-gray-400">Desktop / PC</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
                        <i class="fas fa-tablet-alt text-teal-500 text-lg mb-1 block"></i>
                        <strong class="text-sm block text-gray-900 dark:text-white"><?= (int)$devices['Tablet'] ?></strong>
                        <span class="text-[10px] text-gray-400">Tablet / iPad</span>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- 7. Real-Time Visitor Session Journeys (Paginated Log) -->
    <div class="cj-va-card p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
            <div>
                <h3 class="text-base font-bold font-serif m-0 flex items-center gap-2">
                    <i class="fas fa-history text-amber-500"></i> Real-Time Visitor Session Journeys
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 m-0 mt-0.5">
                    Showing <strong><?= (int)$vaFrom ?>–<?= (int)$vaTo ?></strong> of <strong><?= (int)$vaTotal ?></strong> sessions · Page <?= (int)$vaPage ?> of <?= (int)$vaTotalPages ?>
                </p>
            </div>
            <span class="text-xs text-gray-400"><?= (int)$vaPerPage ?> per page</span>
        </div>

        <?php if (!$recentSessions): ?>
            <p class="text-xs text-gray-400 py-10 text-center">No visitor sessions recorded yet.</p>
        <?php else: ?>
            <div class="space-y-2.5" id="vaLogList">
                <?php foreach ($recentSessions as $i => $s):
                    $pages = $pagesBySession[$s['session_key']] ?? [];
                    $isLive = strtotime((string)$s['last_seen_at']) >= time() - 300;
                    $loc = trim((string)($s['geo_label'] ?: ''));
                    if ($loc === '') {
                        $loc = trim(implode(', ', array_filter([(string)($s['geo_city'] ?? ''), (string)($s['geo_region'] ?? ''), (string)($s['geo_country'] ?? '')])));
                    }
                    if ($loc === '') {
                        if (($s['geo_status'] ?? '') === 'granted' && !empty($s['lat'])) {
                            $loc = round((float)$s['lat'], 4) . ', ' . round((float)$s['lng'], 4);
                        } elseif (($s['geo_status'] ?? '') === 'denied') {
                            $loc = 'Location Denied';
                        } elseif (($s['geo_status'] ?? '') === 'dismissed') {
                            $loc = 'Location Skipped';
                        } else {
                            $loc = 'Location Not Shared';
                        }
                    }

                    $ua = (string)($s['user_agent'] ?? '');
                    $isMobile = preg_match('/mobile|android|iphone|ipod/i', $ua);
                    $devLabel = $isMobile ? 'Mobile' : 'Desktop';
                    $devIcon = $isMobile ? 'fa-mobile-alt text-amber-500' : 'fa-laptop text-slate-500';

                    $landing = trim((string)($s['landing_page'] ?? ''));
                    if ($landing === '' && !empty($pages[0]['page_path'])) {
                        $landing = (string)$pages[0]['page_path'];
                    }
                    $landingTitle = cj_va_page_title($landing);
                    $open = ($i === 0 && $vaPage === 1);
                ?>
                <article class="border border-gray-100 dark:border-gray-800 rounded-xl overflow-hidden bg-gray-50/50 dark:bg-gray-850 transition-all <?= $open ? 'ring-1 ring-amber-300 dark:ring-amber-700/50' : '' ?>">
                    <button type="button" class="va-log-toggle w-full p-3.5 flex items-center justify-between text-left cursor-pointer bg-transparent border-0" aria-expanded="<?= $open ? 'true' : 'false' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 <?= $isLive ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600' ?>"></span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <strong class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate"><?= htmlspecialchars($loc) ?></strong>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 shrink-0">
                                        <i class="fas <?= $devIcon ?>"></i> <?= $devLabel ?>
                                    </span>
                                </div>
                                <span class="text-[11px] text-gray-500 block truncate mt-0.5">
                                    <?= cj_va_format_duration((int)$s['duration_seconds']) ?> · <?= (int)$s['page_count'] ?> page<?= ((int)$s['page_count'] === 1) ? '' : 's' ?>
                                    <?php if (!empty($s['referrer'])): ?>
                                        · via <?= htmlspecialchars(parse_url($s['referrer'], PHP_URL_HOST) ?: $s['referrer']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0 ml-3">
                            <span class="text-[11px] font-mono text-gray-400"><?= htmlspecialchars(date('d M, h:i A', strtotime((string)$s['last_seen_at']))) ?></span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform duration-200 <?= $open ? 'rotate-180' : '' ?>"></i>
                        </div>
                    </button>

                    <div class="va-log-body p-4 border-t border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 <?= $open ? '' : 'hidden' ?>">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs mb-3.5 pb-3 border-b border-gray-100 dark:border-gray-800">
                            <div>
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide block">First Landing</span>
                                <strong class="text-gray-900 dark:text-gray-100 truncate block mt-0.5" title="<?= htmlspecialchars($landing) ?>">
                                    <?= htmlspecialchars($landingTitle) ?>
                                </strong>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide block">Session Duration</span>
                                <strong class="text-gray-900 dark:text-gray-100 block mt-0.5"><?= cj_va_format_duration((int)$s['duration_seconds']) ?></strong>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide block">Geo Status</span>
                                <strong class="text-gray-900 dark:text-gray-100 block mt-0.5"><?= htmlspecialchars((string)($s['geo_status'] ?: 'unknown')) ?></strong>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide block">Started / Ended</span>
                                <strong class="text-gray-900 dark:text-gray-100 block mt-0.5 font-mono">
                                    <?= htmlspecialchars(date('H:i:s', strtotime((string)$s['started_at']))) ?> &rarr; <?= htmlspecialchars(date('H:i:s', strtotime((string)$s['last_seen_at']))) ?>
                                </strong>
                            </div>
                        </div>

                        <?php if ($pages): ?>
                            <span class="text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider block mb-2">
                                <i class="fas fa-shoe-prints mr-1"></i> Chronological Page Journey (<?= count($pages) ?> steps)
                            </span>
                            <div class="space-y-1.5 pl-3 border-l-2 border-amber-300 dark:border-amber-600">
                                <?php foreach ($pages as $pIdx => $pv): ?>
                                    <div class="flex items-center justify-between text-xs py-0.5">
                                        <div class="truncate mr-3">
                                            <span class="text-gray-400 font-mono text-[10px] mr-1.5">#<?= $pIdx + 1 ?></span>
                                            <strong class="text-gray-800 dark:text-gray-200"><?= htmlspecialchars(cj_va_page_title((string)$pv['page_path'])) ?></strong>
                                            <span class="text-[10px] text-gray-400 font-mono ml-1"><?= htmlspecialchars((string)$pv['page_path']) ?></span>
                                        </div>
                                        <div class="text-right shrink-0 font-mono text-[11px] text-gray-500">
                                            <?= cj_va_format_duration((int)$pv['seconds_on_page']) ?>
                                            <span class="text-[10px] text-gray-400 ml-1"><?= htmlspecialchars(date('H:i:s', strtotime((string)$pv['viewed_at']))) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-xs text-gray-400 m-0">Single page visit or background ping.</p>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($vaTotalPages > 1): ?>
                <div class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-800 mt-4 text-xs">
                    <a class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 font-semibold <?= $vaPage <= 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-gray-100 dark:hover:bg-gray-800' ?>" 
                       href="<?= htmlspecialchars(cj_va_url($range, $vaPage - 1)) ?>">
                        &larr; Previous
                    </a>

                    <div class="flex items-center gap-1 font-semibold">
                        <?php
                        $start = max(1, $vaPage - 2);
                        $end = min($vaTotalPages, $vaPage + 2);
                        for ($p = $start; $p <= $end; $p++):
                        ?>
                            <a href="<?= htmlspecialchars(cj_va_url($range, $p)) ?>" 
                               class="w-8 h-8 rounded-lg flex items-center justify-center <?= $p === $vaPage ? 'bg-amber-500 text-white font-bold' : 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-300' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <a class="px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 font-semibold <?= $vaPage >= $vaTotalPages ? 'opacity-40 pointer-events-none' : 'hover:bg-gray-100 dark:hover:bg-gray-800' ?>" 
                       href="<?= htmlspecialchars(cj_va_url($range, $vaPage + 1)) ?>">
                        Next &rarr;
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</section>

<!-- CDN Assets: Leaflet CSS/JS & Chart.js -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<script>
(function initVisitorIntelligence() {
    'use strict';

    // -------------------------------------------------------------------------
    // 1. Chart.js Traffic Area Curve
    // -------------------------------------------------------------------------
    var canvas = document.getElementById('cjVaTrafficChart');
    if (canvas && window.Chart) {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.04)';
        var textColor = isDark ? '#9ca3af' : '#6b7280';

        var labels = <?= json_encode($chartLabels) ?>;
        var visData = <?= json_encode($chartVisitors) ?>;
        var viewData = <?= json_encode($chartPageviews) ?>;

        var ctx = canvas.getContext('2d');
        var goldGrad = ctx.createLinearGradient(0, 0, 0, 260);
        goldGrad.addColorStop(0, isDark ? 'rgba(212, 175, 55, 0.45)' : 'rgba(212, 175, 55, 0.32)');
        goldGrad.addColorStop(1, isDark ? 'rgba(212, 175, 55, 0.01)' : 'rgba(212, 175, 55, 0.02)');

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Unique Visitors',
                        data: visData,
                        borderColor: '#d4af37',
                        borderWidth: 2.5,
                        backgroundColor: goldGrad,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#d4af37',
                        pointBorderColor: '#ffffff',
                    },
                    {
                        label: 'Total Pageviews',
                        data: viewData,
                        borderColor: '#94a3b8',
                        borderWidth: 1.8,
                        borderDash: [4, 4],
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#94a3b8',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 900, easing: 'easeOutQuart' },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? 'rgba(17, 24, 39, 0.95)' : 'rgba(0, 0, 0, 0.85)',
                        titleColor: '#f9fafb',
                        bodyColor: '#e5e7eb',
                        padding: 10,
                        cornerRadius: 8,
                        borderColor: 'rgba(212, 175, 55, 0.4)',
                        borderWidth: 1,
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor, font: { size: 11, family: 'system-ui' } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { color: textColor, precision: 0, font: { size: 11, family: 'system-ui' } }
                    }
                }
            }
        });
    }

    // -------------------------------------------------------------------------
    // 2. Interactive Live Visitor Map with Radar Waves, Flow Beams & Flying Camera
    // -------------------------------------------------------------------------
    var mapEl = document.getElementById('adminVisitorMap');
    var mapDataEl = document.getElementById('adminVisitorMapData');

    function initMap() {
        if (!mapEl || !mapDataEl || typeof L === 'undefined') return;
        window.__CJ_MAP_INITIALIZED__ = true;

        var points = [];
        try { points = JSON.parse(mapDataEl.textContent || '[]'); } catch (e) { points = []; }

        // Setup Leaflet map instance
        var map = L.map(mapEl, {
            scrollWheelZoom: false,
            zoomControl: true
        }).setView([23.2, 72.8], 7);

        // Cartography tile layer powered by CARTO (Voyager for Light, Dark Matter for Dark)
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        var tileUrl = isDark 
            ? 'https://{s}.basemaps.cartocdn.com/rastertiles/dark_all/{z}/{x}/{y}{r}.png'
            : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png';

        L.tileLayer(tileUrl, {
            subdomains: 'abcd',
            maxZoom: 19,
            attribution: '&copy; <a href="https://carto.com/" target="_blank" rel="noopener">CARTO</a> &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'
        }).addTo(map);

        // Responsive resize invalidate
        setTimeout(function () { map.invalidateSize(true); }, 150);
        setTimeout(function () { map.invalidateSize(true); }, 400);
        setTimeout(function () { map.invalidateSize(true); }, 1000);
        window.addEventListener('resize', function () { map.invalidateSize(true); });

        // Showroom HQ Coordinates (Civil Road, Khedbrahma)
        var hqCoords = [24.0314, 73.0464];

        // 1. ChandraKala Jewellers HQ Crown Pin
        var hqIcon = L.divIcon({
            className: 'cj-hq-leaflet-marker',
            html: '<div class="cj-hq-marker-wrap">' +
                  '  <div class="cj-hq-halo"></div>' +
                  '  <div class="cj-hq-core"><i class="fas fa-crown"></i></div>' +
                  '  <div class="cj-hq-tag">ChandraKala HQ</div>' +
                  '</div>',
            iconSize: [44, 44],
            iconAnchor: [22, 22]
        });

        var hqMarker = L.marker(hqCoords, { icon: hqIcon, zIndexOffset: 1000 }).addTo(map);
        hqMarker.bindPopup(
            '<div style="font-size:12px;line-height:1.5;">' +
            '<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">' +
            '  <span style="font-size:15px;">👑</span><strong style="color:#b8972c;font-size:14px;font-family:serif;">ChandraKala Jewellers</strong>' +
            '</div>' +
            '<div style="color:#6b7280;font-size:11px;">Civil Road, Khedbrahma, Sabarkantha, Gujarat</div>' +
            '<div style="margin-top:6px;padding:4px 8px;background:rgba(212,175,55,0.15);border-radius:6px;font-size:11px;font-weight:600;color:#92400e;">Flagship Showroom &amp; Experience Studio</div>' +
            '</div>',
            { className: 'cj-leaflet-popup' }
        );

        // 2. Add Visitor Pins & Animated Traffic Flow Beams
        var visitorMarkers = [];
        var bounds = [hqCoords];
        var beamsLayer = L.layerGroup().addTo(map);
        var showBeams = true;

        points.forEach(function (p) {
            if (!p.lat || !p.lng) return;
            var latLng = [p.lat, p.lng];
            bounds.push(latLng);

            // Custom Animated Pin (Emerald Radar for Live, Gold for Past)
            var iconHtml = '<div class="cj-visitor-pin-wrap' + (p.live ? ' is-live' : '') + '" title="' + (p.label || 'Visitor') + '">' +
                           (p.live ? '<div class="cj-radar-wave"></div><div class="cj-radar-wave wave-2"></div>' : '') +
                           '  <div class="cj-visitor-pin-core"></div>' +
                           '</div>';

            var vIcon = L.divIcon({
                className: 'cj-visitor-leaflet-icon',
                html: iconHtml,
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            var vm = L.marker(latLng, { icon: vIcon }).addTo(map);
            var mins = Math.round((p.duration || 0) / 60);

            vm.bindPopup(
                '<div style="font-size:12px;line-height:1.45;">' +
                '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px;">' +
                '  <strong style="color:#b8972c;font-size:13px;"><i class="fas fa-map-pin mr-1"></i> ' + (p.label || 'Visitor') + '</strong>' +
                (p.live ? '  <span style="background:#10b981;color:#fff;font-size:9px;padding:2px 6px;border-radius:999px;font-weight:700;">LIVE</span>' : '') +
                '</div>' +
                '<div style="color:#4b5563;font-size:11px;margin-bottom:2px;">' +
                '  Duration: <strong>' + mins + ' min</strong> · <strong>' + (p.pages || 0) + ' pages viewed</strong>' +
                '</div>' +
                '<div style="color:#6b7280;font-size:11px;margin-bottom:2px;">Landed: <code>' + (p.landing || '/') + '</code></div>' +
                '<div style="color:#9ca3af;font-size:10px;">Last seen: ' + (p.when || 'Recently') + '</div>' +
                '</div>',
                { className: 'cj-leaflet-popup' }
            );

            vm._city = (p.city || p.label || '').toLowerCase();
            visitorMarkers.push(vm);

            // Animated Laser/Beam flowing from Visitor's location into ChandraKala Showroom HQ
            if (Math.abs(p.lat - hqCoords[0]) > 0.005 || Math.abs(p.lng - hqCoords[1]) > 0.005) {
                var beam = L.polyline([latLng, hqCoords], {
                    color: p.live ? '#10b981' : '#d4af37',
                    weight: p.live ? 2.5 : 1.5,
                    opacity: p.live ? 0.8 : 0.45,
                    dashArray: '6, 8',
                    className: 'cj-traffic-beam' + (p.live ? ' is-live' : '')
                });
                beamsLayer.addLayer(beam);
            }
        });

        // Smart Zoom Bounds
        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 });
        } else {
            map.setView(hqCoords, 10);
        }

        // Camera Preset Actions
        var btnShowroom = document.getElementById('cjZoomShowroom');
        if (btnShowroom) {
            btnShowroom.addEventListener('click', function () {
                map.flyTo(hqCoords, 14, { duration: 1.5 });
                setTimeout(function () { hqMarker.openPopup(); }, 1600);
            });
        }

        var btnGuj = document.getElementById('cjZoomGujarat');
        if (btnGuj) {
            btnGuj.addEventListener('click', function () {
                map.flyTo([22.8, 71.8], 7.5, { duration: 1.3 });
            });
        }

        var btnInd = document.getElementById('cjZoomIndia');
        if (btnInd) {
            btnInd.addEventListener('click', function () {
                map.flyTo([22.5, 78.5], 5, { duration: 1.3 });
            });
        }

        var btnFit = document.getElementById('cjZoomFit');
        if (btnFit) {
            btnFit.addEventListener('click', function () {
                if (bounds.length > 1) {
                    map.flyToBounds(bounds, { padding: [40, 40], maxZoom: 12, duration: 1.3 });
                } else {
                    map.flyTo(hqCoords, 10, { duration: 1.3 });
                }
            });
        }

        var btnBeams = document.getElementById('cjToggleBeams');
        if (btnBeams) {
            btnBeams.addEventListener('click', function () {
                showBeams = !showBeams;
                if (showBeams) {
                    map.addLayer(beamsLayer);
                    btnBeams.classList.remove('opacity-50');
                } else {
                    map.removeLayer(beamsLayer);
                    btnBeams.classList.add('opacity-50');
                }
            });
        }

        // Quick City Flight Chips
        document.querySelectorAll('.cj-city-pill').forEach(function (pill) {
            pill.addEventListener('click', function () {
                var lat = parseFloat(this.getAttribute('data-lat'));
                var lng = parseFloat(this.getAttribute('data-lng'));
                var city = (this.getAttribute('data-city') || '').toLowerCase();

                if (!isNaN(lat) && !isNaN(lng)) {
                    if (city.indexOf('hq') !== -1 || (Math.abs(lat - hqCoords[0]) < 0.001 && Math.abs(lng - hqCoords[1]) < 0.001)) {
                        map.flyTo(hqCoords, 14, { duration: 1.5 });
                        setTimeout(function () { hqMarker.openPopup(); }, 1600);
                        return;
                    }

                    map.flyTo([lat, lng], 12, { duration: 1.4 });
                    var targetMarker = visitorMarkers.find(function (m) {
                        var pos = m.getLatLng();
                        return Math.abs(pos.lat - lat) < 0.01 && Math.abs(pos.lng - lng) < 0.01;
                    });
                    if (targetMarker) {
                        setTimeout(function () { targetMarker.openPopup(); }, 1500);
                    }
                }
            });
        });
    }

    if (typeof L !== 'undefined') {
        initMap();
    } else {
        window.addEventListener('load', initMap);
    }

    // -------------------------------------------------------------------------
    // 3. Visitor Session Accordion Toggle
    // -------------------------------------------------------------------------
    var logList = document.getElementById('vaLogList');
    if (logList) {
        logList.addEventListener('click', function (e) {
            var btn = e.target.closest('.va-log-toggle');
            if (!btn || !logList.contains(btn)) return;
            var article = btn.closest('article');
            if (!article) return;
            var body = article.querySelector('.va-log-body');
            var icon = btn.querySelector('.fa-chevron-down');
            if (!body) return;

            var isHidden = body.classList.toggle('hidden');
            btn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
            article.classList.toggle('ring-1', !isHidden);
            article.classList.toggle('ring-amber-300', !isHidden);
            if (icon) icon.classList.toggle('rotate-180', !isHidden);
        });
    }

})();
</script>
