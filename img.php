<?php
/**
 * On-the-fly image resize + cache (WebP when supported).
 * Usage: img.php?f=photo.jpg&w=600
 * Serves from /uploads and caches under /uploads/cache
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/security.php';

$f = basename((string)($_GET['f'] ?? ''));
$w = max(80, min(1200, (int)($_GET['w'] ?? 600)));
$dirKey = strtolower((string)($_GET['dir'] ?? 'uploads'));
$dir = $dirKey === 'catalogue' ? 'catalogue' : 'uploads';

if ($f === '' || !preg_match('/^[a-zA-Z0-9._\-\s]+\.(jpe?g|png|gif|webp)$/i', $f)) {
    http_response_code(400);
    exit('Bad request');
}

$src = __DIR__ . '/' . $dir . '/' . $f;
if (!is_file($src)) {
    http_response_code(404);
    exit('Not found');
}

$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$wantWebp = stripos($accept, 'image/webp') !== false && function_exists('imagewebp');
$ext = $wantWebp ? 'webp' : 'jpg';
$cacheDir = __DIR__ . '/uploads/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$cacheFile = $cacheDir . '/' . md5($dir . '|' . $f . '|' . $w . '|' . $ext) . '.' . $ext;

$send = function (string $path, string $mime) {
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    readfile($path);
    exit;
};

if (is_file($cacheFile) && filemtime($cacheFile) >= filemtime($src)) {
    $send($cacheFile, $wantWebp ? 'image/webp' : 'image/jpeg');
}

if (!function_exists('imagecreatefromjpeg')) {
    // No GD — fall back to original
    $mime = mime_content_type($src) ?: 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    readfile($src);
    exit;
}

$info = @getimagesize($src);
if (!$info) {
    http_response_code(415);
    exit('Unsupported');
}

[$origW, $origH, $type] = $info;
switch ($type) {
    case IMAGETYPE_JPEG:
        $im = @imagecreatefromjpeg($src);
        break;
    case IMAGETYPE_PNG:
        $im = @imagecreatefrompng($src);
        break;
    case IMAGETYPE_GIF:
        $im = @imagecreatefromgif($src);
        break;
    case IMAGETYPE_WEBP:
        $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false;
        break;
    default:
        $im = false;
}

if (!$im) {
    http_response_code(415);
    exit('Unsupported');
}

if ($origW <= $w) {
    $newW = $origW;
    $newH = $origH;
} else {
    $newW = $w;
    $newH = (int)round($origH * ($w / $origW));
}

$out = imagecreatetruecolor($newW, $newH);
if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
    imagealphablending($out, false);
    imagesavealpha($out, true);
}
imagecopyresampled($out, $im, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
imagedestroy($im);

if ($wantWebp) {
    imagewebp($out, $cacheFile, 78);
    imagedestroy($out);
    $send($cacheFile, 'image/webp');
}

imagejpeg($out, $cacheFile, 78);
imagedestroy($out);
$send($cacheFile, 'image/jpeg');
