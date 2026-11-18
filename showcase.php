<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/img-helper.php';
require_once __DIR__ . '/includes/reels.php';
require_once __DIR__ . '/includes/customers.php';

cj_ensure_reels_schema($conn);

$allReels = cj_get_ranked_reels($conn, 60, true, 'top');
$requestedId = (int)($_GET['v'] ?? $_GET['id'] ?? 0);

$reel = $requestedId > 0 ? cj_get_reel_by_id($conn, $requestedId) : null;
if (!$reel && !empty($allReels)) {
    $reel = $allReels[0];
}

// Determine Prev / Next reel in ranked order
$prevId = 0;
$nextId = 0;
$otherReels = [];
if ($reel && !empty($allReels)) {
    $ids = array_map(static fn($r) => (int)$r['id'], $allReels);
    $pos = array_search((int)$reel['id'], $ids, true);
    if ($pos !== false && count($ids) > 1) {
        $prevId = $ids[($pos - 1 + count($ids)) % count($ids)];
        $nextId = $ids[($pos + 1) % count($ids)];
    }
    foreach ($allReels as $r) {
        if ((int)$r['id'] !== (int)$reel['id']) {
            $otherReels[] = $r;
        }
    }
}

$visitorHash = cj_reel_visitor_hash();
$isLiked = $reel ? cj_reel_is_liked_by_visitor($conn, (int)$reel['id'], $visitorHash) : false;
$reelComments = $reel ? cj_get_reel_comments($conn, (int)$reel['id'], 50) : [];
$custSession = function_exists('cj_customer_session') ? cj_customer_session() : null;

// Live Gold & Silver rates (all stored per 1 gram, matching index1.php)
$rate = cj_get_standard_rates($conn);
$showGold = cj_setting('show_gold_rate', '1', $conn) === '1';
$showSilver = cj_setting('show_silver_rate', '1', $conn) === '1';
$showSilver925 = cj_setting('show_silver_925_rate', '1', $conn) === '1';
$rateGold1g = (float)($rate['gold_rate'] ?? 0);
$rateSilver1g = (float)($rate['silver_rate'] ?? 0);
$rateSilver925_1g = (float)($rate['silver_925_rate'] ?? 0);
$rateUpdatedAt = $rate['updated_at'] ?? null;
$rateUpdatedIst = !empty($rateUpdatedAt) ? cj_format_ist($rateUpdatedAt, 'd M Y, h:i A') : cj_format_ist(null, 'd M Y, h:i A');

// Live IST Showroom Open Status (10:00 AM - 8:00 PM IST, Mon-Sun)
$istHour = (int)date('G');
$isShowroomOpen = ($istHour >= 10 && $istHour < 20);

// Pull verified store reviews (from product_reviews) as extra social proof
$storeReviews = [];
$srRes = @$conn->query("SELECT customer_name, rating, comment, created_at FROM product_reviews WHERE is_approved = 1 ORDER BY id DESC LIMIT 6");
if ($srRes) {
    while ($sr = $srRes->fetch_assoc()) {
        $storeReviews[] = $sr;
    }
}

// Pull 4 ready-to-buy shop pieces to spark purchase interest
$shopPicks = [];
$spRes = @$conn->query("SELECT * FROM jewellery_items WHERE COALESCE(is_sold_out, 0) = 0 ORDER BY is_favourite DESC, id DESC LIMIT 4");
if ($spRes) {
    while ($sp = $spRes->fetch_assoc()) {
        $shopPicks[] = $sp;
    }
}

$rid = $reel ? (int)$reel['id'] : 0;
$reelShareUrl = cj_abs_url('showcase?v=' . $rid);
$waOrderMsg = "Namaste ChandraKala Jewellers! I loved Jewellery Reel #{$rid} on your website and would like to order / know the weight, live price & availability.\nLink: {$reelShareUrl}";
$waOrderUrl = 'https://wa.me/919427080359?text=' . rawurlencode($waOrderMsg);

$scSchemas = [
    cj_local_business_schema(),
    cj_breadcrumb_schema([
        ['name' => 'Home', 'url' => cj_abs_url('index1')],
        ['name' => 'Jewellery Reels', 'url' => cj_abs_url('reels')],
        ['name' => $rid > 0 ? "Showcase Reel #{$rid}" : 'Showroom Showcase', 'url' => $reelShareUrl],
    ]),
];
$scExtraHead = '';
$scOgImage = cj_abs_url('images/logo.png');
if ($reel) {
    $scSchemas[] = cj_reel_video_object_schema($reel);
    if (!empty($reel['poster_thumb'])) {
        $scOgImage = (string)$reel['poster_thumb'];
    }
    $hdEsc = htmlspecialchars((string)$reel['hd_url'], ENT_QUOTES, 'UTF-8');
    $scExtraHead = '<meta property="og:video" content="' . $hdEsc . '">' . "\n"
        . '<meta property="og:video:secure_url" content="' . $hdEsc . '">' . "\n"
        . '<meta property="og:video:type" content="video/mp4">' . "\n";
}

