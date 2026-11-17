<?php
/**
 * Legacy URL — redirects into Super Admin data browser.
 * Superadmin only.
 */
require_once 'config/db.php';
require_once 'includes/auth.php';

cj_auth_bootstrap($conn);
if (empty($_SESSION['loggedin']) || ($_SESSION['role'] ?? '') !== 'superadmin') {
    header("HTTP/1.1 404 Not Found");
    echo "<h1>404 Not Found</h1>";
    echo "The page you requested could not be found.";
    exit();
}

$tab = $_GET['tab'] ?? 'products';
header('Location: superadmin.php?tab=data&data_tab=' . urlencode((string)$tab));
exit;
