/**
 * Visitor analytics: session, pages, dwell time, optional geolocation.
 */
(function () {
  'use strict';

  try {
    if (window.__CJ_ANALYTICS__) return;
    window.__CJ_ANALYTICS__ = true;

    var path = (window.location.pathname || '') + (window.location.search || '');
    if (/admin|superadmin|track\.php|\/api\//i.test(path)) return;

    var ENDPOINT = (function () {
      var scripts = document.getElementsByTagName('script');
      for (var i = scripts.length - 1; i >= 0; i--) {
        var src = scripts[i].src || '';
        if (src.indexOf('visitor-analytics.js') !== -1) {
          return src.replace(/js\/visitor-analytics\.js.*$/i, 'api/track.php');
        }
      }
      return 'api/track.php';
    })();
    var STORAGE_SID = 'cj_vid';
    var STORAGE_GEO = 'cj_geo_asked';

    function setCookie(name, value, days) {
      var maxAge = (days || 365) * 24 * 60 * 60;
      document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
    }

    function getCookie(name) {
      var match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'));
      return match ? decodeURIComponent(match[2]) : null;
    }

    function hasAskedGeo() {
      try {
        if (localStorage.getItem(STORAGE_GEO) === '1') return true;
      } catch (e) {}
      return getCookie(STORAGE_GEO) === '1';
    }

    function markAskedGeo() {
      try { localStorage.setItem(STORAGE_GEO, '1'); } catch (e) {}
      setCookie(STORAGE_GEO, '1', 365);
    }

    var tickMs = 15000;
    var startedAt = Date.now();
    var pageEnteredAt = Date.now();
    var lastSentDuration = 0;

    function uuid() {
      if (window.crypto && crypto.randomUUID) return crypto.randomUUID().replace(/-/g, '');
      return 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.replace(/x/g, function () {
        return ((Math.random() * 16) | 0).toString(16);
      });
    }

    function getSid() {
      var sid = '';
      try { sid = localStorage.getItem(STORAGE_SID) || ''; } catch (e) {}
      if (!sid || sid.length < 16) {
        sid = uuid();
        try { localStorage.setItem(STORAGE_SID, sid); } catch (e) {}
      }
      return sid;
    }

    function pagePath() {
      var p = window.location.pathname || '/';
      p = p.replace(/\\/g, '/');
      var parts = p.split('/');
      var file = parts[parts.length - 1] || 'index1';
      if (file === '' || file === 'htdocs') file = 'index1';
      var q = window.location.search || '';
      return file + q;
    }

    function send(payload, useBeacon) {
      var body = JSON.stringify(Object.assign({
        sid: getSid(),
        page: pagePath(),
        title: document.title || '',
        referrer: document.referrer || '',
        duration: Math.floor((Date.now() - startedAt) / 1000)
      }, payload || {}));

      if (useBeacon && navigator.sendBeacon) {
        try {
          var blob = new Blob([body], { type: 'application/json' });
          navigator.sendBeacon(ENDPOINT, blob);
          return;
        } catch (e) {}
      }
      try {
        fetch(ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: body,
          keepalive: true,
          credentials: 'same-origin'
        }).catch(function () {});
      } catch (e) {}
    }

    function secondsOnPage() {
      return Math.floor((Date.now() - pageEnteredAt) / 1000);
    }

    function heartbeat() {
      var d = Math.floor((Date.now() - startedAt) / 1000);
      if (d === lastSentDuration) return;
      lastSentDuration = d;
      send({
        event: 'heartbeat',
        seconds_on_page: secondsOnPage()
      });
    }

    function closePrompt(el) {
      if (!el) return;
      el.classList.remove('is-open');
      setTimeout(function () { if (el.parentNode) el.remove(); }, 250);
    }

    function showGeoPrompt() {
      if (hasAskedGeo()) return;
      if (document.getElementById('cjGeoPrompt') || document.getElementById('cjNotifyPrompt') || document.getElementById('cjInstallPrompt')) return;
      var el = document.createElement('div');
      el.id = 'cjGeoPrompt';
      el.className = 'cj-geo-prompt';
      el.innerHTML =
        '<div class="cj-geo-prompt-card" style="position:relative;">' +
          '<button type="button" class="cj-geo-close" aria-label="Close" style="position:absolute;top:8px;right:10px;background:none;border:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;padding:4px;">&times;</button>' +
          '<p><strong>Share your location?</strong></p>' +
          '<p>Helps ChandraKala Jewellers understand which areas customers visit from — used only for store insights.</p>' +
          '<div class="cj-geo-prompt-actions">' +
            '<button type="button" class="btn btn-secondary" data-geo-skip>Not now</button>' +
            '<button type="button" class="btn" data-geo-allow>Allow location</button>' +
          '</div>' +
          '<p class="cj-geo-status" data-geo-status hidden></p>' +
        '</div>';
      document.body.appendChild(el);
      requestAnimationFrame(function () { el.classList.add('is-open'); });

      var statusEl = el.querySelector('[data-geo-status]');
      function setStatus(t) {
        if (!statusEl) return;
        statusEl.hidden = !t;
        statusEl.textContent = t || '';
      }

      var closeBtn = el.querySelector('.cj-geo-close');
      if (closeBtn) {
        closeBtn.addEventListener('click', function () {
          markAskedGeo();
          closePrompt(el);
        });
      }

      el.querySelector('[data-geo-skip]').addEventListener('click', function () {
        markAskedGeo();
        send({ event: 'geo', geo_status: 'dismissed' });
        closePrompt(el);
      });

      // Must call geolocation inside the click (user gesture)
      el.querySelector('[data-geo-allow]').addEventListener('click', function () {
        markAskedGeo();
        if (!navigator.geolocation) {
          setStatus('Location is not supported on this browser.');
          send({ event: 'geo', geo_status: 'unsupported' });
          setTimeout(function () { closePrompt(el); }, 1200);
          return;
        }
        setStatus('Waiting for browser permission…');
        navigator.geolocation.getCurrentPosition(
          function (pos) {
            send({
              event: 'geo',
              geo_status: 'granted',
              lat: pos.coords.latitude,
              lng: pos.coords.longitude,
              accuracy: pos.coords.accuracy
            });
            setStatus('Thanks — location saved.');
            setTimeout(function () { closePrompt(el); }, 900);
          },
          function (err) {
            var status = 'denied';
            var msg = 'Location permission was denied.';
            if (err && err.code === 3) { status = 'timeout'; msg = 'Location request timed out. Try again.'; }
            if (err && err.code === 2) { status = 'unavailable'; msg = 'Location unavailable right now.'; }
            send({ event: 'geo', geo_status: status });
            setStatus(msg);
            setTimeout(function () { closePrompt(el); }, 1600);
          },
          { enableHighAccuracy: false, timeout: 15000, maximumAge: 300000 }
        );
      });
    }

    // Expose location requester globally
    window.CJAnalytics = {
      requestLocation: function (onSuccess, onError) {
        if (!navigator.geolocation) {
          if (onError) onError('unsupported');
          return;
        }
        navigator.geolocation.getCurrentPosition(
          function (pos) {
            send({
              event: 'geo',
              geo_status: 'granted',
              lat: pos.coords.latitude,
              lng: pos.coords.longitude,
              accuracy: pos.coords.accuracy
            });
            markAskedGeo();
            if (onSuccess) onSuccess();
          },
          function (err) {
            var status = 'denied';
            if (err && err.code === 3) status = 'timeout';
            if (err && err.code === 2) status = 'unavailable';
            send({ event: 'geo', geo_status: status });
            markAskedGeo();
            if (onError) onError(status);
          },
          { enableHighAccuracy: false, timeout: 15000, maximumAge: 300000 }
        );
      }
    };

    function fetchGeoSilently() {
      if (!navigator.geolocation) return;
      navigator.geolocation.getCurrentPosition(
        function (pos) {
          send({
            event: 'geo',
            geo_status: 'granted',
            lat: pos.coords.latitude,
            lng: pos.coords.longitude,
            accuracy: pos.coords.accuracy
          });
        },
        function () {},
        { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
      );
    }

    function maybeAskGeo() {
      if (hasAskedGeo()) return;

      if (navigator.permissions && navigator.permissions.query) {
        try {
          navigator.permissions.query({ name: 'geolocation' }).then(function (result) {
            if (result.state === 'granted') {
              markAskedGeo();
              fetchGeoSilently();
              return;
            }
            if (result.state === 'denied') {
              markAskedGeo();
              return;
            }
            if (!hasAskedGeo()) {
              setTimeout(showGeoPrompt, 2500);
            }
          }).catch(function () {
            if (!hasAskedGeo()) {
              setTimeout(showGeoPrompt, 2500);
            }
          });
          return;
        } catch (e) {}
      }

      setTimeout(showGeoPrompt, 2500);
    }

    send({
      event: 'page',
      page: pagePath(),
      title: document.title || '',
      referrer: document.referrer || ''
    });

    maybeAskGeo();
    setInterval(heartbeat, tickMs);

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') {
        send({ event: 'heartbeat', seconds_on_page: secondsOnPage() }, true);
      }
    });
    window.addEventListener('pagehide', function () {
      send({ event: 'end', seconds_on_page: secondsOnPage() }, true);
    });
  } catch (e) {
    /* never break the storefront */
  }
})();
