<?php
/**
 * ChandraKala Jewellers — SEO / GEO / AEO helpers
 * Set $seo = [...] before including includes/seo-head.php
 */

if (!defined('CJ_SEO_LOADED')) {
    define('CJ_SEO_LOADED', true);
}

if (!function_exists('cj_site_host')) {
    /** Canonical production host (never www / never old wuaze staging). */
    function cj_site_host() {
        return 'chandrakalajewellers.in';
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

        // Production SEO: always apex https://chandrakalajewellers.in (strip www / old domains)
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
                'ChandraKala Jewellers Civil Road',
                'ચંદ્રકલા જ્વેલર્સ',
            ],
            'description' => 'Family-owned gold and silver jewellery store in Khedbrahma, Sabarkantha, Gujarat. Shop 916 (22K) gold, silver, and 925 silver jewellery with live rates, custom designs, and WhatsApp ordering near Bhoomi Complex, Civil Road.',
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
            'keywords' => 'ChandraKala Jewellers, Chandrakala Jewellers, jewellers in Khedbrahma, jewellery shop Khedbrahma, gold jewellery Khedbrahma, silver jewellery Khedbrahma, 916 gold rate Khedbrahma, jewellers near me, gold shop Civil Road, Sabarkantha jewellers, hallmark gold Gujarat, 925 silver Khedbrahma',
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

if (!function_exists('cj_merge_seo')) {
    function cj_merge_seo(array $overrides = []) {
        return array_merge(cj_default_seo(), $overrides);
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
                ['@type' => 'City', 'name' => 'Khedbrahma'],
                ['@type' => 'AdministrativeArea', 'name' => 'Sabarkantha'],
                ['@type' => 'City', 'name' => 'Idar'],
                ['@type' => 'City', 'name' => 'Himmatnagar'],
                ['@type' => 'State', 'name' => 'Gujarat'],
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
                '916 Gold Jewellery',
                '22K Gold Jewellery Khedbrahma',
                'Silver Jewellery',
                '925 Silver Jewellery',
                'Custom Jewellery Designs',
                'Gold Rate in Khedbrahma',
                'Jewellery Shop Sabarkantha',
                'Hallmark Gold Jewellery',
                'Wedding Jewellery Khedbrahma',
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
                    'urlTemplate' => cj_abs_url('gallery') . '?metal_type={search_term_string}',
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

if (!function_exists('cj_default_faqs')) {
    function cj_default_faqs() {
        return [
            [
                'q' => 'Where is ChandraKala Jewellers located in Khedbrahma?',
                'a' => 'ChandraKala Jewellers (also searched as Chandrakala Jewellers) is at Opp. Bhoomi Complex, Civil Road, 22JW+HG9, Khedbrahma-383255, Sabarkantha, Gujarat, India. Coordinates: 24.0314016, 73.0463503. Call +91 9427080359 or open our Google Maps listing for directions.',
            ],
            [
                'q' => 'Who is the best jeweller in Khedbrahma?',
                'a' => 'ChandraKala Jewellers is a trusted family-owned jewellery store in Khedbrahma offering 916 gold, silver, and 925 silver jewellery with transparent live rates, custom designs, and WhatsApp ordering for customers across Sabarkantha.',
            ],
            [
                'q' => 'Do you sell gold and silver jewellery in Khedbrahma?',
                'a' => 'Yes. We sell 916 (22K) gold jewellery, silver jewellery, and 925 sterling silver designs with clear pricing based on today\'s metal rates at our Civil Road showroom.',
            ],
            [
                'q' => 'What is today\'s gold rate at ChandraKala Jewellers?',
                'a' => 'We publish our latest 916 gold rate per gram on our homepage (index1). Rates are updated regularly so shoppers in Khedbrahma, Idar, Himmatnagar, and Sabarkantha can buy with confidence.',
            ],
            [
                'q' => 'Can I order jewellery on WhatsApp from ChandraKala Jewellers?',
                'a' => 'Yes. Browse the shop or catalogue online, then use Cart Checkout or Buy Now to send an order request on WhatsApp to +91 9427080359 for confirmation and purchase guidance.',
            ],
            [
                'q' => 'Do you offer custom jewellery designs in Sabarkantha?',
                'a' => 'Yes. Browse our Design Catalogue for reference photos and visit the store or message WhatsApp for custom gold and silver jewellery crafted in Khedbrahma.',
            ],
            [
                'q' => 'What are ChandraKala Jewellers opening hours?',
                'a' => 'We are typically open Monday to Saturday, 10:00 AM to 8:00 PM. Call +91 9427080359 before visiting on holidays.',
            ],
            [
                'q' => 'Is Chandrakala Jewellers the same as ChandraKala Jewellers?',
                'a' => 'Yes. ChandraKala Jewellers and Chandrakala Jewellers refer to the same jewellery store on Civil Road, Khedbrahma. Search either spelling on Google Maps to find us.',
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
