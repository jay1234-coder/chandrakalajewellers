<?php
/**
 * Dynamic XML sitemap for Google Search Console & AI crawlers.
 */
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex'); // the sitemap document itself

$urls = [
    ['loc' => cj_abs_url('index1'), 'changefreq' => 'daily', 'priority' => '1.0'],
    ['loc' => cj_abs_url('jewellers-in-khedbrahma'), 'changefreq' => 'weekly', 'priority' => '0.98'],
    ['loc' => cj_abs_url('gold-rate-khedbrahma'), 'changefreq' => 'daily', 'priority' => '0.95'],
    ['loc' => cj_abs_url('gallery'), 'changefreq' => 'daily', 'priority' => '0.95'],
    ['loc' => cj_abs_url('about'), 'changefreq' => 'monthly', 'priority' => '0.9'],
    ['loc' => cj_abs_url('contact'), 'changefreq' => 'monthly', 'priority' => '0.9'],
    ['loc' => cj_abs_url('follow-us'), 'changefreq' => 'monthly', 'priority' => '0.85'],
    ['loc' => cj_abs_url('Catalogue'), 'changefreq' => 'weekly', 'priority' => '0.85'],
    ['loc' => cj_abs_url('blog'), 'changefreq' => 'weekly', 'priority' => '0.8'],
    ['loc' => cj_abs_url('blog-post?slug=jewellery-care-tips'), 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => cj_abs_url('blog-post?slug=choosing-the-right-gold'), 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => cj_abs_url('blog-post?slug=jewellery-for-every-occasion'), 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => cj_abs_url('blog-post?slug=jewellers-in-khedbrahma-guide'), 'changefreq' => 'monthly', 'priority' => '0.75'],
    ['loc' => cj_abs_url('gallery?metal_type=gold'), 'changefreq' => 'daily', 'priority' => '0.85'],
    ['loc' => cj_abs_url('gallery?metal_type=silver'), 'changefreq' => 'daily', 'priority' => '0.85'],
    ['loc' => cj_abs_url('gallery?metal_type=silver_925'), 'changefreq' => 'daily', 'priority' => '0.85'],
];

if (isset($conn) && !$conn->connect_error) {
    $result = $conn->query('SELECT id FROM jewellery_items ORDER BY id DESC LIMIT 500');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $urls[] = [
                'loc' => cj_abs_url('product?id=' . (int)$row['id']),
                'changefreq' => 'weekly',
                'priority' => '0.75',
            ];
        }
    }
}

$today = date('Y-m-d');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= htmlspecialchars($u['loc'], ENT_XML1) ?></loc>
    <lastmod><?= $today ?></lastmod>
    <changefreq><?= $u['changefreq'] ?></changefreq>
    <priority><?= $u['priority'] ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
