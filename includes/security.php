<?php
/**
 * Site-wide security bootstrap: session cookies + HTTP security headers.
 * Safe to include multiple times.
 *
 * Note: InfinityFree / many shared hosts ignore Apache Header directives,
 * so PHP header() is the reliable path for securityheaders.com grades.
 */

if (!function_exists('cj_security_csp')) {
    function cj_security_csp(): string
    {
        // Practical CSP: allows GTM, Tailwind CDN, Google Fonts, Font Awesome, WhatsApp flows.
        // connect-src includes fonts.googleapis.com so SW/document fetches of CSS are allowed.
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self' https://wa.me https://api.whatsapp.com https://chandrakalajewellers.in https://www.chandrakalajewellers.in",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://www.googletagmanager.com https://www.google-analytics.com https://cdnjs.cloudflare.com https://tagmanager.google.com https://unpkg.com https://checkout.razorpay.com https://cdn.razorpay.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://tagmanager.google.com https://unpkg.com",
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com https://www.google-analytics.com https://www.googletagmanager.com https://region1.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com https://maps.googleapis.com https://maps.gstatic.com https://nominatim.openstreetmap.org https://fcm.googleapis.com https://updates.push.services.mozilla.com https://*.notify.windows.com https://*.wns.windows.com https://*.push.apple.com https://api.razorpay.com https://lumberjack.razorpay.com https://lumberjack-cx.razorpay.com https://checkout.razorpay.com https://*.razorpay.com",
            "frame-src 'self' https://www.googletagmanager.com https://www.google.com https://maps.google.com https://www.google.com/maps https://maps.googleapis.com https://www.youtube.com https://www.youtube-nocookie.com https://api.razorpay.com https://checkout.razorpay.com https://*.razorpay.com",
            "worker-src 'self'",
            "manifest-src 'self'",
        ];

        if (cj_is_https()) {
            $directives[] = "upgrade-insecure-requests";
        }

        return implode('; ', $directives);
    }
}

if (!function_exists('cj_is_local_host')) {
    /** XAMPP / local dev hosts — never force Secure cookies or HTTPS redirects. */
    function cj_is_local_host(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?: $host;
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || (isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === '127.0.0.1');
    }
}

if (!function_exists('cj_is_https')) {
    function cj_is_https(): bool
    {
        // Local HTTP must never be treated as HTTPS (Secure cookies would not stick).
        if (cj_is_local_host()) {
            return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        }

        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    }
}

if (!function_exists('cj_session_cookie_params')) {
    /** @return array{lifetime:int,path:string,secure:bool,httponly:bool,samesite:string} */
    function cj_session_cookie_params(): array
    {
        return [
            'lifetime' => 0,
            'path' => '/',
            'secure' => cj_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (!function_exists('cj_configure_session')) {
    function cj_configure_session(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $params = cj_session_cookie_params();
        $secure = $params['secure'];

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        // Avoid session ID in URLs if cookies are blocked; prefer cookie-only auth.
        ini_set('session.cookie_path', '/');

        session_set_cookie_params($params);
    }
}

if (!function_exists('cj_persist_session_cookie')) {
    /**
     * Re-send the session cookie after regenerate_id so hosts that drop the
     * implicit Set-Cookie still keep the login on the following redirect GET.
     */
    function cj_persist_session_cookie(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }
        $params = cj_session_cookie_params();
        $options = [
            'expires' => $params['lifetime'] > 0 ? time() + $params['lifetime'] : 0,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ];
        setcookie(session_name(), session_id(), $options);
    }
}

if (!function_exists('cj_send_security_headers')) {
    function cj_send_security_headers(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('Content-Security-Policy: ' . cj_security_csp());

        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(self), gyroscope=(), magnetometer=(), microphone=(), payment=(self), usb=(), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
        header('X-Permitted-Cross-Domain-Policies: none');

        if (cj_is_https()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        header_remove('X-Powered-By');
    }
}

if (!function_exists('cj_admin_noindex_headers')) {
    /** Keep admin/login portals out of search indexes (reduces phishing false positives). */
    function cj_admin_noindex_headers(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
    }
}

if (!function_exists('cj_auth_secret_key')) {
    function cj_auth_secret_key(): string
    {
        $pass = function_exists('cj_env') ? (string)(cj_env('SESSION_SECRET') ?? cj_env('CJ_DB_PASS') ?? '') : '';
        return hash('sha256', 'cj_signed_auth_token_key_' . $pass);
    }
}

if (!function_exists('cj_issue_auth_cookie')) {
    /**
     * Issues an HMAC-SHA256 signed stateless authentication cookie.
     * Prevents session loss across serverless container restarts or cold starts.
     */
    function cj_issue_auth_cookie(array $user, int $lifetime = 604800): void
    {
        if (headers_sent()) {
            return;
        }
        $payload = [
            'user_id' => (int) ($user['id'] ?? $user['user_id'] ?? 0),
            'username' => (string) ($user['username'] ?? ''),
            'role' => (string) ($user['role'] ?? 'admin'),
            'display_name' => (string) ($user['display_name'] ?? $user['username'] ?? 'Admin'),
            'exp' => time() + $lifetime,
        ];
        $b64 = base64_encode(json_encode($payload));
        $sig = hash_hmac('sha256', $b64, cj_auth_secret_key());
        $val = $b64 . '.' . $sig;

        $params = cj_session_cookie_params();
        $options = [
            'expires' => time() + $lifetime,
            'path' => '/',
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie('cj_admin_jwt', $val, $options);
        $_COOKIE['cj_admin_jwt'] = $val;
    }
}

if (!function_exists('cj_clear_auth_cookie')) {
    /** Clears the signed authentication cookie on signout. */
    function cj_clear_auth_cookie(): void
    {
        if (headers_sent()) {
            return;
        }
        $params = cj_session_cookie_params();
        $options = [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie('cj_admin_jwt', '', $options);
        unset($_COOKIE['cj_admin_jwt']);
    }
}

if (!function_exists('cj_restore_auth_from_cookie')) {
    /** Restores session state from HMAC-signed cookie if PHP session expired or cold-started. */
    function cj_restore_auth_from_cookie(): bool
    {
        $raw = (string) ($_COOKIE['cj_admin_jwt'] ?? '');
        if ($raw === '' || strpos($raw, '.') === false) {
            return false;
        }
        [$b64, $sig] = explode('.', $raw, 2);
        $expected = hash_hmac('sha256', $b64, cj_auth_secret_key());
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        $json = base64_decode($b64, true);
        if (!$json) {
            return false;
        }
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['exp']) || (int) $data['exp'] < time()) {
            return false;
        }

        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = (int) ($data['user_id'] ?? 0);
        $_SESSION['username'] = (string) ($data['username'] ?? 'admin');
        $_SESSION['role'] = (string) ($data['role'] ?? 'admin');
        $_SESSION['display_name'] = (string) ($data['display_name'] ?? 'Admin');
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return true;
    }
}

if (!function_exists('cj_security_bootstrap')) {
    function cj_security_bootstrap(): void
    {
        cj_configure_session();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['loggedin'])) {
            cj_restore_auth_from_cookie();
        }
        cj_send_security_headers();
    }
}

cj_security_bootstrap();
