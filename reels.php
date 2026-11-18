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
$startId = (int)($_GET['v'] ?? 0);

// Video reels for SEO schema + Bento Grid mode
$cjVideoReels = cj_get_ranked_reels($conn, 60, true, $sort);
$cjReelsList = $cjVideoReels;
$cjReelsContext = 'reels';

// Endless Instagram Feed: Personalized Video Reels + Sponsored Gallery & Catalogue Photo Ads!
$cjFeedSlides = ($viewMode === 'feed')
    ? cj_build_endless_reels_feed($conn, $visitorHash, $startId, 28)
    : [];

$totalViewsAll = 0;
foreach ($cjVideoReels as $rr) {
    $totalViewsAll += (int)$rr['display_views'];
}

$videoSchemas = [];
$itemListElements = [];
foreach (array_slice($cjVideoReels, 0, 12) as $vIdx => $rr) {
    $videoSchemas[] = cj_reel_video_object_schema($rr);
    $itemListElements[] = [
        '@type' => 'ListItem',
        'position' => $vIdx + 1,
        'url' => cj_abs_url('showcase?v=' . (int)$rr['id']),
        'name' => cj_reel_seo_title($rr),
    ];
}
$leadPoster = !empty($cjVideoReels[0]['poster_thumb']) ? (string)$cjVideoReels[0]['poster_thumb'] : cj_abs_url('images/logo.png');

$seo = cj_merge_seo([
    'title' => 'Jewellery Reels — 916 Gold & Bridal Videos | ChandraKala Jewellers Khedbrahma',
    'description' => 'Watch real handcrafted 22K 916 gold, bridal, mangalsutra & 925 silver jewellery video reels from ChandraKala Jewellers, Civil Road, Khedbrahma. Tap any reel to order.',
    'keywords' => 'ChandraKala Jewellers reels, gold jewellery videos Khedbrahma, 916 hallmark gold reels, bridal jewellery videos Gujarat, mangalsutra design video, Sabarkantha jewellers',
    'canonical' => cj_abs_url('reels'),
    'og_image' => $leadPoster,
    'schema' => array_merge(
        [
            cj_local_business_schema(),
            cj_breadcrumb_schema([
                ['name' => 'Home', 'url' => cj_abs_url('index1')],
                ['name' => 'Jewellery Reels', 'url' => cj_abs_url('reels')],
            ]),
            [
                '@type' => 'ItemList',
                'name' => 'ChandraKala Jewellers Video Reels',
                'numberOfItems' => count($cjVideoReels),
                'itemListElement' => $itemListElements,
            ],
        ],
        $videoSchemas
    ),
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
    font-size: 17px;
    font-weight: 700;
    color: #fff8e7;
    letter-spacing: 0.02em;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.6);
}

/* Vertical Snap-Scroll Feed */
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
    .cj-ig-slide { padding: 58px 16px 24px; }
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

/* Sponsored Photo Ad Canvas (Ambient Blur + Ken Burns Motion) */
.cj-ig-ad-bg {
    position: absolute;
    inset: -24px;
    width: calc(100% + 48px);
    height: calc(100% + 48px);
    object-fit: cover;
    filter: blur(26px) brightness(0.42) saturate(1.15);
    transform: scale(1.1);
    pointer-events: none;
}
.cj-ig-ad-stage {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 76px 14px 168px;
    cursor: pointer;
    z-index: 1;
}
.cj-ig-ad-photo {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 20px;
    box-shadow: 0 20px 54px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(240, 215, 140, 0.25);
    animation: cjAdKenBurns 11s ease-in-out infinite alternate;
    will-change: transform;
}
@keyframes cjAdKenBurns {
    0% { transform: scale(1); }
    100% { transform: scale(1.065); }
}

.cj-ig-frame::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.38) 0%, rgba(0,0,0,0) 22%, rgba(0,0,0,0) 64%, rgba(0,0,0,0.78) 100%);
    pointer-events: none;
    z-index: 2;
}

/* Badges: Top Viewed / Sponsored & Views */
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
.cj-ig-badge.is-ad {
    background: rgba(255, 255, 255, 0.14);
    border-color: rgba(240, 215, 140, 0.5);
    color: #fde68a;
    letter-spacing: 0.03em;
}

