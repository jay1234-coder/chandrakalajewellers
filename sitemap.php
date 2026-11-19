<?php
/**
 * ChandraKala Jewellers — Real-Time XML + Google Image + Google Video Sitemap.
 *
 * Generated on-the-fly directly from TiDB / MySQL for Search Engines & AI Crawlers.
 * Includes all public core pages, dynamic product categories, stock jewellery items,
 * custom design catalogue items, and video reels with rich Google schema metadata.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/indexnow-helper.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=180, stale-while-revalidate=360');

$xml = cj_build_sitemap_xml($conn);

// Also sync physical file to disk if directory/file is writable
$targetFile = __DIR__ . '/sitemap.xml';
if (is_writable(__DIR__) || (is_file($targetFile) && is_writable($targetFile))) {
    @file_put_contents($targetFile, $xml);
}

echo $xml;
exit;
