<?php
/**
 * Canonical redirect for legacy /manual_rate.php URL to /admin/manual_rate.php.
 */
header('Location: /admin/manual_rate.php', true, 301);
exit;
