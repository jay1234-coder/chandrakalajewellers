<?php
/**
 * Canonical redirect for legacy /delete_c.php URL to /admin/delete_c.php.
 */
header('Location: /admin/delete_c.php', true, 301);
exit;
