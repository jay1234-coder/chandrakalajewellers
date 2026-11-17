<?php
/**
 * Early theme boot — include in <head> on every page (store + admin).
 * Sets data-theme before paint and provides inline theme controller + event listener.
 */
$cj_theme_prefix = (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin') !== false) ? '../' : '';
?>
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#b8972c" id="cjThemeColorMeta">
<script>
(function () {
  'use strict';
  var KEY = 'cj_theme';
  var COOKIE = 'cj_theme';

  function readCookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }

  function writeCookie(name, value, days) {
    var maxAge = (days || 365) * 24 * 60 * 60;
    document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
  }

  function getPreference() {
    var pref = '';
    try { pref = localStorage.getItem(KEY) || ''; } catch (e) {}
    if (!pref) pref = readCookie(COOKIE) || '';
    if (pref !== 'light' && pref !== 'dark' && pref !== 'system') pref = 'system';
    return pref;
  }

  function resolveTheme(pref) {
    if (pref === 'light' || pref === 'dark') return pref;
    try {
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    } catch (e) {
      return 'light';
    }
  }

  function syncToggleUI(pref) {
    document.querySelectorAll('#cjThemeToggle [data-theme-opt], .cj-theme-seg [data-theme-opt]').forEach(function (btn) {
      var on = btn.getAttribute('data-theme-opt') === pref;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function applyTheme(pref) {
    var resolved = resolveTheme(pref);
    document.documentElement.setAttribute('data-theme', resolved);
    document.documentElement.setAttribute('data-theme-pref', pref);
    try {
      var meta = document.getElementById('cjThemeColorMeta') || document.querySelector('meta[name="theme-color"]');
      if (meta) meta.setAttribute('content', resolved === 'dark' ? '#0c0e12' : '#b8972c');
    } catch (e) {}
    syncToggleUI(pref);
  }

  function setPreference(pref) {
    if (pref !== 'light' && pref !== 'dark' && pref !== 'system') pref = 'system';
    try { localStorage.setItem(KEY, pref); } catch (e) {}
    writeCookie(COOKIE, pref, 400);
    applyTheme(pref);
    return pref;
  }

  // Set early attribute before render to prevent FOUC
  var initialPref = getPreference();
  var initialResolved = resolveTheme(initialPref);
  document.documentElement.setAttribute('data-theme', initialResolved);
  document.documentElement.setAttribute('data-theme-pref', initialPref);

  // Click delegation for theme buttons
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-theme-opt]');
    if (!btn) return;
    e.preventDefault();
    setPreference(btn.getAttribute('data-theme-opt'));
  });

  function bootSync() {
    syncToggleUI(getPreference());
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootSync);
  } else {
    bootSync();
  }

  try {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var onChange = function () {
      if (getPreference() === 'system') applyTheme('system');
    };
    if (mq.addEventListener) mq.addEventListener('change', onChange);
    else if (mq.addListener) mq.addListener(onChange);
  } catch (e) {}

  window.CJTheme = {
    get: getPreference,
    set: setPreference,
    apply: applyTheme,
    sync: function () { syncToggleUI(getPreference()); }
  };
})();
</script>
