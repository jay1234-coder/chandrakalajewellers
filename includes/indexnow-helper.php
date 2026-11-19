<?php
/**
 * ChandraKala Jewellers — SEO Auto-Indexing & Real-Time Dynamic Sitemap Engine.
 *
 * Automatically keeps sitemap.xml up-to-date and instantly notifies:
 * - Bing, Yandex, Seznam, Naver, Copilot & ChatGPT Search via IndexNow Protocol
 * - Google & Bing via Sitemap Ping gateways
 * Whenever products, video reels, catalogue designs, or live rates are added or modified.
 */

if (!function_exists('cj_build_sitemap_xml')) {
    /**
     * Build full XML string compliant with sitemaps.org, Google Image & Video Sitemap schemas.
     */
    function cj_build_sitemap_xml(mysqli $conn): string
    {
        require_once __DIR__ . '/seo.php';
        require_once __DIR__ . '/img-helper.php';
        require_once __DIR__ . '/reels.php';
        require_once __DIR__ . '/catalog.php';

        $nowIso = function_exists('cj_format_ist_iso') ? cj_format_ist_iso() : date('c');
        $rateLastMod = $nowIso;

        if ($conn instanceof mysqli && !$conn->connect_error) {
            $rq = $conn->query("SELECT updated_at FROM rates ORDER BY updated_at DESC LIMIT 1");
            if ($rq && ($rr = $rq->fetch_assoc()) && !empty($rr['updated_at'])) {
                $rateLastMod = cj_format_ist_iso($rr['updated_at']);
            }
        }

        $urls = [
            ['loc' => cj_abs_url('index1'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => cj_abs_url('gold-rate-khedbrahma'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '0.98'],
            ['loc' => cj_abs_url('jewellers-in-khedbrahma'), 'lastmod' => $nowIso, 'changefreq' => 'weekly', 'priority' => '0.98'],
            ['loc' => cj_abs_url('gallery'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '0.95'],
            ['loc' => cj_abs_url('gallery?metal_type=gold'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '0.92'],
            ['loc' => cj_abs_url('gallery?metal_type=silver'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '0.90'],
            ['loc' => cj_abs_url('gallery?metal_type=silver_925'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '0.90'],
            ['loc' => cj_abs_url('reels'), 'lastmod' => $nowIso, 'changefreq' => 'daily', 'priority' => '0.95'],
            ['loc' => cj_abs_url('reels?view=grid'), 'lastmod' => $nowIso, 'changefreq' => 'daily', 'priority' => '0.88'],
            ['loc' => cj_abs_url('Catalogue'), 'lastmod' => $nowIso, 'changefreq' => 'weekly', 'priority' => '0.92'],
            ['loc' => cj_abs_url('about'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.85'],
            ['loc' => cj_abs_url('contact'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.85'],
            ['loc' => cj_abs_url('follow-us'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.80'],
            ['loc' => cj_abs_url('blog'), 'lastmod' => $nowIso, 'changefreq' => 'weekly', 'priority' => '0.82'],
            ['loc' => cj_abs_url('blog-post?slug=jewellery-care-tips'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.72'],
            ['loc' => cj_abs_url('blog-post?slug=choosing-the-right-gold'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.72'],
            ['loc' => cj_abs_url('blog-post?slug=jewellery-for-every-occasion'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.72'],
            ['loc' => cj_abs_url('blog-post?slug=jewellers-in-khedbrahma-guide'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.78'],
            ['loc' => cj_abs_url('llms.txt'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '0.85'],
            ['loc' => cj_abs_url('llms-full.txt'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '0.85'],
        ];

        if ($conn instanceof mysqli && !$conn->connect_error) {
            // 1. Dynamic Stock Product Type / Category Filter Pages
            if (function_exists('cj_get_product_types')) {
                foreach (cj_get_product_types($conn) as $pt) {
                    $ptName = trim((string)($pt['name'] ?? ''));
                    if ($ptName === '') continue;
                    $urls[] = [
                        'loc' => cj_abs_url('gallery?category=' . urlencode($ptName)),
                        'lastmod' => $rateLastMod,
                        'changefreq' => 'weekly',
                        'priority' => '0.82',
                    ];
                }
            }

            // 2. All Active Video Reels with Full Google Video Sitemap (<video:video>) Metadata
            $activeReels = cj_get_ranked_reels($conn, 250, true, 'top');
            foreach ($activeReels as $r) {
                $rid = (int)$r['id'];
                $pubIso = !empty($r['created_at']) ? cj_format_ist_iso($r['created_at']) : $nowIso;
                $thumb = (string)($r['poster_thumb'] ?: cj_abs_url('images/logo.png'));
                $contentUrl = (string)$r['hd_url'];
                $pageUrl = cj_abs_url('showcase?v=' . $rid);
                $dur = max(1, (int)round((float)($r['duration'] ?? 15)));
                $views = max(1, (int)$r['display_views']);
                $tags = ['916 Gold Jewellery', 'ChandraKala Jewellers', 'Khedbrahma Jewellers', 'Jewellery Reels'];
                foreach (cj_reel_extract_style_tokens((string)($r['admin_note'] ?? '')) as $tok) {
                    $tags[] = ucfirst($tok);
                }
                $tags = array_slice(array_values(array_unique($tags)), 0, 10);

                $urls[] = [
                    'loc' => $pageUrl,
                    'lastmod' => $pubIso,
                    'changefreq' => 'weekly',
                    'priority' => !empty($r['is_top_viewed']) ? '0.94' : '0.88',
                    'video' => [
                        'thumbnail_loc' => $thumb,
                        'title' => cj_reel_seo_title($r),
                        'description' => cj_reel_seo_description($r),
                        'content_loc' => $contentUrl,
                        'player_loc' => $pageUrl,
                        'duration' => $dur,
                        'view_count' => $views,
                        'publication_date' => $pubIso,
                        'tags' => $tags,
                    ],
                ];
            }

            // 3. All Live Shop Products with Google Image Sitemap (<image:image>)
            $result = $conn->query('SELECT id, name, image, metal_type, weight, created_at FROM jewellery_items ORDER BY id DESC LIMIT 1000');
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $itemLastMod = !empty($row['created_at']) ? cj_format_ist_iso($row['created_at']) : $rateLastMod;
                    $imgUrl = !empty($row['image']) ? cj_img_url($row['image'], 1200, 'uploads') : '';
                    $entry = [
                        'loc' => cj_abs_url('product?id=' . (int)$row['id']),
                        'lastmod' => $itemLastMod,
                        'changefreq' => 'weekly',
                        'priority' => '0.80',
                    ];
                    if ($imgUrl !== '' && preg_match('#^https?://#i', $imgUrl)) {
                        $entry['image'] = [
                            'loc' => $imgUrl,
                            'title' => trim((string)$row['name']) . ' — ChandraKala Jewellers Khedbrahma',
                        ];
                    }
                    $urls[] = $entry;
                }
            }

            // 4. Custom Design Catalogue Categories & Products (<image:image>)
            $hasCatCol = false;
            $colChk = @$conn->query("SHOW COLUMNS FROM products LIKE 'category'");
            if ($colChk && $colChk->num_rows > 0) {
                $hasCatCol = true;
            }

            if ($hasCatCol) {
                $cCatQ = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND TRIM(category) != '' ORDER BY category ASC");
                if ($cCatQ) {
                    while ($crow = $cCatQ->fetch_assoc()) {
                        $cName = trim((string)$crow['category']);
                        if ($cName === '' || strtolower($cName) === 'all') continue;
                        $urls[] = [
                            'loc' => cj_abs_url('Catalogue?cat=' . urlencode($cName)),
                            'lastmod' => $nowIso,
                            'changefreq' => 'weekly',
                            'priority' => '0.84',
                        ];
                    }
                }
            }

            $cProdQ = $conn->query("SELECT id, name, description, image_filename, category FROM products ORDER BY id DESC LIMIT 1000");
            if ($cProdQ) {
                while ($cp = $cProdQ->fetch_assoc()) {
                    $cImg = !empty($cp['image_filename']) ? cj_img_url($cp['image_filename'], 1400, 'catalogue') : '';
                    $cpName = trim((string)$cp['name']) ?: 'Custom Jewellery Design';
                    $cEntry = [
                        'loc' => cj_abs_url('Catalogue?id=' . (int)$cp['id']),
                        'lastmod' => $nowIso,
                        'changefreq' => 'weekly',
                        'priority' => '0.78',
                    ];
                    if ($cImg !== '' && preg_match('#^https?://#i', $cImg)) {
                        $cEntry['image'] = [
                            'loc' => $cImg,
                            'title' => $cpName . ' — ChandraKala Jewellers Custom Catalogue',
                        ];
                    }
                    $urls[] = $cEntry;
                }
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml"' . "\n";
        $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' . "\n";
        $xml .= '        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

        foreach ($urls as $u) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1) . '</lastmod>' . "\n";
            $xml .= '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $u['priority'] . '</priority>' . "\n";

            if (!empty($u['image'])) {
                $xml .= '    <image:image>' . "\n";
                $xml .= '      <image:loc>' . htmlspecialchars($u['image']['loc'], ENT_XML1) . '</image:loc>' . "\n";
                if (!empty($u['image']['title'])) {
                    $xml .= '      <image:title>' . htmlspecialchars($u['image']['title'], ENT_XML1) . '</image:title>' . "\n";
                }
                $xml .= '    </image:image>' . "\n";
            }

            if (!empty($u['video'])) {
                $v = $u['video'];
                $xml .= '    <video:video>' . "\n";
                $xml .= '      <video:thumbnail_loc>' . htmlspecialchars($v['thumbnail_loc'], ENT_XML1) . '</video:thumbnail_loc>' . "\n";
                $xml .= '      <video:title>' . htmlspecialchars($v['title'], ENT_XML1) . '</video:title>' . "\n";
                $xml .= '      <video:description>' . htmlspecialchars($v['description'], ENT_XML1) . '</video:description>' . "\n";
                $xml .= '      <video:content_loc>' . htmlspecialchars($v['content_loc'], ENT_XML1) . '</video:content_loc>' . "\n";
                $xml .= '      <video:player_loc>' . htmlspecialchars($v['player_loc'], ENT_XML1) . '</video:player_loc>' . "\n";
                $xml .= '      <video:duration>' . (int)$v['duration'] . '</video:duration>' . "\n";
                $xml .= '      <video:view_count>' . (int)$v['view_count'] . '</video:view_count>' . "\n";
                $xml .= '      <video:publication_date>' . htmlspecialchars($v['publication_date'], ENT_XML1) . '</video:publication_date>' . "\n";
                $xml .= '      <video:family_friendly>yes</video:family_friendly>' . "\n";
                foreach ($v['tags'] as $tg) {
                    $xml .= '      <video:tag>' . htmlspecialchars($tg, ENT_XML1) . '</video:tag>' . "\n";
                }
                $xml .= '    </video:video>' . "\n";
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>' . "\n";
        return $xml;
    }
}

if (!function_exists('cj_rebuild_sitemap_file')) {
    /**
     * Rebuild and save sitemap.xml to disk (if writable).
     */
    function cj_rebuild_sitemap_file(?mysqli $conn = null): bool
    {
        if (!$conn) {
            global $conn;
        }
        if (!$conn || !($conn instanceof mysqli) || $conn->connect_error) {
            return false;
        }

        $xml = cj_build_sitemap_xml($conn);
        $saved = false;

        $targetFile = dirname(__DIR__) . '/sitemap.xml';
        if (is_writable(dirname($targetFile)) || (is_file($targetFile) && is_writable($targetFile))) {
            $saved = (@file_put_contents($targetFile, $xml) !== false);
        }

        // Also save in /tmp for serverless runtime
        $tmpFile = sys_get_temp_dir() . '/sitemap.xml';
        @file_put_contents($tmpFile, $xml);

        return $saved;
    }
}

if (!function_exists('cj_ping_search_engines')) {
    /**
     * Ping Google and Bing sitemap gateways.
     */
    function cj_ping_search_engines(string $sitemapUrl = 'https://chandrakalajewellers.in/sitemap.xml'): array
    {
        $results = [];
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'curl not available'];
        }

        $endpoints = [
            'google' => 'https://www.google.com/ping?sitemap=' . rawurlencode($sitemapUrl),
            'bing' => 'https://www.bing.com/ping?sitemap=' . rawurlencode($sitemapUrl),
        ];

        foreach ($endpoints as $engine => $pingUrl) {
            $ch = @curl_init($pingUrl);
            if ($ch) {
                @curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 2,
                    CURLOPT_TIMEOUT => 3,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ChandraKalaJewellersBot/2.0)',
                ]);
                $resp = @curl_exec($ch);
                $code = (int)@curl_getinfo($ch, CURLINFO_HTTP_CODE);
                @curl_close($ch);
                $results[$engine] = ['status' => $code, 'ok' => ($code >= 200 && $code < 400)];
            }
        }
        return $results;
    }
}

if (!function_exists('cj_auto_ping_indexnow')) {
    /**
     * Submit changed URLs to IndexNow (Bing, Yandex, Seznam, Naver, Copilot, ChatGPT Search).
     *
     * @param list<string> $extraUrls
     */
    function cj_auto_ping_indexnow(array $extraUrls = [], bool $force = false): array
    {
        if (function_exists('cj_is_local_host') && cj_is_local_host()) {
            return ['ok' => true, 'local' => true, 'urls' => $extraUrls];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'curl not available'];
        }

        $lockFile = sys_get_temp_dir() . '/cj_indexnow_last_ping.txt';
        if (!$force && is_file($lockFile) && (time() - (int)@filemtime($lockFile)) < 180) {
            return ['ok' => true, 'throttled' => true];
        }
        @file_put_contents($lockFile, (string)time());

        $indexNowKey = 'b7e4c91a2f8d4e6ab3c5d0e9f1a2b3c4';
        $host = 'chandrakalajewellers.in';
        $keyLocation = 'https://' . $host . '/' . $indexNowKey . '.txt';

        $baseUrls = [
            'https://' . $host . '/index1',
            'https://' . $host . '/gold-rate-khedbrahma',
            'https://' . $host . '/jewellers-in-khedbrahma',
            'https://' . $host . '/gallery',
            'https://' . $host . '/Catalogue',
            'https://' . $host . '/reels',
            'https://' . $host . '/llms.txt',
            'https://' . $host . '/llms-full.txt',
            'https://' . $host . '/sitemap.xml',
        ];
        $urls = array_values(array_unique(array_merge($baseUrls, $extraUrls)));

        $payload = json_encode([
            'host' => $host,
            'key' => $indexNowKey,
            'keyLocation' => $keyLocation,
            'urlList' => $urls,
        ], JSON_UNESCAPED_SLASHES);

        if (!$payload) {
            return ['ok' => false, 'error' => 'Payload JSON error'];
        }

        $ch = @curl_init('https://api.indexnow.org/indexnow');
        if (!$ch) {
            return ['ok' => false, 'error' => 'Could not init curl'];
        }
        @curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = @curl_exec($ch);
        $code = (int)@curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);

        return [
            'ok' => ($code >= 200 && $code < 300) || $code === 202,
            'status' => $code,
            'count' => count($urls),
            'submitted_urls' => $urls,
        ];
    }
}

if (!function_exists('cj_trigger_auto_indexing')) {
    /**
     * Complete Auto-Indexing Pipeline:
     * 1. Rebuilds and synchronizes sitemap.xml
     * 2. Pings IndexNow (Bing, Yandex, Seznam, Naver, Copilot, ChatGPT Search)
     * 3. Pings Google and Bing Sitemap gateways
     * 4. Logs to Activity Log
     *
     * @param list<string> $extraUrls
     */
    function cj_trigger_auto_indexing(?mysqli $conn = null, array $extraUrls = [], string $source = 'upload', bool $force = true): array
    {
        if (!$conn) {
            global $conn;
        }
        $db = $conn;

        $sitemapUpdated = false;
        if ($db instanceof mysqli && !$db->connect_error) {
            $sitemapUpdated = cj_rebuild_sitemap_file($db);
        }

        $indexNowRes = cj_auto_ping_indexnow($extraUrls, $force);
        $pingRes = cj_ping_search_engines('https://chandrakalajewellers.in/sitemap.xml');

        if ($db instanceof mysqli && !$db->connect_error && function_exists('cj_log_activity')) {
            $details = $source . ' (' . count($extraUrls) . ' specific url(s), sitemap rebuilt)';
            cj_log_activity($db, 'auto_indexing', $details);
        }

        return [
            'ok' => true,
            'sitemap_updated' => $sitemapUpdated,
            'indexnow' => $indexNowRes,
            'search_engines' => $pingRes,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}
