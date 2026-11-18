/**
 * ChandraKala Jewellers — Smart Viewport Autoplay & IAB Impressions Engine
 * - Lazy-streams videos only as the user approaches the video section.
 * - Simultaneously autoplays all visible reels muted in loop, pauses when scrolled away.
 * - Tracks genuine Impressions (>=50% in view for >=1s, once per session) & Clicks via sendBeacon.
 */
(function () {
    'use strict';
    if (window.__CJ_REELS_ENGINE__) return;
    window.__CJ_REELS_ENGINE__ = true;

    var TRACK_URL = '/api/reel-track.php';
    var trackedImpressions = {};
    var impressionTimers = {};

    function sendTrack(reelId, action, onDone) {
        var payload = JSON.stringify({ reel_id: Number(reelId), action: action });
        if (!onDone && navigator.sendBeacon) {
            try {
                var blob = new Blob([payload], { type: 'application/json' });
                navigator.sendBeacon(TRACK_URL, blob);
                return;
            } catch (e) {}
        }
        fetch(TRACK_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: payload,
            credentials: 'same-origin',
            keepalive: true
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) { if (onDone && data) onDone(data); })
            .catch(function () {});
    }

    function hydrateVideo(video) {
        if (!video || video.dataset.hydrated === '1') return;
        var src = video.getAttribute('data-src');
        if (!src) return;
        video.dataset.hydrated = '1';
        video.src = src;
        try { video.load(); } catch (e) {}
    }

    function safePlay(video) {
        if (!video) return;
        video.muted = true;
        var p = video.play();
        if (p && typeof p.catch === 'function') {
            p.catch(function () {});
        }
    }

    function initTiles() {
        var tiles = Array.prototype.slice.call(document.querySelectorAll('[data-reel-tile]'));
        if (!tiles.length) return;

        // 1. Pre-hydrate observer (loads video stream 280px before entering viewport)
        var loadObserver = ('IntersectionObserver' in window)
            ? new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var vid = entry.target.querySelector('video');
                        hydrateVideo(vid);
                        loadObserver.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '280px 0px' })
            : null;

        // 2. Autoplay & Pause observer (plays ALL visible videos smoothly when in view)
        var playObserver = ('IntersectionObserver' in window)
            ? new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    var vid = entry.target.querySelector('video');
                    if (!vid) return;
                    if (entry.isIntersecting) {
                        hydrateVideo(vid);
                        safePlay(vid);
                    } else {
                        try { vid.pause(); } catch (e) {}
                    }
                });
            }, { threshold: 0.2 })
            : null;

        // 3. IAB Viewability Impression observer (>=50% visible for >=1000ms)
        var impressionObserver = ('IntersectionObserver' in window)
            ? new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    var rid = entry.target.getAttribute('data-reel-tile');
                    if (!rid) return;
                    if (entry.isIntersecting && entry.intersectionRatio >= 0.45) {
                        if (trackedImpressions[rid] || impressionTimers[rid]) return;
                        impressionTimers[rid] = setTimeout(function () {
                            delete impressionTimers[rid];
                            if (trackedImpressions[rid]) return;
                            trackedImpressions[rid] = true;
                            var sessKey = 'cj_imp_' + rid;
                            try {
                                if (sessionStorage.getItem(sessKey) === '1') return;
                                sessionStorage.setItem(sessKey, '1');
                            } catch (e) {}
                            sendTrack(rid, 'impression', function (data) {
                                if (data && data.display_views_fmt) {
                                    var el = document.querySelector('[data-reel-views-text="' + rid + '"]');
                                    if (el) el.textContent = data.display_views_fmt;
                                }
                            });
                        }, 1000);
                    } else if (impressionTimers[rid]) {
                        clearTimeout(impressionTimers[rid]);
                        delete impressionTimers[rid];
                    }
                });
            }, { threshold: [0, 0.45, 0.75] })
            : null;

        tiles.forEach(function (tile) {
            var rid = tile.getAttribute('data-reel-tile');
            if (loadObserver) loadObserver.observe(tile);
            else hydrateVideo(tile.querySelector('video'));

            if (playObserver) playObserver.observe(tile);
            else safePlay(tile.querySelector('video'));

            if (impressionObserver) impressionObserver.observe(tile);

            // Track click cleanly on navigation
            tile.addEventListener('click', function () {
                if (rid) sendTrack(rid, 'click');
            });
        });

        // Pause all reels when browser tab is hidden to save battery
        document.addEventListener('visibilitychange', function () {
            tiles.forEach(function (tile) {
                var vid = tile.querySelector('video');
                if (!vid) return;
                if (document.hidden) {
                    try { vid.pause(); } catch (e) {}
                } else {
                    var r = tile.getBoundingClientRect();
                    if (r.bottom > 0 && r.top < window.innerHeight) {
                        safePlay(vid);
                    }
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTiles);
    } else {
        initTiles();
    }

    // Export helper for Showcase page
    window.CJReelsTrack = sendTrack;
})();
