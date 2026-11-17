<?php
/**
 * Shared meta for private admin login screens — not for public indexing.
 * @var string $cj_login_page_title
 * @var string $cj_login_page_description
 */
$cj_login_page_title = $cj_login_page_title ?? 'Store Admin — ChandraKala Jewellers';
$cj_login_page_description = $cj_login_page_description
    ?? 'Private store admin access for ChandraKala Jewellers (Khedbrahma). Not a customer login. Do not enter Google, bank, or other site passwords here.';
?>
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">
<meta name="referrer" content="no-referrer">
<meta name="description" content="<?= htmlspecialchars($cj_login_page_description, ENT_QUOTES, 'UTF-8') ?>">
<title><?= htmlspecialchars($cj_login_page_title, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="canonical" href="https://chandrakalajewellers.in/">
