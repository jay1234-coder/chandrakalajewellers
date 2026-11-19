/**
 * ChandraKala Jewellers — Background Reels Prefetch & Client-Side GPU Warmer
 * Zero-lag video buffering & opportunistic idle loading for mobile & desktop.
 */
(function () {
    'use strict';

    if (window.__CJ_REELS_PREFETCH_INITIALIZED__) return;
    window.__CJ_REELS_PREFETCH_INITIALIZED__ = true;

    // Do not run inside the dedicated Reels player or Admin panel
    var path = window.location.pathname || '';
    if (path.indexOf('/reels') !== -1 || path.indexOf('/admin') !== -1) {
        return;
    }

    var PREFETCH_API = '/api/reels-prefetch';
    var CACHE_NAME = 'cj-reels-v1';
    var STORAGE_KEY = 'cj_reels_prefetched';
    var TTL_MS = 6 * 60 * 1000; // 6 minutes freshness

    var prefetchedKeys = new Set();
    var isPrefetching = false;
    var scrollTimer = null;
    var hasRunInitial = false;
    var hasRunScrollPhase = false;

    // Detect network conditions
    function getNetworkQuality() {
        var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (!conn) return '4g';
        if (conn.saveData) return 'save-data';
        var type = conn.effectiveType || '';
        if (type === 'slow-2g' || type === '2g') return '2g';
        if (type === '3g') return '3g';
        return '4g';
    }

    // Safe Idle Callback wrapper (fallback to setTimeout)
    function runWhenIdle(callback, timeout) {
        if ('requestIdleCallback' in window) {
            return window.requestIdleCallback(callback, { timeout: timeout || 2500 });
        }
        return setTimeout(callback, 200);
    }

    // Read stored cache from sessionStorage
    function getStoredState() {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (Date.now() - (parsed.ts || 0) < TTL_MS) {
                return parsed;
            }
        } catch (e) {}
        return null;
    }

    function saveState(data) {
        try {
            data.ts = Date.now();
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch (e) {}
    }

    // Preload an image poster into browser memory/cache
    function preloadImage(url) {
        if (!url) return;
        var img = new Image();
        img.decoding = 'async';
        img.src = url;
    }

    // Buffer video stream into Cache API for instant 0ms playback
    function bufferVideoIntoCache(videoUrl, netQuality) {
        if (!videoUrl || prefetchedKeys.has(videoUrl)) return Promise.resolve();
        prefetchedKeys.add(videoUrl);

        // On save-data or 2G, avoid downloading full video files
        if (netQuality === 'save-data' || netQuality === '2g') {
            return Promise.resolve();
        }

        if (!('caches' in window)) {
            // Fallback: Use link rel=preload if Cache API not available
            try {
                var link = document.createElement('link');
                link.rel = 'preload';
                link.as = 'video';
                link.href = videoUrl;
                document.head.appendChild(link);
            } catch (e) {}
            return Promise.resolve();
        }

        return caches.open(CACHE_NAME).then(function (cache) {
            return cache.match(videoUrl).then(function (existing) {
                if (existing) return; // already in cache

                // Use range header for 3G to buffer initial 1.2MB, or full on 4G/WiFi
                var fetchOpts = { mode: 'cors', credentials: 'omit' };
                if (netQuality === '3g') {
                    fetchOpts.headers = { 'Range': 'bytes=0-1250000' };
                }

                return fetch(videoUrl, fetchOpts).then(function (res) {
                    if (res && (res.status === 200 || res.status === 206)) {
                        // Put in Cache API for instant blob consumption
                        return cache.put(videoUrl, res);
                    }
                }).catch(function () {});
            });
        }).catch(function () {});
    }

    // Warm GPU hardware decoder by mounting a hidden muted 1x1 video element
    // This primes the platform's video pipeline (DirectX/Metal/MediaCodec) for frame 0
    function warmGpuDecoder(videoUrl) {
        if (!videoUrl || document.getElementById('cj-gpu-warmer-' + encodeURIComponent(videoUrl))) return;
        try {
            var v = document.createElement('video');
            v.id = 'cj-gpu-warmer-' + encodeURIComponent(videoUrl);
            v.muted = true;
            v.setAttribute('muted', '');
            v.playsInline = true;
            v.setAttribute('playsinline', '');
            v.setAttribute('webkit-playsinline', '');
            v.preload = 'auto';
            v.style.position = 'fixed';
            v.style.width = '1px';
            v.style.height = '1px';
            v.style.opacity = '0.001';
            v.style.pointerEvents = 'none';
            v.style.bottom = '0';
            v.style.right = '0';
            v.style.transform = 'translate3d(0,0,0)';
            v.src = videoUrl;
            document.body.appendChild(v);

            // Once the first frame is decoded into GPU memory, pause and clean up
            var cleanup = function () {
                v.removeEventListener('loadeddata', cleanup);
                v.removeEventListener('canplay', cleanup);
                v.removeEventListener('error', cleanup);
                try { v.pause(); } catch (e) {}
            };
            v.addEventListener('loadeddata', cleanup, { once: true });
            v.addEventListener('canplay', cleanup, { once: true });
            v.addEventListener('error', cleanup, { once: true });
            try { v.load(); } catch (e) {}
        } catch (e) {}
    }

    // Phase 1: Immediate Site-Open Warmup (Load top 2 reels)
    function warmupTop2Reels() {
        if (hasRunInitial || isPrefetching) return;
        hasRunInitial = true;
        isPrefetching = true;

        var netQuality = getNetworkQuality();
        var stored = getStoredState();

        var proceedWithReels = function (reels) {
            isPrefetching = false;
            if (!Array.isArray(reels) || !reels.length) return;

            // Pick first 2 reels
            var top2 = reels.slice(0, 2);
            top2.forEach(function (r, idx) {
                // Preload poster image immediately
                preloadImage(r.poster_url);

                // Choose best stream: preview for 3G, full HD for 4G
                var streamUrl = (netQuality === '3g' && r.preview_url) ? r.preview_url : (r.video_url || r.preview_url);

                // Buffer Reel 1 right away
                if (idx === 0) {
                    bufferVideoIntoCache(streamUrl, netQuality).then(function () {
                        // Warm GPU decoder for Reel 1
                        warmGpuDecoder(streamUrl);
                    });
                } else if (idx === 1) {
                    // Buffer Reel 2 with slight pause so network remains silky
                    setTimeout(function () {
                        runWhenIdle(function () {
                            bufferVideoIntoCache(streamUrl, netQuality);
                        }, 2000);
                    }, 500);
                }
            });
        };

        if (stored && Array.isArray(stored.reels) && stored.reels.length >= 2) {
            proceedWithReels(stored.reels);
            return;
        }

        fetch(PREFETCH_API + '?limit=2', { credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (data && data.ok && Array.isArray(data.reels)) {
                    saveState({ reels: data.reels });
                    proceedWithReels(data.reels);
                } else {
                    isPrefetching = false;
                }
            })
            .catch(function () {
                isPrefetching = false;
            });
    }

    // Phase 2: Opportunistic Preloader on User Scroll (Reels 3 to 6)
    function onScrollIdlePreload() {
        if (hasRunScrollPhase) return;
        var netQuality = getNetworkQuality();
        if (netQuality === 'save-data') return; // respect data-saver

        hasRunScrollPhase = true;

        runWhenIdle(function () {
            fetch(PREFETCH_API + '?limit=6', { credentials: 'same-origin' })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (data) {
                    if (!data || !data.ok || !Array.isArray(data.reels)) return;
                    saveState({ reels: data.reels });

                    // Progressively pre-warm posters & next videos
                    var subsequent = data.reels.slice(2, 6);
                    subsequent.forEach(function (r, i) {
                        setTimeout(function () {
                            runWhenIdle(function () {
                                preloadImage(r.poster_url);
                                // On fast WiFi/4G, buffer Reel #3 video
                                if (i === 0 && netQuality === '4g') {
                                    bufferVideoIntoCache(r.video_url || r.preview_url, netQuality);
                                }
                            }, 3000);
                        }, i * 350);
                    });
                })
                .catch(function () {});
        }, 3000);
    }

    // Debounced scroll listener
    function handleScroll() {
        if (hasRunScrollPhase) {
            window.removeEventListener('scroll', handleScroll, { passive: true });
            return;
        }
        if (scrollTimer) clearTimeout(scrollTimer);
        scrollTimer = setTimeout(function () {
            onScrollIdlePreload();
        }, 500);
    }

    // Kick off Phase 1 after page paints
    if (document.readyState === 'complete') {
        runWhenIdle(warmupTop2Reels, 1200);
    } else {
        window.addEventListener('load', function () {
            runWhenIdle(warmupTop2Reels, 1200);
        }, { once: true });
    }

    // Attach passive scroll listener for Phase 2
    window.addEventListener('scroll', handleScroll, { passive: true });

    // Also trigger immediately if user hovers or touches the "Reels" navigation tab!
    function onReelsTabIntent() {
        warmupTop2Reels();
        onScrollIdlePreload();
    }
    document.addEventListener('DOMContentLoaded', function () {
        var reelsLinks = document.querySelectorAll('a[href*="reels"], .app-tab[data-tab="reels"]');
        reelsLinks.forEach(function (el) {
            el.addEventListener('mouseenter', onReelsTabIntent, { passive: true, once: true });
            el.addEventListener('touchstart', onReelsTabIntent, { passive: true, once: true });
        });
    });

})();
