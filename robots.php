<?php
/**
 * Dynamic robots.txt — allow major search + AI crawlers; point to sitemap.
 * Canonical host: https://chandrakalajewellers.in
 */
require_once __DIR__ . '/includes/seo.php';
header('Content-Type: text/plain; charset=UTF-8');
$sitemap = cj_abs_url('sitemap.xml');
$host = 'https://' . cj_site_host();

$aiBots = [
    'GPTBot',
    'ChatGPT-User',
    'OAI-SearchBot',
    'Google-Extended',
    'Googlebot',
    'Googlebot-Image',
    'Googlebot-News',
    'Bingbot',
    'BingPreview',
    'Slurp',
    'DuckDuckBot',
    'Yandex',
    'YandexBot',
    'Applebot',
    'Applebot-Extended',
    'anthropic-ai',
    'ClaudeBot',
    'Claude-Web',
    'PerplexityBot',
    'Bytespider',
    'CCBot',
    'FacebookBot',
    'meta-externalagent',
    'Meta-ExternalAgent',
    'Amazonbot',
    'cohere-ai',
    'Diffbot',
    'YouBot',
    'ia_archiver',
];

$out = <<<TXT
# ChandraKala Jewellers — {$host}
# Allow search engines + AI assistants to crawl public pages

User-agent: *
Allow: /
Allow: /index1
Allow: /about
Allow: /gallery
Allow: /Catalogue
Allow: /product
Allow: /contact
Allow: /blog
Allow: /blog-post
Allow: /jewellers-in-khedbrahma
Allow: /gold-rate-khedbrahma
Allow: /follow-us
Allow: /llms.txt
Allow: /ai.txt
Allow: /agents.txt
Allow: /humans.txt
Allow: /img.php
Allow: /b7e4c91a2f8d4e6ab3c5d0e9f1a2b3c4.txt
Disallow: /admin/
Disallow: /secret
Disallow: /secret.php
Disallow: /db-diagnostic
Disallow: /db-diagnostic.php
Disallow: /api/
Disallow: /tools/
Disallow: /cart
Disallow: /checkout
Disallow: /buynow
Disallow: /pay
Disallow: /verify-payment
Disallow: /webhook-razorpay
Disallow: /review-submit
Disallow: /place_order
Disallow: /tests/
Disallow: /get_cart_items.php
Disallow: /config/
Disallow: /indexnow.php
Disallow: /welcome
Disallow: /cron/

TXT;

foreach ($aiBots as $bot) {
    $out .= "\nUser-agent: {$bot}\nAllow: /\nAllow: /llms.txt\nAllow: /ai.txt\nAllow: /agents.txt\nAllow: /sitemap.xml\n";
}

$out .= <<<TXT

Host: {$host}
Sitemap: {$sitemap}

TXT;

echo $out;
