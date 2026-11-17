<?php
/**
 * Build an image URL (Cloudinary CDN when enabled, falling back to img.php).
 */
require_once __DIR__ . '/cloudinary.php';

if (!function_exists('cj_img_url')) {
    function cj_img_url($filename, $width = 600, $folder = 'uploads') {
        $filename = ltrim((string)$filename, '/');
        if ($filename === '') {
            return '';
        }

        // Deliver directly from Cloudinary CDN when configured
        if (function_exists('cj_cloudinary_is_enabled') && cj_cloudinary_is_enabled()) {
            return cj_cloudinary_url($filename, (int)$width, $folder);
        }

        $folder = $folder === 'catalogue' ? 'catalogue' : 'uploads';
        $url = 'img.php?f=' . rawurlencode(basename($filename)) . '&w=' . (int)$width;
        if ($folder === 'catalogue') {
            $url .= '&dir=catalogue';
        }
        if (function_exists('cj_setting')) {
            $cdn = rtrim(cj_setting('cdn_base_url'), '/');
            if ($cdn !== '') {
                return $cdn . '/' . ltrim($url, '/');
            }
        }
        return $url;
    }
}
