<?php
/**
 * Canonical redirect for legacy /notifications.php URL to /admin/notifications.php.
 */
header('Location: /admin/notifications.php', true, 301);
exit;
