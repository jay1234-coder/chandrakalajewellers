<?php
/**
 * High-performance Critical CSS + asset loader.
 * Inlines essential above-the-fold rules for 0ms First Paint (zero FOUC),
 * and loads versioned site CSS, Tailwind utilities, and webfonts.
 */
require_once __DIR__ . '/seo.php';
$cjFontUrl = 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap';
$cjAssetVer = '9';

if (!defined('CJ_CHARSET_SET')) {
    define('CJ_CHARSET_SET', true);
    echo '<meta charset="UTF-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">' . "\n";
    $cjBaseHref = '/';
    if (function_exists('cj_is_local_host') && cj_is_local_host() && function_exists('cj_base_url')) {
        $parsedPath = (string)parse_url(cj_base_url(), PHP_URL_PATH);
        $cjBaseHref = rtrim($parsedPath, '/') . '/';
    }
    echo '<base href="' . htmlspecialchars($cjBaseHref, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
?>
<!-- Critical Above-The-Fold CSS (Instant Paint - 0ms FOUC) -->
<style id="cj-critical-css">
:root {
  --primary-gold: #d4af37;
  --primary-gold-dark: #b8972c;
  --primary-gold-light: #f3e5ab;
  --bg-light: #f9fafb;
  --bg-card: #ffffff;
  --text-primary: #111827;
  --text-secondary: #4b5563;
  --text-light: #9ca3af;
  --border-color: #e5e7eb;
  --header-bg: #ffffff;
  --gold-shine: linear-gradient(135deg, #f5e6a8 0%, #d4af37 45%, #b8972c 100%);
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  color-scheme: light;
}
html[data-theme="dark"] {
  --primary-gold: #f0d78c;
  --primary-gold-dark: #e8c45a;
  --primary-gold-light: #3f3618;
  --bg-light: #0c0e12;
  --bg-card: #161a22;
  --text-primary: #f5f6f8;
  --text-secondary: #b4bccb;
  --text-light: #8b93a7;
  --border-color: #2e3544;
  --header-bg: #12151c;
  --gold-shine: linear-gradient(135deg, #fff6d0 0%, #f0d78c 28%, #e8c45a 55%, #c9a227 78%, #ffe9a8 100%);
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.4);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
  color-scheme: dark;
}
*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
  margin: 0;
  padding: 0;
  font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  background-color: var(--bg-light);
  color: var(--text-primary);
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}
.header {
  background-color: var(--header-bg);
  color: var(--text-primary);
  padding: 8px 0;
  padding-top: max(8px, env(safe-area-inset-top, 0px));
  position: sticky;
  top: 0;
  z-index: 200;
  border-bottom: 1px solid var(--border-color);
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.06);
}
.header-content {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 12px;
  min-height: 52px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.header-logo { width: 36px; height: 36px; object-fit: contain; flex-shrink: 0; }
.header .logo-container { display: flex; align-items: center; gap: 8px; text-decoration: none; color: inherit; }
.header .site-brand { font-family: 'Playfair Display', Georgia, 'Times New Roman', serif; font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0; }
.header .subtitle { font-size: 8px; color: var(--primary-gold-dark); font-weight: 600; letter-spacing: 1.4px; text-transform: uppercase; }
.nav-right { display: flex; align-items: center; gap: 4px; }
.container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 16px; }
#hero { text-align: center; padding: 24px 12px; }
.rate-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; margin: 16px auto; max-width: 600px; box-shadow: var(--shadow-sm); }
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" href="css/style.css?v=<?= $cjAssetVer ?>" as="style">
<link rel="stylesheet" href="css/style.css?v=<?= $cjAssetVer ?>">
<link rel="stylesheet" href="css/tailwind.min.css?v=<?= $cjAssetVer ?>">
<link rel="stylesheet" href="css/admin-theme.css?v=<?= $cjAssetVer ?>">
<link rel="stylesheet" href="<?= htmlspecialchars($cjFontUrl, ENT_QUOTES, 'UTF-8') ?>">
<?php include __DIR__ . '/theme-boot.php'; ?>
