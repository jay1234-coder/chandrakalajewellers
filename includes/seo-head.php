<?php
/**
 * Outputs SEO / GEO / AEO meta tags + JSON-LD.
 * Requires $seo array (use cj_merge_seo()). Include after charset/viewport if already set, or alone.
 */
require_once __DIR__ . '/seo.php';

if (!isset($seo) || !is_array($seo)) {
    $seo = cj_default_seo();
} else {
    $seo = array_merge(cj_default_seo(), $seo);
}

$title = htmlspecialchars($seo['title'], ENT_QUOTES, 'UTF-8');
$rawDescription = function_exists('cj_normalize_meta_description')
    ? cj_normalize_meta_description((string)($seo['description'] ?? ''))
    : (string)($seo['description'] ?? '');
$description = htmlspecialchars($rawDescription, ENT_QUOTES, 'UTF-8');
$keywords = htmlspecialchars($seo['keywords'], ENT_QUOTES, 'UTF-8');
$canonical = htmlspecialchars($seo['canonical'], ENT_QUOTES, 'UTF-8');
$robots = htmlspecialchars($seo['robots'], ENT_QUOTES, 'UTF-8');
$ogType = htmlspecialchars($seo['og_type'], ENT_QUOTES, 'UTF-8');
$ogImage = htmlspecialchars($seo['og_image'], ENT_QUOTES, 'UTF-8');
$twitterCard = htmlspecialchars($seo['twitter_card'], ENT_QUOTES, 'UTF-8');
$locale = htmlspecialchars($seo['locale'], ENT_QUOTES, 'UTF-8');
$geoRegion = htmlspecialchars($seo['geo_region'], ENT_QUOTES, 'UTF-8');
$geoPlace = htmlspecialchars($seo['geo_placename'], ENT_QUOTES, 'UTF-8');
$geoPos = htmlspecialchars($seo['geo_position'], ENT_QUOTES, 'UTF-8');
$siteName = 'ChandraKala Jewellers';

$seoConfig = @include __DIR__ . '/../config/seo-config.php';
if (!is_array($seoConfig)) {
    $seoConfig = [];
}
require_once __DIR__ . '/settings.php';
$seoConfig['google_site_verification'] = cj_setting('google_site_verification', (string) ($seoConfig['google_site_verification'] ?? ''));
$seoConfig['bing_site_verification'] = cj_setting('bing_site_verification', (string) ($seoConfig['bing_site_verification'] ?? ''));
$seoConfig['yandex_verification'] = cj_setting('yandex_verification', (string) ($seoConfig['yandex_verification'] ?? ''));
$seoConfig['ga4_id'] = cj_setting('ga4_id', (string) ($seoConfig['ga4_id'] ?? ''));

if (!defined('CJ_CHARSET_SET')) {
    define('CJ_CHARSET_SET', true);
    echo '<meta charset="UTF-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover, shrink-to-fit=no">' . "\n";
}

