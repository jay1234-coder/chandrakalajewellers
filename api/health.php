<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'php_version' => PHP_VERSION,
    'server_time' => date('c'),
    'writable_tmp' => is_writable('/tmp'),
    'session_save_path' => ini_get('session.save_path'),
    'extensions' => [
        'mysqli' => extension_loaded('mysqli'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'pdo_pgsql' => extension_loaded('pdo_pgsql'),
        'curl' => extension_loaded('curl'),
        'mbstring' => extension_loaded('mbstring'),
        'openssl' => extension_loaded('openssl'),
    ],
    'db_configured' => [
        'CJ_DB_HOST' => !empty(getenv('CJ_DB_HOST')),
        'POSTGRES_HOST' => !empty(getenv('POSTGRES_HOST')),
        'POSTGRES_URL' => !empty(getenv('POSTGRES_URL')),
    ]
], JSON_PRETTY_PRINT);
