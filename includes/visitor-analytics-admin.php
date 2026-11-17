<?php
/**
 * Admin visitor analytics block (include near bottom of admin dashboard).
 * Expects $conn (mysqli).
 */
require_once __DIR__ . '/visitor-analytics-schema.php';
cj_ensure_visitor_analytics_schema($conn);

$active_cut = date('Y-m-d H:i:s', time() - 5 * 60);
$today_start = date('Y-m-d 00:00:00');

$active_now = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM visitor_sessions WHERE last_seen_at >= '{$active_cut}'");
if ($r) {
    $active_now = (int)($r->fetch_assoc()['c'] ?? 0);
}

$today_visitors = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM visitor_sessions WHERE started_at >= '{$today_start}'");
if ($r) {
    $today_visitors = (int)($r->fetch_assoc()['c'] ?? 0);
}

$avg_minutes = 0;
$r = $conn->query("SELECT AVG(duration_seconds) AS a FROM visitor_sessions WHERE started_at >= '{$today_start}' AND duration_seconds > 0");
if ($r) {
    $avg_minutes = round(((float)($r->fetch_assoc()['a'] ?? 0)) / 60, 1);
}

$today_pageviews = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM visitor_pageviews WHERE viewed_at >= '{$today_start}'");
if ($r) {
    $today_pageviews = (int)($r->fetch_assoc()['c'] ?? 0);
}

$with_geo = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM visitor_sessions WHERE lat IS NOT NULL AND started_at >= '{$today_start}'");
if ($r) {
    $with_geo = (int)($r->fetch_assoc()['c'] ?? 0);
}

$top_pages = [];
$r = $conn->query("SELECT page_path, COUNT(*) AS views, ROUND(AVG(seconds_on_page)/60, 1) AS avg_min
    FROM visitor_pageviews
    WHERE viewed_at >= '{$today_start}' AND page_path IS NOT NULL AND page_path != ''
    GROUP BY page_path
    ORDER BY views DESC
    LIMIT 8");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $top_pages[] = $row;
    }
}
$max_views = 1;
foreach ($top_pages as $tp) {
    $max_views = max($max_views, (int)$tp['views']);
}

$map_points = [];
$r = $conn->query("SELECT session_key, lat, lng, geo_label, geo_city, geo_region, geo_country, duration_seconds, page_count, landing_page, last_seen_at
    FROM visitor_sessions
    WHERE lat IS NOT NULL AND lng IS NOT NULL
    ORDER BY last_seen_at DESC
    LIMIT 120");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $map_points[] = $row;
    }
}

// ---- Clear visitor-log pagination (separate from product ?page=) ----
$va_per_page = 10;
$va_total = 0;
$r = $conn->query('SELECT COUNT(*) AS c FROM visitor_sessions');
if ($r) {
    $va_total = (int)($r->fetch_assoc()['c'] ?? 0);
}
$va_total_pages = max(1, (int)ceil($va_total / $va_per_page));
$va_page = max(1, (int)($_GET['va_page'] ?? 1));
if ($va_page > $va_total_pages) {
    $va_page = $va_total_pages;
}
$va_offset = ($va_page - 1) * $va_per_page;
$va_from = $va_total === 0 ? 0 : ($va_offset + 1);
$va_to = min($va_total, $va_offset + $va_per_page);

$recent = [];
$r = $conn->query("SELECT session_key, started_at, last_seen_at, duration_seconds, page_count, landing_page, referrer,
        geo_label, geo_city, geo_region, geo_country, geo_status, lat, lng
    FROM visitor_sessions
    ORDER BY last_seen_at DESC
    LIMIT {$va_per_page} OFFSET {$va_offset}");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $recent[] = $row;
    }
}

