<?php
/**
 * One-time VAPID key generator for Web Push.
 * Run: php tools/generate-vapid.php
 */
$outDir = dirname(__DIR__) . '/config';
@mkdir($outDir, 0777, true);
$outFile = $outDir . '/vapid.php';

if (file_exists($outFile)) {
    echo "vapid.php already exists — not overwriting.\n";
    exit(0);
}

$key = openssl_pkey_new([
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
]);
if (!$key) {
    fwrite(STDERR, "OpenSSL EC key generation failed\n");
    exit(1);
}

$details = openssl_pkey_get_details($key);
$pub = $details['ec']['x'] . $details['ec']['y']; // wrong - need uncompressed point

// Proper uncompressed public key: 0x04 || x || y
$x = $details['ec']['x'];
$y = $details['ec']['y'];
$pubBin = "\x04" . $x . $y;

openssl_pkey_export($key, $privPem);

function b64url($bin) {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

$publicKey = b64url($pubBin);
// Store PEM private key and also derive raw d for signing
$d = $details['ec']['d'];
$privateKey = b64url($d);

$php = "<?php\nreturn [\n"
    . "    'subject' => 'mailto:chandrakalajewellers849@gmail.com',\n"
    . "    'publicKey' => " . var_export($publicKey, true) . ",\n"
    . "    'privateKey' => " . var_export($privateKey, true) . ",\n"
    . "    'privateKeyPem' => " . var_export($privPem, true) . ",\n"
    . "];\n";

file_put_contents($outFile, $php);
echo "Wrote {$outFile}\n";
echo "Public: {$publicKey}\n";
