<?php
/**
 * Legacy URL — redirects into Super Admin data browser.
 * Superadmin only.
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

cj_auth_bootstrap($conn);
if (empty($_SESSION['loggedin'])) {
    header('Location: /secret');
    exit();
}
if (($_SESSION['role'] ?? '') !== 'superadmin') {
    header('Location: /admin/index.php');
    exit();
}

$tab = $_GET['tab'] ?? 'products';
header('Location: /admin/superadmin.php?tab=data&data_tab=' . urlencode((string)$tab));
exit;
