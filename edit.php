<?php
/**
 * Canonical redirect for legacy /edit.php URL to /admin/edit.php.
 */
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: /admin/edit.php' . $qs, true, 301);
exit;
