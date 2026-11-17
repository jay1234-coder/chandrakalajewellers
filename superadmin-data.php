<?php
/**
 * Canonical redirect for legacy /superadmin-data.php URL to /admin/superadmin-data.php.
 */
header('Location: /admin/superadmin-data.php', true, 301);
exit;
