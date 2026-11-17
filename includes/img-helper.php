<?php
/**
 * Build a resized product image URL (falls back to original if helper unavailable).
 */
if (!function_exists('cj_img_url')) {
    function cj_img_url($filename, $width = 600, $folder = 'uploads') {
        $filename = ltrim((string)$filename, '/');
        if ($filename === '') {
            return '';
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
