<?php
/**
 * ChandraKala Jewellers — SEO / GEO / AEO helpers
 * Set $seo = [...] before including includes/seo-head.php
 */

if (!defined('CJ_SEO_LOADED')) {
    define('CJ_SEO_LOADED', true);
}

if (!function_exists('cj_site_host')) {
    /** Canonical production host (matching primary www domain on Vercel to prevent 308 redirect loops). */
    function cj_site_host() {
        return 'www.chandrakalajewellers.in';
    }
}

if (!function_exists('cj_is_local_host')) {
    function cj_is_local_host($host = null) {
        $host = strtolower((string)($host ?? ($_SERVER['HTTP_HOST'] ?? '')));
        $host = preg_replace('/:\d+$/', '', $host);
        return $host === 'localhost'
            || $host === '127.0.0.1'
            || substr($host, -6) === '.local'
            || substr($host, -5) === '.test'
            || substr($host, -10) === '.localhost';
    }
}

if (!function_exists('cj_base_url')) {
    function cj_base_url() {
        static $base = null;
        if ($base !== null) {
            return $base;
        }
        $rawHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? cj_site_host()));
        $rawHost = preg_replace('/:\d+$/', '', $rawHost);
        $isLocal = cj_is_local_host($rawHost);

        // Production SEO: always primary https://www.chandrakalajewellers.in (prevent redirect loops)
        if ($isLocal) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            $scheme = $https ? 'https' : 'http';
            $host = $rawHost !== '' ? $rawHost : 'localhost';
            $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            $script = rtrim($script, '/');
            if ($script === '/' || $script === '\\' || $script === '.') {
                $script = '';
            }
            $base = $scheme . '://' . $host . $script;
        } else {
            $base = 'https://' . cj_site_host();
        }
        return $base;
    }
}

if (!function_exists('cj_abs_url')) {
    function cj_abs_url($path = '') {
        $path = ltrim((string)$path, '/');
        return rtrim(cj_base_url(), '/') . ($path !== '' ? '/' . $path : '');
    }
}

