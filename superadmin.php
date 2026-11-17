<?php
/**
 * Canonical redirect for legacy /superadmin.php URL to /admin/superadmin.php.
 */
header('Location: /admin/superadmin.php', true, 301);
exit;
