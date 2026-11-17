<?php
/**
 * Site integrations stored in the database (Super Admin panel).
 * Empty DB values fall back to .env, then to built-in shop defaults.
 */
require_once __DIR__ . '/../config/env.php';

if (!function_exists('cj_settings_defaults')) {
    /** @return array<string,string> */
    function cj_settings_defaults(): array
    {
        return [
            'show_gold_rate' => '1',
            'show_silver_rate' => '1',
            'show_silver_925_rate' => '1',
            'show_copper_rate' => '1',
            'gemini_api_key' => '',
            'razorpay_key_id' => '',
            'razorpay_key_secret' => '',
            'razorpay_webhook_secret' => '',
            'whatsapp_number' => '919427080359',
            'shop_phone_display' => '+91 9427080359',
            'shop_email' => 'chandrakalajewellers849@gmail.com',
            'instagram_url' => 'https://www.instagram.com/chandra.kalajewellers?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==',
            'google_review_url' => 'https://g.page/r/CWqeb0I3tG0hEAI/review',
            'gtm_id' => 'GTM-TFC4WZRJ',
            'ga4_id' => '',
            'google_site_verification' => 'yDEeHSv9wZlveqPrUs_-gh0AeDIjgCZcVvw3wU2rw8A',
            'bing_site_verification' => '',
            'yandex_verification' => '',
            'cdn_base_url' => '',
            'reviews_auto_approve' => '0',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_from' => '',
            'smtp_secure' => 'tls',
            'feat_reviews' => '1',
            'feat_wishlist' => '1',
            'feat_related' => '1',
            'feat_zoom' => '1',
            'feat_ai_recs' => '1',
            'feat_abandoned_banner' => '1',
            'feat_account_prompt' => '1',
            'feat_customer_accounts' => '1',
            'feat_order_history' => '1',
            'feat_online_pay' => '1',
            'feat_refunds' => '1',
            'feat_product_specs' => '1',
            'feat_compare' => '1',
            'feat_gst' => '0',
            'feat_email_invoices' => '0',
            'perm_admin_refunds' => '0',
            'gst_percent' => '3',
            'gst_inclusive' => '1',
            'recs_limit' => '6',
            'rate_formula_json' => '{"gold":[{"op":"*","value":0.9166},{"op":"/","value":1.03}],"silver":[],"silver_925":[],"copper":[]}',
            'price_cache_stamp' => '',
        ];
    }
}

if (!function_exists('cj_settings_secret_keys')) {
    /** @return list<string> */
    function cj_settings_secret_keys(): array
    {
        return ['razorpay_key_secret', 'razorpay_webhook_secret', 'smtp_pass'];
    }
}

if (!function_exists('cj_ensure_settings_schema')) {
    function cj_ensure_settings_schema(?mysqli $conn = null): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $conn = $conn ?: (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null);
        if (!$conn) {
            return;
        }
        $done = true;
        $conn->query("CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(64) NOT NULL,
            setting_value TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $envMap = [
            'razorpay_key_id' => 'CJ_RAZORPAY_KEY_ID',
            'razorpay_key_secret' => 'CJ_RAZORPAY_KEY_SECRET',
            'razorpay_webhook_secret' => 'CJ_RAZORPAY_WEBHOOK_SECRET',
        ];
        foreach ($envMap as $key => $envName) {
            $existing = cj_setting_raw($conn, $key);
            if ($existing !== null) {
                continue;
            }
            $fromEnv = trim((string) cj_env($envName, ''));
            if ($fromEnv !== '') {
                cj_setting_write($conn, $key, $fromEnv);
            }
        }
    }
}

if (!function_exists('cj_setting_raw')) {
    function cj_setting_raw(mysqli $conn, string $key): ?string
    {
        $stmt = $conn->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        return (string) $row['setting_value'];
    }
}

if (!function_exists('cj_setting_write')) {
    function cj_setting_write(mysqli $conn, string $key, string $value): void
    {
        $stmt = $conn->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $stmt->close();
        unset($GLOBALS['cj_settings_cache']);
        @unlink(sys_get_temp_dir() . '/cj_settings.json');
    }
}

if (!function_exists('cj_settings_all')) {
    /** @return array<string,string> */
    function cj_settings_all(?mysqli $conn = null): array
    {
        if (isset($GLOBALS['cj_settings_cache']) && is_array($GLOBALS['cj_settings_cache'])) {
            return $GLOBALS['cj_settings_cache'];
        }

        $tmpDir = sys_get_temp_dir();
        $cacheFile = $tmpDir . '/cj_settings.json';
        if (is_file($cacheFile) && (time() - (int)@filemtime($cacheFile)) < 60) {
            $cached = @json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached)) {
                $GLOBALS['cj_settings_cache'] = $cached;
                return $cached;
            }
        }

        $out = cj_settings_defaults();
        $file = @include __DIR__ . '/../config/seo-config.php';
        if (is_array($file)) {
            foreach (['gtm_id', 'ga4_id', 'google_site_verification', 'bing_site_verification', 'yandex_verification'] as $k) {
                if (!empty($file[$k])) {
                    $out[$k] = (string) $file[$k];
                }
            }
        }
        $out['razorpay_key_id'] = trim((string) cj_env('CJ_RAZORPAY_KEY_ID', $out['razorpay_key_id']));
        $out['razorpay_key_secret'] = trim((string) cj_env('CJ_RAZORPAY_KEY_SECRET', $out['razorpay_key_secret']));
        $out['razorpay_webhook_secret'] = trim((string) cj_env('CJ_RAZORPAY_WEBHOOK_SECRET', $out['razorpay_webhook_secret']));

        $conn = $conn ?: (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli ? $GLOBALS['conn'] : null);
        if ($conn) {
            try {
                $res = $conn->query('SELECT setting_key, setting_value FROM site_settings');
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $k = (string) ($row['setting_key'] ?? '');
                        if ($k !== '' && array_key_exists($k, $out)) {
                            $out[$k] = (string) ($row['setting_value'] ?? '');
                        }
                    }
                }
            } catch (Throwable $ignore) {}
        }
        $GLOBALS['cj_settings_cache'] = $out;
        @file_put_contents($cacheFile, json_encode($out));
        return $out;
    }
}