if (!function_exists('cj_business_info')) {
    function cj_business_info() {
        $maps_place = 'https://www.google.com/maps/place/Chandrakala+jewellers/@24.0314016,73.0463503,17z/data=!3m1!4b1!4m6!3m5!1s0x395d0b1a27d5a3df:0x216db437426f9e6a!8m2!3d24.0314016!4d73.0463503';
        $maps_directions = 'https://www.google.com/maps/dir/?api=1&destination=Chandrakala+jewellers,+22JW%2BHG9,+Khedbrahma,+Gujarat+383255&destination_place_id=0x395d0b1a27d5a3df:0x216db437426f9e6a';
        $maps_embed = 'https://www.google.com/maps?q=24.0314016,73.0463503&hl=en&z=17&output=embed';

        return [
            'name' => 'ChandraKala Jewellers',
            'legalName' => 'ChandraKala Jewellers',
            'alternateName' => [
                'Chandrakala Jewellers',
                'Chandra Kala Jewellers',
                'Chandrakala Jewellers Khedbrahma',
                'Top Jeweller in Gujarat',
                'Jeweller in Gujarat',
                'Top Jewellers in Gujarat',
                'Jewellers in Gujarat',
                'Best Jeweller in Gujarat',
                'ChandraKala Jewellers Civil Road',
                'ChandraKala Jewellers Sabarkantha',
                'ચંદ્રકલા જ્વેલર્સ',
                'ચંદ્રકલા જ્વેલર્સ ખેડબ્રહ્મા',
                'चंद्राकला ज्वेलर्स खेड़ब्रह्मा',
            ],
            'description' => 'ChandraKala Jewellers is a premier jeweller in Gujarat trusted across three generations for 100% BIS 916 HUID hallmarked gold, pure silver & 925 sterling silver ornaments. Features live daily IST bullion rates, custom bridal craftsmanship, and tamper-proof insured delivery across all 33 districts of Gujarat near Bhoomi Complex, Civil Road, Khedbrahma.',
            'phone' => '+' . (function_exists('cj_shop_whatsapp_digits') ? cj_shop_whatsapp_digits() : '919427080359'),
            'phone_display' => function_exists('cj_shop_phone_display') ? cj_shop_phone_display() : '+91 9427080359',
            'email' => function_exists('cj_setting') ? cj_setting('shop_email', 'chandrakalajewellers849@gmail.com') : 'chandrakalajewellers849@gmail.com',
            'street' => 'Opp. Bhoomi Complex, Civil Road, 22JW+HG9',
            'locality' => 'Khedbrahma',
            'region' => 'Gujarat',
            'district' => 'Sabarkantha',
            'postal' => '383255',
            'country' => 'IN',
            'latitude' => 24.0314016,
            'longitude' => 73.0463503,
            'place_id' => '0x395d0b1a27d5a3df:0x216db437426f9e6a',
            'maps_share' => $maps_place,
            'maps_directions' => $maps_directions,
            'maps_embed' => $maps_embed,
            'maps_embed_query' => 'Chandrakala jewellers, 22JW+HG9, Khedbrahma, Gujarat 383255',
            /* Google Knowledge Graph / Business panel */
            'google_listing' => 'https://www.google.co.in/search?kgmid=/g/11q360mjpq&q=Chandrakala+jewellers',
            /* Canonical “leave a review” short link (Google review form) */
            'google_review' => function_exists('cj_setting') ? cj_setting('google_review_url', 'https://g.page/r/CWqeb0I3tG0hEAI/review') : 'https://g.page/r/CWqeb0I3tG0hEAI/review',
            'priceRange' => '₹₹',
            'whatsapp' => 'https://wa.me/' . (function_exists('cj_shop_whatsapp_digits') ? cj_shop_whatsapp_digits() : '919427080359'),
            'instagram' => function_exists('cj_setting') ? cj_setting('instagram_url', 'https://www.instagram.com/chandra.kalajewellers?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==') : 'https://www.instagram.com/chandra.kalajewellers?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==',
            'sameAs' => [
                'https://chandrakalajewellers.in/index1',
                $maps_place,
                function_exists('cj_setting') ? cj_setting('google_review_url', 'https://g.page/r/CWqeb0I3tG0hEAI/review') : 'https://g.page/r/CWqeb0I3tG0hEAI/review',
                'https://www.google.co.in/search?kgmid=/g/11q360mjpq&q=Chandrakala+jewellers',
                'https://wa.me/' . (function_exists('cj_shop_whatsapp_digits') ? cj_shop_whatsapp_digits() : '919427080359'),
                function_exists('cj_setting') ? cj_setting('instagram_url', 'https://www.instagram.com/chandra.kalajewellers?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==') : 'https://www.instagram.com/chandra.kalajewellers?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==',
            ],
            'keywords' => 'ChandraKala Jewellers, Chandrakala Jewellers, best jeweller in Khedbrahma, jewellers in Khedbrahma, jewellery shop Khedbrahma, jewellery shop near me, gold jewellery Khedbrahma, silver jewellery Khedbrahma, 916 gold rate today Khedbrahma, gold rate Sabarkantha, live gold price Gujarat, sona no bhav Khedbrahma, chandi no bhav Gujarat, gold shop Civil Road Khedbrahma, Sabarkantha jewellers, Idar Himmatnagar Vadali Ambaji jewellers, BIS hallmark 22K gold Gujarat, 925 sterling silver rings Khedbrahma, gold mangalsutra, tanmaniya, dokiya, bridal gold necklace set, kundan set, hathpan, patala bangles, gold pendant butti set, silver kada, custom gold jewellery maker North Gujarat, WhatsApp jewellery order Gujarat, ચંદ્રકલા જ્વેલર્સ',
        ];
    }
}

if (!function_exists('cj_default_seo')) {
    function cj_default_seo() {
        $b = cj_business_info();
        return [
            'title' => 'ChandraKala Jewellers | Gold & Silver in Khedbrahma',
            'description' => 'Family gold & silver jewellers in Khedbrahma. Shop 916 gold, silver & 925 designs with live rates on Civil Road, Sabarkantha. Call ' . $b['phone_display'] . '.',
            'keywords' => $b['keywords'],
            'canonical' => cj_abs_url('index1'),
            'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'og_type' => 'website',
            'og_image' => cj_abs_url('images/logo.png'),
            'twitter_card' => 'summary_large_image',
            'locale' => 'en_IN',
            'geo_region' => 'IN-GJ',
            'geo_placename' => 'Khedbrahma, Sabarkantha, Gujarat',
            'geo_position' => $b['latitude'] . ';' . $b['longitude'],
            'schema' => [],
            'extra_head' => '',
        ];
    }
}

