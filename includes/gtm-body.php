<?php
require_once __DIR__ . '/settings.php';
$seoConfig = @include __DIR__ . '/../config/seo-config.php';
if (!is_array($seoConfig)) {
    $seoConfig = [];
}
$gtmId = htmlspecialchars(cj_setting('gtm_id', (string) ($seoConfig['gtm_id'] ?? '')), ENT_QUOTES, 'UTF-8');
if ($gtmId === '') {
    return;
}
?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= $gtmId ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
