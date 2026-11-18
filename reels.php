<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/reels.php';

cj_ensure_reels_schema($conn);

$sort = ($_GET['sort'] ?? 'top') === 'latest' ? 'latest' : 'top';
$cjReelsList = cj_get_ranked_reels($conn, 60, true, $sort);
$cjReelsContext = 'reels';

$totalViewsAll = 0;
foreach ($cjReelsList as $rr) {
    $totalViewsAll += (int)$rr['display_views'];
}

$videoSchemas = [];
foreach (array_slice($cjReelsList, 0, 8) as $rr) {
    $videoSchemas[] = [
        '@type' => 'VideoObject',
        'name' => 'ChandraKala Jewellers Handcrafted Jewellery Reel #' . (int)$rr['id'],
        'description' => 'Watch real handcrafted 916 gold, bridal and 925 silver jewellery reels from ChandraKala Jewellers, Civil Road, Khedbrahma.',
        'thumbnailUrl' => [$rr['poster_thumb'] ?: cj_abs_url('images/logo.png')],
        'contentUrl' => $rr['hd_url'],
        'uploadDate' => function_exists('cj_format_ist_iso') ? cj_format_ist_iso($rr['created_at']) : date('c'),
        'interactionStatistic' => [
            '@type' => 'InteractionCounter',
            'interactionType' => ['@type' => 'WatchAction'],
            'userInteractionCount' => (int)$rr['display_views'],
        ],
    ];
}

$seo = cj_merge_seo([
    'title' => 'Jewellery Reels & Live Showcase | ChandraKala Jewellers Khedbrahma',
    'description' => 'Watch real handcrafted 22K 916 gold, bridal, mangalsutra and 925 silver jewellery reels from ChandraKala Jewellers, Civil Road, Khedbrahma. Tap any reel to order or visit.',
    'canonical' => cj_abs_url('reels'),
    'schema' => array_merge([cj_local_business_schema()], $videoSchemas),
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
<style>
.cj-reels-hero {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 22px;
}
.cj-reels-sort {
    display: inline-flex;
    gap: 6px;
    padding: 4px;
    border-radius: 12px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
}
.cj-reels-sort a {
    padding: 7px 14px;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    color: var(--text-secondary);
    transition: all 0.2s ease;
}
.cj-reels-sort a.is-active {
    background: var(--gold-shine);
    color: #1a1204;
    box-shadow: 0 4px 14px rgba(212, 175, 55, 0.3);
}
.cj-reels-cta-strip {
    margin-top: 36px;
    padding: 24px 20px;
    border-radius: 20px;
    background: var(--bg-card);
    border: 1px solid rgba(212, 175, 55, 0.35);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
</style>
</head>
<body>
<?php $activePage = 'reels'; include 'includes/header.php'; ?>

<main id="main-content" class="container page-enter" style="padding: 24px 16px 72px;">
    <div class="cj-reels-hero">
        <div>
            <p style="font-size:11px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--primary-gold-dark);margin:0 0 6px;">ChandraKala Cinema</p>
            <h1 class="section-heading" style="margin:0;text-align:left;">Jewellery Reels Showcase</h1>
            <p style="margin:6px 0 0;font-size:13px;color:var(--text-secondary);">
                Tap any moving reel to like, comment, order directly, or visit our Khedbrahma showroom.
                <?php if ($totalViewsAll > 0): ?>
                    <strong style="color:var(--primary-gold-dark);">&nbsp;·&nbsp;<?= htmlspecialchars(cj_format_reel_count($totalViewsAll)) ?>+ views</strong>
                <?php endif; ?>
            </p>
        </div>
        <div class="cj-reels-sort" role="navigation" aria-label="Sort reels">
            <a href="/reels?sort=top" class="<?= $sort === 'top' ? 'is-active' : '' ?>">👑 Top Viewed</a>
            <a href="/reels?sort=latest" class="<?= $sort === 'latest' ? 'is-active' : '' ?>">✨ Latest</a>
        </div>
    </div>

    <?php if (empty($cjReelsList)): ?>
        <div style="text-align:center;padding:56px 20px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:20px;">
            <p style="font-size:18px;font-weight:700;font-family:'Playfair Display',serif;margin:0 0 8px;">New Reels Arriving Soon</p>
            <p style="font-size:13px;color:var(--text-secondary);max-width:440px;margin:0 auto 20px;">Explore our live 916 gold &amp; 925 silver collections or browse custom handcrafted designs while we upload fresh reels.</p>
            <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                <a href="/gallery" class="btn">Shop Collection</a>
                <a href="/Catalogue" class="btn btn-secondary">Explore Catalogue</a>
            </div>
        </div>
    <?php else: ?>
        <?php include __DIR__ . '/includes/reels-bento.php'; ?>
    <?php endif; ?>

    <section class="cj-reels-cta-strip">
        <div>
            <h2 style="font-family:'Playfair Display',serif;font-size:20px;margin:0 0 4px;">Love a Design? Customize It in Your Weight &amp; Budget</h2>
            <p style="margin:0;font-size:13px;color:var(--text-secondary);">Visit ChandraKala Jewellers, Opp. Bhoomi Complex, Civil Road, Khedbrahma · 100% BIS 916 Hallmarked</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="https://wa.me/919427080359?text=<?= urlencode('Namaste ChandraKala Jewellers! I saw your Jewellery Reels and would like to enquire about custom designs & live gold rates.') ?>" target="_blank" rel="noopener" class="btn">WhatsApp Showroom</a>
            <a href="/contact" class="btn btn-secondary">Showroom Directions</a>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
</body>
</html>