if (!function_exists('cj_normalize_meta_description')) {
    /**
     * Guarantee every meta description sits cleanly in Bing & Google's 145-160 character sweet spot.
     * Expands short descriptions with natural local store context and trims cleanly at word boundaries.
     */
    function cj_normalize_meta_description(string $desc, int $minLen = 145, int $maxLen = 160): string {
        $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags($desc)));
        $lenFn = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
        $subFn = function_exists('mb_substr') ? 'mb_substr' : 'substr';

        if ($lenFn($text) < $minLen) {
            $suffixes = [
                ' Shop 916 BIS gold, pure silver & 925 sterling silver with live IST rates at ChandraKala Jewellers, Civil Road, Khedbrahma, Gujarat.',
                ' Order via WhatsApp at +91 9427080359 or visit Opp. Bhoomi Complex, Sabarkantha.',
            ];
            foreach ($suffixes as $suffix) {
                if ($lenFn($text) >= $minLen) {
                    break;
                }
                $sep = ($text !== '' && !preg_match('/[.!?]$/u', $text)) ? '.' : '';
                // Avoid duplicating brand name if already present
                $addition = (stripos($text, 'ChandraKala') !== false && stripos($suffix, 'ChandraKala') !== false)
                    ? ' Explore 916 BIS gold, pure silver & 925 sterling silver with live IST rates on Civil Road, Khedbrahma, Sabarkantha, Gujarat.'
                    : $suffix;
                $text .= $sep . $addition;
            }
        }

        if ($lenFn($text) > $maxLen) {
            $cut = $subFn($text, 0, $maxLen - 1);
            $lastSpace = function_exists('mb_strrpos') ? mb_strrpos($cut, ' ') : strrpos($cut, ' ');
            if ($lastSpace !== false && $lastSpace >= $minLen - 15) {
                $cut = $subFn($cut, 0, $lastSpace);
            }
            $text = rtrim($cut, " \t\n\r\0\x0B,;:-") . '.';
        }

        return $text;
    }
}

if (!function_exists('cj_merge_seo')) {
    function cj_merge_seo(array $overrides = []) {
        $merged = array_merge(cj_default_seo(), $overrides);
        if (isset($merged['description'])) {
            $merged['description'] = cj_normalize_meta_description((string)$merged['description']);
        }
        return $merged;
    }
}