/* Right-Side Current Instagram Reels Action Rail */
.cj-ig-rail {
    position: absolute;
    right: 12px;
    bottom: max(100px, calc(env(safe-area-inset-bottom, 12px) + 92px));
    z-index: 8;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 17px;
}
@media (min-width: 769px) {
    .cj-ig-rail { bottom: 32px; right: 14px; }
}
.cj-ig-rail-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    background: none;
    border: none;
    color: #fff;
    cursor: pointer;
    text-decoration: none;
    font-family: inherit;
    padding: 2px 4px;
    -webkit-tap-highlight-color: transparent;
    transition: transform 0.16s cubic-bezier(.22,1,.36,1), opacity 0.16s ease;
}
.cj-ig-rail-btn:hover { opacity: 0.92; transform: scale(1.06); }
.cj-ig-rail-btn:active { transform: scale(0.86); }
.cj-ig-rail-svg {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.72));
}
.cj-ig-rail-svg svg {
    width: 27px;
    height: 27px;
    display: block;
}
.cj-ig-rail-btn[data-ig-like] .cj-ig-heart-path {
    fill: transparent;
    stroke: #ffffff;
    stroke-width: 2;
    transition: fill 0.18s ease, stroke 0.18s ease;
}
.cj-ig-rail-btn.is-liked .cj-ig-heart-path {
    fill: #ff3040;
    stroke: #ff3040;
}
.cj-ig-rail-btn.is-liked .cj-ig-rail-svg {
    animation: cjIgLikeSpring 0.36s cubic-bezier(.17,.89,.32,1.45);
}
@keyframes cjIgLikeSpring {
    0% { transform: scale(1); }
    40% { transform: scale(1.32); }
    75% { transform: scale(0.92); }
    100% { transform: scale(1); }
}
.cj-ig-rail-btn.is-order .cj-ig-rail-svg {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #fff3c4 0%, #e5be54 48%, #b88b1e 100%);
    color: #1a1204;
    box-shadow: 0 4px 16px rgba(212, 175, 55, 0.45);
}
.cj-ig-rail-btn.is-order .cj-ig-rail-svg svg {
    width: 19px;
    height: 19px;
}
.cj-ig-rail-label {
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.01em;
    color: #fff;
    text-shadow: 0 1px 6px rgba(0, 0, 0, 0.9);
}

