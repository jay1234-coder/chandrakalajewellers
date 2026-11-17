<?php
/**
 * Generate / repair VAPID keys for Web Push.
 * Open while logged in as admin or superadmin:
 *   /api/setup-vapid.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

cj_auth_bootstrap($conn);
if (empty($_SESSION['loggedin']) || !in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Login as admin or superadmin first, then open this URL.']);
    exit;
}

$force = isset($_GET['force']) && $_GET['force'] === '1';
$outFile = dirname(__DIR__) . '/config/vapid.php';

if (!$force && is_file($outFile)) {
    $existing = include $outFile;
    if (is_array($existing) && !empty($existing['publicKey']) && strlen((string)$existing['publicKey']) > 80) {
        echo json_encode([
            'ok' => true,
            'status' => 'exists',
            'publicKeyLength' => strlen((string)$existing['publicKey']),
            'message' => 'VAPID keys already present. Add ?force=1 to regenerate.',
        ]);
        exit;
    }
}

// Help OpenSSL find config on some Windows / shared hosts
if (empty(getenv('OPENSSL_CONF'))) {
    $candidates = [
        'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
        'C:\\xampp\\apache\\conf\\openssl.cnf',
        '/etc/ssl/openssl.cnf',
    ];
    foreach ($candidates as $c) {
        if (is_file($c)) {
            putenv('OPENSSL_CONF=' . $c);
            break;
        }
    }
}

$key = @openssl_pkey_new([
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
]);
if (!$key) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'OpenSSL could not generate EC keys on this server.',
        'openssl_error' => openssl_error_string() ?: null,
    ]);
    exit;
}

$details = openssl_pkey_get_details($key);
if (!$details || empty($details['ec']['x']) || empty($details['ec']['y']) || empty($details['ec']['d'])) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'OpenSSL returned incomplete EC key details.']);
    exit;
}

openssl_pkey_export($key, $privPem);
$pubBin = "\x04" . $details['ec']['x'] . $details['ec']['y'];
$b64url = static function ($bin) {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
};

$cfg = [
    'subject' => 'mailto:chandrakalajewellers849@gmail.com',
    'publicKey' => $b64url($pubBin),
    'privateKey' => $b64url($details['ec']['d']),
    'privateKeyPem' => $privPem,
];

@mkdir(dirname($outFile), 0777, true);
$written = @file_put_contents(
    $outFile,
    "<?php\nreturn " . var_export($cfg, true) . ";\n"
);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not write config/vapid.php — check folder permissions.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'status' => 'created',
    'publicKeyLength' => strlen($cfg['publicKey']),
    'message' => 'VAPID keys ready. Go back and tap Allow / Enable push again.',
]);