$session_keys = array_column($recent, 'session_key');
$pages_by_session = [];
if ($session_keys) {
    $in = implode(',', array_map(static function ($k) use ($conn) {
        return "'" . $conn->real_escape_string($k) . "'";
    }, $session_keys));
    $r = $conn->query("SELECT session_key, page_path, page_title, viewed_at, seconds_on_page
        FROM visitor_pageviews
        WHERE session_key IN ($in) AND page_path IS NOT NULL AND TRIM(page_path) != ''
        ORDER BY viewed_at ASC");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $sk = $row['session_key'];
            if (!isset($pages_by_session[$sk])) {
                $pages_by_session[$sk] = [];
            }
            if (count($pages_by_session[$sk]) < 20) {
                $pages_by_session[$sk][] = $row;
            }
        }
    }
}

if (!function_exists('cj_fmt_duration')) {
    function cj_fmt_duration($seconds)
    {
        $seconds = (int)$seconds;
        if ($seconds < 60) {
            return $seconds . 's';
        }
        $m = (int)floor($seconds / 60);
        $s = $seconds % 60;
        if ($m < 60) {
            return $m . 'm ' . $s . 's';
        }
        $h = (int)floor($m / 60);
        $m = $m % 60;
        return $h . 'h ' . $m . 'm';
    }
}

if (!function_exists('cj_va_page_url')) {
    function cj_va_page_url($page)
    {
        $params = $_GET;
        $params['va_page'] = max(1, (int)$page);
        // Jump to the visitor panel after reload
        return '?' . http_build_query($params) . '#visitorAnalytics';
    }
}
?>

