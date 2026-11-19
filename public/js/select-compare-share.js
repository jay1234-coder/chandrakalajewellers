/**
 * Multi-select, compare, and WhatsApp/image share for Catalogue & Gallery.
 * WhatsApp cannot attach files via wa.me — we always include image URLs,
 * and use Web Share API to send actual photos when the device supports it.
 */
(function (window) {
  'use strict';

  function absUrl(src) {
    if (!src) return '';
    if (/^https?:\/\//i.test(src)) return src;
    try {
      return new URL(src, window.location.href).href;
    } catch (e) {
      return src;
    }
  }

  function waUrl(phone, text) {
    return 'https://wa.me/' + phone + '?text=' + encodeURIComponent(text);
  }

  function filenameFromItem(item, index) {
    var base = (item.name || 'design-' + (index + 1))
      .replace(/[^\w\-]+/g, '_')
      .replace(/_+/g, '_')
      .slice(0, 40);
    var ext = 'jpg';
    var m = (item.image || '').match(/\.(jpe?g|png|webp|gif)(\?|$)/i);
    if (m) ext = m[1].toLowerCase().replace('jpeg', 'jpg');
    return base + '.' + ext;
  }

  async function fetchAsFile(item, index) {
    var url = absUrl(item.image);
    var res = await fetch(url, { mode: 'cors', credentials: 'same-origin' });
    if (!res.ok) throw new Error('fetch failed');
    var blob = await res.blob();
    var type = blob.type || 'image/jpeg';
    return new File([blob], filenameFromItem(item, index), { type: type });
  }

  function createSelectCompare(options) {
    var cfg = Object.assign({
      phone: (window.CJ_SHOP && window.CJ_SHOP.wa) || '919427080359',
      pageUrl: window.location.href,
      pageLabel: 'page',
      itemSelector: '[data-sc-item]',
      minCompare: 2,
      storageKey: '',
      buildLine: null,
      buildMessage: null
    }, options || {});

    var mode = 'browse'; // browse | select | compare
    var selected = new Map();
    var bar = null;
    var compareModal = null;

    function getItems() {
      return Array.from(document.querySelectorAll(cfg.itemSelector));
    }

    function readItem(el) {
      return {
        id: el.getAttribute('data-id') || '',
        name: el.getAttribute('data-name') || 'Item',
        desc: el.getAttribute('data-desc') || '',
        image: el.getAttribute('data-image') || el.getAttribute('data-src') || '',
        metal: el.getAttribute('data-metal') || '',
        weight: el.getAttribute('data-weight') || '',
        price: el.getAttribute('data-price') || '',
        url: el.getAttribute('data-url') || '',
        sold: el.getAttribute('data-sold') === '1'
      };
    }

    function selectedList() {
      return Array.from(selected.values());
    }

    function defaultLine(item, index) {
      var lines = [(index + 1) + '. *' + item.name + '*'];
      if (item.metal || item.weight) {
        lines.push('   ' + [item.metal, item.weight ? item.weight + ' g' : ''].filter(Boolean).join(' · '));
      }
      if (item.price) lines.push('   Price: ₹' + item.price);
      if (item.desc) lines.push('   ' + item.desc);
      var img = absUrl(item.image);
      if (img) lines.push('   Photo: ' + img);
      if (item.url) lines.push('   Link: ' + absUrl(item.url));
      return lines.join('\n');
    }

    function buildMessage(items, intent) {
      if (typeof cfg.buildMessage === 'function') {
        return cfg.buildMessage(items, intent, absUrl);
      }
      var title =
        intent === 'compare'
          ? 'Please help me *compare* these jewellery options:'
          : items.length === 1
            ? 'I want to enquire / order this design:'
            : 'I want to enquire about these *' + items.length + '* designs:';
      var parts = [
        'Namaste ChandraKala Jewellers,',
        '',
        title,
        ''
      ];
      items.forEach(function (item, i) {
        parts.push(typeof cfg.buildLine === 'function' ? cfg.buildLine(item, i, absUrl) : defaultLine(item, i));
        parts.push('');
      });
      parts.push(cfg.pageLabel + ': ' + cfg.pageUrl);
      parts.push('');
      parts.push('Please confirm metal (Gold / Silver / 925 Silver), weight, making charges and next steps.');
      parts.push('(Photo links are included above — open them to view each design.)');
      return parts.join('\n');
    }

    function persist() {
      if (!cfg.storageKey) return;
      try {
        sessionStorage.setItem(cfg.storageKey, JSON.stringify(selectedList()));
      } catch (e) {}
    }

    function syncChecks() {
      getItems().forEach(function (el) {
        var id = el.getAttribute('data-id');
        var on = selected.has(id);
        el.classList.toggle('is-sc-selected', on);
        var box = el.querySelector('.sc-check');
        if (box) box.checked = on;
        var badge = el.querySelector('.sc-pick-badge');
        if (badge) {
          if (on) {
            var idx = selectedList().findIndex(function (x) { return x.id === id; });
            badge.textContent = String(idx + 1);
            badge.hidden = false;
          } else {
            badge.hidden = true;
          }
        }
      });
      document.body.classList.toggle('sc-mode-select', mode === 'select');
      document.body.classList.toggle('sc-mode-compare', mode === 'compare');
      document.body.classList.toggle('sc-has-selection', selected.size > 0);
      updateBar();
      persist();
    }

    function toggleItem(el) {
      var item = readItem(el);
      if (!item.id) return;
      if (selected.has(item.id)) selected.delete(item.id);
      else selected.set(item.id, item);
      syncChecks();
    }

    function ensureSelected(el) {
      var item = readItem(el);
      if (!item.id) return;
      selected.set(item.id, item);
      setMode(mode === 'browse' ? 'select' : mode);
      syncChecks();
    }

    function setMode(next) {
      mode = next;
      if (mode === 'browse') {
        // keep selection so user can re-enter
      }
      syncChecks();
      var selectBtn = document.getElementById('scEnterSelect');
      var compareBtn = document.getElementById('scEnterCompare');
      if (selectBtn) selectBtn.classList.toggle('is-active', mode === 'select');
      if (compareBtn) compareBtn.classList.toggle('is-active', mode === 'compare');
    }

    function clearSelection() {
      selected.clear();
      syncChecks();
    }

    function openWhatsApp(intent) {
      var items = selectedList();
      if (!items.length) return;
      window.open(waUrl(cfg.phone, buildMessage(items, intent || 'order')), '_blank', 'noopener');
    }

    async function sharePhotos(itemsOverride) {
      var items = itemsOverride || selectedList();
      if (!items.length) return;

      var statusEl = bar && bar.querySelector('[data-sc-status]');
      function setStatus(t) {
        if (statusEl) statusEl.textContent = t || '';
      }

      try {
        setStatus('Preparing photos…');
        var files = [];
        for (var i = 0; i < items.length; i++) {
          try {
            files.push(await fetchAsFile(items[i], i));
          } catch (err) {
            /* skip failed image */
          }
        }
        var text = buildMessage(items, mode === 'compare' ? 'compare' : 'order');
        if (files.length && navigator.canShare && navigator.canShare({ files: files })) {
          await navigator.share({
            files: files,
            title: 'ChandraKala Jewellers',
            text: text
          });
          setStatus('Shared ' + files.length + ' photo' + (files.length === 1 ? '' : 's'));
          return;
        }
        setStatus('Photo attach not supported here — opening WhatsApp with image links');
        window.open(waUrl(cfg.phone, text), '_blank', 'noopener');
      } catch (err) {
        if (err && err.name === 'AbortError') {
          setStatus('');
          return;
        }
        setStatus('Could not share photos — opening WhatsApp with links');
        window.open(waUrl(cfg.phone, buildMessage(items, mode === 'compare' ? 'compare' : 'order')), '_blank', 'noopener');
      }
    }

    function ensureBar() {
      if (bar) return bar;
      bar = document.createElement('div');
      bar.className = 'sc-action-bar';
      bar.hidden = true;
      bar.innerHTML =
        '<div class="sc-action-inner">' +
          '<div class="sc-action-info">' +
            '<strong data-sc-count>0 selected</strong>' +
            '<span data-sc-hint></span>' +
            '<span class="sc-status" data-sc-status></span>' +
          '</div>' +
          '<div class="sc-action-btns">' +
            '<button type="button" class="btn btn-secondary" data-sc-clear>Clear</button>' +
            '<button type="button" class="btn btn-secondary" data-sc-share-photos>Share photos</button>' +
            '<button type="button" class="btn" data-sc-compare-open>Compare</button>' +
            '<button type="button" class="btn btn-whatsapp" data-sc-wa>WhatsApp</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(bar);
      bar.querySelector('[data-sc-clear]').addEventListener('click', clearSelection);
      bar.querySelector('[data-sc-share-photos]').addEventListener('click', function () { sharePhotos(); });
      bar.querySelector('[data-sc-compare-open]').addEventListener('click', openCompare);
      bar.querySelector('[data-sc-wa]').addEventListener('click', function () {
        openWhatsApp(mode === 'compare' ? 'compare' : 'order');
      });
      return bar;
    }

    function updateBar() {
      ensureBar();
      var n = selected.size;
      bar.hidden = !(mode === 'select' || mode === 'compare' || n > 0);
      if (bar.hidden) {
        document.body.classList.remove('sc-bar-visible');
        return;
      }
      document.body.classList.add('sc-bar-visible');
      bar.querySelector('[data-sc-count]').textContent = n + ' selected';
      var hint = bar.querySelector('[data-sc-hint]');
      var compareBtn = bar.querySelector('[data-sc-compare-open]');
      if (mode === 'compare') {
        hint.textContent = n < cfg.minCompare
          ? 'Select at least ' + cfg.minCompare + ' to compare (you can add more)'
          : 'Ready to compare — you can still add more';
        compareBtn.disabled = n < cfg.minCompare;
      } else {
        hint.textContent = n ? 'Send enquiry with photo links, or share photos' : 'Tap items to select';
        compareBtn.disabled = n < cfg.minCompare;
      }
    }

    function ensureCompareModal() {
      if (compareModal) return compareModal;
      compareModal = document.createElement('div');
      compareModal.className = 'sc-compare-modal';
      compareModal.hidden = true;
      compareModal.innerHTML =
        '<div class="sc-compare-backdrop" data-sc-compare-close></div>' +
        '<div class="sc-compare-dialog" role="dialog" aria-modal="true" aria-labelledby="scCompareTitle">' +
          '<div class="sc-compare-head">' +
            '<h2 id="scCompareTitle">Compare designs</h2>' +
            '<button type="button" class="sc-compare-x" data-sc-compare-close aria-label="Close">&times;</button>' +
          '</div>' +
          '<div class="sc-compare-grid" data-sc-compare-grid></div>' +
          '<div class="sc-compare-foot">' +
            '<button type="button" class="btn btn-secondary" data-sc-compare-close>Add more</button>' +
            '<button type="button" class="btn btn-secondary" data-sc-compare-share>Share photos</button>' +
            '<button type="button" class="btn btn-whatsapp" data-sc-compare-wa>Send comparison on WhatsApp</button>' +
          '</div>' +
        '</div>';
      document.body.appendChild(compareModal);
      compareModal.querySelectorAll('[data-sc-compare-close]').forEach(function (el) {
        el.addEventListener('click', closeCompare);
      });
      compareModal.querySelector('[data-sc-compare-wa]').addEventListener('click', function () {
        openWhatsApp('compare');
      });
      compareModal.querySelector('[data-sc-compare-share]').addEventListener('click', function () {
        sharePhotos();
      });
      return compareModal;
    }

    function openCompare() {
      var items = selectedList();
      if (items.length < cfg.minCompare) {
        setMode('compare');
        return;
      }
      ensureCompareModal();
      var grid = compareModal.querySelector('[data-sc-compare-grid]');
      grid.innerHTML = items.map(function (item, i) {
        return (
          '<article class="sc-compare-card">' +
            '<div class="sc-compare-img"><img src="' + absUrl(item.image).replace(/"/g, '&quot;') + '" alt=""></div>' +
            '<h3>' + escapeHtml(item.name) + '</h3>' +
            (item.metal || item.weight
              ? '<p class="sc-compare-meta">' + escapeHtml([item.metal, item.weight ? item.weight + ' g' : ''].filter(Boolean).join(' · ')) + '</p>'
              : '') +
            (item.price ? '<p class="sc-compare-price">₹' + escapeHtml(item.price) + '</p>' : '') +
            (item.desc ? '<p class="sc-compare-desc">' + escapeHtml(item.desc) + '</p>' : '') +
            '<button type="button" class="sc-compare-remove" data-remove-id="' + escapeHtml(item.id) + '">Remove</button>' +
          '</article>'
        );
      }).join('');
      grid.querySelectorAll('[data-remove-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          selected.delete(btn.getAttribute('data-remove-id'));
          syncChecks();
          if (selected.size < cfg.minCompare) closeCompare();
          else openCompare();
        });
      });
      compareModal.hidden = false;
      document.body.classList.add('sc-compare-open');
      requestAnimationFrame(function () {
        compareModal.classList.add('is-open');
      });
    }

    function closeCompare() {
      if (!compareModal) return;
      compareModal.classList.remove('is-open');
      document.body.classList.remove('sc-compare-open');
      setTimeout(function () {
        compareModal.hidden = true;
      }, 280);
    }

    function escapeHtml(s) {
      return String(s || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    function decorateItems() {
      getItems().forEach(function (el) {
        if (el.querySelector('.sc-check-wrap')) return;
        var wrap = document.createElement('label');
        wrap.className = 'sc-check-wrap';
        wrap.innerHTML =
          '<input type="checkbox" class="sc-check" aria-label="Select item">' +
          '<span class="sc-pick-badge" hidden>1</span>';
        el.prepend(wrap);
        wrap.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          if (mode === 'browse') setMode('select');
          toggleItem(el);
        });
      });
    }

    function onItemActivate(el, originalHandler) {
      return function (e) {
        if (mode === 'select' || mode === 'compare') {
          e.preventDefault();
          e.stopPropagation();
          toggleItem(el);
          return;
        }
        if (typeof originalHandler === 'function') originalHandler(e);
      };
    }

    // Public bind helper for catalogue buttons / gallery cards
    function bindItemClicks(handlerFactory) {
      getItems().forEach(function (el) {
        if (el.getAttribute('data-cj-click-bound') === '1') return;
        el.setAttribute('data-cj-click-bound', '1');
        var handler = typeof handlerFactory === 'function' ? handlerFactory(el) : null;
        el.addEventListener('click', onItemActivate(el, handler));
      });
    }

    function wireToolbar() {
      var selectBtn = document.getElementById('scEnterSelect');
      var compareBtn = document.getElementById('scEnterCompare');
      var clearBtn = document.getElementById('scClearAll');
      if (selectBtn) {
        selectBtn.addEventListener('click', function () {
          setMode(mode === 'select' ? 'browse' : 'select');
        });
      }
      if (compareBtn) {
        compareBtn.addEventListener('click', function () {
          if (mode === 'compare') {
            if (selected.size >= cfg.minCompare) openCompare();
            else setMode('browse');
          } else {
            setMode('compare');
            if (selected.size >= cfg.minCompare) openCompare();
          }
        });
      }
      if (clearBtn) clearBtn.addEventListener('click', function () {
        clearSelection();
        setMode('browse');
      });
    }

    decorateItems();
    wireToolbar();
    ensureBar();
    syncChecks();

    return {
      setMode: setMode,
      clearSelection: clearSelection,
      ensureSelected: ensureSelected,
      openWhatsApp: openWhatsApp,
      sharePhotos: sharePhotos,
      openCompare: openCompare,
      bindItemClicks: bindItemClicks,
      refreshItems: function () {
        decorateItems();
        syncChecks();
      },
      selectedList: selectedList,
      absUrl: absUrl,
      buildMessage: function (intent) {
        return buildMessage(selectedList(), intent || 'order');
      },
      buildSingleWhatsApp: function (item, intent) {
        return waUrl(cfg.phone, buildMessage([item], intent || 'order'));
      },
      shareSinglePhoto: async function (item) {
        await sharePhotos([item]);
      }
    };
  }

  window.CJSelectCompare = { create: createSelectCompare, absUrl: absUrl, waUrl: waUrl };
})(window);
