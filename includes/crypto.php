<?php
/**
 * ChandraKala Jewellers — Cryptographic Security & URL Privacy Subsystem
 *
 * Provides:
 *  1. Purpose-separated authenticated encryption (AES-256-GCM / HMAC-SHA256).
 *  2. Opaque Query Sealing (cj_seal / cj_unseal): hides sensitive filters (phone,
 *     search terms, customer IDs) from the top browser address bar, browser history,
 *     proxy logs, and shoulder surfers.
 *  3. Dynamic PII Masking: displays customer mobile numbers and emails safely in the UI.
 *  4. Transparent query bootstrap with automatic client-side History API URL cleansing.
 */

declare(strict_types=1);

require_once __DIR__ . '/security.php';

if (!function_exists('cj_crypto_key')) {
    /**
     * Derives a 256-bit cryptographically strong, purpose-separated subkey
     * using HKDF with HMAC-SHA256 from the master secret.
     */
    function cj_crypto_key(string $purpose = 'general'): string
    {
        $master = function_exists('cj_auth_secret_key') ? cj_auth_secret_key() : 'cj_default_fallback_secret_key';
        if (function_exists('hash_hkdf')) {
            return hash_hkdf('sha256', $master, 32, 'cj_crypto_v1:' . $purpose, 'chandrakala_salt');
        }
        return hash_hmac('sha256', 'cj_crypto_v1:' . $purpose, $master, true);
    }
}

if (!function_exists('cj_seal')) {
    /**
     * Seals any data (array, string, int) into an opaque, tamper-proof,
     * URL-safe Base64 token using AES-256-GCM authenticated encryption.
     *
     * @param mixed  $payload Data to encrypt and seal
     * @param string $purpose Key separation context (e.g. 'admin_filter', 'orders_query')
     * @param int    $ttl     Time-to-live in seconds (default: 7 days)
     * @return string URL-safe Base64 token
     */
    function cj_seal(mixed $payload, string $purpose = 'query', int $ttl = 604800): string
    {
        $envelope = [
            'd'   => $payload,
            'exp' => time() + max(60, $ttl),
            'p'   => $purpose,
            'rnd' => bin2hex(random_bytes(6)), // Random salt guarantees unique ciphertexts for identical inputs
        ];
        $json = (string) json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $key = cj_crypto_key($purpose);

        // Prefer AES-256-GCM (Authenticated Encryption with Associated Data)
        if (in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($json, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $purpose);
            if ($cipher === false || strlen($tag) !== 16) {
                return '';
            }
            // Format: 'g' (1 byte) + IV (12 bytes) + Tag (16 bytes) + Ciphertext
            $binary = 'g' . $iv . $tag . $cipher;
        } else {
            // Fallback: AES-256-CBC with HMAC-SHA256 Encrypt-then-MAC
            $iv = random_bytes(16);
            $cipher = openssl_encrypt($json, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            if ($cipher === false) {
                return '';
            }
            $hmac = hash_hmac('sha256', $iv . $cipher . $purpose, $key, true);
            // Format: 'c' (1 byte) + IV (16 bytes) + HMAC (32 bytes) + Ciphertext
            $binary = 'c' . $iv . $hmac . $cipher;
        }

        // Convert to URL-safe Base64
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}

if (!function_exists('cj_unseal')) {
    /**
     * Verifies the authenticity, purpose, and timestamp of a sealed token,
     * decrypts the payload, and returns the original data.
     *
     * Returns null if the token has expired, been tampered with, or was sealed
     * for a different purpose.
     */
    function cj_unseal(string $token, string $purpose = 'query'): mixed
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 32) {
            return null;
        }

        $b64 = strtr($token, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $binary = base64_decode($b64, true);
        if ($binary === false || strlen($binary) < 30) {
            return null;
        }

        $scheme = $binary[0];
        $key = cj_crypto_key($purpose);

        if ($scheme === 'g') {
            // AES-256-GCM: 1 byte scheme + 12 bytes IV + 16 bytes Tag + Ciphertext
            if (strlen($binary) < 29) {
                return null;
            }
            $iv = substr($binary, 1, 12);
            $tag = substr($binary, 13, 16);
            $cipher = substr($binary, 29);
            $json = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $purpose);
        } elseif ($scheme === 'c') {
            // AES-256-CBC: 1 byte scheme + 16 bytes IV + 32 bytes HMAC + Ciphertext
            if (strlen($binary) < 49) {
                return null;
            }
            $iv = substr($binary, 1, 16);
            $hmac = substr($binary, 17, 32);
            $cipher = substr($binary, 49);
            $expected = hash_hmac('sha256', $iv . $cipher . $purpose, $key, true);
            if (!hash_equals($expected, $hmac)) {
                return null;
            }
            $json = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        } else {
            return null;
        }

        if ($json === false || $json === '') {
            return null;
        }

        $envelope = json_decode($json, true);
        if (!is_array($envelope) || empty($envelope['exp']) || (int)$envelope['exp'] < time()) {
            return null;
        }
        if (($envelope['p'] ?? '') !== $purpose) {
            return null;
        }

        return $envelope['d'] ?? null;
    }
}

