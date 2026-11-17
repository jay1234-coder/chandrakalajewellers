<?php
/**
 * Early theme boot — include in <head> on every page (store + admin).
 * Sets data-theme before paint and loads theme.js.
 */
?>
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#b8972c" id="cjThemeColorMeta">
<script>
(function () {
  try {
    var KEY = 'cj_theme';
    var pref = '';
    try { pref = localStorage.getItem(KEY) || ''; } catch (e) {}
    if (!pref) {
      var m = document.cookie.match(/(?:^|; )cj_theme=([^;]*)/);
      pref = m ? decodeURIComponent(m[1]) : '';
    }
    if (pref !== 'light' && pref !== 'dark' && pref !== 'system') pref = 'system';
    var resolved = pref;
    if (pref === 'system') {
      resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    document.documentElement.setAttribute('data-theme', resolved);
    document.documentElement.setAttribute('data-theme-pref', pref);
    var meta = document.getElementById('cjThemeColorMeta') || document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', resolved === 'dark' ? '#0c0e12' : '#b8972c');
  } catch (e) {}
})();
</script>
<script src="js/theme.js" defer></script>
