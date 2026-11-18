<?php
/**
 * Dynamic XML + Image Sitemap for Google Search Console, Bing & AI crawlers.
 * Uses IST (+05:30) lastmod timestamps and includes live product photos.
 */
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/img-helper.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=600');
header('X-Robots-Tag: noindex'); // the sitemap document itself

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
    ['loc' => cj_abs_url('gallery?metal_type=gold'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '0.90'],
    ['loc' => cj_abs_url('gallery?metal_type=silver'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '0.90'],
    ['loc' => cj_abs_url('gallery?metal_type=silver_925'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '0.90'],
    ['loc' => cj_abs_url('Catalogue'), 'lastmod' => $nowIso, 'changefreq' => 'weekly', 'priority' => '0.88'],
    ['loc' => cj_abs_url('about'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.85'],
    ['loc' => cj_abs_url('contact'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.85'],
    ['loc' => cj_abs_url('follow-us'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.80'],
    ['loc' => cj_abs_url('blog'), 'lastmod' => $nowIso, 'changefreq' => 'weekly', 'priority' => '0.80'],
    ['loc' => cj_abs_url('blog-post?slug=jewellery-care-tips'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.72'],
    ['loc' => cj_abs_url('blog-post?slug=choosing-the-right-gold'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.72'],
    ['loc' => cj_abs_url('blog-post?slug=jewellery-for-every-occasion'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.72'],
    ['loc' => cj_abs_url('blog-post?slug=jewellers-in-khedbrahma-guide'), 'lastmod' => $nowIso, 'changefreq' => 'monthly', 'priority' => '0.78'],
    ['loc' => cj_abs_url('llms.txt'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '0.85'],
    ['loc' => cj_abs_url('llms-full.txt'), 'lastmod' => $rateLastMod, 'changefreq' => 'daily', 'priority' => '0.85'],
];

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
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
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
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
  </url>
<?php endforeach; ?>
</urlset>
