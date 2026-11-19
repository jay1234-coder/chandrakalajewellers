/**
 * Catalogue AI Search + Smart Category Chips + Voice Search + Live AJAX Infinite Scroll.
 */
(function () {
    'use strict';

    var grid = document.getElementById('catalogueGrid');
    var sentinel = document.getElementById('catalogueLazySentinel');
    var statusEl = document.getElementById('catalogueLazyStatus');
    var loadMoreBtn = document.getElementById('catalogueLoadMoreBtn');
    var loadMoreWrap = document.getElementById('catalogueLoadMoreWrap');
    var loadMoreLabel = document.getElementById('catalogueLoadMoreLabel');
    var emptyEl = document.getElementById('catalogueEmptyState');
    var resetBtn = document.getElementById('catalogueResetSearch');

    var form = document.getElementById('catalogueAiSearchForm');
    var input = document.getElementById('catalogueAiInput');
    var clearBtn = document.getElementById('catalogueAiClear');
    var voiceBtn = document.getElementById('catalogueAiVoice');
    var chipsWrap = document.getElementById('catalogueAiChips');
    var metaEl = document.getElementById('catalogueAiMeta');

    if (!grid) return;

    var state = {
        page: parseInt(grid.getAttribute('data-page') || '1', 10) || 1,
        totalPages: parseInt(grid.getAttribute('data-total-pages') || '1', 10) || 1,
        q: grid.getAttribute('data-q') || '',
        cat: grid.getAttribute('data-cat') || 'all',
        loading: false,
        prefetch: {},
        abortCtrl: null,
        debounceTimer: null
    };

    function cacheKey(page, q, cat) {
        return page + '|' + q + '|' + cat;
    }

    function setStatus(on, text) {
        if (!statusEl) return;
        if (text) statusEl.textContent = text;
        statusEl.hidden = !on;
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

    function buildUrl(page, q, cat) {
        var params = ['ajax=1', 'page=' + encodeURIComponent(String(page))];
        if (q) params.push('q=' + encodeURIComponent(q));
        if (cat && cat !== 'all') params.push('cat=' + encodeURIComponent(cat));
        return 'Catalogue?' + params.join('&');
    }

    function fetchPage(page, q, cat, signal) {
        var key = cacheKey(page, q, cat);
        if (state.prefetch[key]) {
            return Promise.resolve(state.prefetch[key]);
        }
        return fetch(buildUrl(page, q, cat), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: signal
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }).then(function (data) {
            if (data && data.ok) state.prefetch[key] = data;
            return data;
        });
    }

    function updateMeta(data) {
        if (!metaEl || !data) return;
        var count = parseInt(data.total_items || 0, 10);
        var parts = ['AI matched <strong>' + count + '</strong> design' + (count === 1 ? '' : 's')];
        if (state.q) parts.push('for “<strong>' + escapeHtml(state.q) + '</strong>”');
        if (state.cat && state.cat !== 'all') parts.push('in <strong>' + escapeHtml(state.cat) + '</strong>');
        if (data.intents && data.intents.length) {
            parts.push('· Detected: ' + escapeHtml(data.intents.slice(0, 4).join(', ')));
        }
        if (data.elapsed_ms) {
            parts.push('(' + data.elapsed_ms + 'ms)');
        }
        metaEl.innerHTML = parts.join(' ');
    }

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function syncLoadMore(totalItems) {
        if (loadMoreWrap) {
            loadMoreWrap.hidden = state.page >= state.totalPages || totalItems === 0;
        }
        if (loadMoreLabel && typeof totalItems === 'number') {
            loadMoreLabel.textContent = 'Load More Designs (' + totalItems + ' Total)';
        }
    }

    function runLiveSearch(newQ, newCat) {
        state.q = String(newQ || '').trim();
        state.cat = String(newCat || 'all').trim() || 'all';
        state.page = 1;
        state.prefetch = {};

        if (clearBtn) clearBtn.hidden = (state.q === '');

        if (state.abortCtrl && typeof state.abortCtrl.abort === 'function') {
            state.abortCtrl.abort();
        }
        state.abortCtrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;

        state.loading = true;
        setStatus(true, '✨ AI searching designs…');

        fetchPage(1, state.q, state.cat, state.abortCtrl ? state.abortCtrl.signal : undefined)
            .then(function (data) {
                if (!data || !data.ok) return;
                grid.innerHTML = data.html || '';
                state.page = data.page || 1;
                state.totalPages = data.total_pages || 1;
                grid.setAttribute('data-page', String(state.page));
                grid.setAttribute('data-total-pages', String(state.totalPages));
                grid.setAttribute('data-q', state.q);
                grid.setAttribute('data-cat', state.cat);

                if (emptyEl) emptyEl.hidden = (data.total_items > 0);
                syncLoadMore(data.total_items);
                updateMeta(data);

                hydrate(grid);
                if (window.CJCatalogueBindNew) window.CJCatalogueBindNew(grid);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
            })
            .finally(function () {
                state.loading = false;
                setStatus(false);
                idlePrefetch();
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
        setStatus(true, 'Loading more designs…');
        var next = state.page + 1;
        fetchPage(next, state.q, state.cat).then(function (data) {
            if (!data || !data.ok) return;
            appendPage(data);
            state.page = data.page || next;
            state.totalPages = data.total_pages || state.totalPages;
            grid.setAttribute('data-page', String(state.page));
            delete state.prefetch[cacheKey(next, state.q, state.cat)];
            syncLoadMore(data.total_items);
        }).catch(function () { /* keep current */ }).finally(function () {
            state.loading = false;
            setStatus(false);
            if (loadMoreBtn) loadMoreBtn.disabled = false;
            idlePrefetch();
        });
    }

    // Search input live debounced AJAX
    if (input) {
        input.addEventListener('input', function () {
            if (clearBtn) clearBtn.hidden = (input.value.trim() === '');
            clearTimeout(state.debounceTimer);
            state.debounceTimer = setTimeout(function () {
                runLiveSearch(input.value, state.cat);
            }, 180);
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearTimeout(state.debounceTimer);
            runLiveSearch(input ? input.value : '', state.cat);
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (input) {
                input.value = '';
                input.focus();
            }
            runLiveSearch('', state.cat);
        });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (input) input.value = '';
            if (chipsWrap) {
                chipsWrap.querySelectorAll('.ai-chip').forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.getAttribute('data-ai-cat') === 'all');
                });
            }
            runLiveSearch('', 'all');
        });
    }

    // Category chips
    if (chipsWrap) {
        chipsWrap.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.ai-chip') : null;
            if (!btn) return;
            var cat = btn.getAttribute('data-ai-cat') || 'all';
            chipsWrap.querySelectorAll('.ai-chip').forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            runLiveSearch(input ? input.value : '', cat);
        });
    }

    // Voice Search (English / Hindi / Gujarati)
    var SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (voiceBtn) {
        if (!SpeechRec) {
            voiceBtn.hidden = true;
        } else {
            voiceBtn.addEventListener('click', function () {
                try {
                    var rec = new SpeechRec();
                    rec.lang = 'en-IN';
                    rec.interimResults = false;
                    rec.maxAlternatives = 1;
                    voiceBtn.classList.add('is-listening');
                    rec.onresult = function (evt) {
                        var spoken = evt.results && evt.results[0] && evt.results[0][0]
                            ? evt.results[0][0].transcript
                            : '';
                        if (spoken && input) {
                            input.value = spoken;
                            runLiveSearch(spoken, state.cat);
                        }
                    };
                    rec.onerror = function () { voiceBtn.classList.remove('is-listening'); };
                    rec.onend = function () { voiceBtn.classList.remove('is-listening'); };
                    rec.start();
                } catch (err) {
                    voiceBtn.classList.remove('is-listening');
                }
            });
        }
    }

    // Animated rotating AI placeholder hints
    if (input) {
        var hints = [
            'Try "royal gold bridal necklace"...',
            'Try "traditional mangalsutra" or "tanmaniya"...',
            'Try "kundan pendant butti set"...',
            'Try "925 silver ring" or "gents kada"...',
            'Try "handcrafted bangles" or "earrings"...'
        ];
        var hintIdx = 0;
        setInterval(function () {
            if (document.activeElement === input || input.value !== '') return;
            hintIdx = (hintIdx + 1) % hints.length;
            input.setAttribute('placeholder', hints[hintIdx]);
        }, 3800);
    }

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function () {
            loadMoreBtn.disabled = true;
            loadMore();
        });
    }

    function idlePrefetch() {
        var ahead = state.page + 1;
        if (ahead > state.totalPages || state.prefetch[cacheKey(ahead, state.q, state.cat)]) return;
        var run = function () { fetchPage(ahead, state.q, state.cat).catch(function () {}); };
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