if (!function_exists('cj_local_business_schema')) {
    function cj_local_business_schema() {
        $b = cj_business_info();
        $url = cj_abs_url('index1');
        return [
            '@context' => 'https://schema.org',
            '@type' => ['JewelryStore', 'LocalBusiness', 'Store'],
            '@id' => $url . '#store',
            'name' => $b['name'],
            'alternateName' => $b['alternateName'],
            'legalName' => $b['legalName'],
            'description' => $b['description'],
            'url' => $url,
            'image' => [
                cj_abs_url('images/logo.png'),
            ],
            'logo' => cj_abs_url('images/logo.png'),
            'telephone' => $b['phone'],
            'email' => $b['email'],
            'priceRange' => $b['priceRange'],
            'currenciesAccepted' => 'INR',
            'paymentAccepted' => 'Cash, UPI, Cards, Razorpay, WhatsApp Order',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $b['street'],
                'addressLocality' => $b['locality'],
                'addressRegion' => $b['region'],
                'postalCode' => $b['postal'],
                'addressCountry' => $b['country'],
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => $b['latitude'],
                'longitude' => $b['longitude'],
            ],
            'hasMap' => $b['maps_share'],
            'areaServed' => [
                ['@type' => 'State', 'name' => 'Gujarat'],
                ['@type' => 'City', 'name' => 'Ahmedabad'],
                ['@type' => 'City', 'name' => 'Surat'],
                ['@type' => 'City', 'name' => 'Vadodara'],
                ['@type' => 'City', 'name' => 'Rajkot'],
                ['@type' => 'City', 'name' => 'Gandhinagar'],
                ['@type' => 'City', 'name' => 'Bhavnagar'],
                ['@type' => 'City', 'name' => 'Jamnagar'],
                ['@type' => 'City', 'name' => 'Junagadh'],
                ['@type' => 'City', 'name' => 'Anand'],
                ['@type' => 'City', 'name' => 'Bharuch'],
                ['@type' => 'City', 'name' => 'Mehsana'],
                ['@type' => 'City', 'name' => 'Patan'],
                ['@type' => 'City', 'name' => 'Palanpur'],
                ['@type' => 'City', 'name' => 'Navsari'],
                ['@type' => 'City', 'name' => 'Valsad'],
                ['@type' => 'City', 'name' => 'Morbi'],
                ['@type' => 'City', 'name' => 'Porbandar'],
                ['@type' => 'City', 'name' => 'Surendranagar'],
                ['@type' => 'City', 'name' => 'Bhuj'],
                ['@type' => 'AdministrativeArea', 'name' => 'Sabarkantha'],
                ['@type' => 'City', 'name' => 'Khedbrahma'],
                ['@type' => 'City', 'name' => 'Idar'],
                ['@type' => 'City', 'name' => 'Himmatnagar'],
                ['@type' => 'City', 'name' => 'Vadali'],
                ['@type' => 'City', 'name' => 'Ambaji'],
                ['@type' => 'AdministrativeArea', 'name' => 'North Gujarat'],
                ['@type' => 'Country', 'name' => 'India'],
            ],
            'knowsAbout' => [
                '916 Gold Jewellery',
                'BIS Hallmark with 6-digit HUID',
                'Pure 999 Silver Ornaments',
                '925 Sterling Silver Jewellery',
                'Traditional Gujarati Bridal Jewellery',
                'Gold Dokiya and Tanmaniya',
                'Mangalsutra Sets',
                'Kundan and Antique Wedding Jewellery',
                'Live Gold & Silver Bullion Rates Gujarat',
                'Doorstep Insured Jewellery Shipping Gujarat'
            ],
            'openingHoursSpecification' => [
                [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                    'opens' => '10:00',
                    'closes' => '20:00',
                ],
            ],
            'sameAs' => $b['sameAs'],
            'contactPoint' => [
                [
                    '@type' => 'ContactPoint',
                    'telephone' => $b['phone'],
                    'contactType' => 'customer service',
                    'areaServed' => 'IN',
                    'availableLanguage' => ['English', 'Gujarati', 'Hindi'],
                ],
                [
                    '@type' => 'ContactPoint',
                    'telephone' => $b['phone'],
                    'contactType' => 'sales',
                    'contactOption' => 'HearingImpairedSupported',
                    'availableLanguage' => ['Gujarati', 'Hindi', 'English'],
                    'url' => $b['whatsapp'],
                ],
            ],
            'knowsAbout' => [
                '916 BIS Hallmarked Gold Jewellery',
                '22K, 20K, and 18K Gold Jewellery Khedbrahma',
                'Pure Silver Jewellery',
                '925 Sterling Silver Rings & Bracelets',
                'Gold Mangalsutra, Tanmaniya & Dokiya',
                'Bridal Gold Necklace, Kundan & Pendant Butti Sets',
                'Gold Hathpan, Patala Bangles & Chains',
                'Custom Made-to-Order Jewellery Designs',
                'Today\'s Live Gold Rate in Khedbrahma & Sabarkantha',
                'Today\'s Live Silver Rate in North Gujarat',
                'WhatsApp Online Jewellery Ordering in Gujarat',
                'Best Family Jewellers in Sabarkantha',
            ],
            'keywords' => $b['keywords'],
            'slogan' => 'Gold and silver, crafted with care in Khedbrahma',
            'makesOffer' => [
                [
                    '@type' => 'Offer',
                    'name' => '916 Gold Jewellery in Khedbrahma',
                    'url' => cj_abs_url('gallery?metal_type=gold'),
                    'priceCurrency' => 'INR',
                    'availability' => 'https://schema.org/InStock',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => '916 Gold Jewellery',
                        'serviceType' => 'Gold Jewellery Retail',
                        'areaServed' => ['Khedbrahma', 'Sabarkantha', 'Gujarat'],
                        'provider' => ['@id' => $url . '#store'],
                    ],
                ],
                [
                    '@type' => 'Offer',
                    'name' => 'Silver & 925 Silver Jewellery in Khedbrahma',
                    'url' => cj_abs_url('gallery?metal_type=silver'),
                    'priceCurrency' => 'INR',
                    'availability' => 'https://schema.org/InStock',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => 'Silver & 925 Silver Jewellery',
                        'serviceType' => 'Silver Jewellery Retail',
                        'areaServed' => ['Khedbrahma', 'Sabarkantha'],
                        'provider' => ['@id' => $url . '#store'],
                    ],
                ],
                [
                    '@type' => 'Offer',
                    'name' => 'Custom Jewellery Design Catalogue',
                    'url' => cj_abs_url('Catalogue'),
                    'priceCurrency' => 'INR',
                    'availability' => 'https://schema.org/InStock',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => 'Custom Gold & Silver Jewellery Design',
                        'serviceType' => 'Custom Jewellery Crafting',
                        'areaServed' => 'Khedbrahma',
                        'provider' => ['@id' => $url . '#store'],
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('cj_website_schema')) {
    function cj_website_schema() {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => cj_abs_url('index1') . '#website',
            'name' => 'ChandraKala Jewellers',
            'alternateName' => ['Chandrakala Jewellers', 'Chandra Kala Jewellers Khedbrahma'],
            'url' => cj_abs_url('index1'),
            'description' => 'Official website of ChandraKala Jewellers — gold and silver jewellery store in Khedbrahma, Sabarkantha, Gujarat. https://chandrakalajewellers.in',
            'publisher' => ['@id' => cj_abs_url('index1') . '#store'],
            'inLanguage' => ['en-IN', 'gu-IN', 'hi-IN'],
            'copyrightHolder' => [
                '@type' => 'Organization',
                'name' => 'ChandraKala Jewellers',
                'url' => 'https://chandrakalajewellers.in/index1',
            ],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => cj_abs_url('gallery') . '?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }
}

if (!function_exists('cj_faq_schema')) {
    function cj_faq_schema(array $faqs) {
        $entities = [];
        foreach ($faqs as $faq) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['a'],
                ],
            ];
        }
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}