if (!empty($seoConfig['google_site_verification'])) {
    echo '<meta name="google-site-verification" content="' . htmlspecialchars($seoConfig['google_site_verification'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
if (!empty($seoConfig['bing_site_verification'])) {
    echo '<meta name="msvalidate.01" content="' . htmlspecialchars($seoConfig['bing_site_verification'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
if (!empty($seoConfig['yandex_verification'])) {
    echo '<meta name="yandex-verification" content="' . htmlspecialchars($seoConfig['yandex_verification'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
}
if (!empty($seoConfig['ga4_id'])) {
    $ga4Id = htmlspecialchars($seoConfig['ga4_id'], ENT_QUOTES, 'UTF-8');
    echo '<!-- Google Analytics (gtag.js) -->' . "\n";
    echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $ga4Id . '"></script>' . "\n";
    echo '<script>' . "\n";
    echo '  window.dataLayer = window.dataLayer || [];' . "\n";
    echo '  function gtag(){dataLayer.push(arguments);}' . "\n";
    echo '  gtag("js", new Date());' . "\n";
    echo '  gtag("config", "' . $ga4Id . '");' . "\n";
    echo '</script>' . "\n";
}
?>
<?php include __DIR__ . '/gtm-head.php'; ?>
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta name="theme-color" content="#b8972c">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ChandraKala">
<meta name="mobile-web-app-capable" content="yes">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="apple-touch-icon" href="/images/icons/icon-192.png">
<title><?= $title ?></title>
<meta name="description" content="<?= $description ?>">
<meta name="keywords" content="<?= $keywords ?>">
<meta name="author" content="ChandraKala Jewellers">
<meta http-equiv="content-language" content="en-IN">
<meta name="language" content="English">
<meta name="robots" content="<?= $robots ?>">
<meta name="googlebot" content="<?= $robots ?>">
<meta name="bingbot" content="<?= $robots ?>">
<meta name="GPTBot" content="index, follow">
<meta name="OAI-SearchBot" content="index, follow">
<meta name="PerplexityBot" content="index, follow">
<meta name="ClaudeBot" content="index, follow">
<meta name="Google-Extended" content="index, follow">
<meta name="yandex" content="index, follow">
<meta name="yahoo" content="index, follow">
<meta name="duckduckbot" content="index, follow">
<meta name="applebot" content="index, follow">
<meta name="revisit-after" content="1 days">
<link rel="canonical" href="<?= $canonical ?>">
<?php
$cjModifiedIso = !empty($seo['modified_iso'])
    ? (string)$seo['modified_iso']
    : (function_exists('cj_format_ist_iso') ? cj_format_ist_iso() : date('c'));
?>
<meta property="og:updated_time" content="<?= htmlspecialchars($cjModifiedIso, ENT_QUOTES, 'UTF-8') ?>">
<meta property="article:modified_time" content="<?= htmlspecialchars($cjModifiedIso, ENT_QUOTES, 'UTF-8') ?>">

<!-- Local / GEO -->
<meta name="geo.region" content="<?= $geoRegion ?>">
<meta name="geo.placename" content="<?= $geoPlace ?>">
<meta name="geo.position" content="<?= $geoPos ?>">
<meta name="ICBM" content="<?= str_replace(';', ', ', $geoPos) ?>">
<meta name="rating" content="general">
<meta name="distribution" content="global">
<meta name="coverage" content="Khedbrahma, Sabarkantha, Idar, Himmatnagar, Vadali, Ambaji, North Gujarat, Gujarat, India">
<meta name="target" content="all">
<meta name="HandheldFriendly" content="true">
<meta name="format-detection" content="telephone=yes">

<!-- Open Graph -->
<meta property="og:type" content="<?= $ogType ?>">
<meta property="og:site_name" content="<?= $siteName ?>">
<meta property="og:locale" content="<?= $locale ?>">
<meta property="og:locale:alternate" content="gu_IN">
<meta property="og:locale:alternate" content="hi_IN">
<meta property="og:title" content="<?= $title ?>">
<meta property="og:description" content="<?= $description ?>">
<meta property="og:url" content="<?= $canonical ?>">
<meta property="og:image" content="<?= $ogImage ?>">
<meta property="og:image:alt" content="ChandraKala Jewellers — Gold & Silver Jewellery in Khedbrahma">
<meta property="og:see_also" content="https://chandrakalajewellers.in/index1">
<meta property="business:contact_data:street_address" content="Opp. Bhoomi Complex, Civil Road">
<meta property="business:contact_data:locality" content="Khedbrahma">
<meta property="business:contact_data:region" content="Gujarat">
<meta property="business:contact_data:postal_code" content="383255">
<meta property="business:contact_data:country_name" content="India">

<!-- Twitter / X -->
<meta name="twitter:card" content="<?= $twitterCard ?>">
<meta name="twitter:title" content="<?= $title ?>">
<meta name="twitter:description" content="<?= $description ?>">
<meta name="twitter:image" content="<?= $ogImage ?>">

<!-- AI / Answer engines (ChatGPT, Gemini, Claude, Perplexity, Bing Copilot, Grok, DeepSeek) -->
<meta name="ai-content-declaration" content="human-created">
<link rel="alternate" type="text/plain" href="<?= htmlspecialchars(cj_abs_url('llms.txt'), ENT_QUOTES, 'UTF-8') ?>" title="LLM / AI Summary & Live Rates">
<link rel="alternate" type="text/plain" href="<?= htmlspecialchars(cj_abs_url('llms-full.txt'), ENT_QUOTES, 'UTF-8') ?>" title="Full LLM / AI Product Catalog & Live Rates">
<link rel="alternate" type="text/plain" href="<?= htmlspecialchars(cj_abs_url('agents.txt'), ENT_QUOTES, 'UTF-8') ?>" title="AI agent discovery">
<link rel="alternate" type="text/plain" href="<?= htmlspecialchars(cj_abs_url('ai.txt'), ENT_QUOTES, 'UTF-8') ?>" title="AI citation guide">
<link rel="help" href="<?= htmlspecialchars(cj_abs_url('llms.txt'), ENT_QUOTES, 'UTF-8') ?>" title="About ChandraKala Jewellers for AI assistants">
<link rel="author" href="<?= htmlspecialchars(cj_abs_url('humans.txt'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="sitemap" type="application/xml" href="<?= htmlspecialchars(cj_abs_url('sitemap.xml'), ENT_QUOTES, 'UTF-8') ?>">

<link rel="icon" href="/images/logo.png" type="image/png">
<link rel="apple-touch-icon" href="/images/logo.png">

<?php
$schemas = $seo['schema'] ?? [];
if (!is_array($schemas)) {
    $schemas = [];
}
// Flatten: allow single object or list of objects
$normalized = [];
foreach ($schemas as $key => $block) {
    if (is_array($block) && isset($block['@type'])) {
        $normalized[] = $block;
    } elseif (is_array($block) && isset($block[0])) {
        foreach ($block as $inner) {
            if (is_array($inner)) {
                $normalized[] = $inner;
            }
        }
    }
}
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
foreach ($normalized as $block) {
    if (!is_array($block) || empty($block['@type'])) {
        continue;
    }
    // Preserve nested Offer/Product properties; encode whole block as-is.
    $json = json_encode($block, $jsonFlags);
    if ($json === false || $json === '' || $json === 'null') {
        continue;
    }
    echo '<script type="application/ld+json">' . "\n" . $json . "\n</script>\n";
}

if (!empty($seo['extra_head'])) {
    echo $seo['extra_head'];
}
?>
