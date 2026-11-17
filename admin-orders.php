<?php
/**
 * Canonical redirect for legacy /admin-orders.php URL to /admin/orders.php.
 */
header('Location: /admin/orders.php', true, 301);
exit;
