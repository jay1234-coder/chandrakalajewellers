<?php
/**
 * Reliable PWA web app manifest for InfinityFree / hosts that serve HTML 404s
 * for .webmanifest. Rewritten from manifest.webmanifest and site.webmanifest.
 */
declare(strict_types=1);

$manifestFile = __DIR__ . '/manifest.webmanifest';
$json = is_readable($manifestFile) ? (string) file_get_contents($manifestFile) : '';

$data = json_decode($json, true);
if (!is_array($data)) {
    // Fallback inline copy if static file is missing/corrupt
    $data = [
        'name' => 'ChandraKala Jewellers',
        'short_name' => 'ChandraKala',
        'description' => 'Gold, silver & 925 jewellery from ChandraKala Jewellers, Khedbrahma. Live rates, shop & catalogue.',
        'start_url' => './index1',
        'scope' => './',
        'display' => 'standalone',
        'orientation' => 'portrait-primary',
        'background_color' => '#111827',
        'theme_color' => '#b8972c',
        'lang' => 'en-IN',
        'categories' => ['shopping', 'lifestyle'],
        'icons' => [
            [
                'src' => 'images/icons/icon-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => 'images/icons/icon-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
            [
                'src' => 'images/icons/icon-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => 'images/icons/icon-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
        ],
        'shortcuts' => [
            [
                'name' => 'Shop',
                'short_name' => 'Shop',
                'url' => './gallery',
                'icons' => [['src' => 'images/icons/icon-192.png', 'sizes' => '192x192']],
            ],
            [
                'name' => 'Catalogue',
                'short_name' => 'Catalogue',
                'url' => './Catalogue',
                'icons' => [['src' => 'images/icons/icon-192.png', 'sizes' => '192x192']],
            ],
        ],
    ];
}

header('Content-Type: application/manifest+json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');

echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
