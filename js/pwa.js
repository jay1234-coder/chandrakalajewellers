/**
 * PWA register, install prompt (10s), notification permission popup, push subscribe.
 */
(function () {
  'use strict';
  if (window.__CJ_PWA__) return;
  window.__CJ_PWA__ = true;

  var isAdminPath = /admin|superadmin|notifications|cron|track\.php|\/api\//i.test(location.pathname + location.href);
  var deferredPrompt = null;
  var vapidKey = '';
  var swReg = null;

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  }

  function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(base64);
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }

  function endpointBase() {
    var scripts = document.getElementsByTagName('script');
    for (var i = scripts.length - 1; i >= 0; i--) {
      var src = scripts[i].src || '';
      if (src.indexOf('pwa.js') !== -1) {
        return src.replace(/js\/pwa\.js.*$/i, '');
      }
    }
    return './';
  }

  var BASE = endpointBase();
  // Prefer extensionless API path (InfinityFree / .htaccess rewrite)
  var SUBSCRIBE_URL = BASE + 'api/push-subscribe';

  function setCookie(name, value, days) {
    var maxAge = (days || 365) * 24 * 60 * 60;
    document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
  }

  function getCookie(name) {
    var match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : null;
  }

  function hasDismissedNotify() {
    try {
      if (localStorage.getItem('cj_notify_dismissed') === '1') return true;
    } catch (e) {}
    return getCookie('cj_notify_dismissed') === '1';
  }

  function markDismissedNotify() {
    try { localStorage.setItem('cj_notify_dismissed', '1'); } catch (e) {}
    setCookie('cj_notify_dismissed', '1', 365);
  }

  function hasDismissedInstall() {
    try {
      if (localStorage.getItem('cj_install_dismissed') === '1') return true;
    } catch (e) {}
    return getCookie('cj_install_dismissed') === '1';
  }

  function markDismissedInstall() {
    try { localStorage.setItem('cj_install_dismissed', '1'); } catch (e) {}
    setCookie('cj_install_dismissed', '1', 365);
  }

  function closeEl(el) {
    if (!el) return;
    el.classList.remove('is-open');
    setTimeout(function () { if (el.parentNode) el.remove(); }, 280);
  }

  async function parseJsonResponse(res) {
    var text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      throw new Error('Server returned non-JSON (' + res.status + '). Check api/push-subscribe.');
    }
  }

  function showInstallPopup() {
    if (isStandalone() || isAdminPath) return;
    if (hasDismissedInstall()) return;
    try {
      if (sessionStorage.getItem('cj_install_shown') === '1') return;
      sessionStorage.setItem('cj_install_shown', '1');
    } catch (e) {}
    if (document.getElementById('cjInstallPrompt') || document.getElementById('cjNotifyPrompt') || document.getElementById('cjGeoPrompt')) return;

    var el = document.createElement('div');
    el.id = 'cjInstallPrompt';
    el.className = 'cj-install-prompt';
    el.innerHTML =
      '<div class="cj-install-card" style="position:relative;">' +
        '<button type="button" class="cj-install-close" aria-label="Close" style="position:absolute;top:8px;right:10px;background:none;border:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;padding:4px;">&times;</button>' +
        '<div class="cj-install-icon"><img src="' + BASE + 'images/icons/icon-192.png" alt="" width="48" height="48" onerror="this.style.display=\'none\'"></div>' +
        '<div class="cj-install-copy">' +
          '<strong>Install ChandraKala App</strong>' +
          '<p>Add to your home screen for faster shopping, live rates &amp; offers.</p>' +
        '</div>' +
        '<div class="cj-install-actions">' +
          '<button type="button" class="btn btn-secondary" data-install-later>Later</button>' +
          '<button type="button" class="btn" data-install-now>Install</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-open'); });

    var closeBtn = el.querySelector('.cj-install-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        markDismissedInstall();
        closeEl(el);
      });
    }

    el.querySelector('[data-install-later]').addEventListener('click', function () {
      markDismissedInstall();
      closeEl(el);
    });
    el.querySelector('[data-install-now]').addEventListener('click', async function () {
      markDismissedInstall();
      if (deferredPrompt) {
        deferredPrompt.prompt();
        try { await deferredPrompt.userChoice; } catch (e) {}
        deferredPrompt = null;
      } else {
        var isiOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
        alert(
          isiOS
            ? 'On iPhone/iPad: tap Share → Add to Home Screen.'
            : 'On Android Chrome: tap menu (⋮) → Install app / Add to Home screen.'
        );
      }
      closeEl(el);
    });
  }

  async function completeSubscribe(reg, audience) {
    try {
      if (!('Notification' in window)) {
        return { ok: false, reason: 'unsupported', detail: 'This browser does not support notifications.' };
      }
      if (!reg) {
        return { ok: false, reason: 'no-sw', detail: 'Service worker not ready. Refresh the page.' };
      }

      // Ensure SW is active before PushManager.subscribe
      try {
        reg = await navigator.serviceWorker.ready;
      } catch (e) {
        return { ok: false, reason: 'no-sw', detail: 'Service worker failed to activate.' };
      }
      if (!reg.pushManager) {
        return { ok: false, reason: 'no-push', detail: 'Push messaging is not supported here (need HTTPS / Chrome / Edge / Android).' };
      }

      var res = await fetch(SUBSCRIBE_URL, { credentials: 'same-origin', cache: 'no-store' });
      // Fallback to .php if rewrite missing
      if (!res.ok) {
        res = await fetch(BASE + 'api/push-subscribe.php', { credentials: 'same-origin', cache: 'no-store' });
      }
      var meta = await parseJsonResponse(res);
      vapidKey = meta.publicKey || '';
      if (!vapidKey) {
        return {
          ok: false,
          reason: 'no-vapid',
          detail: 'Server push keys missing. Open /notifications once, or /api/setup-vapid.php while logged in as admin.'
        };
      }

      var keyBytes;
      try {
        keyBytes = urlBase64ToUint8Array(vapidKey);
      } catch (e) {
        return { ok: false, reason: 'bad-key', detail: 'Invalid VAPID public key from server.' };
      }
      if (keyBytes.length !== 65) {
        return {
          ok: false,
          reason: 'bad-key',
          detail: 'VAPID key length is ' + keyBytes.length + ' (expected 65). Regenerate keys via /api/setup-vapid.php'
        };
      }

      var permission = Notification.permission;
      if (permission === 'denied') {
        return {
          ok: false,
          reason: 'denied',
          detail: 'Notifications blocked. Browser site settings → Notifications → Allow, then retry.'
        };
      }
      if (permission !== 'granted') {
        permission = await Notification.requestPermission();
        if (permission !== 'granted') {
          return { ok: false, reason: 'dismissed', detail: 'Permission was not granted.' };
        }
      }

      var sub;
      try {
        sub = await reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: keyBytes
        });
      } catch (e) {
        // Retry after unsubscribe of broken subscription
        try {
          var existing = await reg.pushManager.getSubscription();
          if (existing) await existing.unsubscribe();
          sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: keyBytes
          });
        } catch (e2) {
          return {
            ok: false,
            reason: 'subscribe-fail',
            detail: (e2 && e2.message) ? e2.message : 'Browser rejected push subscribe.'
          };
        }
      }

      var json = sub.toJSON();
      var postRes = await fetch(SUBSCRIBE_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'subscribe',
          endpoint: json.endpoint,
          keys: json.keys,
          audience: audience || 'customer',
          is_pwa: isStandalone() ? 1 : 0
        })
      });
      if (!postRes.ok) {
        postRes = await fetch(BASE + 'api/push-subscribe.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'subscribe',
            endpoint: json.endpoint,
            keys: json.keys,
            audience: audience || 'customer',
            is_pwa: isStandalone() ? 1 : 0
          })
        });
      }
      var saved = await parseJsonResponse(postRes);
      if (!saved.ok) {
        return { ok: false, reason: 'save-fail', detail: 'Could not save subscription on server.' };
      }

      try { localStorage.setItem('cj_push_subscribed', '1'); } catch (e) {}
      return { ok: true };
    } catch (e) {
      return {
        ok: false,
        reason: 'error',
        detail: (e && e.message) ? e.message : 'Unknown error'
      };
    }
  }

  function showNotifyPopup(reg, audience) {
    if (!('Notification' in window) || !reg) return;
    if (Notification.permission === 'granted') {
      markDismissedNotify();
      completeSubscribe(reg, audience).catch(function () {});
      return;
    }
    if (Notification.permission === 'denied') {
      markDismissedNotify();
      return;
    }
    if (hasDismissedNotify()) return;
    if (document.getElementById('cjNotifyPrompt') || document.getElementById('cjInstallPrompt') || document.getElementById('cjGeoPrompt')) return;

    var el = document.createElement('div');
    el.id = 'cjNotifyPrompt';
    el.className = 'cj-notify-prompt';
    el.innerHTML =
      '<div class="cj-notify-card" style="position:relative;">' +
        '<button type="button" class="cj-notify-close" aria-label="Close" style="position:absolute;top:8px;right:10px;background:none;border:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;padding:4px;">&times;</button>' +
        '<div class="cj-notify-icon" aria-hidden="true">🔔</div>' +
        '<div class="cj-notify-copy">' +
          '<strong>Allow notifications?</strong>' +
          '<p>Get live rates &amp; offers even when the website or app is closed.</p>' +
        '</div>' +
        '<div class="cj-notify-actions">' +
          '<button type="button" class="btn btn-secondary" data-notify-later>Not now</button>' +
          '<button type="button" class="btn" data-notify-allow>Allow</button>' +
        '</div>' +
        '<p class="cj-notify-status" data-notify-status hidden></p>' +
      '</div>';
    document.body.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-open'); });

    var statusEl = el.querySelector('[data-notify-status]');
    function setStatus(t) {
      if (!statusEl) return;
      statusEl.hidden = !t;
      statusEl.textContent = t || '';
    }

    var closeBtn = el.querySelector('.cj-notify-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        markDismissedNotify();
        closeEl(el);
      });
    }

    el.querySelector('[data-notify-later]').addEventListener('click', function () {
      markDismissedNotify();
      closeEl(el);
    });

    el.querySelector('[data-notify-allow]').addEventListener('click', async function () {
      markDismissedNotify();
      setStatus('Waiting for browser permission…');
      var result = await completeSubscribe(reg, audience || 'customer');
      if (result.ok) {
        setStatus('Enabled — you can get alerts even when the app is closed.');
        setTimeout(function () { closeEl(el); }, 1400);
        return;
      }
      setStatus(result.detail || ('Could not enable (' + (result.reason || 'error') + ').'));
      setTimeout(function () { closeEl(el); }, 3000);
    });
  }

  if (!isAdminPath && !isStandalone()) {
    setTimeout(showInstallPopup, 10000);
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
  });

  window.addEventListener('appinstalled', function () {
    markDismissedInstall();
    deferredPrompt = null;
  });

  if (!('serviceWorker' in navigator)) {
    window.CJPwa = {
      subscribe: function () {
        return Promise.resolve({ ok: false, reason: 'unsupported', detail: 'No service worker support.' });
      }
    };
    return;
  }

  navigator.serviceWorker.register(BASE + 'sw.js').then(function (reg) {
    swReg = reg;
    var audience = 'customer';
    if (/admin/i.test(location.pathname) && !/superadmin/i.test(location.pathname)) audience = 'admin';
    if (/superadmin/i.test(location.pathname)) audience = 'superadmin';

    if (!isAdminPath) {
      if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
        markDismissedNotify();
        setTimeout(function () { completeSubscribe(reg, 'customer'); }, 2500);
      } else if (typeof Notification !== 'undefined' && Notification.permission === 'denied') {
        markDismissedNotify();
      } else if (!hasDismissedNotify()) {
        setTimeout(function () { showNotifyPopup(reg, 'customer'); }, 14000);
      }
      // Offline image + page cache (Cache API — not cookies; cookies only store preference)
      navigator.serviceWorker.ready.then(function (readyReg) {
        setTimeout(function () { precacheSiteAssets(readyReg); }, 2500);
      });
    } else {
      if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
        markDismissedNotify();
        setTimeout(function () { completeSubscribe(reg, audience); }, 2500);
      } else if (!hasDismissedNotify()) {
        setTimeout(function () { showNotifyPopup(reg, audience); }, 2500);
      }
    }
  }).catch(function () {});

  function collectPageImageUrls() {
    var urls = [];
    document.querySelectorAll('img[src]').forEach(function (img) {
      var src = img.currentSrc || img.src;
      if (!src) return;
      try {
        var u = new URL(src, location.href);
        if (u.origin === location.origin) urls.push(u.href);
      } catch (e) {}
    });
    document.querySelectorAll('link[rel="apple-touch-icon"], link[rel="icon"]').forEach(function (link) {
      if (link.href) urls.push(link.href);
    });
    return urls;
  }

  function precacheSiteAssets(reg) {
    if (isAdminPath) return;
    var worker = (reg && reg.active) || (navigator.serviceWorker && navigator.serviceWorker.controller);
    var pageUrls = collectPageImageUrls();

    function sendToSw(urls) {
      if (!urls.length) return;
      var payload = { type: 'PRECACHE_URLS', urls: urls };
      try {
        if (worker) worker.postMessage(payload);
        else if (navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage(payload);
      } catch (e) {}
      // Also warm browser HTTP cache
      urls.slice(0, 40).forEach(function (u) {
        try {
          var isSame = (new URL(u, location.href).origin === location.origin);
          fetch(u, { credentials: isSame ? 'same-origin' : 'omit', cache: 'force-cache' }).catch(function () {});
        } catch (e) {
          fetch(u, { cache: 'force-cache' }).catch(function () {});
        }
      });
    }

    sendToSw(pageUrls);

    fetch(BASE + 'api/cache-manifest', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .catch(function () {
        return fetch(BASE + 'api/cache-manifest.php', { credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.json() : null; });
      })
      .then(function (data) {
        if (!data || !data.urls) return;
        var abs = data.urls.map(function (u) {
          try { return new URL(u, location.href).href; } catch (e) { return null; }
        }).filter(Boolean);
        sendToSw(abs.concat(pageUrls));
        try {
          localStorage.setItem('cj_offline_images', String(abs.length));
          localStorage.setItem('cj_offline_at', String(Date.now()));
        } catch (e) {}
        setCookie('cj_offline_ready', '1', 30);
        setCookie('cj_offline_count', String(abs.length), 30);
      })
      .catch(function () {});
  }

  if (navigator.serviceWorker) {
    navigator.serviceWorker.addEventListener('message', function (event) {
      if (event.data && event.data.type === 'PRECACHE_DONE') {
        setCookie('cj_offline_ready', '1', 30);
      }
    });
  }

  window.CJPwa = {
    subscribe: function (audience) {
      if (swReg) return completeSubscribe(swReg, audience || 'customer');
      return navigator.serviceWorker.ready.then(function (reg) {
        swReg = reg;
        return completeSubscribe(reg, audience || 'customer');
      });
    }
  };

  // --- Header Settings panel (theme, push, location, install) ---
  (function () {
    var settingsBtn = document.getElementById('header-settings-btn') || document.getElementById('push-bell-btn');
    var dropdown = document.getElementById('header-settings-panel') || document.getElementById('push-notification-dropdown');
    var pushToggle = document.getElementById('push-notify-toggle');
    var inAppToggle = document.getElementById('in-app-notify-toggle');
    var locationToggle = document.getElementById('header-location-toggle');
    var installBtn = document.getElementById('header-install-btn');
    var installRow = document.getElementById('header-install-row');
    var statusEl = document.getElementById('push-dropdown-status');
    var badge = document.getElementById('push-bell-badge');

    if (!settingsBtn || !dropdown) return;

    function setStatus(msg) {
      if (statusEl) {
        statusEl.textContent = msg || '';
      }
    }

    function openPanel() {
      dropdown.hidden = false;
      settingsBtn.setAttribute('aria-expanded', 'true');
      try { document.dispatchEvent(new CustomEvent('cj:settings-open')); } catch (e) {}
      if (window.CJTheme && window.CJTheme.sync) window.CJTheme.sync();
    }

    function closePanel() {
      dropdown.hidden = true;
      settingsBtn.setAttribute('aria-expanded', 'false');
    }

    // Settings open is primarily handled in main.js; keep permission prompt here
    settingsBtn.addEventListener('click', function () {
      // After panel opens (main.js), request permission if needed
      setTimeout(function () {
        if (dropdown.hidden) return;
        if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
          setStatus('Tip: turn on Push Alerts for offers.');
        }
      }, 50);
    });

    // In-App toggle logic
    if (inAppToggle) {
      var inAppEnabled = localStorage.getItem('cj_in_app_notifications_enabled') !== '0';
      inAppToggle.checked = inAppEnabled;
      inAppToggle.addEventListener('change', function () {
        localStorage.setItem('cj_in_app_notifications_enabled', this.checked ? '1' : '0');
        setStatus(this.checked ? 'In-App Alerts enabled.' : 'In-App Alerts disabled.');
      });
    }

    // Push toggle logic
    function syncPushToggleState(reg) {
      if (!pushToggle || !reg) return;
      reg.pushManager.getSubscription().then(function (sub) {
        var isSubbed = !!sub;
        pushToggle.checked = isSubbed && Notification.permission === 'granted';
        if (badge) {
          badge.hidden = (Notification.permission === 'granted' && isSubbed);
        }
      }).catch(function () {});
    }

    navigator.serviceWorker.ready.then(function (reg) {
      syncPushToggleState(reg);
    });

    if (pushToggle) {
      pushToggle.addEventListener('change', async function () {
        var activeReg = swReg;
        if (!activeReg) {
          try {
            activeReg = await navigator.serviceWorker.ready;
          } catch (e) {
            setStatus('Service worker not ready.');
            this.checked = false;
            return;
          }
        }

        var audience = 'customer';
        if (/admin/i.test(location.pathname) && !/superadmin/i.test(location.pathname)) audience = 'admin';
        if (/superadmin/i.test(location.pathname)) audience = 'superadmin';

        if (this.checked) {
          setStatus('Enabling push alerts…');
          var result = await completeSubscribe(activeReg, audience);
          if (result.ok) {
            setStatus('Push alerts active!');
            if (badge) badge.hidden = true;
          } else {
            setStatus(result.detail || 'Could not enable push.');
            this.checked = false;
          }
        } else {
          setStatus('Disabling push alerts…');
          try {
            var sub = await activeReg.pushManager.getSubscription();
            if (sub) {
              var endpoint = sub.endpoint;
              await sub.unsubscribe();
              var unsubscribePayload = { action: 'unsubscribe', endpoint: endpoint };
              var unsubscribeOptions = {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(unsubscribePayload)
              };
              await fetch(SUBSCRIBE_URL, unsubscribeOptions).catch(function () {
                return fetch(BASE + 'api/push-subscribe.php', unsubscribeOptions);
              });
            }
            try { localStorage.removeItem('cj_push_subscribed'); } catch (err) {}
            setStatus('Push alerts disabled.');
            if (badge) badge.hidden = false;
          } catch (e) {
            setStatus('Error unsubscribing: ' + e.message);
            this.checked = true;
          }
        }
      });
    }

    // Geolocation sharing logic
    if (locationToggle) {
      if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then(function (result) {
          locationToggle.checked = (result.state === 'granted');
          result.onchange = function () {
            locationToggle.checked = (result.state === 'granted');
          };
        }).catch(function () {});
      }

      locationToggle.addEventListener('change', function () {
        if (this.checked) {
          setStatus('Requesting location access…');
          if (window.CJAnalytics && window.CJAnalytics.requestLocation) {
            window.CJAnalytics.requestLocation(
              function () {
                setStatus('Location shared successfully!');
              },
              function (err) {
                setStatus('Location denied or failed: ' + err);
                locationToggle.checked = false;
              }
            );
          } else {
            navigator.geolocation.getCurrentPosition(
              function () {
                setStatus('Location shared successfully!');
                try { localStorage.setItem('cj_geo_asked', '1'); } catch (e) {}
                setCookie('cj_geo_asked', '1', 365);
              },
              function () {
                setStatus('Location permission denied.');
                locationToggle.checked = false;
                try { localStorage.setItem('cj_geo_asked', '1'); } catch (e) {}
                setCookie('cj_geo_asked', '1', 365);
              }
            );
          }
        } else {
          setStatus('Location sharing disabled.');
        }
      });
    }

    // App Installation logic
    function updateInstallState() {
      if (!installBtn || !installRow) return;
      if (deferredPrompt) {
        installRow.hidden = false;
        installBtn.disabled = false;
        installBtn.textContent = 'Install';
      } else {
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
          installRow.hidden = false;
          installBtn.disabled = true;
          installBtn.textContent = 'Installed';
        } else {
          installRow.hidden = true;
        }
      }
    }

    setTimeout(updateInstallState, 1000);

    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferredPrompt = e;
      updateInstallState();
    });

    if (installBtn) {
      installBtn.addEventListener('click', async function () {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        try {
          var choice = await deferredPrompt.userChoice;
          if (choice.outcome === 'accepted') {
            setStatus('App installed successfully!');
            deferredPrompt = null;
            updateInstallState();
          } else {
            setStatus('App install dismissed.');
          }
        } catch (err) {
          setStatus('Installation error: ' + err.message);
        }
      });
    }
  })();

  // --- Network Connection Status Bar & Low Connectivity Mode ---
  (function () {
    var statusBar = null;

    function showStatus(text, type) {
      if (!statusBar) {
        statusBar = document.createElement('div');
        statusBar.id = 'cj-network-status-bar';
        statusBar.style.position = 'fixed';
        statusBar.style.top = '0';
        statusBar.style.left = '0';
        statusBar.style.right = '0';
        statusBar.style.height = '36px';
        statusBar.style.zIndex = '9999';
        statusBar.style.display = 'flex';
        statusBar.style.alignItems = 'center';
        statusBar.style.justifyContent = 'center';
        statusBar.style.fontSize = '12px';
        statusBar.style.fontWeight = '600';
        statusBar.style.color = '#ffffff';
        statusBar.style.transition = 'transform 0.3s ease';
        statusBar.style.boxShadow = '0 2px 8px rgba(0,0,0,0.16)';
        statusBar.style.fontFamily = 'system-ui, -apple-system, sans-serif';
        document.body.appendChild(statusBar);
        document.body.style.paddingTop = '36px';
      }
      statusBar.textContent = text;
      if (type === 'offline') {
        statusBar.style.backgroundColor = '#ef4444'; // Red
      } else if (type === 'slow') {
        statusBar.style.backgroundColor = '#b8972c'; // Gold/Amber
      } else {
        statusBar.style.backgroundColor = '#10b981'; // Green
      }
      statusBar.style.transform = 'translateY(0)';
    }

    function hideStatus() {
      if (statusBar) {
        statusBar.style.transform = 'translateY(-36px)';
        setTimeout(function () {
          if (statusBar) {
            statusBar.remove();
            statusBar = null;
            document.body.style.paddingTop = '';
          }
        }, 300);
      }
    }

    function checkConnection() {
      if (typeof navigator.onLine === 'boolean' && !navigator.onLine) {
        showStatus('Offline Mode — Viewing cached version of the site.', 'offline');
        document.body.classList.add('low-connectivity');
        return;
      }

      hideStatus();
      document.body.classList.remove('low-connectivity');
    }

    window.addEventListener('offline', function () {
      showStatus('Disconnected: You are offline.', 'offline');
      document.body.classList.add('low-connectivity');
    });

    window.addEventListener('online', function () {
      showStatus('Back Online!', 'online');
      setTimeout(checkConnection, 1500);
    });

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', checkConnection);
    } else {
      checkConnection();
    }
  })();
})();
