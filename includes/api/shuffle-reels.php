<?php
/**
 * ChandraKala Jewellers — Live Shuffle Reels API
 * Returns 7 random active reels for instant client-side reshuffling on index1.php
 */
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/reels.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    $allReels = cj_get_ranked_reels($conn, 40, true, 'top');
    if (!empty($allReels)) {
        shuffle($allReels);
        $cjReelsList = array_slice($allReels, 0, min(7, count($allReels)));
    } else {
        $cjReelsList = [];
    }

    $cjReelsCount = count($cjReelsList);
    $tilesHtml = '';

    foreach ($cjReelsList as $idx => $reel) {
        $rid = (int)$reel['id'];
        $isTop = !empty($reel['is_top_viewed']);
        $viewsFmt = cj_format_reel_count((int)$reel['display_views']);
        $likesCount = (int)($reel['likes_count'] ?? 0);

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

        ob_start();
        ?>
        <a href="/showcase?v=<?= $rid ?>"
           class="cj-bento-tile <?= $tileClass ?>"
           data-reel-tile="<?= $rid ?>"
           aria-label="Explore Jewellery Reel #<?= $rid ?>"
           style="animation:fadeIn 0.4s ease;">
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
        <?php
        $tilesHtml .= ob_get_clean();
    }

    echo json_encode([
        'ok' => true,
        'count' => $cjReelsCount,
        'html' => $tilesHtml,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
exit;
