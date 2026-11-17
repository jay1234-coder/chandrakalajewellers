<?php
/**
 * Canonical redirect for legacy /admin.php URL to /admin/index.php.
 */
header('Location: /admin/index.php', true, 301);
exit;
