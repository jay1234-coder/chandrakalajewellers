<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/reels.php';
require_once __DIR__ . '/includes/customers.php';

cj_ensure_reels_schema($conn);

$viewMode = ($_GET['view'] ?? 'feed') === 'grid' ? 'grid' : 'feed';
$sort = ($_GET['sort'] ?? 'top') === 'latest' ? 'latest' : 'top';
$visitorHash = cj_reel_visitor_hash();
$custSession = function_exists('cj_customer_session') ? cj_customer_session() : null;

// In Instagram Reels Feed mode: Personalized + Randomized Algorithm!
// In Grid mode: Ranked Top Viewed / Latest Bento Masonry!
$cjReelsList = ($viewMode === 'feed')
    ? cj_get_personalized_reels($conn, $visitorHash, 60)
    : cj_get_ranked_reels($conn, 60, true, $sort);
$cjReelsContext = 'reels';

// Optional: if user opened /reels?v=12, bring reel #12 to the very top of the feed
$startId = (int)($_GET['v'] ?? 0);
if ($viewMode === 'feed' && $startId > 0 && count($cjReelsList) > 1) {
    foreach ($cjReelsList as $k => $r) {
        if ((int)$r['id'] === $startId) {
            $picked = $cjReelsList[$k];
            unset($cjReelsList[$k]);
            array_unshift($cjReelsList, $picked);
            $cjReelsList = array_values($cjReelsList);
            break;
        }
    }
}

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
    'title' => 'Jewellery Reels | ChandraKala Jewellers Khedbrahma',
    'description' => 'Swipe through real handcrafted 22K 916 gold, bridal, mangalsutra and 925 silver jewellery reels from ChandraKala Jewellers, Civil Road, Khedbrahma.',
    'canonical' => cj_abs_url('reels'),
    'schema' => array_merge([cj_local_business_schema()], $videoSchemas),
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
<?php if ($viewMode === 'feed'): ?>
<style>
html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    overflow: hidden;
    background: #07080c !important;
    color: #fff;
}
.cj-ig-shell {
    position: fixed;
    inset: 0;
    background: #07080c;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
/* Floating Top Header Over Reels */
.cj-ig-topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 40;
    padding: max(12px, env(safe-area-inset-top, 12px)) 14px 12px;
    background: linear-gradient(180deg, rgba(7,8,12,0.78) 0%, rgba(7,8,12,0) 100%);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    pointer-events: none;
}
.cj-ig-topbar > * { pointer-events: auto; }
.cj-ig-top-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 13px;
    border-radius: 999px;
    background: rgba(15, 18, 26, 0.65);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.16);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s ease;
}
.cj-ig-top-btn:hover {
    border-color: rgba(240, 215, 140, 0.7);
    color: #fde68a;
}
.cj-ig-brand {
    font-family: 'Playfair Display', serif;
    font-size: 16px;
    font-weight: 700;
    color: #fff8e7;
    letter-spacing: 0.02em;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.6);
}

/* Vertical Snap-Scroll Feed Container */
.cj-ig-feed {
    width: 100%;
    height: 100dvh;
    overflow-y: scroll;
    scroll-snap-type: y mandatory;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.cj-ig-feed::-webkit-scrollbar { display: none; }

.cj-ig-slide {
    width: 100%;
    height: 100dvh;
    scroll-snap-align: start;
    scroll-snap-stop: always;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 0;
}
@media (min-width: 769px) {
    .cj-ig-slide {
        padding: 58px 16px 24px;
    }
}

.cj-ig-frame {
    position: relative;
    width: 100%;
    height: 100%;
    background: #0b0d14;
    overflow: hidden;
    isolation: isolate;
}
@media (min-width: 769px) {
    .cj-ig-frame {
        width: auto;
        height: 100%;
        aspect-ratio: 9 / 16;
        max-width: min(460px, 92vw);
        border-radius: 26px;
        border: 1.5px solid rgba(240, 215, 140, 0.38);
        box-shadow: 0 26px 70px rgba(0, 0, 0, 0.65), 0 0 40px rgba(212, 175, 55, 0.14);
    }
}
.cj-ig-video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    background: #0b0d14;
    cursor: pointer;
}
/* Subtle top/bottom vignette for control legibility */
.cj-ig-frame::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.34) 0%, rgba(0,0,0,0) 22%, rgba(0,0,0,0) 68%, rgba(0,0,0,0.68) 100%);
    pointer-events: none;
    z-index: 2;
}

