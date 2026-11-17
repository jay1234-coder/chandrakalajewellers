<?php
/**
 * VAPID key loader / auto-generator for Web Push (empty payload + VAPID auth).
 */
if (!function_exists('cj_vapid_config')) {
    function cj_vapid_config(): array
    {
        static $cfg = null;
        if ($cfg !== null) return $cfg;

        $file = dirname(__DIR__) . '/config/vapid.php';
        if (is_file($file)) {
            $loaded = require $file;
            if (is_array($loaded) && !empty($loaded['publicKey']) && strlen((string)$loaded['publicKey']) > 80) {
                $cfg = $loaded;
                return $cfg;
            }
        }

        // Help OpenSSL on XAMPP / some hosts
        if (empty(getenv('OPENSSL_CONF'))) {
            foreach ([
                'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
                'C:\\xampp\\apache\\conf\\openssl.cnf',
                '/etc/ssl/openssl.cnf',
            ] as $c) {
                if (is_file($c)) {
                    putenv('OPENSSL_CONF=' . $c);
                    break;
                }
            }
        }

        // Try generate once
        $key = @openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($key) {
            $details = openssl_pkey_get_details($key);
            if (!empty($details['ec']['x']) && !empty($details['ec']['y']) && !empty($details['ec']['d'])) {
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
                @mkdir(dirname($file), 0777, true);
                @file_put_contents($file, "<?php\nreturn " . var_export($cfg, true) . ";\n");
                return $cfg;
            }
        }

        // Fallback placeholder — push send will no-op until real keys exist
        $cfg = [
            'subject' => 'mailto:chandrakalajewellers849@gmail.com',
            'publicKey' => '',
            'privateKey' => '',
            'privateKeyPem' => '',
        ];
        return $cfg;
    }
}

if (!function_exists('cj_b64url_decode')) {
    function cj_b64url_decode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return (string)base64_decode(strtr($data, '-_', '+/'));
    }
}

if (!function_exists('cj_b64url_encode')) {
    function cj_b64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('cj_vapid_jwt')) {
    function cj_vapid_jwt(string $audience, array $vapid): string
    {
        $header = cj_b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = cj_b64url_encode(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => $vapid['subject'] ?? 'mailto:chandrakalajewellers849@gmail.com',
        ]));
        $data = $header . '.' . $payload;

        $pem = $vapid['privateKeyPem'] ?? '';
        if ($pem === '') {
            return '';
        }
        $key = openssl_pkey_get_private($pem);
        if (!$key) {
            return '';
        }
        $sig = '';
        // OpenSSL signs as DER; Web Push needs raw R||S (64 bytes)
        openssl_sign($data, $der, $key, OPENSSL_ALGO_SHA256);
        $raw = cj_der_to_raw_ecdsa($der);
        return $data . '.' . cj_b64url_encode($raw);
    }
}

if (!function_exists('cj_der_to_raw_ecdsa')) {
    function cj_der_to_raw_ecdsa(string $der): string
    {
        // Minimal DER ECDSA decode to r||s (each 32 bytes)
        $pos = 0;
        if (ord($der[$pos++]) !== 0x30) return $der;
        $len = ord($der[$pos++]);
        if ($len & 0x80) {
            $n = $len & 0x7f;
            $pos += $n;
        }
        if (ord($der[$pos++]) !== 0x02) return $der;
        $rLen = ord($der[$pos++]);
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;
        if (ord($der[$pos++]) !== 0x02) return $der;
        $sLen = ord($der[$pos++]);
        $s = substr($der, $pos, $sLen);
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);
        return $r . $s;
    }
}