if (!function_exists('cj_setting')) {
    function cj_setting(string $key, ?string $default = null): string
    {
        $all = cj_settings_all();
        if (array_key_exists($key, $all) && $all[$key] !== '') {
            return $all[$key];
        }
        if ($default !== null) {
            return $default;
        }
        $defaults = cj_settings_defaults();
        return (string) ($defaults[$key] ?? '');
    }
}

if (!function_exists('cj_setting_set')) {
    function cj_setting_set(mysqli $conn, string $key, string $value): void
    {
        cj_ensure_settings_schema($conn);
        $allowed = array_keys(cj_settings_defaults());
        if (!in_array($key, $allowed, true)) {
            return;
        }
        cj_setting_write($conn, $key, $value);
    }
}

if (!function_exists('cj_shop_whatsapp_digits')) {
    function cj_shop_whatsapp_digits(): string
    {
        $n = preg_replace('/\D+/', '', cj_setting('whatsapp_number', '919427080359')) ?? '';
        return $n !== '' ? $n : '919427080359';
    }
}

if (!function_exists('cj_shop_phone_display')) {
    function cj_shop_phone_display(): string
    {
        $d = trim(cj_setting('shop_phone_display', '+91 9427080359'));
        return $d !== '' ? $d : '+91 9427080359';
    }
}

if (!function_exists('cj_shop_tel_href')) {
    function cj_shop_tel_href(): string
    {
        $n = cj_shop_whatsapp_digits();
        if ($n[0] !== '+') {
            $n = '+' . $n;
        }
        return 'tel:' . $n;
    }
}

if (!function_exists('cj_settings_flag_keys')) {
    /** @return list<string> */
    function cj_settings_flag_keys(): array
    {
        $keys = [];
        foreach (cj_settings_defaults() as $key => $value) {
            if (str_starts_with($key, 'feat_') || str_starts_with($key, 'perm_') || $key === 'gst_inclusive' || $key === 'reviews_auto_approve') {
                $keys[] = $key;
            }
        }
        return $keys;
    }
}

if (!function_exists('cj_feature')) {
    function cj_feature(string $key): bool
    {
        $v = strtolower(trim(cj_setting($key, '0')));
        return $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
    }
}

if (!function_exists('cj_gst_compute')) {
    /**
     * Pure GST math (no DB). Used by checkout and tests.
     *
     * @return array{enabled:bool,rate:float,inclusive:bool,taxable:float,gst_amount:float,grand_total:float}
     */
    function cj_gst_compute(float $subtotal, float $rate, bool $enabled, bool $inclusive): array
    {
        $subtotal = round(max(0, $subtotal), 2);
        $rate = max(0, $rate);
        if (!$enabled || $rate <= 0) {
            return [
                'enabled' => false,
                'rate' => 0.0,
                'inclusive' => false,
                'taxable' => $subtotal,
                'gst_amount' => 0.0,
                'grand_total' => $subtotal,
            ];
        }
        if ($inclusive) {
            $gst = round($subtotal - ($subtotal * 100 / (100 + $rate)), 2);
            return [
                'enabled' => true,
                'rate' => $rate,
                'inclusive' => true,
                'taxable' => round($subtotal - $gst, 2),
                'gst_amount' => $gst,
                'grand_total' => $subtotal,
            ];
        }
        $gst = round($subtotal * $rate / 100, 2);
        return [
            'enabled' => true,
            'rate' => $rate,
            'inclusive' => false,
            'taxable' => $subtotal,
            'gst_amount' => $gst,
            'grand_total' => round($subtotal + $gst, 2),
        ];
    }
}

if (!function_exists('cj_gst_breakdown')) {
    /**
     * @return array{enabled:bool,rate:float,inclusive:bool,taxable:float,gst_amount:float,grand_total:float}
     */
    function cj_gst_breakdown(float $subtotal): array
    {
        $rate = (float) cj_setting('gst_percent', '3');
        return cj_gst_compute($subtotal, $rate, cj_feature('feat_gst'), cj_feature('gst_inclusive'));
    }
}

if (!function_exists('cj_order_payable_rupees')) {
    /** @param array<string,mixed> $order */
    function cj_order_payable_rupees(array $order): float
    {
        $grand = (float) ($order['grand_total'] ?? 0);
        if ($grand > 0) {
            return $grand;
        }
        return (float) ($order['subtotal'] ?? 0);
    }
}

if (!function_exists('cj_recs_limit')) {
    function cj_recs_limit(): int
    {
        return max(2, min(12, (int) cj_setting('recs_limit', '6')));
    }
}