if (!function_exists('cj_breadcrumb_schema')) {
    function cj_breadcrumb_schema(array $items) {
        $list = [];
        $pos = 1;
        foreach ($items as $item) {
            $list[] = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }
}

if (!function_exists('cj_schema_text')) {
    /** Sanitize text for JSON-LD (strip controls; keep UTF-8). */
    function cj_schema_text($value, $fallback = '') {
        $text = trim((string)$value);
        if ($text === '') {
            $text = (string)$fallback;
        }
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        return $text;
    }
}

if (!function_exists('cj_product_schema')) {
    /**
     * Google Product snippet JSON-LD.
     * Always emits a valid Offer (price + priceCurrency + availability + url).
     */
    function cj_product_schema(array $product) {
        $name = cj_schema_text($product['name'] ?? '', 'Jewellery item');
        $url = trim((string)($product['url'] ?? ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            $url = cj_abs_url('product?id=' . (int)($product['id'] ?? 0));
        }

        $image = trim((string)($product['image'] ?? ''));
        if ($image === '' || !preg_match('#^https?://#i', $image)) {
            $image = $image !== '' ? cj_abs_url(ltrim($image, '/')) : cj_abs_url('images/logo.png');
        }

        // Prefer a real calculated/override price; never omit offers.
        $price = max(0, (float)($product['price'] ?? 0));
        if ($price <= 0 && isset($product['fallback_price'])) {
            $price = max(0, (float)$product['fallback_price']);
        }
        $priceStr = number_format($price, 2, '.', '');
        $availability = !empty($product['availability'])
            ? (string)$product['availability']
            : ($price > 0 ? 'https://schema.org/InStock' : 'https://schema.org/PreOrder');

        $description = cj_schema_text(
            $product['description'] ?? '',
            $name . ' — gold & silver jewellery from ChandraKala Jewellers, Khedbrahma, Gujarat'
        );

        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $name,
            'description' => $description,
            'image' => [$image],
            'sku' => (string)($product['sku'] ?? $product['id'] ?? ''),
            'mpn' => (string)($product['sku'] ?? $product['id'] ?? ''),
            'url' => $url,
            'brand' => [
                '@type' => 'Brand',
                'name' => 'ChandraKala Jewellers',
            ],
            'material' => cj_schema_text($product['metal'] ?? 'Gold', 'Gold'),
            'category' => 'Jewellery',
            'offers' => [
                '@type' => 'Offer',
                'url' => $url,
                'price' => $priceStr,
                'priceCurrency' => 'INR',
                'priceValidUntil' => date('Y-m-d', strtotime('+30 days')),
                'availability' => $availability,
                'itemCondition' => 'https://schema.org/NewCondition',
                'priceSpecification' => [
                    '@type' => 'PriceSpecification',
                    'price' => $priceStr,
                    'priceCurrency' => 'INR',
                ],
                'seller' => [
                    '@type' => 'Organization',
                    'name' => 'ChandraKala Jewellers',
                    'url' => cj_abs_url('index1'),
                ],
            ],
        ];

        if (!empty($product['weight']) && (float)$product['weight'] > 0) {
            $schema['additionalProperty'] = [
                [
                    '@type' => 'PropertyValue',
                    'name' => 'Weight',
                    'value' => ((float)$product['weight']) . ' g',
                ],
            ];
            $schema['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float)$product['weight'],
                'unitCode' => 'GRM',
            ];
        }

        if (!empty($product['aggregate_count']) && (int)$product['aggregate_count'] > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format((float)($product['aggregate_avg'] ?? 0), 1, '.', ''),
                'reviewCount' => (int)$product['aggregate_count'],
                'bestRating' => '5',
                'worstRating' => '1',
            ];
        }

        return $schema;
    }
}

