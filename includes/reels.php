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
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_reels_rank (is_active, views, impressions, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS video_reel_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reel_id INT UNSIGNED NOT NULL,
            event_type VARCHAR(24) NOT NULL,
            visitor_hash VARCHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_reel_ev_type_time (reel_id, event_type, created_at),
            KEY idx_reel_ev_dedup (reel_id, event_type, visitor_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
