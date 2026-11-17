<?php
// Safety rollback script for UI optimization
if (php_sapi_name() !== 'cli') {
    session_start();
    if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
        die('Unauthorized. Log in as admin to trigger rollback.');
    }
}

$source = __DIR__ . '/backup_ui_pre_opt';
$dest = __DIR__;

if (!is_dir($source)) {
    die("Backup directory not found: $source");
}

function recursiveCopy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                recursiveCopy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

recursiveCopy($source, $dest);
echo "Rollback complete. Original files restored from backup_ui_pre_opt.\n";