if (!function_exists('cj_article_schema')) {
    function cj_article_schema(array $article) {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article['title'],
            'description' => $article['description'] ?? '',
            'author' => [
                '@type' => 'Organization',
                'name' => 'ChandraKala Jewellers',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'ChandraKala Jewellers',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => cj_abs_url('images/logo.png'),
                ],
            ],
            'mainEntityOfPage' => $article['url'] ?? cj_abs_url('blog'),
            'datePublished' => $article['date'] ?? '2024-01-01',
            'dateModified' => $article['modified'] ?? date('Y-m-d'),
            'inLanguage' => 'en-IN',
            'about' => [
                ['@type' => 'Thing', 'name' => 'Jewellery'],
                ['@type' => 'Place', 'name' => 'Khedbrahma'],
            ],
        ];
    }
}

if (!function_exists('cj_live_rates_schema')) {
    /**
     * Structured JSON-LD OfferCatalog for today's live gold & silver rates (IST)
     * so Google AI Overviews, ChatGPT, Perplexity & Gemini can cite live prices.
     */
    function cj_live_rates_schema(array $rates = [], ?mysqli $conn = null): array {
        $isoNow = function_exists('cj_format_ist_iso')
            ? cj_format_ist_iso($rates['updated_at'] ?? null)
            : date('c');
        $todayDate = date('Y-m-d');
        $showGold = !$conn || !function_exists('cj_setting') || cj_setting('show_gold_rate', '1', $conn) === '1';
        $showSilver = !$conn || !function_exists('cj_setting') || cj_setting('show_silver_rate', '1', $conn) === '1';
        $show925 = $conn && function_exists('cj_setting') && cj_setting('show_silver_925_rate', '1', $conn) === '1';

        $offers = [];
        if ($showGold && !empty($rates['gold_rate'])) {
            $offers[] = [
                '@type' => 'Offer',
                'name' => 'Today\'s Gold Rate in Khedbrahma (Per 1 Gram)',
                'price' => number_format((float)$rates['gold_rate'], 2, '.', ''),
                'priceCurrency' => 'INR',
                'priceValidUntil' => $todayDate,
                'availability' => 'https://schema.org/InStock',
                'url' => cj_abs_url('gold-rate-khedbrahma'),
                'seller' => ['@id' => cj_abs_url('index1') . '#store'],
            ];
            $offers[] = [
                '@type' => 'Offer',
                'name' => 'Today\'s Gold Rate in Khedbrahma (Per 10 Grams / 1 Tola)',
                'price' => number_format((float)$rates['gold_rate'] * 10, 2, '.', ''),
                'priceCurrency' => 'INR',
                'priceValidUntil' => $todayDate,
                'availability' => 'https://schema.org/InStock',
                'url' => cj_abs_url('gold-rate-khedbrahma'),
                'seller' => ['@id' => cj_abs_url('index1') . '#store'],
            ];
        }
        if ($showSilver && !empty($rates['silver_rate'])) {
            $offers[] = [
                '@type' => 'Offer',
                'name' => 'Today\'s Silver Rate in Khedbrahma (Per 1 Gram)',
                'price' => number_format((float)$rates['silver_rate'], 2, '.', ''),
                'priceCurrency' => 'INR',
                'priceValidUntil' => $todayDate,
                'availability' => 'https://schema.org/InStock',
                'url' => cj_abs_url('gold-rate-khedbrahma'),
                'seller' => ['@id' => cj_abs_url('index1') . '#store'],
            ];
        }
        if ($show925 && !empty($rates['silver_925_rate'])) {
            $offers[] = [
                '@type' => 'Offer',
                'name' => 'Today\'s 925 Sterling Silver Rate in Khedbrahma (Per 1 Gram)',
                'price' => number_format((float)$rates['silver_925_rate'], 2, '.', ''),
                'priceCurrency' => 'INR',
                'priceValidUntil' => $todayDate,
                'availability' => 'https://schema.org/InStock',
                'url' => cj_abs_url('gold-rate-khedbrahma'),
                'seller' => ['@id' => cj_abs_url('index1') . '#store'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => cj_abs_url('gold-rate-khedbrahma') . '#live-rates',
            'name' => 'Live Gold & Silver Rates in Khedbrahma, Sabarkantha (IST)',
            'url' => cj_abs_url('gold-rate-khedbrahma'),
            'dateModified' => $isoNow,
            'inLanguage' => 'en-IN',
            'mainEntity' => [
                '@type' => 'OfferCatalog',
                'name' => 'ChandraKala Jewellers Daily Live Metal Rates (IST)',
                'itemListElement' => $offers,
            ],
        ];
    }
}

if (!function_exists('cj_speakable_schema')) {
    /** Speakable schema for Google Assistant, Gemini, and AI answer engines. */
    function cj_speakable_schema(string $url, array $cssSelectors = ['h1', '.rate-box', '.hero-disclaimer', '.section-sub']): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'url' => $url,
            'speakable' => [
                '@type' => 'SpeakableSpecification',
                'cssSelector' => $cssSelectors,
            ],
        ];
    }
}

