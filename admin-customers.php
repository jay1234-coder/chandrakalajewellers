<?php
/**
 * Canonical redirect for legacy /admin-customers.php URL to /admin/customers.php.
 */
header('Location: /admin/customers.php', true, 301);
exit;