/* Bottom-Left Showroom Pill (Video Reels) & Instagram Sponsored Product Card (Photo Ads) */
.cj-ig-bottom-cta {
    position: absolute;
    left: 14px;
    right: 74px;
    bottom: max(18px, calc(env(safe-area-inset-bottom, 12px) + 12px));
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

/* Instagram-Style Sponsored Product Overlay Card */
.cj-ig-ad-card {
    width: 100%;
    border-radius: 16px;
    background: rgba(15, 18, 26, 0.78);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(240, 215, 140, 0.4);
    padding: 10px 12px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45);
    display: flex;
    flex-direction: column;
    gap: 9px;
}
.cj-ig-ad-card-top {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}
.cj-ig-ad-thumb {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid rgba(255,255,255,0.18);
    flex-shrink: 0;
    background: #161a22;
}
.cj-ig-ad-title {
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    margin: 0;
    line-height: 1.25;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.cj-ig-ad-sub {
    font-size: 11px;
    color: #fde68a;
    font-weight: 600;
    margin: 2px 0 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cj-ig-ad-cta-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 14px;
    border-radius: 11px;
    background: linear-gradient(135deg, #fff3c4 0%, #e5be54 50%, #c49222 100%);
    color: #1a1204;
    font-size: 12.5px;
    font-weight: 800;
    text-decoration: none;
    box-shadow: 0 4px 16px rgba(212, 175, 55, 0.32);
    transition: transform 0.16s ease, filter 0.16s ease;
}
.cj-ig-ad-cta-btn:hover { filter: brightness(1.06); }
.cj-ig-ad-cta-btn:active { transform: scale(0.98); }

/* First Reel Scroll Hint */
.cj-ig-scroll-hint {
    position: absolute;
    left: 50%;
    bottom: max(76px, calc(env(safe-area-inset-bottom, 12px) + 66px));
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

.cj-ig-heart-burst {
    position: absolute;
    left: 50%;
    top: 50%;
    z-index: 12;
    transform: translate(-50%, -50%) scale(0);
    opacity: 0;
    pointer-events: none;
    filter: drop-shadow(0 6px 24px rgba(255, 48, 64, 0.7));
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

.cj-ig-desktop-nav { display: none; }
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
<!-- ENDLESS INSTAGRAM-STYLE REELS FEED (VIDEOS + SPONSORED SHOP & CATALOGUE PHOTO ADS) -->
<div class="cj-ig-shell">
    <header class="cj-ig-topbar">
        <a href="/index1" class="cj-ig-top-btn" aria-label="Back to home">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            <span>Home</span>
        </a>
        <span class="cj-ig-brand">Reels</span>
        <div style="display:flex;gap:8px;align-items:center;">
            <button type="button" id="cjIgSoundBtn" class="cj-ig-top-btn" aria-label="Toggle sound" style="padding:7px 10px;">
                <svg id="cjIgSoundSvg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5L6 9H2v6h4l5 4V5z"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
            </button>
            <a href="/reels?view=grid" class="cj-ig-top-btn" title="Switch to Bento Grid View">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                <span>Grid</span>
            </a>
        </div>
    </header>

    <div class="cj-ig-feed" id="cjIgFeed">
        <?php foreach ($cjFeedSlides as $idx => $slide):
            $kind = (string)($slide['kind'] ?? 'video');
            $itemKey = (string)($slide['item_key'] ?? ('vid:' . (int)$slide['id']));
            $isVideo = ($kind === 'video');
            $sid = (int)$slide['id'];
            $isLiked = !empty($slide['is_liked']);
            $viewsFmt = cj_format_reel_count((int)$slide['display_views']);
            $likesCount = (int)($slide['likes_count'] ?? 0);
            $commentsCount = (int)($slide['comments_count'] ?? 0);

            if ($isVideo) {
                $targetUrl = '/showcase?v=' . $sid;
                $shareUrl = cj_abs_url('showcase?v=' . $sid);
                $waUrl = 'https://wa.me/919427080359?text=' . rawurlencode("Namaste ChandraKala Jewellers! I loved Jewellery Reel #{$sid} on your website and want to order / enquire.\nLink: {$shareUrl}");
            } else {
                $targetUrl = (string)$slide['target_url'];
                $shareUrl = (string)$slide['share_url'];
                $waUrl = 'https://wa.me/919427080359?text=' . rawurlencode((string)($slide['wa_text'] ?? ''));
            }
        ?>
        <section class="cj-ig-slide"
                 data-ig-slide="<?= $idx ?>"
                 data-slide-kind="<?= htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') ?>"
                 data-item-key="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>"
                 data-reel-id="<?= $isVideo ? $sid : 0 ?>">
            <div class="cj-ig-frame">
                <?php if ($isVideo): ?>
                    <video class="cj-ig-video"
                           data-src="<?= htmlspecialchars((string)$slide['hd_url'], ENT_QUOTES, 'UTF-8') ?>"
                           <?php if (!empty($slide['poster_thumb'])): ?>poster="<?= htmlspecialchars((string)$slide['poster_thumb'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                           muted
                           loop
                           playsinline
                           webkit-playsinline
                           preload="<?= $idx <= 1 ? 'auto' : 'none' ?>"
                           disablepictureinpicture></video>
                <?php else: ?>
                    <img class="cj-ig-ad-bg" src="<?= htmlspecialchars((string)$slide['thumb_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" aria-hidden="true" loading="lazy">
                    <div class="cj-ig-ad-stage" data-ig-tap-surface="1">
                        <img class="cj-ig-ad-photo"
                             src="<?= htmlspecialchars((string)$slide['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars((string)$slide['title'], ENT_QUOTES, 'UTF-8') ?>"
                             loading="<?= $idx <= 2 ? 'eager' : 'lazy' ?>"
                             decoding="async">
                    </div>
                <?php endif; ?>

                <div class="cj-ig-heart-burst" aria-hidden="true">
                    <svg width="88" height="88" viewBox="0 0 24 24" fill="#ff3040"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                </div>

                <!-- Top-Left Badges -->
                <div class="cj-ig-badges">
                    <?php if ($isVideo && !empty($slide['is_top_viewed'])): ?>
                        <span class="cj-ig-badge is-top">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/></svg>
                            Top Viewed
                        </span>
                    <?php elseif (!$isVideo): ?>
                        <span class="cj-ig-badge is-ad">
                            <?= htmlspecialchars((string)$slide['badge_text']) ?>
                        </span>
                    <?php endif; ?>
                    <span class="cj-ig-badge">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        <span data-ig-views="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($viewsFmt) ?></span>
                    </span>
                </div>

                <!-- Right-Side Modern Instagram Vector SVG Action Rail -->
                <div class="cj-ig-rail">
                    <button type="button" class="cj-ig-rail-btn <?= $isLiked ? 'is-liked' : '' ?>" data-ig-like="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>" aria-label="Like">
                        <span class="cj-ig-rail-svg">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path class="cj-ig-heart-path" stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                        </span>
                        <span class="cj-ig-rail-label" data-ig-like-count="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>"><?= $likesCount ?></span>
                    </button>

                    <button type="button" class="cj-ig-rail-btn" data-ig-comment="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>" aria-label="Comments">
                        <span class="cj-ig-rail-svg">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                            </svg>
                        </span>
                        <span class="cj-ig-rail-label" data-ig-cmt-count="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>"><?= $commentsCount ?></span>
                    </button>

                    <button type="button" class="cj-ig-rail-btn" data-ig-share="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>" data-share-url="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Share">
                        <span class="cj-ig-rail-svg">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                        </span>
                        <span class="cj-ig-rail-label" data-ig-share-lbl="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>">Share</span>
                    </button>

                    <a href="<?= htmlspecialchars($waUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="cj-ig-rail-btn" data-ig-wa="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>" aria-label="Chat on WhatsApp">
                        <span class="cj-ig-rail-svg">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 11.5a8.5 8.5 0 0 1-12.5 7.5L3 20.5l1.6-5.2A8.5 8.5 0 1 1 21 11.5z"/>
                                <path d="M9.5 9.2c.2-.4.5-.5.8-.5h.5c.2 0 .4.1.5.4l.7 1.6c.1.2.1.5-.1.7l-.5.6c.5.9 1.3 1.7 2.2 2.2l.6-.5c.2-.2.5-.2.7-.1l1.6.7c.3.1.4.3.4.5v.5c0 .3-.2.6-.5.8-.5.3-1.2.4-2 .1-1.9-.6-4.1-2.8-4.7-4.7-.3-.8-.2-1.5.1-2z"/>
                            </svg>
                        </span>
                        <span class="cj-ig-rail-label">Chat</span>
                    </a>

                    <a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') ?>" class="cj-ig-rail-btn is-order" data-ig-click="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= $isVideo ? 'Order' : 'Shop product' ?>">
                        <span class="cj-ig-rail-svg">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                <line x1="3" y1="6" x2="21" y2="6"/>
                                <path d="M16 10a4 4 0 0 1-8 0"/>
                            </svg>
                        </span>
                        <span class="cj-ig-rail-label"><?= $isVideo ? 'Order' : 'Shop' ?></span>
                    </a>
                </div>

                <!-- Bottom CTA: Showroom Pill (Video) OR Instagram Sponsored Ad Card (Gallery/Catalogue Photo) -->
                <div class="cj-ig-bottom-cta">
                    <?php if ($isVideo): ?>
                        <a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') ?>" class="cj-ig-showroom-pill" data-ig-click="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span>Showroom Contact, Live Rates &amp; Reviews</span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                    <?php else: ?>
                        <div class="cj-ig-ad-card">
                            <div class="cj-ig-ad-card-top">
                                <img class="cj-ig-ad-thumb" src="<?= htmlspecialchars((string)$slide['thumb_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                <div style="min-width:0;flex:1;">
                                    <p class="cj-ig-ad-title"><?= htmlspecialchars((string)$slide['title']) ?></p>
                                    <p class="cj-ig-ad-sub"><?= htmlspecialchars((string)$slide['subtitle']) ?></p>
                                </div>
                            </div>
                            <a href="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') ?>" class="cj-ig-ad-cta-btn" data-ig-click="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>">
                                <span><?= htmlspecialchars((string)$slide['cta_label']) ?></span>
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- First Slide ONLY: Animated Scroll Hint -->
                <?php if ($idx === 0 && count($cjFeedSlides) > 1): ?>
                <button type="button" id="cjIgScrollHint" class="cj-ig-scroll-hint" aria-label="Scroll to next reel">
                    <span>Scroll to see next</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <?php endif; ?>

                <?php if ($isVideo): ?>
                <div class="cj-ig-progress-track">
                    <div class="cj-ig-progress-bar" data-ig-progress="<?= htmlspecialchars($itemKey, ENT_QUOTES, 'UTF-8') ?>"></div>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div>

    <!-- Desktop Up/Down Navigation Arrows -->
    <div class="cj-ig-desktop-nav" aria-label="Reel navigation">
        <button type="button" id="cjIgPrevBtn" class="cj-ig-nav-arrow" title="Previous reel (↑)">↑</button>
        <button type="button" id="cjIgNextBtn" class="cj-ig-nav-arrow" title="Next reel (↓)">↓</button>
    </div>

    <!-- Slide-Up Instagram Comments & Reviews Drawer (Works for BOTH Video Reels & Sponsored Ads!) -->
    <div id="cjIgDrawer" class="cj-ig-drawer-backdrop" role="dialog" aria-modal="true" aria-label="Comments">
        <div class="cj-ig-drawer">
            <div class="cj-ig-drawer-head">
                <strong style="font-family:'Playfair Display',serif;font-size:16px;">Comments &amp; Reviews <span id="cjIgDrawerTag" style="color:#fde68a;font-size:12px;"></span></strong>
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
</div>

<script>
(function () {
    'use strict';
    var TRACK_URL = '/api/reel-track.php';
    var feed = document.getElementById('cjIgFeed');
    var soundBtn = document.getElementById('cjIgSoundBtn');
    var scrollHint = document.getElementById('cjIgScrollHint');
    if (!feed) return;

    var globalMuted = true;
    var activeKey = null;
    var activeVideoId = 0;
    var activeStartMs = 0;
    var trackedImp = {};
    var seenKeys = new Set();
    var isLoadingMore = false;
    var drawerKey = '';

    function payloadForKey(key, extra) {
        var obj = Object.assign({}, extra || {});
        if (key && key.indexOf('vid:') === 0) {
            obj.reel_id = Number(key.slice(4));
        } else if (key) {
            obj.item_key = key;
        }
        return obj;
    }

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
        if (!activeVideoId || !activeStartMs) return;
        var elapsed = Date.now() - activeStartMs;
        if (elapsed >= 600) {
            beacon({ reel_id: activeVideoId, action: 'watch', watch_ms: Math.min(300000, elapsed) });
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
        var soundSvg = document.getElementById('cjIgSoundSvg');
        if (soundSvg) {
            soundSvg.innerHTML = globalMuted
                ? '<path d="M11 5L6 9H2v6h4l5 4V5z"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/>'
                : '<path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>';
        }
        feed.querySelectorAll('video').forEach(function (v) { v.muted = globalMuted; });
    }

    if (soundBtn) {
        soundBtn.addEventListener('click', function () {
            globalMuted = !globalMuted;
            syncSoundUI();
            if (activeKey) {
                var cur = feed.querySelector('.cj-ig-slide[data-item-key="' + activeKey + '"] video');
                if (cur) cur.play().catch(function () {});
            }
        });
    }

    // Infinite Loader: Appends 16 fresh Sponsored Shop & Catalogue Ads before user reaches the bottom!
    function maybeLoadMoreSlides(currentIdx) {
        var total = feed.querySelectorAll('.cj-ig-slide').length;
        if (isLoadingMore || currentIdx < total - 4) return;
        isLoadingMore = true;
        var excl = Array.from(seenKeys).filter(function (k) { return k.indexOf('vid:') !== 0; }).slice(-80).join(',');
        fetch(TRACK_URL + '?action=more_feed&exclude=' + encodeURIComponent(excl), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || !d.ok || !Array.isArray(d.slides)) return;
                d.slides.forEach(function (s) {
                    var idx = feed.querySelectorAll('.cj-ig-slide').length;
                    var sec = buildAdSlideEl(s, idx);
                    feed.appendChild(sec);
                    bindSlide(sec);
                });
            })
            .catch(function () {})
            .finally(function () { isLoadingMore = false; });
    }

    function escHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
        });
    }

    function buildAdSlideEl(s, idx) {
        var key = s.item_key;
        var sec = document.createElement('section');
        sec.className = 'cj-ig-slide';
        sec.setAttribute('data-ig-slide', String(idx));
        sec.setAttribute('data-slide-kind', s.kind || 'shop_ad');
        sec.setAttribute('data-item-key', key);
        sec.setAttribute('data-reel-id', '0');

        sec.innerHTML =
            '<div class="cj-ig-frame">'
            + '<img class="cj-ig-ad-bg" src="' + escHtml(s.thumb_url) + '" alt="" aria-hidden="true" loading="lazy">'
            + '<div class="cj-ig-ad-stage" data-ig-tap-surface="1">'
            +   '<img class="cj-ig-ad-photo" src="' + escHtml(s.image_url) + '" alt="' + escHtml(s.title) + '" loading="lazy" decoding="async">'
            + '</div>'
            + '<div class="cj-ig-heart-burst" aria-hidden="true"><svg width="88" height="88" viewBox="0 0 24 24" fill="#ff3040"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></div>'
            + '<div class="cj-ig-badges">'
            +   '<span class="cj-ig-badge is-ad">' + escHtml(s.badge_text) + '</span>'
            +   '<span class="cj-ig-badge"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg><span data-ig-views="' + escHtml(key) + '">' + escHtml(s.views_fmt || '1') + '</span></span>'
            + '</div>'
            + '<div class="cj-ig-rail">'
            +   '<button type="button" class="cj-ig-rail-btn' + (s.is_liked ? ' is-liked' : '') + '" data-ig-like="' + escHtml(key) + '" aria-label="Like"><span class="cj-ig-rail-svg"><svg viewBox="0 0 24 24"><path class="cj-ig-heart-path" stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></span><span class="cj-ig-rail-label" data-ig-like-count="' + escHtml(key) + '">' + Number(s.likes_count || 0) + '</span></button>'
            +   '<button type="button" class="cj-ig-rail-btn" data-ig-comment="' + escHtml(key) + '" aria-label="Comments"><span class="cj-ig-rail-svg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></span><span class="cj-ig-rail-label" data-ig-cmt-count="' + escHtml(key) + '">' + Number(s.comments_count || 0) + '</span></button>'
            +   '<button type="button" class="cj-ig-rail-btn" data-ig-share="' + escHtml(key) + '" data-share-url="' + escHtml(s.share_url) + '" aria-label="Share"><span class="cj-ig-rail-svg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></span><span class="cj-ig-rail-label" data-ig-share-lbl="' + escHtml(key) + '">Share</span></button>'
            +   '<a href="' + escHtml(s.wa_url) + '" target="_blank" rel="noopener" class="cj-ig-rail-btn" data-ig-wa="' + escHtml(key) + '" aria-label="Chat"><span class="cj-ig-rail-svg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-12.5 7.5L3 20.5l1.6-5.2A8.5 8.5 0 1 1 21 11.5z"/><path d="M9.5 9.2c.2-.4.5-.5.8-.5h.5c.2 0 .4.1.5.4l.7 1.6c.1.2.1.5-.1.7l-.5.6c.5.9 1.3 1.7 2.2 2.2l.6-.5c.2-.2.5-.2.7-.1l1.6.7c.3.1.4.3.4.5v.5c0 .3-.2.6-.5.8-.5.3-1.2.4-2 .1-1.9-.6-4.1-2.8-4.7-4.7-.3-.8-.2-1.5.1-2z"/></svg></span><span class="cj-ig-rail-label">Chat</span></a>'
            +   '<a href="' + escHtml(s.target_url) + '" class="cj-ig-rail-btn is-order" data-ig-click="' + escHtml(key) + '" aria-label="Shop"><span class="cj-ig-rail-svg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></span><span class="cj-ig-rail-label">Shop</span></a>'
            + '</div>'
            + '<div class="cj-ig-bottom-cta">'
            +   '<div class="cj-ig-ad-card">'
            +     '<div class="cj-ig-ad-card-top"><img class="cj-ig-ad-thumb" src="' + escHtml(s.thumb_url) + '" alt="" loading="lazy"><div style="min-width:0;flex:1;"><p class="cj-ig-ad-title">' + escHtml(s.title) + '</p><p class="cj-ig-ad-sub">' + escHtml(s.subtitle) + '</p></div></div>'
            +     '<a href="' + escHtml(s.target_url) + '" class="cj-ig-ad-cta-btn" data-ig-click="' + escHtml(key) + '"><span>' + escHtml(s.cta_label) + '</span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>'
            +   '</div>'
            + '</div>'
            + '</div>';
        return sec;
    }

    var slideObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var slide = entry.target;
            var key = slide.getAttribute('data-item-key');
            var vidId = Number(slide.getAttribute('data-reel-id') || 0);
            var idx = Number(slide.getAttribute('data-ig-slide') || 0);
            var vid = slide.querySelector('video');

            if (entry.isIntersecting && entry.intersectionRatio >= 0.65) {
                if (idx > 0 && scrollHint) scrollHint.classList.add('is-hidden');
                if (activeKey && activeKey !== key) flushWatchTime();

                activeKey = key;
                activeVideoId = vidId;
                activeStartMs = Date.now();
                seenKeys.add(key);

                // Hydrate current & next video slide cleanly
                hydrateVideo(vid);
                var nextSlide = slide.nextElementSibling;
                if (nextSlide) hydrateVideo(nextSlide.querySelector('video'));

                if (vid) {
                    vid.muted = globalMuted;
                    vid.play().catch(function () {
                        vid.muted = true;
                        vid.play().catch(function () {});
                    });
                }

                // Trigger infinite feed loader when nearing bottom
                maybeLoadMoreSlides(idx);

                // Count Impression once per session
                if (key && !trackedImp[key]) {
                    trackedImp[key] = true;
                    setTimeout(function () {
                        if (activeKey !== key) return;
                        beacon(payloadForKey(key, { action: 'impression' }), function (d) {
                            if (d && d.display_views_fmt) {
                                var el = feed.querySelector('[data-ig-views="' + key + '"]');
                                if (el) el.textContent = d.display_views_fmt;
                            }
                        });
                    }, 900);
                }
            } else {
                if (vid) { try { vid.pause(); } catch (e) {} }
                if (activeKey === key) {
                    flushWatchTime();
                    activeKey = null;
                    activeVideoId = 0;
                }
            }
        });
    }, { root: feed, threshold: [0.2, 0.65, 0.9] });

    function bindSlide(slide) {
        if (!slide || slide.dataset.bound === '1') return;
        slide.dataset.bound = '1';
        slideObserver.observe(slide);

        var key = slide.getAttribute('data-item-key');
        seenKeys.add(key);
        var vid = slide.querySelector('video');
        var tapSurface = vid || slide.querySelector('[data-ig-tap-surface]');
        var prog = slide.querySelector('[data-ig-progress]');
        var burst = slide.querySelector('.cj-ig-heart-burst');

        if (vid && prog) {
            vid.addEventListener('timeupdate', function () {
                if (vid.duration && vid.duration > 0) {
                    prog.style.width = ((vid.currentTime / vid.duration) * 100).toFixed(1) + '%';
                }
            });
        }

        // Single tap = Pause/Play (video) · Double tap = Instagram Heart Like Burst (video & photo ads!)
        if (tapSurface) {
            var lastTap = 0;
            var tapTimer = null;
            tapSurface.addEventListener('click', function () {
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
                    if (likeBtn && !likeBtn.classList.contains('is-liked')) likeBtn.click();
                } else {
                    lastTap = now;
                    if (vid) {
                        tapTimer = setTimeout(function () {
                            if (vid.paused) vid.play().catch(function () {});
                            else vid.pause();
                        }, 260);
                    }
                }
            });
        }

        // Like Button
        var likeBtn = slide.querySelector('[data-ig-like]');
        if (likeBtn) {
            likeBtn.addEventListener('click', function () {
                beacon(payloadForKey(key, { action: 'like' }), function (d) {
                    if (!d || !d.ok) return;
                    likeBtn.classList.toggle('is-liked', !!d.liked);
                    var cnt = slide.querySelector('[data-ig-like-count]');
                    if (cnt) cnt.textContent = d.likes_count;
                });
            });
        }

        // Comment Button
        var cmtBtn = slide.querySelector('[data-ig-comment]');
        if (cmtBtn) {
            cmtBtn.addEventListener('click', function () { openCommentsDrawer(key); });
        }

        // Share Button
        var shareBtn = slide.querySelector('[data-ig-share]');
        if (shareBtn) {
            shareBtn.addEventListener('click', function () {
                var url = shareBtn.getAttribute('data-share-url') || window.location.href;
                beacon(payloadForKey(key, { action: 'share' }));
                if (navigator.share) {
                    navigator.share({ title: 'ChandraKala Jewellers Khedbrahma', url: url }).catch(function () {});
                } else if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(function () {
                        var lbl = slide.querySelector('[data-ig-share-lbl]');
                        if (lbl) { lbl.textContent = 'Copied!'; setTimeout(function () { lbl.textContent = 'Share'; }, 1800); }
                    }).catch(function () {});
                }
            });
        }

        // Track Clicks & WhatsApp
        slide.querySelectorAll('[data-ig-click]').forEach(function (el) {
            el.addEventListener('click', function () {
                flushWatchTime();
                beacon(payloadForKey(key, { action: 'click' }));
            });
        });
        slide.querySelectorAll('[data-ig-wa]').forEach(function (el) {
            el.addEventListener('click', function () {
                flushWatchTime();
                beacon(payloadForKey(key, { action: 'whatsapp' }));
            });
        });
    }

    feed.querySelectorAll('.cj-ig-slide').forEach(bindSlide);

    if (scrollHint) {
        scrollHint.addEventListener('click', function () {
            scrollHint.classList.add('is-hidden');
            var all = feed.querySelectorAll('.cj-ig-slide');
            if (all[1]) all[1].scrollIntoView({ behavior: 'smooth' });
        });
    }

    function jumpSlide(delta) {
        var curH = feed.clientHeight || window.innerHeight;
        var allCount = feed.querySelectorAll('.cj-ig-slide').length;
        var curIdx = Math.round(feed.scrollTop / curH);
        var nextIdx = Math.max(0, Math.min(allCount - 1, curIdx + delta));
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

    // Comments Drawer (supports vid:*, shop:*, cat:*)
    var drawer = document.getElementById('cjIgDrawer');
    var drawerList = document.getElementById('cjIgDrawerList');
    var drawerTag = document.getElementById('cjIgDrawerTag');
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

    function openCommentsDrawer(key) {
        drawerKey = key;
        if (drawerTag) drawerTag.textContent = key.indexOf('vid:') === 0 ? ('· Reel #' + key.slice(4)) : (key.indexOf('shop:') === 0 ? ('· Product #' + key.slice(5)) : ('· Design #' + key.slice(4)));
        if (cmtStatus) cmtStatus.textContent = '';
        if (drawerList) drawerList.innerHTML = '<p style="font-size:12px;color:#94a3b8;text-align:center;">Loading...</p>';
        if (drawer) drawer.classList.add('is-open');

        var qUrl = key.indexOf('vid:') === 0
            ? (TRACK_URL + '?action=comments&reel_id=' + Number(key.slice(4)))
            : (TRACK_URL + '?action=ad_comments&item_key=' + encodeURIComponent(key));

        fetch(qUrl, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!drawerList) return;
                drawerList.innerHTML = '';
                var list = (d && d.comments) || [];
                if (!list.length) {
                    drawerList.innerHTML = '<p style="font-size:12.5px;color:#94a3b8;text-align:center;margin:16px 0;">No comments yet — ask weight, price or share a review!</p>';
                    return;
                }
                list.forEach(function (c) { drawerList.appendChild(renderCommentRow(c)); });
            })
            .catch(function () {
                if (drawerList) drawerList.innerHTML = '<p style="font-size:12px;color:#f87171;text-align:center;">Could not load comments.</p>';
            });
    }

    if (drawerClose && drawer) drawerClose.addEventListener('click', function () { drawer.classList.remove('is-open'); });
    if (drawer) drawer.addEventListener('click', function (e) { if (e.target === drawer) drawer.classList.remove('is-open'); });

    if (drawerForm) {
        drawerForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!drawerKey) return;
            if (cmtStatus) { cmtStatus.style.color = '#fbbf24'; cmtStatus.textContent = 'Posting...'; }
            var fd = new FormData(drawerForm);
            fd.append('action', 'comment');
            if (drawerKey.indexOf('vid:') === 0) fd.append('reel_id', drawerKey.slice(4));
            else fd.append('item_key', drawerKey);

            fetch(TRACK_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || !d.ok) {
                        if (cmtStatus) { cmtStatus.style.color = '#f87171'; cmtStatus.textContent = (d && d.error) || 'Error posting.'; }
                        return;
                    }
                    if (cmtStatus) { cmtStatus.style.color = '#34d399'; cmtStatus.textContent = 'Posted!'; }
                    if (drawerList && d.comment) {
                        var emptyP = drawerList.querySelector('p');
                        if (emptyP && !emptyP.closest('.cj-ig-drawer-item')) emptyP.remove();
                        drawerList.insertBefore(renderCommentRow(d.comment), drawerList.firstChild);
                    }
                    var cnt = feed.querySelector('[data-ig-cmt-count="' + drawerKey + '"]');
                    if (cnt && typeof d.comments_count !== 'undefined') cnt.textContent = d.comments_count;
                    var tx = document.getElementById('cjIgCmtText');
                    if (tx) tx.value = '';
                })
                .catch(function () {
                    if (cmtStatus) { cmtStatus.style.color = '#f87171'; cmtStatus.textContent = 'Network error.'; }
                });
        });
    }

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
            <a href="/reels">▶ Swipe Reels</a>
            <a href="/reels?view=grid&amp;sort=top" class="<?= $sort === 'top' ? 'is-active' : '' ?>">Top Viewed</a>
            <a href="/reels?view=grid&amp;sort=latest" class="<?= $sort === 'latest' ? 'is-active' : '' ?>">Latest</a>
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