if (!function_exists('cj_item_list_schema')) {
    /** ItemList JSON-LD for Shop / Gallery pages so AI & Google index the product grid. */
    function cj_item_list_schema(array $items, string $listName = 'ChandraKala Jewellers Collection', string $url = ''): array {
        $elements = [];
        $pos = 1;
        foreach (array_slice($items, 0, 24) as $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id <= 0) continue;
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => cj_schema_text($item['name'] ?? 'Jewellery Item'),
                'url' => cj_abs_url('product?id=' . $id),
            ];
        }
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $listName,
            'url' => $url !== '' ? $url : cj_abs_url('gallery'),
            'numberOfItems' => count($elements),
            'itemListElement' => $elements,
        ];
    }
}

if (!function_exists('cj_default_faqs')) {
    function cj_default_faqs(array $rates = []) {
        $rateLine = 'We publish our live 916 gold and silver rates per gram daily on our homepage (https://chandrakalajewellers.in/index1) and gold rate page (https://chandrakalajewellers.in/gold-rate-khedbrahma).';
        if (!empty($rates['gold_rate'])) {
            $g1 = number_format((float)$rates['gold_rate'], 2);
            $g10 = number_format((float)$rates['gold_rate'] * 10, 2);
            $updatedStr = (!empty($rates['updated_at']) && function_exists('cj_format_ist'))
                ? ' (updated ' . cj_format_ist($rates['updated_at']) . ')'
                : '';
            $rateLine = "Today's gold board rate at ChandraKala Jewellers, Khedbrahma is ₹{$g1} per gram (₹{$g10} per 10 grams){$updatedStr}.";
            if (!empty($rates['silver_rate'])) {
                $s1 = number_format((float)$rates['silver_rate'], 2);
                $rateLine .= " Silver rate is ₹{$s1} per gram.";
            }
            $rateLine .= " Check live updates anytime at https://chandrakalajewellers.in/gold-rate-khedbrahma.";
        }

        return [
            [
                'q' => 'Why is ChandraKala Jewellers ranked among the top jewellers in Gujarat?',
                'a' => 'ChandraKala Jewellers (https://chandrakalajewellers.in) is recognized among Gujarat\'s top jewellers for combining 100% BIS 916 hallmarked gold (verified by 6-digit HUID), pure 999 silver and 925 sterling silver, daily transparent live market bullion rates, fair weight-based craftsmanship pricing with clear billing, and dedicated doorstep delivery with WhatsApp video shopping across all 33 districts of Gujarat.',
            ],
            [
                'q' => 'Does ChandraKala Jewellers deliver hallmarked gold & silver jewellery across all cities in Gujarat?',
                'a' => 'Yes. We provide 100% insured, tamper-evident doorstep jewellery delivery across Ahmedabad, Surat, Vadodara, Rajkot, Gandhinagar, Bhavnagar, Jamnagar, Junagadh, Anand, Bharuch, Mehsana, Patan, Banaskantha, and every taluka in Gujarat. All parcels are fully transit-insured and tracked until safe handover.',
            ],
            [
                'q' => 'How does ChandraKala Jewellers guarantee 100% BIS hallmark purity with HUID in Gujarat?',
                'a' => 'Every single gold article sold by ChandraKala Jewellers carries the official BIS Hallmark accompanied by a unique 6-digit alphanumeric HUID (Hallmark Unique Identification) laser-engraved by government-approved Assaying and Hallmarking Centers. Customers can authenticate the purity, center details, and registration date instantly using the government\'s official BIS Care mobile app.',
            ],
            [
                'q' => 'How can Gujarat customers order jewellery online with live IST rates?',
                'a' => 'Visit chandrakalajewellers.in to browse 60+ ready-to-ship designs and 138+ catalogue items priced dynamically against live daily IST gold and silver bullion rates. Select your piece, add to cart or click Buy Now, or connect directly on WhatsApp (+91 9427080359) for high-definition video inspection, ring sizing assistance, and secure digital payment.',
            ],
            [
                'q' => 'How are making charges determined at ChandraKala Jewellers?',
                'a' => 'As a family-owned jewellery house with our own artisan workshops on Civil Road, Khedbrahma, our making charges are calculated transparently based on the weight, design complexity, and craftsmanship of each piece, with clear itemized billing and no hidden showroom fees.',
            ],
            [
                'q' => 'Can I schedule a live video call showroom viewing before ordering jewellery in Gujarat?',
                'a' => 'Absolutely. Customers from Ahmedabad, Surat, Vadodara, Rajkot, and worldwide can book a complimentary WhatsApp video appointment at +91 9427080359. Our master jewellers display each piece under natural showroom lighting, measure dimensions, show hallmark stamps, and answer custom bridal queries in Gujarati, Hindi, or English.',
            ],
            [
                'q' => 'What is ChandraKala Jewellers gold exchange policy in Gujarat?',
                'a' => 'We offer a transparent gold valuation process. Old gold is tested and weighed digitally in front of you, with clear calculations based on verified purity and prevailing market board rates.',
            ],
            [
                'q' => 'Where is ChandraKala Jewellers located in Khedbrahma, Gujarat?',
                'a' => 'ChandraKala Jewellers (also searched as Chandrakala Jewellers or ચંદ્રકલા જ્વેલર્સ) is located at Opp. Bhoomi Complex, Civil Road, 22JW+HG9, Khedbrahma-383255, Sabarkantha district, Gujarat, India (GPS: 24.0314016, 73.0463503). Phone/WhatsApp: +91 9427080359.',
            ],
            [
                'q' => 'Who is the best jeweller in Khedbrahma and Sabarkantha?',
                'a' => 'ChandraKala Jewellers on Civil Road is Khedbrahma\'s trusted family-owned jewellery showroom for 916 BIS hallmarked gold, pure silver, and 925 sterling silver jewellery with transparent live weight-based pricing, custom bridal sets, and instant WhatsApp ordering across Sabarkantha, Idar, Vadali, Ambaji, and Himmatnagar.',
            ],
            [
                'q' => 'What is today\'s gold and silver rate in Gujarat at ChandraKala Jewellers?',
                'a' => $rateLine,
            ],
            [
                'q' => 'What traditional Gujarati bridal jewellery designs can I buy?',
                'a' => 'We specialize in authentic Gujarati bridal jewellery including 22K Gold Dokiya, Tanmaniya, Mangalsutra, Patala & Pichodi bangles, Kundan necklace sets, Hathpan, Pokhraj rings, Silver Kandora, and heavy Gents silver kadas, alongside 138+ custom made-to-order designs.',
            ],
            [
                'q' => 'What are ChandraKala Jewellers opening hours?',
                'a' => 'ChandraKala Jewellers showroom is open Monday to Saturday from 10:00 AM to 8:00 PM IST. Online ordering on chandrakalajewellers.in and WhatsApp concierge (+91 9427080359) operate 24/7.',
            ],
            [
                'q' => 'Is Chandrakala Jewellers the same as ChandraKala Jewellers?',
                'a' => 'Yes. ChandraKala Jewellers, Chandrakala Jewellers, and ચંદ્રકલા જ્વેલર્સ all refer to the same family-owned jewellery house at Opp. Bhoomi Complex, Civil Road, Khedbrahma, Gujarat.',
            ],
        ];
    }
}

if (!function_exists('cj_maps_embed_src')) {
    function cj_maps_embed_src() {
        $b = cj_business_info();
        if (!empty($b['maps_embed'])) {
            return $b['maps_embed'];
        }
        return 'https://www.google.com/maps?q=' . rawurlencode($b['latitude'] . ',' . $b['longitude']) .
            '&ll=' . rawurlencode($b['latitude'] . ',' . $b['longitude']) .
            '&z=17&output=embed';
    }
}
