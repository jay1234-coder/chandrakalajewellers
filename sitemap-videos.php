<?php
/**
 * ChandraKala Jewellers — Real-Time Dedicated Google Video XML Sitemap.
 * Outputs pure Google Video Sitemap schema for search engine crawlers.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/indexnow-helper.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=180, stale-while-revalidate=360');

$xml = str_replace(
    'https://www.chandrakalajewellers.in',
    'https://chandrakalajewellers.in',
    cj_build_video_sitemap_xml($conn)
);

// Also sync physical file if writable
$targetFile = __DIR__ . '/sitemap-videos.xml';
if (is_writable(__DIR__) || (is_file($targetFile) && is_writable($targetFile))) {
    @file_put_contents($targetFile, $xml);
}

echo $xml;
exit;