$seo = cj_merge_seo([
    'title' => $reel ? cj_reel_seo_title($reel) : 'Showroom Showcase | ChandraKala Jewellers Khedbrahma',
    'description' => $reel ? cj_reel_seo_description($reel) : 'Watch handcrafted 916 gold & silver jewellery reels from ChandraKala Jewellers, Civil Road, Khedbrahma. Check live per-gram gold rates, reviews & order on WhatsApp.',
    'keywords' => 'ChandraKala Jewellers Khedbrahma, 916 gold jewellery video, hallmark gold reel Gujarat, Sabarkantha jewellers, bridal gold jewellery video',
    'canonical' => $reelShareUrl,
    'og_type' => $reel ? 'video.other' : 'website',
    'og_image' => $scOgImage,
    'extra_head' => $scExtraHead,
    'schema' => $scSchemas,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
<style>
.cj-sc-wrap {
    max-width: 1180px;
    margin: 0 auto;
    padding: 18px 16px 76px;
}
.cj-sc-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 24px;
    align-items: start;
}
@media (min-width: 960px) {
    .cj-sc-grid {
        grid-template-columns: minmax(0, 440px) minmax(0, 1fr);
        gap: 28px;
    }
}
.cj-sc-player-card {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    background: #0a0c12;
    border: 1.5px solid rgba(212, 175, 55, 0.45);
    box-shadow: 0 22px 54px rgba(0, 0, 0, 0.32), 0 0 36px rgba(212, 175, 55, 0.14);
    aspect-ratio: 9 / 15;
    max-height: 76vh;
    width: 100%;
    margin: 0 auto;
}
.cj-sc-player-card video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    background: #0a0c12;
}
.cj-sc-top-bar {
    position: absolute;
    top: 14px;
    left: 14px;
    right: 14px;
    z-index: 5;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    pointer-events: none;
}
.cj-sc-top-bar > * { pointer-events: auto; }
.cj-sc-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    background: rgba(12, 14, 18, 0.68);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.18);
}
.cj-sc-pill.is-gold {
    background: linear-gradient(135deg, #fff3c4 0%, #e5be54 48%, #b88b1e 100%);
    color: #1a1204;
    border-color: transparent;
}
.cj-sc-ctrl-btn {
    border: 1px solid rgba(255,255,255,0.22);
    background: rgba(12, 14, 18, 0.68);
    backdrop-filter: blur(10px);
    color: #fff;
    border-radius: 999px;
    padding: 7px 12px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.cj-sc-nav-arrows {
    position: absolute;
    bottom: 16px;
    left: 14px;
    right: 14px;
    z-index: 5;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    pointer-events: none;
}
.cj-sc-nav-arrows > * { pointer-events: auto; }
.cj-sc-nav-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 13px;
    border-radius: 999px;
    background: rgba(12, 14, 18, 0.72);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
}
.cj-sc-nav-link:hover {
    border-color: #f0d78c;
    color: #fde68a;
}

/* Interactive Action Bar: Like · Comment · Order · Share */
.cj-sc-actions {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin-top: 14px;
}
.cj-sc-act-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 11px 6px;
    border-radius: 16px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.22s ease;
}
.cj-sc-act-btn:hover {
    border-color: var(--primary-gold);
    transform: translateY(-2px);
}
.cj-sc-act-btn.is-liked {
    background: rgba(255, 48, 64, 0.1);
    border-color: rgba(255, 48, 64, 0.55);
    color: #ff3040;
}
.cj-sc-act-btn .cj-sc-heart-path {
    fill: transparent;
    stroke: currentColor;
    stroke-width: 2;
    transition: fill 0.18s ease, stroke 0.18s ease;
}
.cj-sc-act-btn.is-liked .cj-sc-heart-path {
    fill: #ff3040;
    stroke: #ff3040;
}
.cj-sc-act-btn.is-order {
    background: var(--gold-shine);
    color: #1a1204;
    border-color: transparent;
    box-shadow: 0 8px 22px rgba(212, 175, 55, 0.32);
}
.cj-sc-act-icon {
    width: 22px;
    height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.cj-sc-act-icon svg {
    width: 21px;
    height: 21px;
    display: block;
}

/* Right Column Showroom Contact & Conversion Hub */
.cj-sc-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 20px;
    margin-bottom: 18px;
    box-shadow: var(--shadow-sm);
}
.cj-sc-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 10px;
}
.cj-sc-status-pill.is-open {
    background: rgba(16, 185, 129, 0.14);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.35);
}
.cj-sc-status-pill.is-closed {
    background: rgba(245, 158, 11, 0.14);
    color: #d97706;
    border: 1px solid rgba(245, 158, 11, 0.35);
}
.cj-sc-contact-btns {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    margin-top: 14px;
}
@media (min-width: 520px) {
    .cj-sc-contact-btns {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
.cj-sc-cbtn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 12px 14px;
    border-radius: 14px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: transform 0.2s ease, opacity 0.2s ease;
}
.cj-sc-cbtn:hover { transform: translateY(-1px); }
.cj-sc-cbtn.is-wa { background: #16a34a; color: #fff; }
.cj-sc-cbtn.is-call { background: var(--gold-shine); color: #1a1204; }
.cj-sc-cbtn.is-map { background: var(--bg-light); color: var(--text-primary); border: 1px solid var(--border-color); }

.cj-sc-rates-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 12px;
}
.cj-sc-rate-box {
    padding: 11px 10px;
    border-radius: 14px;
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    text-align: center;
}
.cj-sc-rate-box small {
    display: block;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-secondary);
}
.cj-sc-rate-box strong {
    display: block;
    font-size: 15px;
    font-weight: 800;
    color: var(--primary-gold-dark);
    margin-top: 3px;
}
.cj-sc-trust-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
@media (min-width: 560px) {
    .cj-sc-trust-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
.cj-sc-trust-item {
    padding: 12px 10px;
    border-radius: 14px;
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    text-align: center;
    font-size: 11px;
    font-weight: 600;
}
.cj-sc-trust-item span { display: block; font-size: 18px; margin-bottom: 4px; }

.cj-sc-form-grid {
    display: grid;
    gap: 10px;
}
.cj-sc-input {
    width: 100%;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    background: var(--bg-light);
    color: var(--text-primary);
    font: inherit;
    font-size: 13px;
}
.cj-sc-stars {
    display: inline-flex;
    gap: 4px;
}
.cj-sc-star-btn {
    border: none;
    background: transparent;
    font-size: 22px;
    cursor: pointer;
    color: #9ca3af;
    padding: 2px;
}
.cj-sc-star-btn.is-on { color: #f59e0b; }

.cj-sc-comment-item {
    padding: 12px 14px;
    border-radius: 14px;
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    margin-bottom: 10px;
}
.cj-sc-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.cj-sc-modal.is-open { display: flex; }
.cj-sc-modal-box {
    width: 100%;
    max-width: 440px;
    border-radius: 22px;
    background: var(--bg-card);
    border: 1px solid rgba(212, 175, 55, 0.4);
    padding: 22px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
}
</style>
</head>
<body>
<?php $activePage = 'reels'; include 'includes/header.php'; ?>

<main id="main-content" class="cj-sc-wrap page-enter">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
        <a href="/reels" style="font-size:13px;font-weight:700;color:var(--primary-gold-dark);text-decoration:none;">&larr; Back to All Reels</a>
        <?php if ($reel): ?>
            <span style="font-size:12px;color:var(--text-secondary);">
                Reel #<?= $rid ?> · <strong id="scTopViews"><?= htmlspecialchars(cj_format_reel_count((int)$reel['display_views'])) ?></strong> views
            </span>
        <?php endif; ?>
    </div>

    <?php if (!$reel): ?>
        <div class="cj-sc-card" style="text-align:center;padding:48px 20px;">
            <h1 style="font-family:'Playfair Display',serif;margin:0 0 8px;">ChandraKala Showroom Showcase</h1>
            <p style="color:var(--text-secondary);margin:0 0 18px;">This reel is no longer available. Visit our live jewellery collection or showroom!</p>
            <a href="/gallery" class="btn">Explore Jewellery</a>
        </div>
    <?php else: ?>
    <div class="cj-sc-grid">
        <!-- LEFT: HD Cinema Reel Player + Like / Comment / Order / Share Bar -->
        <div>
            <div class="cj-sc-player-card">
                <video id="cjShowcaseVideo"
                       src="<?= htmlspecialchars((string)$reel['hd_url'], ENT_QUOTES, 'UTF-8') ?>"
                       <?php if (!empty($reel['poster_thumb'])): ?>poster="<?= htmlspecialchars((string)$reel['poster_thumb'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                       autoplay
                       muted
                       loop
                       playsinline
                       webkit-playsinline></video>

                <div class="cj-sc-top-bar">
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php if (!empty($reel['is_top_viewed'])): ?>
                            <span class="cj-sc-pill is-gold">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/></svg>
                                Top Viewed
                            </span>
                        <?php endif; ?>
                        <span class="cj-sc-pill">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                            <span id="scBadgeViews"><?= htmlspecialchars(cj_format_reel_count((int)$reel['display_views'])) ?></span>
                        </span>
                    </div>
                    <button type="button" id="cjMuteToggle" class="cj-sc-ctrl-btn" aria-label="Toggle sound">
                        <svg id="cjScMuteSvg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4V5z"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
                        <span id="cjScMuteLbl">Muted</span>
                    </button>
                </div>

                <?php if ($prevId > 0 && $nextId > 0): ?>
                <div class="cj-sc-nav-arrows">
                    <a href="/showcase?v=<?= $prevId ?>" class="cj-sc-nav-link">&larr; Prev</a>
                    <a href="/showcase?v=<?= $nextId ?>" class="cj-sc-nav-link">Next &rarr;</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Interactive Bar: Modern Instagram-Style Vector SVG Controls -->
            <div class="cj-sc-actions">
                <button type="button" id="cjLikeBtn" class="cj-sc-act-btn <?= $isLiked ? 'is-liked' : '' ?>" data-reel-id="<?= $rid ?>">
                    <span class="cj-sc-act-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path class="cj-sc-heart-path" stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    </span>
                    <span>Like (<span id="cjLikeCount"><?= (int)$reel['likes_count'] ?></span>)</span>
                </button>
                <a href="#reel-comments" class="cj-sc-act-btn">
                    <span class="cj-sc-act-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    </span>
                    <span>Comment (<span id="cjCommentCount"><?= (int)$reel['comments_count'] ?></span>)</span>
                </a>
                <button type="button" id="cjOpenOrderModalBtn" class="cj-sc-act-btn is-order">
                    <span class="cj-sc-act-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    </span>
                    <span>Order Now</span>
                </button>
                <button type="button" id="cjShareBtn" class="cj-sc-act-btn">
                    <span class="cj-sc-act-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </span>
                    <span id="cjShareText">Share</span>
                </button>
            </div>
        </div>

        <!-- RIGHT: Showroom Contacts, Live Rates, Trust Badges, Booking & Reviews -->
        <div>
            <!-- 1. Showroom Contact Hub -->
            <section class="cj-sc-card">
                <span class="cj-sc-status-pill <?= $isShowroomOpen ? 'is-open' : 'is-closed' ?>">
                    <span>●</span>
                    <?= $isShowroomOpen ? 'Showroom Open Now · Until 8:00 PM IST' : 'Showroom Hours: 10:00 AM – 8:00 PM IST' ?>
                </span>
                <h1 style="font-family:'Playfair Display',serif;font-size:24px;margin:0 0 6px;line-height:1.25;">
                    ChandraKala Jewellers, Khedbrahma
                </h1>
                <p style="margin:0;font-size:13px;color:var(--text-secondary);">
                    📍 Opp. Bhoomi Complex, Civil Road, Khedbrahma, Sabarkantha, Gujarat 383255 · <strong>BIS 916 Hallmarked Gold &amp; 925 Silver</strong>
                </p>

                <div class="cj-sc-contact-btns">
                    <a href="<?= htmlspecialchars($waOrderUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="cj-sc-cbtn is-wa" data-track-cta="whatsapp">
                        💬 Order on WhatsApp
                    </a>
                    <a href="tel:+919427080359" class="cj-sc-cbtn is-call" data-track-cta="call">
                        📞 Call +91 94270 80359
                    </a>
                    <a href="<?= htmlspecialchars(cj_business_info()['maps_directions'] ?? 'https://www.google.com/maps/dir/?api=1&destination=Chandrakala+jewellers,+22JW%2BHG9,+Khedbrahma,+Gujarat+383255&destination_place_id=0x395d0b1a27d5a3df:0x216db437426f9e6a', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="cj-sc-cbtn is-map" data-track-cta="directions">
                        🗺️ Get Directions
                    </a>
                </div>
            </section>

            <!-- 2. Today's Live Gold & Silver Rates (Per 1 Gram — Matches Homepage) -->
            <section class="cj-sc-card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                    <h2 style="font-family:'Playfair Display',serif;font-size:17px;margin:0;">Today's Live Showroom Rates</h2>
                    <span style="font-size:11px;color:var(--text-secondary);">Updated: <?= htmlspecialchars($rateUpdatedIst) ?></span>
                </div>
                <div class="cj-sc-rates-row">
                    <?php if ($showGold): ?>
                    <div class="cj-sc-rate-box">
                        <small>Gold (per 1g)</small>
                        <strong><?= $rateGold1g > 0 ? ('₹' . number_format($rateGold1g, 2) . '/g') : 'Call' ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($showSilver): ?>
                    <div class="cj-sc-rate-box">
                        <small>Silver (per 1g)</small>
                        <strong><?= $rateSilver1g > 0 ? ('₹' . number_format($rateSilver1g, 2) . '/g') : 'Call' ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($showSilver925): ?>
                    <div class="cj-sc-rate-box">
                        <small>925 Silver (per 1g)</small>
                        <strong><?= $rateSilver925_1g > 0 ? ('₹' . number_format($rateSilver925_1g, 2) . '/g') : 'Call' ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="cj-sc-trust-grid" style="margin-top:14px;">
                    <div class="cj-sc-trust-item"><span>🏅</span>BIS 916 HUID</div>
                    <div class="cj-sc-trust-item"><span>⚖️</span>Zero Hidden Charges</div>
                    <div class="cj-sc-trust-item"><span>♻️</span>100% Gold Exchange</div>
                    <div class="cj-sc-trust-item"><span>✨</span>Custom Karigar Order</div>
                </div>
            </section>

            <!-- 3. Interactive Khedbrahma Showroom Map -->
            <section class="cj-sc-card" style="padding:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;padding:0 4px;">
                    <strong style="font-size:13px;">Visit Our Civil Road Showroom</strong>
                    <a href="/contact" style="font-size:12px;color:var(--primary-gold-dark);font-weight:700;text-decoration:none;">Full Contact Page &rarr;</a>
                </div>
                <iframe title="ChandraKala Jewellers Khedbrahma Map"
                        src="https://www.google.com/maps?q=ChandraKala+Jewellers+Civil+Road+Khedbrahma+Gujarat&output=embed"
                        width="100%" height="190" style="border:0;border-radius:14px;display:block;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </section>

            <!-- 4. Comments & Customer Reviews (Live AJAX + Verified Store Reviews) -->
            <section id="reel-comments" class="cj-sc-card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
                    <div>
                        <h2 style="font-family:'Playfair Display',serif;font-size:19px;margin:0;">Comments &amp; Showroom Reviews</h2>
                        <p style="margin:2px 0 0;font-size:12px;color:var(--text-secondary);">Ask about weight, making charges, or share your ChandraKala experience</p>
                    </div>
                    <a href="<?= htmlspecialchars(cj_business_info()['google_review'] ?? 'https://g.page/r/CWqeb0I3tG0hEAI/review', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" style="font-size:12px;font-weight:700;color:var(--primary-gold-dark);text-decoration:none;">★ Review on Google</a>
                </div>

                <!-- Post Comment / Star Review Form -->
                <form id="cjReelCommentForm" class="cj-sc-form-grid" style="margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid var(--border-color);">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <input type="text" name="name" id="cjCmtName" class="cj-sc-input" required maxlength="120" placeholder="Your name *" value="<?= htmlspecialchars($custSession['name'] ?? '') ?>">
                        <input type="tel" name="phone" id="cjCmtPhone" class="cj-sc-input" maxlength="15" placeholder="Mobile (optional)" value="<?= htmlspecialchars($custSession['phone'] ?? '') ?>">
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                        <span style="font-size:12px;font-weight:600;color:var(--text-secondary);">Rate this design / our showroom (optional):</span>
                        <div class="cj-sc-stars" id="cjStarPicker" role="radiogroup" aria-label="Star rating">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <button type="button" class="cj-sc-star-btn" data-star="<?= $s ?>" aria-label="<?= $s ?> star">★</button>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="cjCmtRating" value="0">
                    </div>
                    <textarea name="comment" id="cjCmtText" rows="2" class="cj-sc-input" required maxlength="800" placeholder="Write a comment, ask price/weight, or share your showroom review..."></textarea>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <span id="cjCmtStatus" style="font-size:12px;font-weight:600;"></span>
                        <button type="submit" class="btn" style="padding:9px 18px;font-size:13px;">Post Comment / Review</button>
                    </div>
                </form>

                <!-- Live Reel Comments List -->
                <div id="cjCommentsList">
                    <?php if (empty($reelComments)): ?>
                        <p id="cjNoCommentsMsg" style="font-size:13px;color:var(--text-secondary);margin:0 0 12px;">Be the first to comment or ask about this design!</p>
                    <?php else: foreach ($reelComments as $cm): ?>
                        <article class="cj-sc-comment-item">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:4px;">
                                <strong style="font-size:13px;"><?= htmlspecialchars((string)$cm['customer_name']) ?></strong>
                                <span style="font-size:11px;color:var(--text-secondary);"><?= htmlspecialchars(cj_format_ist($cm['created_at'] ?? '', 'd M Y, h:i A')) ?></span>
                            </div>
                            <?php if ((int)$cm['rating'] > 0): ?>
                                <div style="color:#f59e0b;font-size:13px;margin-bottom:4px;"><?= str_repeat('★', (int)$cm['rating']) . str_repeat('☆', 5 - (int)$cm['rating']) ?></div>
                            <?php endif; ?>
                            <p style="margin:0;font-size:13px;line-height:1.5;"><?= nl2br(htmlspecialchars((string)$cm['comment'])) ?></p>
                        </article>
                    <?php endforeach; endif; ?>
                </div>

                <?php if (!empty($storeReviews)): ?>
                    <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border-color);">
                        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--primary-gold-dark);margin:0 0 10px;">Verified Customer Store Reviews</p>
                        <?php foreach (array_slice($storeReviews, 0, 3) as $sr): ?>
                            <article class="cj-sc-comment-item">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:3px;">
                                    <strong style="font-size:13px;"><?= htmlspecialchars((string)$sr['customer_name']) ?></strong>
                                    <span style="color:#f59e0b;font-size:12px;"><?= str_repeat('★', max(1, min(5, (int)$sr['rating']))) ?></span>
                                </div>
                                <p style="margin:0;font-size:12.5px;color:var(--text-secondary);"><?= htmlspecialchars((string)$sr['comment']) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <!-- More Reels Strip -->
    <?php if (!empty($otherReels)): ?>
    <section style="margin-top:44px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 class="section-heading" style="margin:0;text-align:left;font-size:22px;">More Sparkle to Watch</h2>
            <a href="/reels" style="font-size:13px;font-weight:700;color:var(--primary-gold-dark);text-decoration:none;">View All &rarr;</a>
        </div>
        <?php
        $cjReelsList = array_slice($otherReels, 0, 5);
        $cjReelsContext = 'showcase';
        include __DIR__ . '/includes/reels-bento.php';
        ?>
    </section>
    <?php endif; ?>

    <!-- Ready-to-Buy Shop Picks -->
    <?php if (!empty($shopPicks)): ?>
    <section style="margin-top:44px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 class="section-heading" style="margin:0;text-align:left;font-size:22px;">Ready-to-Buy Showroom Picks</h2>
            <a href="/gallery" style="font-size:13px;font-weight:700;color:var(--primary-gold-dark);text-decoration:none;">Shop All &rarr;</a>
        </div>
        <div class="products-grid">
            <?php foreach ($shopPicks as $row):
                $price = (isset($row['manual_price']) && $row['manual_price'] !== null && (float)$row['manual_price'] > 0)
                    ? (float)$row['manual_price']
                    : cj_calculate_price((string)($row['metal_type'] ?? 'gold'), (float)($row['weight'] ?? 0), $rate);
                $img = cj_img_url((string)$row['image'], 400);
            ?>
            <div class="product-card">
                <a href="/product?id=<?= (int)$row['id'] ?>" class="product-image-link">
                    <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string)$row['name']) ?>" class="product-image" loading="lazy">
                </a>
                <div class="product-details">
                    <h3 class="product-title"><a href="/product?id=<?= (int)$row['id'] ?>"><?= htmlspecialchars((string)$row['name']) ?></a></h3>
                    <div class="product-price">₹<?= number_format(round($price)) ?></div>
                </div>
                <div class="product-actions">
                    <a href="/product?id=<?= (int)$row['id'] ?>" class="btn btn-secondary">View Details</a>
                    <a href="/buynow?id=<?= (int)$row['id'] ?>" class="btn">Buy Now</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Order / Custom Design / Book Showroom Visit Modal -->
    <div id="cjOrderModal" class="cj-sc-modal" role="dialog" aria-modal="true" aria-labelledby="cjOrderModalTitle">
        <div class="cj-sc-modal-box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h3 id="cjOrderModalTitle" style="font-family:'Playfair Display',serif;font-size:20px;margin:0;">Order / Enquire Reel #<?= $rid ?></h3>
                <button type="button" id="cjCloseOrderModal" style="border:none;background:none;font-size:24px;cursor:pointer;color:var(--text-secondary);">&times;</button>
            </div>
            <p style="font-size:12.5px;color:var(--text-secondary);margin:0 0 14px;">
                Want this exact design or custom weight? Submit below for an instant callback or book a private viewing at our Khedbrahma showroom.
            </p>
            <form id="cjReelOrderForm" class="cj-sc-form-grid">
                <input type="text" name="name" class="cj-sc-input" required maxlength="120" placeholder="Your Full Name *" value="<?= htmlspecialchars($custSession['name'] ?? '') ?>">
                <input type="tel" name="phone" class="cj-sc-input" required maxlength="15" placeholder="10-Digit Mobile / WhatsApp Number *" value="<?= htmlspecialchars($custSession['phone'] ?? '') ?>">
                <input type="text" name="preferred_time" class="cj-sc-input" maxlength="80" placeholder="Preferred Call / Showroom Visit Time (optional)">
                <textarea name="message" rows="2" class="cj-sc-input" maxlength="600" placeholder="Any weight, purity (22K/18K/925), or custom note..."></textarea>
                <div id="cjOrderStatus" style="font-size:12.5px;font-weight:600;"></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
                    <button type="submit" class="btn" style="flex:1;">Confirm Order Inquiry</button>
                    <a href="<?= htmlspecialchars($waOrderUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="cj-sc-cbtn is-wa" style="padding:10px 14px;" data-track-cta="whatsapp">💬 Direct WhatsApp</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        var reelId = <?= (int)$rid ?>;
        var trackUrl = '/api/reel-track.php';
        var video = document.getElementById('cjShowcaseVideo');
        var muteBtn = document.getElementById('cjMuteToggle');
        var likeBtn = document.getElementById('cjLikeBtn');
        var likeIcon = document.getElementById('cjLikeIcon');
        var likeCount = document.getElementById('cjLikeCount');
        var shareBtn = document.getElementById('cjShareBtn');
        var shareText = document.getElementById('cjShareText');
        var orderModal = document.getElementById('cjOrderModal');
        var openOrderBtn = document.getElementById('cjOpenOrderModalBtn');
        var closeOrderBtn = document.getElementById('cjCloseOrderModal');

        // Record genuine showcase view on page load
        fetch(trackUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reel_id: reelId, action: 'view' }),
            credentials: 'same-origin'
        }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
            if (d && d.display_views_fmt) {
                var a = document.getElementById('scTopViews');
                var b = document.getElementById('scBadgeViews');
                if (a) a.textContent = d.display_views_fmt;
                if (b) b.textContent = d.display_views_fmt;
            }
        }).catch(function () {});

        // Sound toggle (crisp SVG update)
        if (muteBtn && video) {
            muteBtn.addEventListener('click', function () {
                video.muted = !video.muted;
                var svg = document.getElementById('cjScMuteSvg');
                var lbl = document.getElementById('cjScMuteLbl');
                if (svg) {
                    svg.innerHTML = video.muted
                        ? '<path d="M11 5L6 9H2v6h4l5 4V5z"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/>'
                        : '<path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>';
                }
                if (lbl) lbl.textContent = video.muted ? 'Muted' : 'Sound On';
                if (!video.muted) { video.play().catch(function () {}); }
            });
        }

        // Like toggle (SVG outline <-> solid crimson controlled via .is-liked)
        if (likeBtn) {
            likeBtn.addEventListener('click', function () {
                fetch(trackUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reel_id: reelId, action: 'like' }),
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); }).then(function (d) {
                    if (!d || !d.ok) return;
                    likeBtn.classList.toggle('is-liked', !!d.liked);
                    if (likeCount) likeCount.textContent = d.likes_count;
                }).catch(function () {});
            });
        }

        // Share button
        if (shareBtn) {
            shareBtn.addEventListener('click', function () {
                fetch(trackUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reel_id: reelId, action: 'share' }),
                    credentials: 'same-origin'
                }).catch(function () {});
                var url = window.location.href;
                if (navigator.share) {
                    navigator.share({ title: 'ChandraKala Jewellers Showcase Reel #' + reelId, url: url }).catch(function () {});
                } else if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(function () {
                        if (shareText) shareText.textContent = 'Copied!';
                        setTimeout(function () { if (shareText) shareText.textContent = 'Share'; }, 2000);
                    }).catch(function () {});
                }
            });
        }

        // Track WhatsApp / Call / Directions CTA clicks
        document.querySelectorAll('[data-track-cta]').forEach(function (el) {
            el.addEventListener('click', function () {
                var act = el.getAttribute('data-track-cta');
                if (!act) return;
                var body = JSON.stringify({ reel_id: reelId, action: act });
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(trackUrl, new Blob([body], { type: 'application/json' }));
                }
            });
        });

        // Order Modal open/close
        if (openOrderBtn && orderModal) {
            openOrderBtn.addEventListener('click', function () { orderModal.classList.add('is-open'); });
        }
        if (closeOrderBtn && orderModal) {
            closeOrderBtn.addEventListener('click', function () { orderModal.classList.remove('is-open'); });
        }
        if (orderModal) {
            orderModal.addEventListener('click', function (e) {
                if (e.target === orderModal) orderModal.classList.remove('is-open');
            });
        }

        // Submit Order Inquiry
        var orderForm = document.getElementById('cjReelOrderForm');
        if (orderForm) {
            orderForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var st = document.getElementById('cjOrderStatus');
                if (st) { st.style.color = '#d97706'; st.textContent = 'Submitting your request...'; }
                var fd = new FormData(orderForm);
                fd.append('reel_id', String(reelId));
                fd.append('action', 'order');
                fetch(trackUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d || !d.ok) {
                            if (st) { st.style.color = '#dc2626'; st.textContent = (d && d.error) || 'Please check your details.'; }
                            return;
                        }
                        if (st) {
                            st.style.color = '#10b981';
                            st.innerHTML = '✅ ' + d.message + (d.whatsapp_url ? ' <a href="' + d.whatsapp_url + '" target="_blank" rel="noopener" style="color:#16a34a;text-decoration:underline;">Also send on WhatsApp &rarr;</a>' : '');
                        }
                        orderForm.reset();
                    })
                    .catch(function () {
                        if (st) { st.style.color = '#dc2626'; st.textContent = 'Network error. Please use Direct WhatsApp.'; }
                    });
            });
        }

        // Star rating picker
        var ratingInput = document.getElementById('cjCmtRating');
        var stars = document.querySelectorAll('#cjStarPicker .cj-sc-star-btn');
        stars.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var val = Number(btn.getAttribute('data-star') || 0);
                if (ratingInput) ratingInput.value = String(val);
                stars.forEach(function (b) {
                    b.classList.toggle('is-on', Number(b.getAttribute('data-star') || 0) <= val);
                });
            });
        });

        // Submit Comment / Review live via AJAX
        var cmtForm = document.getElementById('cjReelCommentForm');
        if (cmtForm) {
            cmtForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var st = document.getElementById('cjCmtStatus');
                if (st) { st.style.color = '#d97706'; st.textContent = 'Posting...'; }
                var fd = new FormData(cmtForm);
                fd.append('reel_id', String(reelId));
                fd.append('action', 'comment');
                fetch(trackUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d || !d.ok) {
                            if (st) { st.style.color = '#dc2626'; st.textContent = (d && d.error) || 'Could not post comment.'; }
                            return;
                        }
                        if (st) { st.style.color = '#10b981'; st.textContent = '✅ Posted!'; }
                        var list = document.getElementById('cjCommentsList');
                        var empty = document.getElementById('cjNoCommentsMsg');
                        if (empty) empty.remove();
                        if (list && d.comment) {
                            var c = d.comment;
                            var art = document.createElement('article');
                            art.className = 'cj-sc-comment-item';
                            var starsHtml = c.rating > 0 ? '<div style="color:#f59e0b;font-size:13px;margin-bottom:4px;">' + '★'.repeat(c.rating) + '☆'.repeat(5 - c.rating) + '</div>' : '';
                            art.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:4px;"><strong style="font-size:13px;"></strong><span style="font-size:11px;color:var(--text-secondary);"></span></div>' + starsHtml + '<p style="margin:0;font-size:13px;line-height:1.5;"></p>';
                            art.querySelector('strong').textContent = c.customer_name;
                            art.querySelector('span').textContent = c.time_ist;
                            art.querySelector('p').textContent = c.comment;
                            list.insertBefore(art, list.firstChild);
                        }
                        var cc = document.getElementById('cjCommentCount');
                        if (cc && typeof d.comments_count !== 'undefined') cc.textContent = d.comments_count;
                        document.getElementById('cjCmtText').value = '';
                        if (ratingInput) ratingInput.value = '0';
                        stars.forEach(function (b) { b.classList.remove('is-on'); });
                    })
                    .catch(function () {
                        if (st) { st.style.color = '#dc2626'; st.textContent = 'Network error. Please try again.'; }
                    });
            });
        }
    })();
    </script>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
</body>
</html>