/* Badges: Top Viewed & Live Views */
.cj-ig-badges {
    position: absolute;
    top: max(62px, calc(env(safe-area-inset-top, 12px) + 50px));
    left: 14px;
    z-index: 6;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    pointer-events: none;
}
@media (min-width: 769px) {
    .cj-ig-badges { top: 14px; }
}
.cj-ig-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 11px;
    border-radius: 999px;
    background: rgba(12, 14, 18, 0.58);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.18);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
}
.cj-ig-badge.is-top {
    background: linear-gradient(135deg, #fff3c4 0%, #e5be54 48%, #b88b1e 100%);
    color: #1a1204;
    border-color: transparent;
    box-shadow: 0 4px 16px rgba(212, 175, 55, 0.45);
}

/* Right-Side Instagram Action Rail */
.cj-ig-rail {
    position: absolute;
    right: 12px;
    bottom: max(76px, calc(env(safe-area-inset-bottom, 12px) + 66px));
    z-index: 8;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
}
@media (min-width: 769px) {
    .cj-ig-rail { bottom: 26px; }
}
.cj-ig-rail-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    background: none;
    border: none;
    color: #fff;
    cursor: pointer;
    text-decoration: none;
    font-family: inherit;
    padding: 0;
    -webkit-tap-highlight-color: transparent;
}
.cj-ig-rail-circle {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: rgba(15, 18, 26, 0.58);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.35);
}
.cj-ig-rail-btn:active .cj-ig-rail-circle { transform: scale(0.88); }
.cj-ig-rail-btn.is-liked .cj-ig-rail-circle {
    background: rgba(244, 63, 94, 0.25);
    border-color: rgba(244, 63, 94, 0.8);
}
.cj-ig-rail-btn.is-order .cj-ig-rail-circle {
    background: linear-gradient(135deg, #fff3c4 0%, #e5be54 48%, #b88b1e 100%);
    color: #1a1204;
    border-color: transparent;
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.45);
}
.cj-ig-rail-label {
    font-size: 11px;
    font-weight: 700;
    text-shadow: 0 1px 6px rgba(0,0,0,0.85);
}

/* Bottom-Left Showroom CTA Pill (Zero Title Clutter) */
.cj-ig-bottom-cta {
    position: absolute;
    left: 14px;
    right: 74px;
    bottom: max(20px, calc(env(safe-area-inset-bottom, 12px) + 12px));
    z-index: 8;
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: flex-start;
}
.cj-ig-showroom-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 15px;
    border-radius: 999px;
    background: rgba(15, 18, 26, 0.68);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(240, 215, 140, 0.55);
    color: #fff8e7;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    transition: all 0.2s ease;
}
.cj-ig-showroom-pill:hover {
    background: linear-gradient(135deg, #fff3c4 0%, #e5be54 50%, #c49222 100%);
    color: #1a1204;
}

/* First Reel "Scroll / Swipe Up to See Next" Animated Hint */
.cj-ig-scroll-hint {
    position: absolute;
    left: 50%;
    bottom: max(74px, calc(env(safe-area-inset-bottom, 12px) + 64px));
    transform: translateX(-50%);
    z-index: 9;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    border-radius: 999px;
    background: rgba(12, 14, 18, 0.72);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(240, 215, 140, 0.65);
    color: #fde68a;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.02em;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45), 0 0 20px rgba(212, 175, 55, 0.28);
    animation: cjScrollHintBounce 1.8s ease-in-out infinite;
    cursor: pointer;
    transition: opacity 0.45s ease;
}
.cj-ig-scroll-hint.is-hidden {
    opacity: 0;
    pointer-events: none;
}
@keyframes cjScrollHintBounce {
    0%, 100% { transform: translate(-50%, 0); }
    50% { transform: translate(-50%, -8px); }
}

/* Double-Tap Animated Heart Burst */
.cj-ig-heart-burst {
    position: absolute;
    left: 50%;
    top: 50%;
    z-index: 12;
    font-size: 86px;
    transform: translate(-50%, -50%) scale(0);
    opacity: 0;
    pointer-events: none;
    filter: drop-shadow(0 6px 24px rgba(244, 63, 94, 0.65));
}
.cj-ig-heart-burst.is-pop {
    animation: cjHeartPop 0.75s cubic-bezier(.17,.89,.32,1.28) forwards;
}
@keyframes cjHeartPop {
    0% { transform: translate(-50%, -50%) scale(0.2); opacity: 0; }
    35% { transform: translate(-50%, -50%) scale(1.18); opacity: 1; }
    70% { transform: translate(-50%, -50%) scale(0.98); opacity: 0.95; }
    100% { transform: translate(-50%, -50%) scale(1.35); opacity: 0; }
}

/* Playback Progress Bar */
.cj-ig-progress-track {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 3px;
    background: rgba(255,255,255,0.18);
    z-index: 10;
}
.cj-ig-progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #fde68a, #d4af37);
    transition: width 0.15s linear;
}

