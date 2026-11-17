/**
 * Catalogue: first screen only, then idle-prefetch remaining pages.
 */
(function () {
    'use strict';

    var grid = document.getElementById('catalogueGrid');
    var sentinel = document.getElementById('catalogueLazySentinel');
    var statusEl = document.getElementById('catalogueLazyStatus');
    var loadMoreBtn = document.getElementById('catalogueLoadMoreBtn');
    var loadMoreWrap = document.getElementById('catalogueLoadMoreWrap');
    if (!grid) return;

    var state = {
        page: parseInt(grid.getAttribute('data-page') || '1', 10) || 1,
        totalPages: parseInt(grid.getAttribute('data-total-pages') || '1', 10) || 1,
        loading: false,
        prefetch: {}
    };

    function setStatus(on) {
        if (statusEl) statusEl.hidden = !on;
    }

    function hydrate(root) {
        var scope = root || grid;
        var imgs = scope.querySelectorAll ? scope.querySelectorAll('img.cj-lazy-img[data-src]') : [];
        for (var i = 0; i < imgs.length; i++) {
            var img = imgs[i];
            var src = img.getAttribute('data-src');
            if (src) {
                img.removeAttribute('data-src');
                (function (el, s) {
                    el.onload = function () { el.classList.add('is-loaded'); };
                    el.onerror = function () { el.classList.add('is-loaded'); };
                    el.src = s;
                })(img, src);
            }
        }
        if (window.CJSelectCompareRefresh) window.CJSelectCompareRefresh();
    }

    function fetchPage(page) {
        if (state.prefetch[page]) {
            return Promise.resolve(state.prefetch[page]);
        }
        var url = 'Catalogue?ajax=1&page=' + encodeURIComponent(String(page));
        return fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }).then(function (data) {
            if (data && data.ok) state.prefetch[page] = data;
            return data;
        });
    }

    function appendPage(data) {
        if (!data || !data.html) return;
        var wrap = document.createElement('div');
        wrap.innerHTML = data.html;
        while (wrap.firstChild) grid.appendChild(wrap.firstChild);
        hydrate(grid);
        if (window.CJCatalogueBindNew) window.CJCatalogueBindNew(grid);
    }

    function loadMore() {
        if (state.loading || state.page >= state.totalPages) return;
        state.loading = true;
        setStatus(true);
        var next = state.page + 1;
        fetchPage(next).then(function (data) {
            if (!data || !data.ok) return;
            appendPage(data);
            state.page = data.page || next;
            state.totalPages = data.total_pages || state.totalPages;
            grid.setAttribute('data-page', String(state.page));
            delete state.prefetch[next];
            if (state.page >= state.totalPages && loadMoreWrap) {
                loadMoreWrap.hidden = true;
            }
        }).catch(function () { /* keep current */ }).finally(function () {
            state.loading = false;
            setStatus(false);
            if (loadMoreBtn) loadMoreBtn.disabled = false;
            idlePrefetch();
        });
    }

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function () {
            loadMoreBtn.disabled = true;
            loadMore();
        });
    }

    function idlePrefetch() {
        var ahead = state.page + 1;
        if (ahead > state.totalPages || state.prefetch[ahead]) return;
        var run = function () { fetchPage(ahead).catch(function () {}); };
        if (window.cjIdle) window.cjIdle(run, 2200);
        else setTimeout(run, 900);
    }

    if (sentinel && 'IntersectionObserver' in window) {
        var moreIo = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) loadMore();
            });
        }, { rootMargin: '280px 0px' });
        moreIo.observe(sentinel);
    }

    hydrate(grid);
    idlePrefetch();
})();
