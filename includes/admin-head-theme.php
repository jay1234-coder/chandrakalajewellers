<?php
/**
 * Shared head bits for admin/superadmin/notifications pages.
 */
$cj_admin_theme_css = (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin') !== false) ? '../css/admin-theme.css' : 'css/admin-theme.css';
?>
<?php include __DIR__ . '/theme-boot.php'; ?>
<link rel="stylesheet" href="<?= $cj_admin_theme_css ?>">