/* Desktop Floating Up/Down Arrows */
.cj-ig-desktop-nav {
    display: none;
}
@media (min-width: 769px) {
    .cj-ig-desktop-nav {
        position: fixed;
        right: 26px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 35;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .cj-ig-nav-arrow {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: rgba(22, 26, 34, 0.85);
        border: 1px solid rgba(240, 215, 140, 0.35);
        color: #fde68a;
        font-size: 18px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    .cj-ig-nav-arrow:hover {
        background: linear-gradient(135deg, #fff3c4, #d4af37);
        color: #1a1204;
    }
}

/* Slide-Up Instagram Comments & Reviews Drawer */
.cj-ig-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 90;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(3px);
    display: none;
    align-items: flex-end;
    justify-content: center;
}
.cj-ig-drawer-backdrop.is-open { display: flex; }
.cj-ig-drawer {
    width: 100%;
    max-width: 480px;
    max-height: 76dvh;
    background: #141821;
    color: #f3f4f6;
    border-radius: 24px 24px 0 0;
    border: 1px solid rgba(240, 215, 140, 0.35);
    border-bottom: none;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 -16px 50px rgba(0,0,0,0.65);
}
.cj-ig-drawer-head {
    padding: 14px 18px;
    border-bottom: 1px solid #262e40;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.cj-ig-drawer-list {
    flex: 1;
    overflow-y: auto;
    padding: 14px 18px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.cj-ig-drawer-item {
    background: #1c2230;
    border: 1px solid #2a3347;
    border-radius: 14px;
    padding: 10px 12px;
    font-size: 12.5px;
}
.cj-ig-drawer-form {
    padding: 12px 16px max(14px, env(safe-area-inset-bottom, 14px));
    border-top: 1px solid #262e40;
    background: #10131b;
    display: grid;
    gap: 8px;
}
.cj-ig-drawer-input {
    width: 100%;
    padding: 9px 12px;
    border-radius: 12px;
    border: 1px solid #303a50;
    background: #181d29;
    color: #fff;
    font: inherit;
    font-size: 12.5px;
}
</style>
<?php else: ?>
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
}
.cj-reels-sort a.is-active {
    background: var(--gold-shine);
    color: #1a1204;
}
</style>
<?php endif; ?>
</head>
<body>

<?php if ($viewMode === 'feed'): ?>
<!-- INSTAGRAM-STYLE FULL-SCREEN VERTICAL REELS EXPERIENCE -->
<div class="cj-ig-shell">
    <header class="cj-ig-topbar">
        <a href="/index1" class="cj-ig-top-btn">&larr; Home</a>
        <span class="cj-ig-brand">ChandraKala Reels</span>
        <div style="display:flex;gap:6px;align-items:center;">
            <button type="button" id="cjIgSoundBtn" class="cj-ig-top-btn" aria-label="Toggle sound">🔇</button>
            <a href="/reels?view=grid" class="cj-ig-top-btn" title="Switch to Bento Grid View">⊞ Grid</a>
        </div>
    </header>

    <?php if (empty($cjReelsList)): ?>
        <div style="text-align:center;padding:32px 20px;max-width:400px;">
            <div style="font-size:46px;margin-bottom:10px;">✨</div>
            <h1 style="font-family:'Playfair Display',serif;font-size:24px;margin:0 0 8px;">New Jewellery Reels Arriving Soon</h1>
            <p style="font-size:13px;color:#9ca3af;margin:0 0 22px;">Explore our live 916 gold &amp; silver collections while we upload fresh showroom reels.</p>
            <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
                <a href="/gallery" class="btn">Shop Collection</a>
                <a href="/Catalogue" class="btn btn-secondary">Explore Catalogue</a>
            </div>
        </div>
    <?php else: ?>
        <div class="cj-ig-feed" id="cjIgFeed">
            <?php foreach ($cjReelsList as $idx => $reel):
                $rid = (int)$reel['id'];
                $isTop = !empty($reel['is_top_viewed']);
                $isLiked = !empty($reel['is_liked']);
                $viewsFmt = cj_format_reel_count((int)$reel['display_views']);
                $likesCount = (int)$reel['likes_count'];
                $commentsCount = (int)$reel['comments_count'];
                $waMsg = "Namaste ChandraKala Jewellers! I loved Jewellery Reel #{$rid} on your website and want to order / enquire about weight & live price.\nLink: " . cj_abs_url('showcase?v=' . $rid);
                $waUrl = 'https://wa.me/919427080359?text=' . rawurlencode($waMsg);
            ?>
            <section class="cj-ig-slide" data-ig-slide="<?= $idx ?>" data-reel-id="<?= $rid ?>">
                <div class="cj-ig-frame">
                    <video class="cj-ig-video"
                           data-src="<?= htmlspecialchars((string)$reel['hd_url'], ENT_QUOTES, 'UTF-8') ?>"
                           <?php if (!empty($reel['poster_thumb'])): ?>poster="<?= htmlspecialchars((string)$reel['poster_thumb'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                           muted
                           loop
                           playsinline
                           webkit-playsinline
                           preload="<?= $idx <= 1 ? 'auto' : 'none' ?>"
                           disablepictureinpicture></video>

                    <div class="cj-ig-heart-burst" aria-hidden="true">❤️</div>

                    <!-- Top-Left Minimal Badges -->
                    <div class="cj-ig-badges">
                        <?php if ($isTop): ?>
                            <span class="cj-ig-badge is-top">👑 Top Viewed</span>
                        <?php endif; ?>
                        <span class="cj-ig-badge">
                            👁 <span data-ig-views="<?= $rid ?>"><?= htmlspecialchars($viewsFmt) ?></span>
                        </span>
                    </div>

                    <!-- Right-Side Instagram Action Rail -->
                    <div class="cj-ig-rail">
                        <button type="button" class="cj-ig-rail-btn <?= $isLiked ? 'is-liked' : '' ?>" data-ig-like="<?= $rid ?>" aria-label="Like reel">
                            <span class="cj-ig-rail-circle" data-ig-like-icon="<?= $rid ?>"><?= $isLiked ? '❤️' : '🤍' ?></span>
                            <span class="cj-ig-rail-label" data-ig-like-count="<?= $rid ?>"><?= $likesCount ?></span>
                        </button>

                        <button type="button" class="cj-ig-rail-btn" data-ig-comment="<?= $rid ?>" aria-label="Comments">
                            <span class="cj-ig-rail-circle">💬</span>
                            <span class="cj-ig-rail-label" data-ig-cmt-count="<?= $rid ?>"><?= $commentsCount ?></span>
                        </button>

                        <a href="/showcase?v=<?= $rid ?>" class="cj-ig-rail-btn is-order" data-ig-click="<?= $rid ?>" aria-label="Order or visit showroom">
                            <span class="cj-ig-rail-circle">🛍️</span>
                            <span class="cj-ig-rail-label">Order</span>
                        </a>

                        <a href="<?= htmlspecialchars($waUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="cj-ig-rail-btn" data-ig-wa="<?= $rid ?>" aria-label="WhatsApp showroom">
                            <span class="cj-ig-rail-circle" style="background:rgba(22,163,74,0.75);border-color:rgba(134,239,172,0.5);">📞</span>
                            <span class="cj-ig-rail-label">WhatsApp</span>
                        </a>

                        <button type="button" class="cj-ig-rail-btn" data-ig-share="<?= $rid ?>" aria-label="Share reel">
                            <span class="cj-ig-rail-circle">🔗</span>
                            <span class="cj-ig-rail-label" data-ig-share-lbl="<?= $rid ?>">Share</span>
                        </button>
                    </div>

                    <!-- Bottom-Left Clean Showroom Contact & Reviews Link -->
                    <div class="cj-ig-bottom-cta">
                        <a href="/showcase?v=<?= $rid ?>" class="cj-ig-showroom-pill" data-ig-click="<?= $rid ?>">
                            <span>📍 Showroom Contact, Live Rates &amp; Reviews</span>
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>

                    <!-- First Reel ONLY: Animated Scroll/Swipe Up Hint -->
                    <?php if ($idx === 0 && count($cjReelsList) > 1): ?>
                    <button type="button" id="cjIgScrollHint" class="cj-ig-scroll-hint" aria-label="Scroll to next reel">
                        <span>Scroll to see next reel</span>
                        <span aria-hidden="true">↓</span>
                    </button>
                    <?php endif; ?>

                    <div class="cj-ig-progress-track">
                        <div class="cj-ig-progress-bar" data-ig-progress="<?= $rid ?>"></div>
                    </div>
                </div>
            </section>
            <?php endforeach; ?>

            <!-- End-of-Feed Luxury Card (Watch Fresh Mix / Visit Showroom) -->
            <section class="cj-ig-slide" data-ig-end="1">
                <div class="cj-ig-frame" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:32px 24px;background:radial-gradient(circle at 50% 25%, rgba(212,175,55,0.22), #0b0d14 70%);">
                    <div style="font-size:48px;margin-bottom:10px;">✨</div>
                    <h2 style="font-family:'Playfair Display',serif;font-size:24px;color:#fff8e7;margin:0 0 8px;">Loved Our Sparkle?</h2>
                    <p style="font-size:13px;color:#cbd5e1;margin:0 0 22px;max-width:300px;">
                        Visit ChandraKala Jewellers on Civil Road, Khedbrahma or shuffle for a fresh personalized mix!
                    </p>
                    <div style="display:flex;flex-direction:column;gap:10px;width:100%;max-width:260px;">
                        <a href="/reels?r=<?= time() ?>" class="btn" style="text-align:center;">🔀 Watch Fresh Mix</a>
                        <a href="/gallery" class="btn btn-secondary" style="text-align:center;">🛍️ Shop Ready Jewellery</a>
                        <a href="/contact" class="cj-ig-top-btn" style="justify-content:center;padding:11px;">📍 Contact Showroom &amp; Map</a>
                    </div>
                </div>
            </section>
        </div>

        <!-- Desktop Up/Down Navigation Arrows -->
        <div class="cj-ig-desktop-nav" aria-label="Reel navigation">
            <button type="button" id="cjIgPrevBtn" class="cj-ig-nav-arrow" title="Previous reel (↑)">↑</button>
            <button type="button" id="cjIgNextBtn" class="cj-ig-nav-arrow" title="Next reel (↓)">↓</button>
        </div>

        <!-- Slide-Up Instagram Comments & Reviews Drawer -->
        <div id="cjIgDrawer" class="cj-ig-drawer-backdrop" role="dialog" aria-modal="true" aria-label="Reel comments">
            <div class="cj-ig-drawer">
                <div class="cj-ig-drawer-head">
                    <strong style="font-family:'Playfair Display',serif;font-size:16px;">Comments &amp; Reviews <span id="cjIgDrawerReelTag" style="color:#fde68a;font-size:12px;"></span></strong>
                    <button type="button" id="cjIgDrawerClose" style="border:none;background:none;color:#9ca3af;font-size:24px;cursor:pointer;line-height:1;">&times;</button>
                </div>
                <div id="cjIgDrawerList" class="cj-ig-drawer-list">
                    <p style="font-size:12px;color:#9ca3af;text-align:center;">Loading comments...</p>
                </div>
                <form id="cjIgDrawerForm" class="cj-ig-drawer-form">
                    <div style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center;">
                        <input type="text" name="name" id="cjIgCmtName" class="cj-ig-drawer-input" required maxlength="120" placeholder="Your name *" value="<?= htmlspecialchars($custSession['name'] ?? '') ?>">
                        <select name="rating" id="cjIgCmtRating" class="cj-ig-drawer-input" style="width:auto;">
                            <option value="0">No ★</option>
                            <option value="5">★★★★★</option>
                            <option value="4">★★★★</option>
                            <option value="3">★★★</option>
                            <option value="2">★★</option>
                            <option value="1">★</option>
                        </select>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="comment" id="cjIgCmtText" class="cj-ig-drawer-input" required maxlength="500" placeholder="Ask weight/price or post a review...">
                        <button type="submit" class="btn" style="padding:8px 16px;font-size:12px;white-space:nowrap;">Post</button>
                    </div>
                    <div id="cjIgCmtStatus" style="font-size:11.5px;font-weight:600;"></div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';
    var TRACK_URL = '/api/reel-track.php';
    var feed = document.getElementById('cjIgFeed');
    var soundBtn = document.getElementById('cjIgSoundBtn');
    var scrollHint = document.getElementById('cjIgScrollHint');
    var slides = Array.prototype.slice.call(document.querySelectorAll('.cj-ig-slide[data-reel-id]'));
    if (!feed || !slides.length) return;

    var globalMuted = true;
    var activeReelId = null;
    var activeStartMs = 0;
    var trackedImp = {};
    var drawerReelId = 0;

    function beacon(payload, onDone) {
        var json = JSON.stringify(payload);
        if (!onDone && navigator.sendBeacon) {
            try {
                navigator.sendBeacon(TRACK_URL, new Blob([json], { type: 'application/json' }));
                return;
            } catch (e) {}
        }
        fetch(TRACK_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: json,
            credentials: 'same-origin',
            keepalive: true
        }).then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) { if (onDone && d) onDone(d); })
          .catch(function () {});
    }

    function flushWatchTime() {
        if (!activeReelId || !activeStartMs) return;
        var elapsed = Date.now() - activeStartMs;
        if (elapsed >= 600) {
            beacon({ reel_id: Number(activeReelId), action: 'watch', watch_ms: Math.min(300000, elapsed) });
        }
        activeStartMs = 0;
    }

    function hydrateVideo(v) {
        if (!v || v.dataset.hydrated === '1') return;
        var src = v.getAttribute('data-src');
        if (!src) return;
        v.dataset.hydrated = '1';
        v.src = src;
        try { v.load(); } catch (e) {}
    }

    function syncSoundUI() {
        if (soundBtn) soundBtn.textContent = globalMuted ? '🔇' : '🔊';
        slides.forEach(function (s) {
            var v = s.querySelector('video');
            if (v) v.muted = globalMuted;
        });
    }

    if (soundBtn) {
        soundBtn.addEventListener('click', function () {
            globalMuted = !globalMuted;
            syncSoundUI();
            if (activeReelId) {
                var curSlide = document.querySelector('.cj-ig-slide[data-reel-id="' + activeReelId + '"]');
                var v = curSlide && curSlide.querySelector('video');
                if (v) v.play().catch(function () {});
            }
        });
    }

    // Observe active full-screen slide (Instagram Snap Autoplay + Watch-Time Algorithm Tracker)
    var slideObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var slide = entry.target;
            var rid = slide.getAttribute('data-reel-id');
            var idx = Number(slide.getAttribute('data-ig-slide') || 0);
            var vid = slide.querySelector('video');

            if (entry.isIntersecting && entry.intersectionRatio >= 0.65) {
                // Hide scroll hint as soon as user leaves Reel #1
                if (idx > 0 && scrollHint) {
                    scrollHint.classList.add('is-hidden');
                }
                // Flush previous reel's watch time to train the recommendation algorithm
                if (activeReelId && activeReelId !== rid) {
                    flushWatchTime();
                }
                activeReelId = rid;
                activeStartMs = Date.now();

                // Hydrate current & next reel immediately for 0ms swipe playback
                hydrateVideo(vid);
                var nextSlide = slides[idx + 1];
                if (nextSlide) hydrateVideo(nextSlide.querySelector('video'));

                if (vid) {
                    vid.muted = globalMuted;
                    vid.play().catch(function () {
                        vid.muted = true;
                        vid.play().catch(function () {});
                    });
                }

                // Count genuine Impression + View once per session
                if (rid && !trackedImp[rid]) {
                    trackedImp[rid] = true;
                    setTimeout(function () {
                        if (activeReelId !== rid) return;
                        beacon({ reel_id: Number(rid), action: 'impression' }, function (d) {
                            if (d && d.display_views_fmt) {
                                var el = document.querySelector('[data-ig-views="' + rid + '"]');
                                if (el) el.textContent = d.display_views_fmt;
                            }
                        });
                    }, 900);
                }
            } else {
                if (vid) {
                    try { vid.pause(); } catch (e) {}
                }
                if (activeReelId === rid) {
                    flushWatchTime();
                    activeReelId = null;
                }
            }
        });
    }, { root: feed, threshold: [0.2, 0.65, 0.9] });

    slides.forEach(function (slide) {
        slideObserver.observe(slide);
        var rid = slide.getAttribute('data-reel-id');
        var vid = slide.querySelector('video');
        var prog = slide.querySelector('[data-ig-progress]');
        var burst = slide.querySelector('.cj-ig-heart-burst');

        if (vid && prog) {
            vid.addEventListener('timeupdate', function () {
                if (vid.duration && vid.duration > 0) {
                    prog.style.width = ((vid.currentTime / vid.duration) * 100).toFixed(1) + '%';
                }
            });
        }

        // Single tap = Pause/Play · Double tap = Instagram Heart Like Burst!
        if (vid) {
            var lastTap = 0;
            var tapTimer = null;
            vid.addEventListener('click', function () {
                var now = Date.now();
                if (now - lastTap < 280) {
                    clearTimeout(tapTimer);
                    lastTap = 0;
                    if (burst) {
                        burst.classList.remove('is-pop');
                        void burst.offsetWidth;
                        burst.classList.add('is-pop');
                    }
                    var likeBtn = slide.querySelector('[data-ig-like]');
                    if (likeBtn && !likeBtn.classList.contains('is-liked')) {
                        likeBtn.click();
                    }
                } else {
                    lastTap = now;
                    tapTimer = setTimeout(function () {
                        if (vid.paused) vid.play().catch(function () {});
                        else vid.pause();
                    }, 260);
                }
            });
        }
    });

    // Scroll Hint click jumps smoothly to Reel #2
    if (scrollHint) {
        scrollHint.addEventListener('click', function () {
            scrollHint.classList.add('is-hidden');
            if (slides[1]) slides[1].scrollIntoView({ behavior: 'smooth' });
        });
    }

    // Desktop Up/Down buttons + Keyboard ArrowUp / ArrowDown
    function jumpSlide(delta) {
        var curH = feed.clientHeight || window.innerHeight;
        var curIdx = Math.round(feed.scrollTop / curH);
        var nextIdx = Math.max(0, Math.min(slides.length, curIdx + delta));
        feed.scrollTo({ top: nextIdx * curH, behavior: 'smooth' });
    }
    var prevBtn = document.getElementById('cjIgPrevBtn');
    var nextBtn = document.getElementById('cjIgNextBtn');
    if (prevBtn) prevBtn.addEventListener('click', function () { jumpSlide(-1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { jumpSlide(1); });
    document.addEventListener('keydown', function (e) {
        if (document.getElementById('cjIgDrawer')?.classList.contains('is-open')) return;
        if (e.key === 'ArrowDown' || e.key === 'PageDown') { e.preventDefault(); jumpSlide(1); }
        else if (e.key === 'ArrowUp' || e.key === 'PageUp') { e.preventDefault(); jumpSlide(-1); }
    });

    // Like Button AJAX Toggle
    document.querySelectorAll('[data-ig-like]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var rid = btn.getAttribute('data-ig-like');
            beacon({ reel_id: Number(rid), action: 'like' }, function (d) {
                if (!d || !d.ok) return;
                btn.classList.toggle('is-liked', !!d.liked);
                var ic = document.querySelector('[data-ig-like-icon="' + rid + '"]');
                var cnt = document.querySelector('[data-ig-like-count="' + rid + '"]');
                if (ic) ic.textContent = d.liked ? '❤️' : '🤍';
                if (cnt) cnt.textContent = d.likes_count;
            });
        });
    });

    // Track Showroom Clicks & WhatsApp Taps
    document.querySelectorAll('[data-ig-click]').forEach(function (el) {
        el.addEventListener('click', function () {
            flushWatchTime();
            beacon({ reel_id: Number(el.getAttribute('data-ig-click')), action: 'click' });
        });
    });
    document.querySelectorAll('[data-ig-wa]').forEach(function (el) {
        el.addEventListener('click', function () {
            flushWatchTime();
            beacon({ reel_id: Number(el.getAttribute('data-ig-wa')), action: 'whatsapp' });
        });
    });

    // Share Button
    document.querySelectorAll('[data-ig-share]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var rid = btn.getAttribute('data-ig-share');
            var url = window.location.origin + '/showcase?v=' + rid;
            beacon({ reel_id: Number(rid), action: 'share' });
            if (navigator.share) {
                navigator.share({ title: 'ChandraKala Jewellers Reel #' + rid, url: url }).catch(function () {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    var lbl = document.querySelector('[data-ig-share-lbl="' + rid + '"]');
                    if (lbl) { lbl.textContent = 'Copied!'; setTimeout(function () { lbl.textContent = 'Share'; }, 1800); }
                }).catch(function () {});
            }
        });
    });

    // Slide-Up Comments Drawer
    var drawer = document.getElementById('cjIgDrawer');
    var drawerList = document.getElementById('cjIgDrawerList');
    var drawerTag = document.getElementById('cjIgDrawerReelTag');
    var drawerClose = document.getElementById('cjIgDrawerClose');
    var drawerForm = document.getElementById('cjIgDrawerForm');
    var cmtStatus = document.getElementById('cjIgCmtStatus');

    function renderCommentRow(c) {
        var div = document.createElement('div');
        div.className = 'cj-ig-drawer-item';
        var stars = c.rating > 0 ? '<div style="color:#f59e0b;font-size:12px;margin:2px 0;">' + '★'.repeat(c.rating) + '☆'.repeat(5 - c.rating) + '</div>' : '';
        div.innerHTML = '<div style="display:flex;justify-content:space-between;gap:8px;"><strong style="color:#fff;"></strong><span style="color:#94a3b8;font-size:10.5px;"></span></div>' + stars + '<p style="margin:4px 0 0;color:#e2e8f0;line-height:1.45;"></p>';
        div.querySelector('strong').textContent = c.customer_name;
        div.querySelector('span').textContent = c.time_ist;
        div.querySelector('p').textContent = c.comment;
        return div;
    }

    function openCommentsDrawer(rid) {
        drawerReelId = Number(rid);
        if (drawerTag) drawerTag.textContent = '· Reel #' + drawerReelId;
        if (cmtStatus) cmtStatus.textContent = '';
        if (drawerList) drawerList.innerHTML = '<p style="font-size:12px;color:#94a3b8;text-align:center;">Loading...</p>';
        if (drawer) drawer.classList.add('is-open');

        fetch(TRACK_URL + '?action=comments&reel_id=' + drawerReelId, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!drawerList) return;
                drawerList.innerHTML = '';
                var list = (d && d.comments) || [];
                if (!list.length) {
                    drawerList.innerHTML = '<p style="font-size:12.5px;color:#94a3b8;text-align:center;margin:16px 0;">No comments yet — be the first to ask weight, price or leave a review!</p>';
                    return;
                }
                list.forEach(function (c) { drawerList.appendChild(renderCommentRow(c)); });
            })
            .catch(function () {
                if (drawerList) drawerList.innerHTML = '<p style="font-size:12px;color:#f87171;text-align:center;">Could not load comments.</p>';
            });
    }

    document.querySelectorAll('[data-ig-comment]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openCommentsDrawer(btn.getAttribute('data-ig-comment'));
        });
    });
    if (drawerClose && drawer) drawerClose.addEventListener('click', function () { drawer.classList.remove('is-open'); });
    if (drawer) drawer.addEventListener('click', function (e) { if (e.target === drawer) drawer.classList.remove('is-open'); });

    if (drawerForm) {
        drawerForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!drawerReelId) return;
            if (cmtStatus) { cmtStatus.style.color = '#fbbf24'; cmtStatus.textContent = 'Posting...'; }
            var fd = new FormData(drawerForm);
            fd.append('reel_id', String(drawerReelId));
            fd.append('action', 'comment');
            fetch(TRACK_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || !d.ok) {
                        if (cmtStatus) { cmtStatus.style.color = '#f87171'; cmtStatus.textContent = (d && d.error) || 'Error posting.'; }
                        return;
                    }
                    if (cmtStatus) { cmtStatus.style.color = '#34d399'; cmtStatus.textContent = '✅ Posted!'; }
                    if (drawerList && d.comment) {
                        var emptyP = drawerList.querySelector('p');
                        if (emptyP && !emptyP.closest('.cj-ig-drawer-item')) emptyP.remove();
                        drawerList.insertBefore(renderCommentRow(d.comment), drawerList.firstChild);
                    }
                    var cnt = document.querySelector('[data-ig-cmt-count="' + drawerReelId + '"]');
                    if (cnt && typeof d.comments_count !== 'undefined') cnt.textContent = d.comments_count;
                    var tx = document.getElementById('cjIgCmtText');
                    if (tx) tx.value = '';
                })
                .catch(function () {
                    if (cmtStatus) { cmtStatus.style.color = '#f87171'; cmtStatus.textContent = 'Network error.'; }
                });
        });
    }

    // Flush watch duration when user leaves or closes the page
    window.addEventListener('pagehide', flushWatchTime);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) flushWatchTime();
    });
    try { localStorage.setItem('cj_reels_launch_notif_v1', '1'); } catch (e) {}
})();
</script>