<section id="visitorAnalytics" class="va-panel mb-8 scroll-mt-24">
    <div class="va-panel-head">
        <div>
            <h2><i class="fas fa-map-marker-alt text-amber-500 mr-1"></i> Visitor Insights</h2>
            <p>Live location (when allowed), time on site, and pages viewed.</p>
        </div>
        <a class="va-refresh" href="<?= htmlspecialchars(cj_va_page_url($va_page), ENT_QUOTES, 'UTF-8') ?>">Refresh data</a>
    </div>

    <div class="va-kpis">
        <div class="va-kpi">
            <p class="va-kpi-label">Active now</p>
            <p class="va-kpi-value"><?= (int)$active_now ?></p>
            <p class="va-kpi-hint">Seen in last 5 min</p>
        </div>
        <div class="va-kpi">
            <p class="va-kpi-label">Visitors today</p>
            <p class="va-kpi-value"><?= (int)$today_visitors ?></p>
            <p class="va-kpi-hint">Unique sessions</p>
        </div>
        <div class="va-kpi">
            <p class="va-kpi-label">Avg time today</p>
            <p class="va-kpi-value"><?= htmlspecialchars((string)$avg_minutes) ?><span> min</span></p>
            <p class="va-kpi-hint">Engagement length</p>
        </div>
        <div class="va-kpi">
            <p class="va-kpi-label">Page views today</p>
            <p class="va-kpi-value"><?= (int)$today_pageviews ?></p>
            <p class="va-kpi-hint">Across all sessions</p>
        </div>
        <div class="va-kpi">
            <p class="va-kpi-label">Shared location</p>
            <p class="va-kpi-value"><?= (int)$with_geo ?></p>
            <p class="va-kpi-hint">Granted geo today</p>
        </div>
    </div>

    <div class="va-block">
        <h3>Visitor map</h3>
        <div id="adminVisitorMap" class="va-map" aria-label="Visitor locations map"></div>
        <p class="va-help">Pins appear when a visitor allows location. Denied/skipped visits still show in the logs below.</p>
        <script type="application/json" id="adminVisitorMapData"><?= json_encode(array_map(static function ($p) {
            return [
                'lat' => (float)$p['lat'],
                'lng' => (float)$p['lng'],
                'label' => $p['geo_label'] ?: trim(($p['geo_city'] ?? '') . ' ' . ($p['geo_country'] ?? '')),
                'duration' => (int)$p['duration_seconds'],
                'pages' => (int)$p['page_count'],
                'landing' => $p['landing_page'],
                'when' => $p['last_seen_at'],
            ];
        }, $map_points), JSON_UNESCAPED_UNICODE) ?></script>
    </div>

    <div class="va-block">
        <h3>Top pages today</h3>
        <?php if (!$top_pages): ?>
            <p class="va-empty">No page views recorded yet today.</p>
        <?php else: ?>
            <ul class="va-bars">
                <?php foreach ($top_pages as $tp):
                    $pct = max(8, (int)round(((int)$tp['views'] / $max_views) * 100));
                    $path = trim((string)$tp['page_path']);
                    if ($path === '') {
                        continue;
                    }
                ?>
                    <li>
                        <div class="va-bar-meta">
                            <span class="va-bar-path" title="<?= htmlspecialchars($path) ?>"><?= htmlspecialchars($path) ?></span>
                            <span class="va-bar-stats"><?= (int)$tp['views'] ?> views · <?= htmlspecialchars((string)$tp['avg_min']) ?> min avg</span>
                        </div>
                        <div class="va-bar-track"><span style="width:<?= $pct ?>%"></span></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="va-block va-logs-block">
        <div class="va-logs-head">
            <div>
                <h3>Visitor session logs</h3>
                <p class="va-logs-range">
                    <?php if ($va_total === 0): ?>
                        No sessions yet
                    <?php else: ?>
                        Showing <strong><?= (int)$va_from ?>–<?= (int)$va_to ?></strong> of <strong><?= (int)$va_total ?></strong>
                        · Page <strong><?= (int)$va_page ?></strong> of <strong><?= (int)$va_total_pages ?></strong>
                    <?php endif; ?>
                </p>
            </div>
            <span class="va-logs-count"><?= (int)$va_per_page ?> per page</span>
        </div>

        <?php if (!$recent): ?>
            <p class="va-empty">No visitor sessions yet.</p>
        <?php else: ?>
            <div class="va-log-list" id="vaLogList">
                <?php foreach ($recent as $i => $s):
                    $pages = $pages_by_session[$s['session_key']] ?? [];
                    $is_active = strtotime((string)$s['last_seen_at']) >= time() - 300;
                    $loc = trim((string)($s['geo_label'] ?: ''));
                    if ($loc === '') {
                        $loc = trim(implode(', ', array_filter([(string)($s['geo_city'] ?? ''), (string)($s['geo_region'] ?? ''), (string)($s['geo_country'] ?? '')])));
                    }
                    if ($loc === '') {
                        if (($s['geo_status'] ?? '') === 'granted' && !empty($s['lat'])) {
                            $loc = round((float)$s['lat'], 4) . ', ' . round((float)$s['lng'], 4);
                        } elseif (($s['geo_status'] ?? '') === 'denied') {
                            $loc = 'Location denied';
                        } elseif (($s['geo_status'] ?? '') === 'dismissed') {
                            $loc = 'Location skipped';
                        } else {
                            $loc = 'Location not shared';
                        }
                    }
                    $landing = trim((string)($s['landing_page'] ?? ''));
                    if ($landing === '' && !empty($pages[0]['page_path'])) {
                        $landing = (string)$pages[0]['page_path'];
                    }
                    if ($landing === '') {
                        $landing = '—';
                    }
                    $open = $i === 0 && $va_page === 1;
                ?>
                    <article class="va-log<?= $open ? ' is-open' : '' ?>">
                        <button type="button" class="va-log-toggle" aria-expanded="<?= $open ? 'true' : 'false' ?>">
                            <span class="va-dot<?= $is_active ? ' is-live' : '' ?>"></span>
                            <span class="va-log-main">
                                <strong><?= htmlspecialchars($loc) ?></strong>
                                <span><?= htmlspecialchars(cj_fmt_duration($s['duration_seconds'])) ?> · <?= (int)$s['page_count'] ?> page<?= ((int)$s['page_count'] === 1) ? '' : 's' ?></span>
                            </span>
                            <span class="va-log-time"><?= htmlspecialchars(date('d M, h:i A', strtotime((string)$s['last_seen_at']))) ?></span>
                            <i class="fas fa-chevron-down va-chevron" aria-hidden="true"></i>
                        </button>
                        <div class="va-log-body"<?= $open ? '' : ' hidden' ?>>
                            <div class="va-meta-grid">
                                <div><span>Landed on</span><strong><?= htmlspecialchars($landing) ?></strong></div>
                                <?php if (!empty($s['referrer'])): ?>
                                    <div><span>Referrer</span><strong class="va-break"><?= htmlspecialchars((string)$s['referrer']) ?></strong></div>
                                <?php endif; ?>
                                <div><span>Geo status</span><strong><?= htmlspecialchars((string)($s['geo_status'] ?: 'unknown')) ?></strong></div>
                                <div><span>Session</span><strong><?= htmlspecialchars(date('d M H:i', strtotime((string)$s['started_at']))) ?> → <?= htmlspecialchars(date('H:i', strtotime((string)$s['last_seen_at']))) ?></strong></div>
                            </div>
                            <?php if ($pages): ?>
                                <p class="va-pages-label">Pages in this visit</p>
                                <ul class="va-pages">
                                    <?php foreach ($pages as $pv): ?>
                                        <li>
                                            <strong><?= htmlspecialchars((string)$pv['page_path']) ?></strong>
                                            <em><?= htmlspecialchars(cj_fmt_duration($pv['seconds_on_page'])) ?></em>
                                            <small><?= htmlspecialchars(date('H:i:s', strtotime((string)$pv['viewed_at']))) ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="va-empty">No page paths stored for this session.</p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($va_total_pages > 1): ?>
                <nav class="va-pagination" aria-label="Visitor logs pages">
                    <a class="va-page-btn<?= $va_page <= 1 ? ' is-disabled' : '' ?>"
                       href="<?= $va_page <= 1 ? '#' : htmlspecialchars(cj_va_page_url($va_page - 1), ENT_QUOTES, 'UTF-8') ?>"
                       <?= $va_page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>← Previous</a>

                    <div class="va-page-numbers">
                        <?php
                        $window = 2;
                        $start = max(1, $va_page - $window);
                        $end = min($va_total_pages, $va_page + $window);
                        if ($start > 1) {
                            echo '<a href="' . htmlspecialchars(cj_va_page_url(1), ENT_QUOTES, 'UTF-8') . '">1</a>';
                            if ($start > 2) {
                                echo '<span class="va-page-ellipsis">…</span>';
                            }
                        }
                        for ($p = $start; $p <= $end; $p++) {
                            if ($p === $va_page) {
                                echo '<span class="is-current" aria-current="page">' . $p . '</span>';
                            } else {
                                echo '<a href="' . htmlspecialchars(cj_va_page_url($p), ENT_QUOTES, 'UTF-8') . '">' . $p . '</a>';
                            }
                        }
                        if ($end < $va_total_pages) {
                            if ($end < $va_total_pages - 1) {
                                echo '<span class="va-page-ellipsis">…</span>';
                            }
                            echo '<a href="' . htmlspecialchars(cj_va_page_url($va_total_pages), ENT_QUOTES, 'UTF-8') . '">' . $va_total_pages . '</a>';
                        }
                        ?>
                    </div>

                    <a class="va-page-btn<?= $va_page >= $va_total_pages ? ' is-disabled' : '' ?>"
                       href="<?= $va_page >= $va_total_pages ? '#' : htmlspecialchars(cj_va_page_url($va_page + 1), ENT_QUOTES, 'UTF-8') ?>"
                       <?= $va_page >= $va_total_pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Next →</a>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
  var list = document.getElementById('vaLogList');
  if (!list) return;
  list.addEventListener('click', function (e) {
    var btn = e.target.closest('.va-log-toggle');
    if (!btn || !list.contains(btn)) return;
    var card = btn.closest('.va-log');
    if (!card) return;
    var body = card.querySelector('.va-log-body');
    var open = card.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (body) body.hidden = !open;
  });
})();
</script>
