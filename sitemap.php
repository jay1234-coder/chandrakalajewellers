<?php
/**
 * Dynamic XML + Google Image + Google Video Sitemap for Search Engines & AI Crawlers.
 * Uses IST (+05:30) lastmod timestamps and includes live products, category pages,
 * Reels Showcase, and every active video reel with full <video:video> search metadata.
 */
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/img-helper.php';
require_once __DIR__ . '/includes/reels.php';
require_once __DIR__ . '/includes/catalog.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=600');
header('X-Robots-Tag: noindex');

$nowIso = function_exists('cj_format_ist_iso') ? cj_format_ist_iso() : date('c');
$rateLastMod = $nowIso;

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
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
    ['loc' => cj_abs_url('Catalogue'), 'lastmod' => $nowIso, 'changefreq' => 'weekly', 'priority' => '0.90'],
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

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    // 1. Dynamic Product Type / Category Filter Pages
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
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= htmlspecialchars($u['loc'], ENT_XML1) ?></loc>
    <lastmod><?= htmlspecialchars($u['lastmod'], ENT_XML1) ?></lastmod>
    <changefreq><?= $u['changefreq'] ?></changefreq>
    <priority><?= $u['priority'] ?></priority>
<?php if (!empty($u['image'])): ?>
    <image:image>
      <image:loc><?= htmlspecialchars($u['image']['loc'], ENT_XML1) ?></image:loc>
      <image:title><?= htmlspecialchars($u['image']['title'], ENT_XML1) ?></image:title>
    </image:image>
<?php endif; ?>
<?php if (!empty($u['video'])): $v = $u['video']; ?>
    <video:video>
      <video:thumbnail_loc><?= htmlspecialchars($v['thumbnail_loc'], ENT_XML1) ?></video:thumbnail_loc>
      <video:title><?= htmlspecialchars($v['title'], ENT_XML1) ?></video:title>
      <video:description><?= htmlspecialchars($v['description'], ENT_XML1) ?></video:description>
      <video:content_loc><?= htmlspecialchars($v['content_loc'], ENT_XML1) ?></video:content_loc>
      <video:player_loc><?= htmlspecialchars($v['player_loc'], ENT_XML1) ?></video:player_loc>
      <video:duration><?= (int)$v['duration'] ?></video:duration>
      <video:view_count><?= (int)$v['view_count'] ?></video:view_count>
      <video:publication_date><?= htmlspecialchars($v['publication_date'], ENT_XML1) ?></video:publication_date>
      <video:family_friendly>yes</video:family_friendly>
<?php foreach ($v['tags'] as $tg): ?>
      <video:tag><?= htmlspecialchars($tg, ENT_XML1) ?></video:tag>
<?php endforeach; ?>
    </video:video>
<?php endif; ?>
  </url>
<?php endforeach; ?>
</urlset>