<?php else: ?>
<!-- OPTIONAL CINEMATIC BENTO MASONRY GRID VIEW (/reels?view=grid) -->
<?php $activePage = 'reels'; include 'includes/header.php'; ?>
<main id="main-content" class="container page-enter" style="padding: 24px 16px 72px;">
    <div class="cj-reels-hero">
        <div>
            <p style="font-size:11px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--primary-gold-dark);margin:0 0 6px;">ChandraKala Cinema</p>
            <h1 class="section-heading" style="margin:0;text-align:left;">Jewellery Reels Showcase</h1>
            <p style="margin:6px 0 0;font-size:13px;color:var(--text-secondary);">
                Tap any reel to order or switch to full-screen vertical swipe mode.
                <?php if ($totalViewsAll > 0): ?>
                    <strong style="color:var(--primary-gold-dark);">&nbsp;·&nbsp;<?= htmlspecialchars(cj_format_reel_count($totalViewsAll)) ?>+ views</strong>
                <?php endif; ?>
            </p>
        </div>
        <div class="cj-reels-sort" role="navigation" aria-label="View & sort reels">
            <a href="/reels" class="">▶ Swipe Reels</a>
            <a href="/reels?view=grid&amp;sort=top" class="<?= $sort === 'top' ? 'is-active' : '' ?>">👑 Top Viewed</a>
            <a href="/reels?view=grid&amp;sort=latest" class="<?= $sort === 'latest' ? 'is-active' : '' ?>">✨ Latest</a>
        </div>
    </div>

    <?php if (empty($cjReelsList)): ?>
        <div style="text-align:center;padding:56px 20px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:20px;">
            <p style="font-size:18px;font-weight:700;font-family:'Playfair Display',serif;margin:0 0 8px;">New Reels Arriving Soon</p>
            <a href="/gallery" class="btn">Shop Collection</a>
        </div>
    <?php else: ?>
        <?php include __DIR__ . '/includes/reels-bento.php'; ?>
    <?php endif; ?>
</main>
<?php include 'includes/footer.php'; ?>
<?php endif; ?>

</body>
</html>