if (!function_exists('cj_mask_phone')) {
    /**
     * Masks a customer's phone number for UI display to prevent shoulder-surfing.
     * Example: '9876543210' -> '98****3210', or '+91 9876543210' -> '+91 98****3210'
     */
    function cj_mask_phone(string $phone, bool $fullMask = false): string
    {
        $raw = trim($phone);
        if ($raw === '') {
            return '—';
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) < 7) {
            return '***' . substr($digits, -2);
        }
        if ($fullMask) {
            return substr($digits, 0, 2) . '******' . substr($digits, -2);
        }
        $prefix = substr($digits, 0, 2);
        $suffix = substr($digits, -4);
        $mask = str_repeat('*', max(4, strlen($digits) - 6));
        return $prefix . $mask . $suffix;
    }
}

if (!function_exists('cj_mask_email')) {
    /**
     * Masks an email address for UI display.
     * Example: 'poojapatel@gmail.com' -> 'p***l@gmail.com'
     */
    function cj_mask_email(string $email): string
    {
        $email = trim($email);
        if ($email === '' || strpos($email, '@') === false) {
            return $email;
        }
        [$local, $domain] = explode('@', $email, 2);
        $len = mb_strlen($local);
        if ($len <= 2) {
            $maskedLocal = substr($local, 0, 1) . '*';
        } else {
            $maskedLocal = mb_substr($local, 0, 1) . str_repeat('*', min(4, $len - 2)) . mb_substr($local, -1);
        }
        return $maskedLocal . '@' . $domain;
    }
}

if (!function_exists('cj_bootstrap_encrypted_query')) {
    /**
     * Decrypts any sealed query token (?f=...) and merges it into $_GET.
     * If sensitive plain-text parameters are detected in $_GET, calculates the
     * sealed token and returns a clean URL for instant History API masking.
     *
     * @param string   $purpose       Cryptographic purpose context
     * @param string[] $sensitiveKeys Keys that should not remain in plain text in the URL
     * @return array{unsealed:bool,clean_url:string|null,token:string|null}
     */
    function cj_bootstrap_encrypted_query(string $purpose, array $sensitiveKeys = ['phone', 'search', 'email', 'q', 'customer_id']): array
    {
        $unsealed = false;
        $token = null;

        // 1. Unseal encrypted token if provided
        if (!empty($_GET['f']) && is_string($_GET['f'])) {
            $unpacked = cj_unseal($_GET['f'], $purpose);
            if (is_array($unpacked)) {
                $unsealed = true;
                $token = $_GET['f'];
                foreach ($unpacked as $k => $v) {
                    if (!isset($_GET[$k])) {
                        $_GET[$k] = $v;
                    }
                }
            }
        }

        // 2. Check if sensitive parameters exist in plain text in $_GET
        $hasSensitive = false;
        $paramsToSeal = [];
        foreach ($_GET as $k => $v) {
            if ($k === 'f') {
                continue;
            }
            $paramsToSeal[$k] = $v;
            if (in_array($k, $sensitiveKeys, true) && (string)$v !== '') {
                $hasSensitive = true;
            }
        }

        $cleanUrl = null;
        if ($hasSensitive) {
            // Encrypt all query parameters into an opaque token
            $newToken = cj_seal($paramsToSeal, $purpose);
            $token = $newToken;
            $currentPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
            $embed = !empty($_GET['embed']) ? '&embed=1' : '';
            $cleanUrl = $currentPath . '?f=' . $newToken . $embed;
        }

        return [
            'unsealed'  => $unsealed,
            'clean_url' => $cleanUrl,
            'token'     => $token,
        ];
    }
}

if (!function_exists('cj_render_url_mask_script')) {
    /**
     * Outputs an instant client-side script that cleanses the browser's top address bar
     * using history.replaceState, preventing sensitive queries from remaining in browser history.
     */
    function cj_render_url_mask_script(?string $targetUrl = null): void
    {
        $safeUrl = $targetUrl ? json_encode($targetUrl, JSON_HEX_TAG | JSON_HEX_AMP) : 'null';
        echo '<script>
        (function() {
            var target = ' . $safeUrl . ';
            if (target && window.history && window.history.replaceState) {
                try { window.history.replaceState(null, document.title, target); } catch (e) {}
            }
        })();
        </script>';
    }
}
