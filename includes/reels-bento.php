<?php
/**
 * Reusable Cinematic Bento Masonry Video Wall.
 * Zero titles / captions on video tiles — pure autoplaying luxury reels.
 * Top Viewed reel is automatically placed in the #1 Hero Bento slot with a golden badge.
 *
 * Expected variables before including:
 *   $cjReelsList (array from cj_get_ranked_reels)
 *   $cjReelsContext ('home' | 'reels')
 */
if (empty($cjReelsList) || !is_array($cjReelsList)) {
    return;
}
$cjReelsContext = $cjReelsContext ?? 'home';
$cjReelsCount = count($cjReelsList);
?>
<style>
.cj-bento-wall {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-auto-rows: 195px;
    grid-auto-flow: dense;
    gap: 10px;
}
@media (min-width: 640px) {
    .cj-bento-wall {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        grid-auto-rows: 220px;
        gap: 14px;
    }
}
@media (min-width: 1024px) {
    .cj-bento-wall {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        grid-auto-rows: 235px;
        gap: 16px;
    }
}
.cj-bento-tile {
    position: relative;
    display: block;
    overflow: hidden;
    border-radius: 18px;
    background: #0d1017;
    border: 1px solid rgba(212, 175, 55, 0.22);
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.14);
    text-decoration: none;
    cursor: pointer;
    isolation: isolate;
    transition: transform 0.35s cubic-bezier(.22,1,.36,1), box-shadow 0.35s ease, border-color 0.3s ease;
}
.cj-bento-tile:hover {
    transform: translateY(-3px);
    border-color: rgba(240, 215, 140, 0.78);
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.28), 0 0 24px rgba(212, 175, 55, 0.26);
}
/* Hero slot (#1 Top Viewed Reel) */
.cj-bento-tile.is-hero {
    grid-column: span 2;
    grid-row: span 2;
    border-width: 1.5px;
    border-color: rgba(240, 215, 140, 0.65);
    box-shadow: 0 16px 42px rgba(0, 0, 0, 0.24), 0 0 32px rgba(212, 175, 55, 0.18);
}
/* Tall 9:16 portrait slots for cinematic bento rhythm */
.cj-bento-tile.is-tall {
    grid-row: span 2;
}
/* Wide cinematic accent slot on desktop */
@media (min-width: 1024px) {
    .cj-bento-tile.is-wide {
        grid-column: span 2;
    }
}
.cj-bento-video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    background: #0d1017;
    transition: transform 0.65s cubic-bezier(.22,1,.36,1), opacity 0.45s ease;
}
.cj-bento-tile:hover .cj-bento-video {
    transform: scale(1.045);
}
/* Subtle bottom vignette purely to keep view count pill legible */
.cj-bento-tile::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.22) 0%, rgba(0,0,0,0) 28%, rgba(0,0,0,0) 70%, rgba(0,0,0,0.48) 100%);
    pointer-events: none;
    z-index: 2;
}
.cj-bento-top-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 4;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 11px;
    border-radius: 999px;
    background: linear-gradient(135deg, #fff3c4 0%, #e5be54 48%, #b88b1e 100%);
    color: #1a1204;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35), 0 0 18px rgba(240, 215, 140, 0.45);
    pointer-events: none;
}
.cj-bento-views-pill {
    position: absolute;
    bottom: 10px;
    left: 10px;
    z-index: 4;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    background: rgba(12, 14, 18, 0.56);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.16);
    color: #f9fafb;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.01em;
    pointer-events: none;
}
.cj-bento-views-pill svg {
    width: 13px;
    height: 13px;
    opacity: 0.9;
}
.cj-bento-like-pill {
    position: absolute;
    bottom: 10px;
    right: 10px;
    z-index: 4;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 9px;
    border-radius: 999px;
    background: rgba(12, 14, 18, 0.56);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.16);
    color: #fda4af;
    font-size: 11px;
    font-weight: 600;
    pointer-events: none;
}
.cj-bento-pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #fbbf24;
    box-shadow: 0 0 8px #fbbf24;
    animation: cjReelPulse 1.8s infinite;
}
@keyframes cjReelPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.45; transform: scale(0.75); }
}
</style>

<div class="cj-bento-wall" data-cj-reels-wall="1">
    <?php foreach ($cjReelsList as $idx => $reel):
        $rid = (int)$reel['id'];
        $isTop = !empty($reel['is_top_viewed']);
        $viewsFmt = cj_format_reel_count((int)$reel['display_views']);
        $likesCount = (int)($reel['likes_count'] ?? 0);

        // Cinematic Bento rhythm:
        // Index 0 = Giant 2x2 Hero (always #1 Top Viewed on default ranking)
        // Indices 1, 4, 7... = Tall 9:16 portrait cards
        // Indices 5, 11... = Wide desktop accent card
        $tileClass = '';
        if ($idx === 0) {
            $tileClass = 'is-hero';
        } elseif ($idx % 3 === 1) {
            $tileClass = 'is-tall';
        } elseif ($idx > 0 && $idx % 6 === 5 && $cjReelsCount >= 6) {
            $tileClass = 'is-wide';
        }

        $previewUrl = (string)$reel['preview_url'];
        $posterUrl = (string)$reel['poster_thumb'];
    ?>
    <a href="/showcase?v=<?= $rid ?>"
       class="cj-bento-tile <?= $tileClass ?>"
       data-reel-tile="<?= $rid ?>"
       aria-label="Explore Jewellery Reel #<?= $rid ?>">
        <video class="cj-bento-video"
               data-src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>"
               <?php if ($posterUrl !== ''): ?>poster="<?= htmlspecialchars($posterUrl, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
               muted
               loop
               playsinline
               webkit-playsinline
               preload="none"
               disablepictureinpicture></video>

        <?php if ($isTop): ?>
            <span class="cj-bento-top-badge">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/></svg>
                Top Viewed
            </span>
        <?php endif; ?>

        <span class="cj-bento-views-pill" data-reel-views-badge="<?= $rid ?>">
            <span class="cj-bento-pulse-dot" aria-hidden="true"></span>
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <span data-reel-views-text="<?= $rid ?>"><?= htmlspecialchars($viewsFmt) ?></span>
        </span>

        <?php if ($likesCount > 0): ?>
            <span class="cj-bento-like-pill" aria-hidden="true">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="#ff3040" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                <?= htmlspecialchars(cj_format_reel_count($likesCount)) ?>
            </span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>
<script src="/js/reels-engine.js?v=1" defer></script>
