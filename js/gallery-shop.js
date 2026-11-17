/**
 * Gallery super-search live fetch + infinite scroll lazy load.
 */
(function () {
    'use strict';

    var grid = document.getElementById('shopGrid');
    var form = document.getElementById('shopSuperSearch');
    var input = document.getElementById('shopSearchInput');
    var countEl = document.getElementById('shopResultCount');
    var statusEl = document.getElementById('shopLazyStatus');
    var sentinel = document.getElementById('shopLazySentinel');
    var pagination = document.getElementById('shopPagination');
    if (!grid) return;

    var state = {
        page: parseInt(grid.getAttribute('data-page') || '1', 10) || 1,
        totalPages: parseInt(grid.getAttribute('data-total-pages') || '1', 10) || 1,
        metal: grid.getAttribute('data-metal') || 'all',
        sort: grid.getAttribute('data-sort') || 'newest',
        min: grid.getAttribute('data-min') || '',
        max: grid.getAttribute('data-max') || '',
        q: grid.getAttribute('data-q') || '',
        loading: false,
        liveTimer: null
    };

    function buildUrl(page, qOverride) {
        var params = new URLSearchParams();
        params.set('metal_type', state.metal);
        if (state.sort && state.sort !== 'newest') params.set('sort', state.sort);
        if (state.min !== '') params.set('min_price', state.min);
        if (state.max !== '') params.set('max_price', state.max);
        var q = typeof qOverride === 'string' ? qOverride : state.q;
        if (q) params.set('q', q);
        if (page > 1) params.set('page', String(page));
        params.set('ajax', '1');
        return 'gallery?' + params.toString();
    }

    function hydrateLazy(root) {
        if (window.cjHydrateLazy) {
            window.cjHydrateLazy(root || grid);
            return;
        }
        var scope = root || document;
        var imgs = scope.querySelectorAll('img.cj-lazy-img[data-src]');
        if (!imgs.length) return;
        imgs.forEach(function (img) {
            var src = img.getAttribute('data-src');
            if (!src) return;
            img.removeAttribute('data-src');
            img.src = src;
            img.classList.add('is-loaded');
        });
    }

    function bindSelectCompare(root) {
        if (window.CJGallerySelect && typeof window.CJGallerySelect.refreshItems === 'function') {
            window.CJGallerySelect.refreshItems();
        }
        if (typeof window.CJGalleryBindNav === 'function') {
            window.CJGalleryBindNav(root || document);
        }
    }

    function updateCount(total, q) {
        if (!countEl) return;
        var bits = [String(total) + ' item' + (total === 1 ? '' : 's')];
        if (q) bits.push('· “' + q + '”');
        if (state.metal && state.metal !== 'all') {
            var map = { gold: 'Gold', silver: 'Silver', silver_925: '925 Silver' };
            bits.push('· ' + (map[state.metal] || state.metal));
        }
        countEl.textContent = bits.join(' ');
    }

    function setLoading(on) {
        state.loading = on;
        if (statusEl) statusEl.hidden = !on;
    }

    function syncHistory(q, page) {
        try {
            var params = new URLSearchParams();
            params.set('metal_type', state.metal);
            if (state.sort && state.sort !== 'newest') params.set('sort', state.sort);
            if (state.min !== '') params.set('min_price', state.min);
            if (state.max !== '') params.set('max_price', state.max);
            if (q) params.set('q', q);
            if (page > 1) params.set('page', String(page));
            var url = 'gallery' + (params.toString() ? '?' + params.toString() : '');
            window.history.replaceState({ cjShop: true }, '', url);
        } catch (e) { /* ignore */ }
    }

    var prefetch = {};

    function prefetchPage(page) {
        if (page < 2 || page > state.totalPages || prefetch[page] || state.loading) return;
        prefetch[page] = true;
        fetch(buildUrl(page), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (res) { return res.ok ? res.json() : null; })
          .then(function (data) { if (data && data.ok) prefetch[page] = data; else delete prefetch[page]; })
          .catch(function () { delete prefetch[page]; });
    }

    function fetchPage(page, replace) {
        if (state.loading) return Promise.resolve();
        var cached = prefetch[page];
        if (cached && cached.ok && cached.html !== undefined) {
            prefetch[page] = true;
            return Promise.resolve(applyPage(cached, page, replace));
        }
        setLoading(true);
        return fetch(buildUrl(page), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                return applyPage(data, page, replace);
            })
            .catch(function () {
                /* keep current grid */
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function applyPage(data, page, replace) {
        if (!data || !data.ok) throw new Error('Bad response');
        state.page = data.page || page;
        state.totalPages = data.total_pages || 1;
        grid.setAttribute('data-page', String(state.page));
        grid.setAttribute('data-total-pages', String(state.totalPages));
        grid.setAttribute('data-q', data.q || state.q);

        if (replace) {
            grid.innerHTML = data.html || '';
            if (!data.html) {
                grid.innerHTML = '<div class="shop-empty" id="shopEmpty"><p>No jewellery matched your search. Try another word, metal, or price.</p><a href="gallery" class="btn btn-secondary">Reset filters</a></div>';
            }
            prefetch = {};
        } else if (data.html) {
            var wrap = document.createElement('div');
            wrap.innerHTML = data.html;
            while (wrap.firstChild) {
                grid.appendChild(wrap.firstChild);
            }
        }

        hydrateLazy(grid);
        bindSelectCompare(grid);
        updateCount(data.total_items || 0, data.q || state.q);
        if (pagination) pagination.hidden = true;
        var idle = window.cjIdle || function (fn) { setTimeout(fn, 800); };
        idle(function () { prefetchPage(state.page + 1); }, 2000);
        return data;
    }

    function loadMore() {
        if (state.loading || state.page >= state.totalPages) return;
        fetchPage(state.page + 1, false);
    }

    // Infinite scroll
    if (sentinel && 'IntersectionObserver' in window) {
        if (pagination) {
            pagination.hidden = true;
            pagination.classList.add('shop-pagination-fallback');
        }
        var moreIo = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) loadMore();
            });
        }, { rootMargin: '400px 0px' });
        moreIo.observe(sentinel);
    }

    // Live super-search (debounced)
    if (input && form) {
        input.addEventListener('input', function () {
            var val = (input.value || '').trim();
            clearTimeout(state.liveTimer);
            state.liveTimer = setTimeout(function () {
                state.q = val;
                state.page = 1;
                syncHistory(val, 1);
                fetchPage(1, true);
            }, 320);
        });

        form.addEventListener('submit', function (e) {
            // Allow normal navigation if JS fetch fails mid-flight; prefer live replace
            e.preventDefault();
            clearTimeout(state.liveTimer);
            state.q = (input.value || '').trim();
            state.page = 1;
            syncHistory(state.q, 1);
            fetchPage(1, true).then(function () {
                if (window.matchMedia('(max-width: 960px)').matches) {
                    input.blur();
                }
            });
        });
    }

    hydrateLazy(grid);
    if (window.cjIdle) {
        window.cjIdle(function () { prefetchPage(state.page + 1); }, 1800);
    }
})();
