/**
 * Viewport lazy-load + idle background work.
 * Images with.cj-lazy-img[data-src] load only when near the screen.
 */
(function (global) {
    'use strict';

    var io = null;

    function loadImg(img) {
        var src = img.getAttribute('data-src');
        if (!src) return;
        img.removeAttribute('data-src');
        img.onload = function () { img.classList.add('is-loaded'); };
        img.onerror = function () { img.classList.add('is-loaded'); };
        img.src = src;
    }

    function hydrate(root) {
        var scope = root || document;
        var imgs = scope.querySelectorAll ? scope.querySelectorAll('img.cj-lazy-img[data-src]') : [];
        if (!imgs.length) return;

        if (!('IntersectionObserver' in window)) {
            for (var i = 0; i < imgs.length; i++) loadImg(imgs[i]);
            return;
        }
        if (!io) {
            io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    var img = entry.target;
                    io.unobserve(img);
                    loadImg(img);
                });
            }, { rootMargin: '180px 0px', threshold: 0.01 });
        }
        for (var j = 0; j < imgs.length; j++) io.observe(imgs[j]);
    }

    function idle(fn, timeout) {
        timeout = timeout || 1800;
        if (typeof fn !== 'function') return;
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(function () { fn(); }, { timeout: timeout });
        } else {
            setTimeout(fn, Math.min(timeout, 900));
        }
    }

    global.cjHydrateLazy = hydrate;
    global.cjIdle = idle;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { hydrate(document); });
    } else {
        hydrate(document);
    }
})(window);
