<?php
/**
 * Automatic IndexNow ping helper for instant re-indexing on Bing, Yandex,
 * ChatGPT Search (Bing-backed), and Copilot whenever rates or products change.
 */

if (!function_exists('cj_auto_ping_indexnow')) {
    /**
     * Submit changed URLs to IndexNow with a short timeout and 5-minute throttle
     * so admin actions remain fast while keeping search/AI indexes fresh.
     *
     * @param list<string> $extraUrls
     */
    function cj_auto_ping_indexnow(array $extraUrls = [], bool $force = false): void
    {
        if (function_exists('cj_is_local_host') && cj_is_local_host()) {
            return;
        }
        if (!function_exists('curl_init')) {
            return;
        }

        $lockFile = sys_get_temp_dir() . '/cj_indexnow_last_ping.txt';
        if (!$force && is_file($lockFile) && (time() - (int)@filemtime($lockFile)) < 300) {
            return;
        }
        @file_put_contents($lockFile, (string)time());

        $indexNowKey = 'b7e4c91a2f8d4e6ab3c5d0e9f1a2b3c4';
        $host = 'chandrakalajewellers.in';
        $keyLocation = 'https://' . $host . '/' . $indexNowKey . '.txt';

        $baseUrls = [
            'https://' . $host . '/index1',
            'https://' . $host . '/gold-rate-khedbrahma',
            'https://' . $host . '/jewellers-in-khedbrahma',
            'https://' . $host . '/gallery',
            'https://' . $host . '/Catalogue',
            'https://' . $host . '/llms.txt',
            'https://' . $host . '/llms-full.txt',
            'https://' . $host . '/sitemap.xml',
        ];
        $urls = array_values(array_unique(array_merge($baseUrls, $extraUrls)));

        $payload = json_encode([
            'host' => $host,
            'key' => $indexNowKey,
            'keyLocation' => $keyLocation,
            'urlList' => $urls,
        ], JSON_UNESCAPED_SLASHES);

        if (!$payload) {
            return;
        }

        $ch = @curl_init('https://api.indexnow.org/indexnow');
        if (!$ch) {
            return;
        }
        @curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        @curl_exec($ch);
        @curl_close($ch);
    }
}
