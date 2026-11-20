<?php
/**
 * ChandraKala Jewellers — Video Reels, Top-Viewed Ranking Engine & Impressions Analytics
 * Supports direct Cloudinary video streaming, automatic Top-Viewed ranking,
 * real-time impressions/views/clicks/CTR tracking, likes, comments/reviews, and order inquiries.
 */
require_once __DIR__ . '/cloudinary.php';

if (!function_exists('cj_ensure_reels_schema')) {
    function cj_ensure_reels_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $conn->query("CREATE TABLE IF NOT EXISTS video_reels (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(255) NOT NULL DEFAULT '',
            video_url VARCHAR(500) NOT NULL,
            poster_url VARCHAR(500) NOT NULL DEFAULT '',
            admin_note VARCHAR(200) NOT NULL DEFAULT '',
            width INT UNSIGNED NOT NULL DEFAULT 0,
            height INT UNSIGNED NOT NULL DEFAULT 0,
            duration DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            impressions INT UNSIGNED NOT NULL DEFAULT 0,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            clicks INT UNSIGNED NOT NULL DEFAULT 0,
            likes_count INT UNSIGNED NOT NULL DEFAULT 0,
            comments_count INT UNSIGNED NOT NULL DEFAULT 0,
            order_clicks INT UNSIGNED NOT NULL DEFAULT 0,
            whatsapp_clicks INT UNSIGNED NOT NULL DEFAULT 0,
            total_watch_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
            watch_sessions INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_reels_rank (is_active, views, impressions, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Auto-migrate watch-time columns on existing video_reels tables
        $vrCols = [];
        if ($cr = @$conn->query("SHOW COLUMNS FROM video_reels")) {
            while ($col = $cr->fetch_assoc()) $vrCols[$col['Field']] = true;
        }
        if (empty($vrCols['total_watch_ms'])) {
            @$conn->query("ALTER TABLE video_reels ADD COLUMN total_watch_ms BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER whatsapp_clicks");
        }
        if (empty($vrCols['watch_sessions'])) {
            @$conn->query("ALTER TABLE video_reels ADD COLUMN watch_sessions INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_watch_ms");
        }
        if (empty($vrCols['ig_media_id'])) {
            @$conn->query("ALTER TABLE video_reels ADD COLUMN ig_media_id VARCHAR(80) NOT NULL DEFAULT '' AFTER public_id");
            @$conn->query("ALTER TABLE video_reels ADD KEY idx_reels_ig_media (ig_media_id)");
        }

        $conn->query("CREATE TABLE IF NOT EXISTS video_reel_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reel_id INT UNSIGNED NOT NULL,
            event_type VARCHAR(24) NOT NULL,
            visitor_hash VARCHAR(64) NOT NULL DEFAULT '',
            watch_ms INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_reel_ev_type_time (reel_id, event_type, created_at),
            KEY idx_reel_ev_dedup (reel_id, event_type, visitor_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $evCols = [];
        if ($er = @$conn->query("SHOW COLUMNS FROM video_reel_events")) {
            while ($col = $er->fetch_assoc()) $evCols[$col['Field']] = true;
        }
        if (empty($evCols['watch_ms'])) {
            @$conn->query("ALTER TABLE video_reel_events ADD COLUMN watch_ms INT UNSIGNED NOT NULL DEFAULT 0 AFTER visitor_hash");
        }

        $conn->query("CREATE TABLE IF NOT EXISTS video_reel_likes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reel_id INT UNSIGNED NOT NULL,
            visitor_hash VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_reel_like (reel_id, visitor_hash),
            KEY idx_reel_likes_reel (reel_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS video_reel_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reel_id INT UNSIGNED NOT NULL,
            customer_name VARCHAR(120) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL DEFAULT '',
            rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
            comment TEXT NOT NULL,
            is_approved TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_reel_comments (reel_id, is_approved, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS video_reel_orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reel_id INT UNSIGNED NOT NULL,
            customer_name VARCHAR(120) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            preferred_time VARCHAR(80) NOT NULL DEFAULT '',
            message TEXT,
            status VARCHAR(24) NOT NULL DEFAULT 'new',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_reel_orders (reel_id, status, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Sponsored Photo Reel Ads (Gallery Shop & Catalogue items shown as endless Reels)
        $conn->query("CREATE TABLE IF NOT EXISTS reel_ad_stats (
            item_key VARCHAR(40) PRIMARY KEY,
            impressions INT UNSIGNED NOT NULL DEFAULT 0,
            likes_count INT UNSIGNED NOT NULL DEFAULT 0,
            comments_count INT UNSIGNED NOT NULL DEFAULT 0,
            clicks INT UNSIGNED NOT NULL DEFAULT 0,
            whatsapp_clicks INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS reel_ad_likes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_key VARCHAR(40) NOT NULL,
            visitor_hash VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_reel_ad_like (item_key, visitor_hash),
            KEY idx_reel_ad_likes_key (item_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS reel_ad_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_key VARCHAR(40) NOT NULL,
            customer_name VARCHAR(120) NOT NULL,
            rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
            comment TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_reel_ad_comments_key (item_key, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('cj_reel_visitor_hash')) {
    /** Stable privacy-safe visitor identifier (cookie + IP/UA fallback) for dedup & likes. */
    function cj_reel_visitor_hash(): string
    {
        $vid = (string)($_COOKIE['cj_vid'] ?? '');
        if ($vid === '' || !preg_match('/^[a-f0-9]{16,64}$/', $vid)) {
            $vid = bin2hex(random_bytes(16));
            if (!headers_sent() && function_exists('cj_session_cookie_params')) {
                $p = cj_session_cookie_params();
                setcookie('cj_vid', $vid, [
                    'expires' => time() + 31536000,
                    'path' => '/',
                    'domain' => $p['domain'],
                    'secure' => $p['secure'],
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                $_COOKIE['cj_vid'] = $vid;
            }
        }
        return hash('sha256', 'cj_reel_v1_' . $vid);
    }
}

if (!function_exists('cj_reel_preview_url')) {
    /** Lightweight muted loop stream for fast simultaneous Bento Masonry autoplay. */
    function cj_reel_preview_url(array $reel): string
    {
        $publicId = trim((string)($reel['public_id'] ?? ''));
        $rawUrl = trim((string)($reel['video_url'] ?? ''));
        if ($publicId !== '' && function_exists('cj_cloudinary_config')) {
            $cfg = cj_cloudinary_config();
            $cloud = trim((string)($cfg['cloud_name'] ?? ''));
            if ($cloud !== '') {
                return 'https://res.cloudinary.com/' . rawurlencode($cloud)
                    . '/video/upload/f_auto,q_auto:eco,w_540,ac_none,vc_auto/'
                    . ltrim($publicId, '/') . '.mp4';
            }
        }
        return $rawUrl;
    }
}

if (!function_exists('cj_reel_hd_url')) {
    /** Crisp HD video stream with audio for the Showcase detail page. */
    function cj_reel_hd_url(array $reel): string
    {
        $publicId = trim((string)($reel['public_id'] ?? ''));
        $rawUrl = trim((string)($reel['video_url'] ?? ''));
        if ($publicId !== '' && function_exists('cj_cloudinary_config')) {
            $cfg = cj_cloudinary_config();
            $cloud = trim((string)($cfg['cloud_name'] ?? ''));
            if ($cloud !== '') {
                return 'https://res.cloudinary.com/' . rawurlencode($cloud)
                    . '/video/upload/f_auto,q_auto,w_1080/'
                    . ltrim($publicId, '/') . '.mp4';
            }
        }
        return $rawUrl;
    }
}

if (!function_exists('cj_reel_poster_url')) {
    /** Instant auto-generated Cloudinary video poster thumbnail. */
    function cj_reel_poster_url(array $reel, int $width = 540): string
    {
        $poster = trim((string)($reel['poster_url'] ?? ''));
        $publicId = trim((string)($reel['public_id'] ?? ''));
        if ($publicId !== '' && function_exists('cj_cloudinary_config')) {
            $cfg = cj_cloudinary_config();
            $cloud = trim((string)($cfg['cloud_name'] ?? ''));
            if ($cloud !== '') {
                return 'https://res.cloudinary.com/' . rawurlencode($cloud)
                    . '/video/upload/so_0.5,f_auto,q_auto,w_' . $width . '/'
                    . ltrim($publicId, '/') . '.jpg';
            }
        }
        return $poster;
    }
}

if (!function_exists('cj_cloudinary_destroy_video')) {
    /** Delete a video from Cloudinary when removed from Admin. */
    function cj_cloudinary_destroy_video(string $publicId): bool
    {
        $publicId = trim($publicId);
        if ($publicId === '' || !function_exists('cj_cloudinary_config')) {
            return false;
        }
        $cfg = cj_cloudinary_config();
        if (empty($cfg['cloud_name']) || empty($cfg['api_key']) || empty($cfg['api_secret'])) {
            return false;
        }
        $ts = time();
        $sig = sha1('public_id=' . $publicId . '&timestamp=' . $ts . $cfg['api_secret']);
        $url = 'https://api.cloudinary.com/v1_1/' . rawurlencode($cfg['cloud_name']) . '/video/destroy';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'public_id' => $publicId,
                'api_key' => $cfg['api_key'],
                'timestamp' => (string)$ts,
                'signature' => $sig,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $resp !== false && $code >= 200 && $code < 300;
    }
}

if (!function_exists('cj_cloudinary_upload_remote_video')) {
    /**
     * Tell Cloudinary to fetch a remote video URL (e.g., official Meta Graph API media_url
     * or direct .mp4 link) directly server-to-server — bypasses Vercel's 4.5MB limit completely!
     *
     * @return array{ok:bool,secure_url?:string,public_id?:string,width?:int,height?:int,duration?:float,error?:string}
     */
    function cj_cloudinary_upload_remote_video(string $remoteUrl, ?string $publicId = null, string $folder = 'chandrakala/reels'): array
    {
        $remoteUrl = trim($remoteUrl);
        if ($remoteUrl === '' || !preg_match('#^https?://#i', $remoteUrl)) {
            return ['ok' => false, 'error' => 'Valid https:// video URL required.'];
        }
        if (!function_exists('cj_cloudinary_config')) {
            return ['ok' => false, 'error' => 'Cloudinary helper unavailable.'];
        }
        $cfg = cj_cloudinary_config();
        if (empty($cfg['cloud_name']) || empty($cfg['api_key']) || empty($cfg['api_secret'])) {
            return ['ok' => false, 'error' => 'Cloudinary credentials missing.'];
        }

        $ts = time();
        $pid = $publicId ?: ('ig_reel_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)));
        $params = [
            'folder' => $folder,
            'public_id' => $pid,
            'timestamp' => $ts,
        ];
        ksort($params);
        $toSign = '';
        foreach ($params as $k => $v) {
            $toSign .= $k . '=' . $v . '&';
        }
        $sig = sha1(rtrim($toSign, '&') . $cfg['api_secret']);

        $url = 'https://api.cloudinary.com/v1_1/' . rawurlencode($cfg['cloud_name']) . '/video/upload';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => $remoteUrl,
                'api_key' => $cfg['api_key'],
                'timestamp' => (string)$ts,
                'folder' => $folder,
                'public_id' => $pid,
                'signature' => $sig,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 55,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $err !== '') {
            return ['ok' => false, 'error' => 'Cloudinary fetch error: ' . $err];
        }
        $data = json_decode((string)$resp, true);
        if ($code >= 200 && $code < 300 && !empty($data['secure_url'])) {
            return [
                'ok' => true,
                'secure_url' => (string)$data['secure_url'],
                'public_id' => (string)($data['public_id'] ?? ($folder . '/' . $pid)),
                'width' => (int)($data['width'] ?? 0),
                'height' => (int)($data['height'] ?? 0),
                'duration' => (float)($data['duration'] ?? 0),
            ];
        }
        $msg = (string)($data['error']['message'] ?? ('Cloudinary HTTP ' . $code));
        return ['ok' => false, 'error' => $msg];
    }
}

if (!function_exists('cj_format_reel_count')) {
    function cj_format_reel_count(int $num): string
    {
        if ($num >= 1000000) {
            return round($num / 1000000, 1) . 'M';
        }
        if ($num >= 1000) {
            return round($num / 1000, 1) . 'K';
        }
        return (string)$num;
    }
}

if (!function_exists('cj_get_ranked_reels')) {
    /**
     * Top-Viewed Ranking Engine:
     * Automatically places the #1 Most Viewed Reel at position 0 (Hero Bento slot)
     * and marks it `is_top_viewed = true`.
     *
     * @return list<array<string,mixed>>
     */
    function cj_get_ranked_reels(mysqli $conn, int $limit = 24, bool $onlyActive = true, string $sort = 'top'): array
    {
        cj_ensure_reels_schema($conn);
        $where = $onlyActive ? 'WHERE is_active = 1' : '';
        $order = $sort === 'latest'
            ? 'ORDER BY created_at DESC, id DESC'
            : 'ORDER BY GREATEST(views, impressions) DESC, (likes_count * 3 + clicks * 2 + comments_count * 4) DESC, id DESC';

        $limitSql = $limit > 0 ? ('LIMIT ' . (int)$limit) : '';
        $res = $conn->query("SELECT * FROM video_reels $where $order $limitSql");
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $r['id'] = (int)$r['id'];
                $r['impressions'] = (int)$r['impressions'];
                $r['views'] = (int)$r['views'];
                $r['clicks'] = (int)$r['clicks'];
                $r['likes_count'] = (int)$r['likes_count'];
                $r['comments_count'] = (int)$r['comments_count'];
                $r['order_clicks'] = (int)$r['order_clicks'];
                $r['whatsapp_clicks'] = (int)$r['whatsapp_clicks'];
                $r['display_views'] = max($r['views'], $r['impressions']);
                $r['preview_url'] = cj_reel_preview_url($r);
                $r['hd_url'] = cj_reel_hd_url($r);
                $r['poster_thumb'] = cj_reel_poster_url($r, 540);
                $r['is_top_viewed'] = false;
                $rows[] = $r;
            }
        }

        // Identify the true #1 Top Viewed reel across all active reels
        $topId = 0;
        $topRes = $conn->query("SELECT id FROM video_reels WHERE is_active = 1 ORDER BY GREATEST(views, impressions) DESC, (likes_count * 3 + clicks * 2) DESC, id DESC LIMIT 1");
        if ($topRes && ($tr = $topRes->fetch_assoc())) {
            $topId = (int)$tr['id'];
        }
        foreach ($rows as &$row) {
            if ($topId > 0 && $row['id'] === $topId) {
                $row['is_top_viewed'] = true;
            }
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('cj_get_reel_by_id')) {
    /** @return array<string,mixed>|null */
    function cj_get_reel_by_id(mysqli $conn, int $id): ?array
    {
        cj_ensure_reels_schema($conn);
        if ($id <= 0) {
            return null;
        }
        $stmt = $conn->prepare("SELECT * FROM video_reels WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$r) {
            return null;
        }
        $r['id'] = (int)$r['id'];
        $r['impressions'] = (int)$r['impressions'];
        $r['views'] = (int)$r['views'];
        $r['clicks'] = (int)$r['clicks'];
        $r['likes_count'] = (int)$r['likes_count'];
        $r['comments_count'] = (int)$r['comments_count'];
        $r['order_clicks'] = (int)$r['order_clicks'];
        $r['whatsapp_clicks'] = (int)$r['whatsapp_clicks'];
        $r['display_views'] = max($r['views'], $r['impressions']);
        $r['preview_url'] = cj_reel_preview_url($r);
        $r['hd_url'] = cj_reel_hd_url($r);
        $r['poster_thumb'] = cj_reel_poster_url($r, 720);

        $topRes = $conn->query("SELECT id FROM video_reels WHERE is_active = 1 ORDER BY GREATEST(views, impressions) DESC, (likes_count * 3 + clicks * 2) DESC, id DESC LIMIT 1");
        $topId = ($topRes && ($tr = $topRes->fetch_assoc())) ? (int)$tr['id'] : 0;
        $r['is_top_viewed'] = ($topId > 0 && $r['id'] === $topId);
        return $r;
    }
}

if (!function_exists('cj_reel_is_liked_by_visitor')) {
    function cj_reel_is_liked_by_visitor(mysqli $conn, int $reelId, string $visitorHash): bool
    {
        cj_ensure_reels_schema($conn);
        $st = $conn->prepare("SELECT id FROM video_reel_likes WHERE reel_id = ? AND visitor_hash = ? LIMIT 1");
        if (!$st) return false;
        $st->bind_param('is', $reelId, $visitorHash);
        $st->execute();
        $liked = (bool)$st->get_result()->fetch_assoc();
        $st->close();
        return $liked;
    }
}

if (!function_exists('cj_get_reel_comments')) {
    /** @return list<array<string,mixed>> */
    function cj_get_reel_comments(mysqli $conn, int $reelId, int $limit = 40): array
    {
        cj_ensure_reels_schema($conn);
        $st = $conn->prepare("SELECT id, reel_id, customer_name, rating, comment, created_at FROM video_reel_comments WHERE reel_id = ? AND is_approved = 1 ORDER BY id DESC LIMIT ?");
        if (!$st) return [];
        $st->bind_param('ii', $reelId, $limit);
        $st->execute();
        $res = $st->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = $row;
        }
        $st->close();
        return $out;
    }
}

if (!function_exists('cj_get_reel_analytics')) {
    /**
     * Aggregated analytics for Admin Panel (All Time / Today / 7d / 30d).
     * @return array<string,mixed>
     */
    function cj_get_reel_analytics(mysqli $conn, string $range = 'all'): array
    {
        cj_ensure_reels_schema($conn);
        $summary = [
            'total_reels' => 0,
            'active_reels' => 0,
            'impressions' => 0,
            'views' => 0,
            'clicks' => 0,
            'ctr' => 0.0,
            'likes' => 0,
            'comments' => 0,
            'orders' => 0,
            'whatsapp' => 0,
            'top_reel' => null,
            'per_reel' => [],
        ];

        $q = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
            COALESCE(SUM(impressions),0) AS imp, COALESCE(SUM(views),0) AS vw,
            COALESCE(SUM(clicks),0) AS clk, COALESCE(SUM(likes_count),0) AS lks,
            COALESCE(SUM(comments_count),0) AS cmts, COALESCE(SUM(order_clicks),0) AS ords,
            COALESCE(SUM(whatsapp_clicks),0) AS wa
            FROM video_reels");
        if ($q && ($r = $q->fetch_assoc())) {
            $summary['total_reels'] = (int)$r['total'];
            $summary['active_reels'] = (int)$r['active'];
            $summary['impressions'] = (int)$r['imp'];
            $summary['views'] = (int)$r['vw'];
            $summary['clicks'] = (int)$r['clk'];
            $summary['likes'] = (int)$r['lks'];
            $summary['comments'] = (int)$r['cmts'];
            $summary['orders'] = (int)$r['ords'];
            $summary['whatsapp'] = (int)$r['wa'];
        }

        // If time-windowed range selected, compute impressions/views/clicks/whatsapp/orders from video_reel_events
        if (in_array($range, ['today', '7d', '30d'], true)) {
            $cond = $range === 'today'
                ? 'created_at >= CURDATE()'
                : ($range === '7d' ? 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)' : 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
            $ev = $conn->query("SELECT reel_id, event_type, COUNT(*) AS cnt FROM video_reel_events WHERE {$cond} GROUP BY reel_id, event_type");
            $summary['impressions'] = 0;
            $summary['views'] = 0;
            $summary['clicks'] = 0;
            $summary['whatsapp'] = 0;
            $summary['orders'] = 0;
            $perReel = [];
            if ($ev) {
                while ($er = $ev->fetch_assoc()) {
                    $rid = (int)$er['reel_id'];
                    $et = (string)$er['event_type'];
                    $cnt = (int)$er['cnt'];
                    if (!isset($perReel[$rid])) {
                        $perReel[$rid] = ['impressions' => 0, 'views' => 0, 'clicks' => 0, 'whatsapp' => 0, 'orders' => 0];
                    }
                    if ($et === 'impression') { $summary['impressions'] += $cnt; $perReel[$rid]['impressions'] = $cnt; }
                    elseif ($et === 'view') { $summary['views'] += $cnt; $perReel[$rid]['views'] = $cnt; }
                    elseif ($et === 'click') { $summary['clicks'] += $cnt; $perReel[$rid]['clicks'] = $cnt; }
                    elseif ($et === 'whatsapp') { $summary['whatsapp'] += $cnt; $perReel[$rid]['whatsapp'] = $cnt; }
                    elseif ($et === 'order') { $summary['orders'] += $cnt; $perReel[$rid]['orders'] = $cnt; }
                }
            }
            $summary['per_reel'] = $perReel;
        }

        $base = max($summary['impressions'], $summary['views']);
        $summary['ctr'] = $base > 0 ? round(($summary['clicks'] / $base) * 100, 1) : 0.0;

        $ranked = cj_get_ranked_reels($conn, 1, false, 'top');
        if (!empty($ranked)) {
            $summary['top_reel'] = $ranked[0];
        }

        return $summary;
    }
}

if (!function_exists('cj_reel_extract_style_tokens')) {
    /** Extract jewellery interest tokens from internal admin notes for personalized matching. */
    function cj_reel_extract_style_tokens(string $text): array
    {
        $text = strtolower(trim($text));
        if ($text === '') return [];
        $parts = preg_split('/[^a-z0-9]+/', $text) ?: [];
        $stop = ['the', 'and', 'for', 'with', 'new', 'reel', 'video', 'shoot', 'jewellery', 'jewelry', 'design', 'from'];
        $tokens = [];
        foreach ($parts as $p) {
            if (strlen($p) >= 3 && !in_array($p, $stop, true)) {
                $tokens[] = $p;
            }
        }
        return array_values(array_unique($tokens));
    }
}

if (!function_exists('cj_get_personalized_reels')) {
    /**
     * Instagram-Style "For You" Recommendation + Controlled Randomization Engine.
     * Learns from each visitor's impressions, watch duration, likes, comments & clicks:
     *   - Builds visitor interest profile (preferred jewellery styles & reel durations)
     *   - Boosts unseen reels matching their taste + high global watch engagement
     *   - Applies anti-fatigue demotion to reels just seen recently
     *   - Injects controlled randomization (epsilon-greedy jitter) so every visit feels fresh!
     *
     * @return list<array<string,mixed>>
     */
    function cj_get_personalized_reels(mysqli $conn, string $visitorHash, int $limit = 60): array
    {
        $all = cj_get_ranked_reels($conn, max(60, $limit), true, 'top');
        if (count($all) <= 1) {
            return $all;
        }

        // Index reels by ID & extract style tags
        $byId = [];
        $reelTokens = [];
        foreach ($all as $r) {
            $rid = (int)$r['id'];
            $byId[$rid] = $r;
            $reelTokens[$rid] = cj_reel_extract_style_tokens((string)($r['admin_note'] ?? ''));
        }

        // 1. Gather visitor's recent interaction signals (last 30 days)
        $likedIds = [];
        if ($st = $conn->prepare("SELECT reel_id FROM video_reel_likes WHERE visitor_hash = ?")) {
            $st->bind_param('s', $visitorHash);
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) $likedIds[(int)$row['reel_id']] = true;
            $st->close();
        }

        $recentSeen = [];
        $interestWeights = [];
        $preferredDurationSum = 0.0;
        $preferredDurationCount = 0;

        if ($st = $conn->prepare(
            "SELECT reel_id, event_type, watch_ms, TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS mins_ago
             FROM video_reel_events
             WHERE visitor_hash = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY id DESC LIMIT 150"
        )) {
            $st->bind_param('s', $visitorHash);
            $st->execute();
            $res = $st->get_result();
            while ($ev = $res->fetch_assoc()) {
                $rid = (int)$ev['reel_id'];
                $et = (string)$ev['event_type'];
                $wms = (int)($ev['watch_ms'] ?? 0);
                $minsAgo = (int)($ev['mins_ago'] ?? 9999);

                // Anti-fatigue: remember recently seen reels in last 3 hours
                if ($minsAgo <= 180 && ($et === 'impression' || $et === 'view' || $et === 'watch')) {
                    $recentSeen[$rid] = min($recentSeen[$rid] ?? 9999, $minsAgo);
                }

                // Positive interest signal weight
                $signal = 0.0;
                if ($et === 'order' || $et === 'whatsapp') $signal = 8.0;
                elseif ($et === 'click' || $et === 'share') $signal = 5.0;
                elseif ($et === 'watch') {
                    // Long watch (>4s) = strong interest; quick skip (<1.5s) = negative signal
                    if ($wms >= 7000) $signal = 6.0;
                    elseif ($wms >= 3500) $signal = 3.0;
                    elseif ($wms > 0 && $wms < 1400) $signal = -2.0;
                }

                if ($signal !== 0.0 && !empty($reelTokens[$rid])) {
                    foreach ($reelTokens[$rid] as $tok) {
                        $interestWeights[$tok] = ($interestWeights[$tok] ?? 0.0) + $signal;
                    }
                }
                if ($signal > 0 && isset($byId[$rid]) && (float)($byId[$rid]['duration'] ?? 0) > 0) {
                    $preferredDurationSum += (float)$byId[$rid]['duration'];
                    $preferredDurationCount++;
                }
            }
            $st->close();
        }

        // Likes give strong interest boost to matching tags
        foreach (array_keys($likedIds) as $lrid) {
            if (!empty($reelTokens[$lrid])) {
                foreach ($reelTokens[$lrid] as $tok) {
                    $interestWeights[$tok] = ($interestWeights[$tok] ?? 0.0) + 6.5;
                }
            }
        }

        $avgPrefDur = $preferredDurationCount > 0 ? ($preferredDurationSum / $preferredDurationCount) : 0.0;

        // 2. Score every reel: Global Momentum + Personalized Affinity + Freshness + Random Discovery
        $maxViews = 1;
        foreach ($all as $r) {
            $maxViews = max($maxViews, (int)$r['display_views']);
        }

        $scored = [];
        foreach ($all as $r) {
            $rid = (int)$r['id'];
            $r['is_liked'] = !empty($likedIds[$rid]);

            // Global popularity & retention quality (0..30)
            $viewRatio = ((int)$r['display_views']) / $maxViews;
            $avgWatchSec = ((int)($r['watch_sessions'] ?? 0) > 0)
                ? (((int)($r['total_watch_ms'] ?? 0) / (int)$r['watch_sessions']) / 1000.0)
                : 0.0;
            $globalScore = ($viewRatio * 18.0)
                + min(8.0, $avgWatchSec * 0.8)
                + min(6.0, ((int)$r['likes_count']) * 0.5 + ((int)$r['comments_count']) * 0.8);

            // Personal interest affinity (can be positive or negative)
            $affinity = 0.0;
            foreach ($reelTokens[$rid] as $tok) {
                if (isset($interestWeights[$tok])) {
                    $affinity += $interestWeights[$tok] * 2.2;
                }
            }
            $affinity = max(-15.0, min(35.0, $affinity));

            if ($avgPrefDur > 0 && (float)($r['duration'] ?? 0) > 0) {
                $diff = abs((float)$r['duration'] - $avgPrefDur);
                if ($diff <= 6.0) $affinity += 3.5;
            }

            // Anti-fatigue penalty so recently swiped reels rotate back cleanly
            $fatigue = 0.0;
            if (isset($recentSeen[$rid])) {
                $fatigue = $recentSeen[$rid] < 20 ? -18.0 : -9.0;
            }

            // Controlled random discovery jitter (0..26) so every session feels fresh & randomized
            $randomJitter = (mt_rand(0, 2600) / 100.0);

            $r['_algo_score'] = $globalScore + $affinity + $fatigue + $randomJitter;
            $scored[] = $r;
        }

        usort($scored, static function ($a, $b) {
            return $b['_algo_score'] <=> $a['_algo_score'];
        });

        return array_slice($scored, 0, $limit);
    }
}

if (!function_exists('cj_get_prefetch_reels')) {
    /**
     * Fast retrieval of top-ranked reels for zero-lag background pre-buffering.
     * Returns compact metadata for client-side GPU warming and Cache API preloading.
     *
     * @return list<array<string,mixed>>
     */
    function cj_get_prefetch_reels(mysqli $conn, string $visitorHash = '', int $limit = 2): array
    {
        $limit = max(1, min(10, $limit));
        $reels = ($visitorHash !== '' && function_exists('cj_get_personalized_reels'))
            ? cj_get_personalized_reels($conn, $visitorHash, $limit)
            : cj_get_ranked_reels($conn, $limit, true, 'top');

        $out = [];
        foreach (array_slice($reels, 0, $limit) as $r) {
            $rid = (int)$r['id'];
            $out[] = [
                'id' => $rid,
                'item_key' => 'vid:' . $rid,
                'title' => (string)(!empty($r['admin_note']) ? $r['admin_note'] : ('Jewellery Reel #' . $rid)),
                'video_url' => cj_reel_hd_url($r),
                'preview_url' => cj_reel_preview_url($r),
                'poster_url' => cj_reel_poster_url($r, 540),
                'width' => (int)($r['width'] ?? 1080),
                'height' => (int)($r['height'] ?? 1920),
                'duration' => (float)($r['duration'] ?? 0.0),
                'likes_count' => (int)($r['likes_count'] ?? 0),
                'views_fmt' => cj_format_reel_count(max((int)($r['views'] ?? 0), (int)($r['impressions'] ?? 0))),
                'is_top_viewed' => !empty($r['is_top_viewed']),
            ];
        }
        return $out;
    }
}

if (!function_exists('cj_reel_seo_title')) {
    /** SEO-only title for Google Video Search, Sitemap & VideoObject JSON-LD (never overlaid on video). */
    function cj_reel_seo_title(array $reel): string
    {
        $rid = (int)($reel['id'] ?? 0);
        $note = trim((string)($reel['admin_note'] ?? ''));
        if ($note !== '') {
            return mb_substr($note, 0, 70) . ' — 916 Gold & Silver Reel | ChandraKala Jewellers Khedbrahma';
        }
        return "Handcrafted 916 Gold & Silver Jewellery Reel #{$rid} — ChandraKala Jewellers Khedbrahma";
    }
}

if (!function_exists('cj_reel_seo_description')) {
    /** SEO-only description (145-160 chars) for Google Video Rich Snippets & OpenGraph. */
    function cj_reel_seo_description(array $reel): string
    {
        $rid = (int)($reel['id'] ?? 0);
        $note = trim((string)($reel['admin_note'] ?? ''));
        $prefix = $note !== '' ? "Watch {$note} live at ChandraKala Jewellers, Civil Road, Khedbrahma." : "Watch handcrafted 22K 916 gold & 925 silver jewellery Reel #{$rid} at ChandraKala Jewellers, Khedbrahma.";
        return $prefix . ' Check live per-gram gold rates, reviews & order on WhatsApp.';
    }
}

if (!function_exists('cj_reel_iso_duration')) {
    function cj_reel_iso_duration(array $reel): string
    {
        $sec = max(1, (int)round((float)($reel['duration'] ?? 15)));
        $m = intdiv($sec, 60);
        $s = $sec % 60;
        return $m > 0 ? "PT{$m}M{$s}S" : "PT{$s}S";
    }
}

if (!function_exists('cj_reel_video_object_schema')) {
    /** Complete Google Video Rich Result VideoObject JSON-LD schema. */
    function cj_reel_video_object_schema(array $reel): array
    {
        $rid = (int)($reel['id'] ?? 0);
        $pageUrl = function_exists('cj_abs_url') ? cj_abs_url('showcase?v=' . $rid) : ('https://chandrakalajewellers.in/showcase?v=' . $rid);
        $thumb = (string)($reel['poster_thumb'] ?? cj_reel_poster_url($reel, 720));
        if ($thumb === '') {
            $thumb = function_exists('cj_abs_url') ? cj_abs_url('images/logo.png') : 'https://chandrakalajewellers.in/images/logo.png';
        }
        $contentUrl = (string)($reel['hd_url'] ?? cj_reel_hd_url($reel));
        $uploadDate = function_exists('cj_format_ist_iso') ? cj_format_ist_iso($reel['created_at'] ?? null) : date('c');
        $views = max(1, (int)($reel['display_views'] ?? max((int)($reel['views'] ?? 0), (int)($reel['impressions'] ?? 0))));
        $likes = (int)($reel['likes_count'] ?? 0);
        $comments = (int)($reel['comments_count'] ?? 0);

        $embedUrl = $pageUrl . '&embed=1';

        $thumbnails = [$thumb];
        $thumbWide = (string)($reel['poster_url'] ?? '');
        if ($thumbWide !== '' && !in_array($thumbWide, $thumbnails, true)) {
            $thumbnails[] = $thumbWide;
        }

        return [
            '@type' => 'VideoObject',
            '@id' => $pageUrl . '#video',
            'name' => cj_reel_seo_title($reel),
            'description' => cj_reel_seo_description($reel),
            'thumbnailUrl' => $thumbnails,
            'contentUrl' => $contentUrl,
            'embedUrl' => $embedUrl,
            'url' => $pageUrl,
            'uploadDate' => $uploadDate,
            'duration' => cj_reel_iso_duration($reel),
            'encodingFormat' => 'video/mp4',
            'inLanguage' => 'en-IN',
            'isFamilyFriendly' => true,
            'regionsAllowed' => ['IN'],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'ChandraKala Jewellers',
                'url' => 'https://chandrakalajewellers.in/',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => 'https://chandrakalajewellers.in/images/logo.png',
                ],
            ],
            'interactionStatistic' => [
                [
                    '@type' => 'InteractionCounter',
                    'interactionType' => ['@type' => 'WatchAction'],
                    'userInteractionCount' => $views,
                ],
                [
                    '@type' => 'InteractionCounter',
                    'interactionType' => ['@type' => 'LikeAction'],
                    'userInteractionCount' => $likes,
                ],
                [
                    '@type' => 'InteractionCounter',
                    'interactionType' => ['@type' => 'CommentAction'],
                    'userInteractionCount' => $comments,
                ],
            ],
            'potentialAction' => [
                '@type' => 'WatchAction',
                'target' => $pageUrl,
            ],
        ];
    }
}

if (!function_exists('cj_get_sponsored_ad_slides')) {
    /**
     * Build randomized Sponsored Photo Reel Ads from Gallery Shop (`jewellery_items`)
     * and Design Catalogue (`products`) so the Reels feed NEVER runs out!
     *
     * @return list<array<string,mixed>>
     */
    function cj_get_sponsored_ad_slides(mysqli $conn, string $visitorHash, int $limit = 30, array $excludeKeys = []): array
    {
        cj_ensure_reels_schema($conn);
        require_once __DIR__ . '/img-helper.php';
        require_once __DIR__ . '/pricing.php';

        $stdRates = cj_get_standard_rates($conn);
        $manRates = cj_get_manual_rates($conn);
        $metalNames = ['gold' => '916 Gold', 'silver' => 'Pure Silver', 'silver_925' => '925 Silver'];

        // Preload ad stats & visitor likes in 2 fast queries
        $statsMap = [];
        if ($sr = @$conn->query("SELECT item_key, impressions, likes_count, comments_count, clicks FROM reel_ad_stats")) {
            while ($s = $sr->fetch_assoc()) {
                $statsMap[(string)$s['item_key']] = $s;
            }
        }
        $likedMap = [];
        if ($lst = $conn->prepare("SELECT item_key FROM reel_ad_likes WHERE visitor_hash = ?")) {
            $lst->bind_param('s', $visitorHash);
            $lst->execute();
            $lr = $lst->get_result();
            while ($l = $lr->fetch_assoc()) $likedMap[(string)$l['item_key']] = true;
            $lst->close();
        }

        $excl = array_flip($excludeKeys);
        $shopSlides = [];
        $catSlides = [];

        // 1. Available Shop Gallery Items (priced, ready to buy)
        $sq = @$conn->query("SELECT id, name, category, metal_type, weight, image, manual_price, use_manual_rates FROM jewellery_items WHERE COALESCE(is_sold_out, 0) = 0 AND image IS NOT NULL AND image != '' ORDER BY RAND() LIMIT 40");
        if ($sq) {
            while ($row = $sq->fetch_assoc()) {
                $pid = (int)$row['id'];
                $key = 'shop:' . $pid;
                if (isset($excl[$key])) continue;
                $ratesUse = !empty($row['use_manual_rates']) ? $manRates : $stdRates;
                $price = (isset($row['manual_price']) && $row['manual_price'] !== null && (float)$row['manual_price'] > 0)
                    ? (float)$row['manual_price']
                    : cj_calculate_price((string)($row['metal_type'] ?? 'gold'), (float)($row['weight'] ?? 0), $ratesUse);
                $st = $statsMap[$key] ?? [];
                $metalLbl = $metalNames[$row['metal_type'] ?? ''] ?? 'Jewellery';
                $imgUrl = cj_img_url((string)$row['image'], 900, 'uploads');
                $thumbUrl = cj_img_url((string)$row['image'], 200, 'uploads');
                $productUrl = '/product?id=' . $pid;
                $priceFmt = '₹' . number_format(round($price));

                $shopSlides[] = [
                    'kind' => 'shop_ad',
                    'item_key' => $key,
                    'id' => $pid,
                    'title' => (string)$row['name'],
                    'subtitle' => $metalLbl . (!empty($row['weight']) ? (' · ' . (float)$row['weight'] . 'g') : '') . ' · ' . $priceFmt,
                    'badge_text' => 'Sponsored · ' . ($row['category'] ?: $metalLbl),
                    'cta_label' => 'Shop Now · ' . $priceFmt,
                    'target_url' => $productUrl,
                    'share_url' => (function_exists('cj_abs_url') ? cj_abs_url('product?id=' . $pid) : ('https://chandrakalajewellers.in/product?id=' . $pid)),
                    'image_url' => $imgUrl,
                    'thumb_url' => $thumbUrl,
                    'display_views' => max(1, (int)($st['impressions'] ?? 0)),
                    'likes_count' => (int)($st['likes_count'] ?? 0),
                    'comments_count' => (int)($st['comments_count'] ?? 0),
                    'is_liked' => !empty($likedMap[$key]),
                    'wa_text' => "Namaste ChandraKala Jewellers! I saw {$row['name']} ({$priceFmt}) in your Reels Ad and want to buy / enquire.\nProduct Link: https://chandrakalajewellers.in/product?id={$pid}",
                ];
            }
        }

        // 2. Custom Design Catalogue Items
        $cq = @$conn->query("SELECT id, name, category, image_filename FROM products WHERE image_filename IS NOT NULL AND image_filename != '' ORDER BY RAND() LIMIT 40");
        if ($cq) {
            while ($row = $cq->fetch_assoc()) {
                $cid = (int)$row['id'];
                $key = 'cat:' . $cid;
                if (isset($excl[$key])) continue;
                $st = $statsMap[$key] ?? [];
                $catLbl = trim((string)($row['category'] ?? '')) ?: 'Custom Design';
                $title = trim((string)($row['name'] ?? '')) ?: ("Handcrafted Design #{$cid}");
                $imgUrl = cj_img_url((string)$row['image_filename'], 900, 'catalogue');
                $thumbUrl = cj_img_url((string)$row['image_filename'], 200, 'catalogue');
                $searchQ = preg_replace('/\s*#\d+$/', '', $title);
                $targetUrl = '/Catalogue?q=' . rawurlencode($searchQ ?: $catLbl);

                $catSlides[] = [
                    'kind' => 'cat_ad',
                    'item_key' => $key,
                    'id' => $cid,
                    'title' => $title,
                    'subtitle' => $catLbl . ' · Custom Crafted in 916 Gold / Silver',
                    'badge_text' => 'Sponsored · ' . $catLbl,
                    'cta_label' => 'View Design & Order',
                    'target_url' => $targetUrl,
                    'share_url' => (function_exists('cj_abs_url') ? cj_abs_url('Catalogue?q=' . rawurlencode($searchQ ?: $catLbl)) : ('https://chandrakalajewellers.in' . $targetUrl)),
                    'image_url' => $imgUrl,
                    'thumb_url' => $thumbUrl,
                    'display_views' => max(1, (int)($st['impressions'] ?? 0)),
                    'likes_count' => (int)($st['likes_count'] ?? 0),
                    'comments_count' => (int)($st['comments_count'] ?? 0),
                    'is_liked' => !empty($likedMap[$key]),
                    'wa_text' => "Namaste ChandraKala Jewellers! I saw Catalogue Design \"{$title}\" (#{$cid}) in your Reels Ad and want to order it.\nLink: https://chandrakalajewellers.in{$targetUrl}",
                ];
            }
        }

        // Alternate Shop Product Ads & Catalogue Design Ads
        $merged = [];
        $maxLen = max(count($shopSlides), count($catSlides));
        for ($i = 0; $i < $maxLen; $i++) {
            if (isset($shopSlides[$i])) $merged[] = $shopSlides[$i];
            if (isset($catSlides[$i])) $merged[] = $catSlides[$i];
            if (count($merged) >= $limit) break;
        }
        return array_slice($merged, 0, $limit);
    }
}

if (!function_exists('cj_build_endless_reels_feed')) {
    /**
     * Interleave Personalized Video Reels with Sponsored Gallery & Catalogue Photo Ads:
     * Starts with real Video Reels, inserts a Sponsored Ad every 2 videos, and continues
     * seamlessly with shuffled Shop & Catalogue Ads so the feed NEVER runs out!
     *
     * @return list<array<string,mixed>>
     */
    function cj_build_endless_reels_feed(mysqli $conn, string $visitorHash, int $startVideoId = 0, int $initialSlides = 28): array
    {
        $videos = cj_get_personalized_reels($conn, $visitorHash, 60);
        if ($startVideoId > 0 && count($videos) > 1) {
            foreach ($videos as $k => $v) {
                if ((int)$v['id'] === $startVideoId) {
                    $picked = $videos[$k];
                    unset($videos[$k]);
                    array_unshift($videos, $picked);
                    $videos = array_values($videos);
                    break;
                }
            }
        }
        foreach ($videos as &$v) {
            $v['kind'] = 'video';
            $v['item_key'] = 'vid:' . (int)$v['id'];
        }
        unset($v);

        $ads = cj_get_sponsored_ad_slides($conn, $visitorHash, $initialSlides);

        $feed = [];
        $vIdx = 0;
        $aIdx = 0;
        $vCount = count($videos);
        $aCount = count($ads);

        // Pattern: 2 videos -> 1 Sponsored Ad while videos remain, then keep streaming Ads!
        while (count($feed) < $initialSlides && ($vIdx < $vCount || $aIdx < $aCount)) {
            if ($vIdx < $vCount) {
                $feed[] = $videos[$vIdx++];
            }
            if ($vIdx < $vCount && count($feed) < $initialSlides) {
                $feed[] = $videos[$vIdx++];
            }
            if ($aIdx < $aCount && count($feed) < $initialSlides) {
                $feed[] = $ads[$aIdx++];
            }
            // Once initial videos are done, keep adding Ads seamlessly
            if ($vIdx >= $vCount) {
                while ($aIdx < $aCount && count($feed) < $initialSlides) {
                    $feed[] = $ads[$aIdx++];
                }
            }
        }

        return $feed;
    }
}
