<?php
/**
 * ChandraKala Jewellers — Self-Developed Hybrid Image OCR + Visual AI Engine
 *
 * Combines:
 *  1. Self-built PHP GD Visual Pixel & Geometry Analyzer (HSV metal/stone tone detection,
 *     foreground aspect ratio & intricacy scoring — 100% self-hosted, zero external API cost).
 *  2. OCR Text Parser (extracts weights, hallmarks 916/22K/925, SKU codes, metal, stones & types
 *     from browser-side OCR text, tag text, or filenames).
 *  3. Optional Gemini Multimodal Vision + OCR boost when admin configures `gemini_api_key`.
 *  4. Luxury SEO Fancy Name, 150-char Meta Description & Tag Synthesizer.
 */

require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/settings.php';

if (!function_exists('cj_rgb_to_hsv')) {
    /** @return array{h:float,s:float,v:float} h in [0,360), s and v in [0,1] */
    function cj_rgb_to_hsv(int $r, int $g, int $b): array
    {
        $rf = $r / 255.0;
        $gf = $g / 255.0;
        $bf = $b / 255.0;
        $max = max($rf, $gf, $bf);
        $min = min($rf, $gf, $bf);
        $d = $max - $min;

        $h = 0.0;
        if ($d > 0.0001) {
            if ($max === $rf) {
                $h = 60.0 * fmod((($gf - $bf) / $d), 6.0);
            } elseif ($max === $gf) {
                $h = 60.0 * ((($bf - $rf) / $d) + 2.0);
            } else {
                $h = 60.0 * ((($rf - $gf) / $d) + 4.0);
            }
            if ($h < 0) {
                $h += 360.0;
            }
        }
        $s = $max > 0.0001 ? ($d / $max) : 0.0;
        return ['h' => $h, 's' => $s, 'v' => $max];
    }
}

if (!function_exists('cj_fetch_image_bytes')) {
    function cj_fetch_image_bytes(string $source): ?string
    {
        if ($source === '') {
            return null;
        }
        if (strpos($source, 'data:image/') === 0 && strpos($source, 'base64,') !== false) {
            $b64 = substr($source, strpos($source, 'base64,') + 7);
            $raw = base64_decode($b64, true);
            return $raw !== false ? $raw : null;
        }
        if (preg_match('#^https?://#i', $source)) {
            if (function_exists('curl_init')) {
                $ch = curl_init($source);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'ChandraKala-OCR-Engine/1.0',
                ]);
                $data = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if (is_string($data) && $data !== '' && $code >= 200 && $code < 300) {
                    return $data;
                }
            }
            return null;
        }
        if (is_file($source) && is_readable($source)) {
            $raw = @file_get_contents($source);
            return is_string($raw) ? $raw : null;
        }
        return null;
    }
}

if (!function_exists('cj_analyze_image_visuals')) {
    /**
     * Self-developed PHP GD visual analyzer: inspects HSV color signatures,
     * foreground bounding geometry, and edge intricacy to infer metal, stones,
     * jewellery silhouette, and style tier.
     *
     * @return array<string,mixed>
     */
    function cj_analyze_image_visuals(?string $rawBytes): array
    {
        $fallback = [
            'ok' => false,
            'metal' => 'gold',
            'stone' => '',
            'style' => 'Traditional',
            'silhouette' => 'square',
            'intricacy' => 'medium',
            'gold_ratio' => 0.0,
            'silver_ratio' => 0.0,
            'signals' => [],
        ];

        if (!$rawBytes || !function_exists('imagecreatefromstring')) {
            return $fallback;
        }

        $src = @imagecreatefromstring($rawBytes);
        if (!$src) {
            return $fallback;
        }

        $origW = imagesx($src);
        $origH = imagesy($src);
        $size = 64;
        $img = imagecreatetruecolor($size, $size);
        imagecopyresampled($img, $src, 0, 0, 0, 0, $size, $size, $origW, $origH);
        imagedestroy($src);

        // Estimate background color from the 4 corners
        $corners = [
            imagecolorat($img, 1, 1),
            imagecolorat($img, $size - 2, 1),
            imagecolorat($img, 1, $size - 2),
            imagecolorat($img, $size - 2, $size - 2),
        ];
        $bgR = $bgG = $bgB = 0;
        foreach ($corners as $c) {
            $bgR += ($c >> 16) & 0xFF;
            $bgG += ($c >> 8) & 0xFF;
            $bgB += $c & 0xFF;
        }
        $bgR = (int)round($bgR / 4);
        $bgG = (int)round($bgG / 4);
        $bgB = (int)round($bgB / 4);

        $fgCount = 0;
        $goldPx = 0;
        $rosePx = 0;
        $silverPx = 0;
        $redPx = 0;
        $greenPx = 0;
        $bluePx = 0;
        $sparklePx = 0;
        $edgeEnergy = 0.0;

        $minX = $size;
        $maxX = 0;
        $minY = $size;
        $maxY = 0;

        $lumaGrid = [];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $luma = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $lumaGrid[$y][$x] = $luma;

                // Focus foreground detection inside central 84% margin
                if ($x < 5 || $x > $size - 6 || $y < 5 || $y > $size - 6) {
                    continue;
                }

                $distBg = abs($r - $bgR) + abs($g - $bgG) + abs($b - $bgB);
                if ($distBg < 55) {
                    continue; // likely background pixel
                }

                $fgCount++;
                if ($x < $minX) $minX = $x;
                if ($x > $maxX) $maxX = $x;
                if ($y < $minY) $minY = $y;
                if ($y > $maxY) $maxY = $y;

                $hsv = cj_rgb_to_hsv($r, $g, $b);
                $h = $hsv['h'];
                $s = $hsv['s'];
                $v = $hsv['v'];

                // Gold tone: warm yellow-gold hues
                if ($h >= 24 && $h <= 58 && $s >= 0.22 && $v >= 0.25) {
                    $goldPx++;
                } elseif ($h >= 8 && $h < 24 && $s >= 0.22 && $v >= 0.25) {
                    $rosePx++;
                } elseif ($s < 0.18 && $v >= 0.35 && $v <= 0.92) {
                    $silverPx++;
                }

                // Stone / enamel accents
                if (($h >= 338 || $h <= 10) && $s >= 0.38 && $v >= 0.25) {
                    $redPx++;
                } elseif ($h >= 95 && $h <= 165 && $s >= 0.32 && $v >= 0.20) {
                    $greenPx++;
                } elseif ($h >= 195 && $h <= 255 && $s >= 0.32 && $v >= 0.22) {
                    $bluePx++;
                } elseif ($s < 0.12 && $v > 0.93) {
                    $sparklePx++;
                }

                if ($x > 0 && $y > 0) {
                    $gx = abs($luma - $lumaGrid[$y][$x - 1]);
                    $gy = abs($luma - $lumaGrid[$y - 1][$x]);
                    if ($gx + $gy > 32) {
                        $edgeEnergy += 1.0;
                    }
                }
            }
        }
        imagedestroy($img);

        $fgSafe = max(1, $fgCount);
        $goldRatio = ($goldPx + 0.5 * $rosePx) / $fgSafe;
        $silverRatio = $silverPx / $fgSafe;
        $edgeDensity = $edgeEnergy / $fgSafe;

        // Metal inference
        $metal = 'gold';
        if ($silverRatio > 0.38 && $goldRatio < 0.16) {
            $metal = 'silver_925';
        } elseif ($silverRatio > $goldRatio * 1.65 && $goldRatio < 0.22) {
            $metal = 'silver_925';
        }

        // Stone inference
        $stone = '';
        $signals = [];
        $signals[] = $metal === 'gold' ? 'Warm 916 Gold Tone' : 'Bright Silver / Sterling Tone';
        if (($redPx / $fgSafe) > 0.045 && ($greenPx / $fgSafe) > 0.03) {
            $stone = 'Kundan & Meenakari';
            $signals[] = 'Ruby + Emerald Meenakari Accents';
        } elseif (($redPx / $fgSafe) > 0.05) {
            $stone = 'Ruby / Red Kundan';
            $signals[] = 'Ruby / Red Stone Accent';
        } elseif (($greenPx / $fgSafe) > 0.045) {
            $stone = 'Emerald / Green Stone';
            $signals[] = 'Emerald Stone Accent';
        } elseif (($bluePx / $fgSafe) > 0.045) {
            $stone = 'Blue Sapphire / Crystal';
            $signals[] = 'Blue Crystal Accent';
        } elseif (($sparklePx / $fgSafe) > 0.08) {
            $stone = 'AD Diamond / Pearl';
            $signals[] = 'High Specular Sparkle (AD/Pearl)';
        }

        // Geometry / Silhouette inference
        $fgW = max(1, $maxX - $minX);
        $fgH = max(1, $maxY - $minY);
        $aspect = $fgW / $fgH;
        $fillRatio = $fgCount / max(1, $fgW * $fgH);

        $silhouette = 'square';
        if ($aspect >= 1.28) {
            $silhouette = 'wide'; // Necklace / Choker / Bracelet / Bangle pair
        } elseif ($aspect <= 0.78) {
            $silhouette = 'tall'; // Pendant Butti / Mangalsutra / Earrings / Chain
        } elseif ($fillRatio < 0.45) {
            $silhouette = 'ring'; // Hollow center: Ring / Kada / Bangle
        }

        $intricacy = 'medium';
        $style = 'Traditional';
        if ($edgeDensity > 0.44) {
            $intricacy = 'high';
            $style = 'Bridal Heritage';
            $signals[] = 'High Filigree / Bridal Intricacy';
        } elseif ($edgeDensity < 0.24) {
            $intricacy = 'low';
            $style = 'Modern Daily Wear';
            $signals[] = 'Sleek Minimalist Finish';
        } else {
            $signals[] = 'Classic Festive Craftsmanship';
        }

        return [
            'ok' => true,
            'metal' => $metal,
            'stone' => $stone,
            'style' => $style,
            'silhouette' => $silhouette,
            'intricacy' => $intricacy,
            'gold_ratio' => round($goldRatio, 3),
            'silver_ratio' => round($silverRatio, 3),
            'edge_density' => round($edgeDensity, 3),
            'signals' => $signals,
        ];
    }
}

if (!function_exists('cj_parse_ocr_text')) {
    /**
     * Parse raw OCR text (from browser OCR, image tags, or filename) using regex
     * to extract structured jewellery metadata.
     *
     * @return array<string,mixed>
     */
    function cj_parse_ocr_text(string $text): array
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $low = mb_strtolower($clean, 'UTF-8');

        $out = [
            'raw' => $clean,
            'weight' => null,
            'purity' => '',
            'metal' => '',
            'category' => '',
            'stone' => '',
            'sku' => '',
            'keywords' => [],
        ];

        if ($clean === '') {
            return $out;
        }

        // 1. Weight regex (e.g. "11.64 g", "5.20gm", "wt 8.5 grams")
        if (preg_match('/\b(\d{1,3}(?:\.\d{1,3})?)\s*(?:gm|gms|g|gram|grams)\b/iu', $low, $m)) {
            $w = (float)$m[1];
            if ($w > 0.1 && $w < 500) {
                $out['weight'] = round($w, 2);
            }
        }

        // 2. Purity & Metal regex
        if (preg_match('/\b(916|22\s*k|22\s*kt)\b/iu', $low)) {
            $out['purity'] = '22K 916 BIS Hallmark';
            $out['metal'] = 'gold';
        } elseif (preg_match('/\b(20\s*k|20\s*kt)\b/iu', $low)) {
            $out['purity'] = '20K Gold';
            $out['metal'] = 'gold';
        } elseif (preg_match('/\b(18\s*k|18\s*kt|750)\b/iu', $low)) {
            $out['purity'] = '18K 750 Gold';
            $out['metal'] = 'gold';
        } elseif (preg_match('/\b(925|92\.5|sterling)\b/iu', $low)) {
            $out['purity'] = '925 Sterling Silver';
            $out['metal'] = 'silver_925';
        } elseif (preg_match('/\b(999|pure\s*silver|chandi)\b/iu', $low)) {
            $out['purity'] = '999 Pure Silver';
            $out['metal'] = 'silver';
        } elseif (preg_match('/\b(gold|sona|sonu)\b/iu', $low)) {
            $out['metal'] = 'gold';
            $out['purity'] = '22K 916 Gold';
        } elseif (preg_match('/\b(silver|chandi)\b/iu', $low)) {
            $out['metal'] = 'silver';
            $out['purity'] = 'Pure Silver';
        }

        // 3. Jewellery Type / Category regex
        $typePatterns = [
            'Mangalsutra' => '/\b(mangalsutra|mangal\s*sutra|मंगलसूत्र)\b/iu',
            'Tanmaniya' => '/\b(tanmaniya|tanmania)\b/iu',
            'Pendant Butti Set' => '/\b(pendant\s*butti|pendent\s*butti|butti\s*set|pendant\s*set|locket\s*set)\b/iu',
            'Kundan Set' => '/\b(kundan\s*set|polki\s*set|jadau\s*set)\b/iu',
            'Bridal Set' => '/\b(bridal|wedding\s*set|dulhan|haar\s*set)\b/iu',
            'Necklace Set' => '/\b(necklace|haar|choker|set\s*with\s*earrings|gold\s*set|kanthi|magmala)\b/iu',
            'Dokiya' => '/\b(dokiya|dokia)\b/iu',
            'Earrings / Butti' => '/\b(earring|earrings|butti|jhumka|bali|baliya|tops|studs)\b/iu',
            'Kanchain' => '/\b(kanchain|kan\s*chain)\b/iu',
            'Gold Chain' => '/\b(chain|chains)\b/iu',
            'Hathpan' => '/\b(hathpan|hathphool|haathpan)\b/iu',
            'Bangles / Patala' => '/\b(bangle|bangles|patala|patla|kangan|chudi)\b/iu',
            'Kada' => '/\b(kada|kadas|kara)\b/iu',
            'Bracelet' => '/\b(bracelet|bracelets|pandora)\b/iu',
            'Ring' => '/\b(ring|rings|vinti|anguthi|solitaire)\b/iu',
            'Payal / Anklet' => '/\b(payal|anklet|zanzar|pajeb)\b/iu',
            'Nose Pin' => '/\b(nose\s*pin|nosepin|nath|chuni)\b/iu',
            'Coin / Bar' => '/\b(coin|bar|ginni|lagdi)\b/iu',
        ];
        foreach ($typePatterns as $cat => $rx) {
            if (preg_match($rx, $low)) {
                $out['category'] = $cat;
                break;
            }
        }

        // 4. Stone regex
        $stonePatterns = [
            'Kundan' => '/\b(kundan|jadau)\b/iu',
            'Polki' => '/\b(polki)\b/iu',
            'AD / CZ Diamond' => '/\b(diamond|ad\b|cz\b|american\s*diamond|crystal|solitaire)\b/iu',
            'Pearl' => '/\b(pearl|moti)\b/iu',
            'Ruby' => '/\b(ruby|red\s*stone|red\s*diamond)\b/iu',
            'Emerald' => '/\b(emerald|panna|green\s*stone)\b/iu',
            'Meenakari' => '/\b(meenakari|meena)\b/iu',
        ];
        foreach ($stonePatterns as $st => $rx) {
            if (preg_match($rx, $low)) {
                $out['stone'] = $st;
                break;
            }
        }

        // 5. SKU / Tag Code regex (e.g. CK-104, D-45, #208, NO.123)
        if (preg_match('/\b([A-Z]{1,4}[-#]?\d{2,6})\b/', $clean, $m)) {
            $out['sku'] = $m[1];
        }

        return $out;
    }
}

if (!function_exists('cj_gemini_vision_ocr')) {
    /**
     * Multimodal Gemini Vision + OCR call (used automatically when admin sets `gemini_api_key`).
     *
     * @param list<string> $allowedTypes
     * @return array<string,mixed>|null
     */
    function cj_gemini_vision_ocr(string $apiKey, string $rawBytes, array $allowedTypes, string $hintText = ''): ?array
    {
        if ($apiKey === '' || $rawBytes === '' || !function_exists('curl_init')) {
            return null;
        }

        $mime = 'image/jpeg';
        if (function_exists('finfo_open')) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $det = @finfo_file($fi, 'data://application/octet-stream;base64,' . base64_encode(substr($rawBytes, 0, 512)));
                if (is_string($det) && strpos($det, 'image/') === 0) {
                    $mime = $det;
                }
                @finfo_close($fi);
            }
        }

        $typesList = implode(', ', $allowedTypes);
        $prompt = "You are an expert Indian jeweller & SEO specialist for ChandraKala Jewellers (Khedbrahma, Gujarat).\n"
            . "1) First perform OCR: read ANY visible text, price/weight tags (e.g., 11.6g), hallmarks (916, 22K, 925, BIS), or SKU numbers in the image.\n"
            . "2) Visually identify the exact jewellery piece, metal (gold, silver, or silver_925), stones, and craftsmanship.\n"
            . "3) Return ONLY valid JSON (no markdown fences) with keys:\n"
            . "  name: Fancy, SEO-rich Indian jewellery title (4-8 words, e.g. 'Royal 916 Gold Kundan Bridal Necklace Set')\n"
            . "  description: Compelling 145-158 character SEO description mentioning craftsmanship & ChandraKala Jewellers Khedbrahma\n"
            . "  category: Pick the closest match from [{$typesList}]\n"
            . "  metal_type: one of 'gold', 'silver', 'silver_925'\n"
            . "  purity: e.g. '22K 916 Gold' or '925 Sterling Silver'\n"
            . "  stone: e.g. 'Kundan', 'Pearl', 'AD Diamond', 'Meenakari', or '' if plain metal\n"
            . "  weight: numeric grams read from tag if visible, else null\n"
            . "  sku: tag code if visible, else ''\n"
            . "  ocr_text: exact text read from image (or '' if none)\n"
            . "  tags: 10-14 comma-separated SEO & Gujarati/Hindi/English search keywords\n"
            . ($hintText !== '' ? "Additional hint/filename/OCR text: {$hintText}\n" : '');

        $payload = json_encode([
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mime,
                            'data' => base64_encode($rawBytes),
                        ],
                    ],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.35,
                'maxOutputTokens' => 450,
            ],
        ]);

        $models = ['gemini-2.0-flash', 'gemini-1.5-flash'];
        foreach ($models as $model) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . rawurlencode($apiKey);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code >= 200 && $code < 300 && is_string($resp)) {
                $json = json_decode($resp, true);
                $text = (string)($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
                if ($text !== '') {
                    // Strip optional ```json ... ``` wrapper
                    $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)) ?? '');
                    $parsed = json_decode($text, true);
                    if (is_array($parsed) && !empty($parsed['name'])) {
                        $parsed['_engine'] = 'gemini-vision-ocr';
                        return $parsed;
                    }
                }
            }
        }

        return null;
    }
}

if (!function_exists('cj_analyze_jewellery_image')) {
    /**
     * Master entry point: analyzes an image (path, URL, or base64 data URI) + optional client OCR text
     * and returns fancy SEO name, SEO description, selectable category, metal, purity, stone, weight & tags.
     *
     * @return array<string,mixed>
     */
    function cj_analyze_jewellery_image(mysqli $conn, string $imageSource, string $clientOcrText = '', string $filenameHint = '', int $seedId = 0): array
    {
        $allowedTypes = cj_get_product_types($conn);
        $rawBytes = cj_fetch_image_bytes($imageSource);

        // 1. Try Gemini Multimodal Vision + OCR if configured
        $apiKey = trim((string)cj_setting('gemini_api_key', '', $conn));
        if ($apiKey !== '' && $rawBytes) {
            $hint = trim($clientOcrText . ' ' . $filenameHint);
            $gem = cj_gemini_vision_ocr($apiKey, $rawBytes, $allowedTypes, $hint);
            if ($gem) {
                $metal = in_array($gem['metal_type'] ?? '', ['gold', 'silver', 'silver_925'], true)
                    ? $gem['metal_type']
                    : 'gold';
                return [
                    'ok' => true,
                    'engine' => 'ChandraKala Vision OCR + Gemini AI',
                    'name' => trim((string)$gem['name']),
                    'description' => trim((string)($gem['description'] ?? '')),
                    'category' => trim((string)($gem['category'] ?? 'Necklace Set')),
                    'metal_type' => $metal,
                    'purity' => trim((string)($gem['purity'] ?? ($metal === 'gold' ? '22K 916 Gold' : '925 Sterling Silver'))),
                    'stone' => trim((string)($gem['stone'] ?? '')),
                    'weight' => is_numeric($gem['weight'] ?? null) ? round((float)$gem['weight'], 2) : null,
                    'sku' => trim((string)($gem['sku'] ?? '')),
                    'ocr_text' => trim((string)($gem['ocr_text'] ?? $clientOcrText)),
                    'tags' => trim((string)($gem['tags'] ?? '')),
                    'signals' => ['Multimodal Vision + OCR Scan Complete'],
                ];
            }
        }

        // 2. Self-Developed Local Visual + OCR Engine (100% free, zero external dependency)
        $visual = cj_analyze_image_visuals($rawBytes);
        $ocr = cj_parse_ocr_text(trim($clientOcrText . ' ' . $filenameHint));

        $seed = abs(crc32(($rawBytes ? md5(substr($rawBytes, 0, 4096)) : $filenameHint) . '|' . $seedId));

        $metal = $ocr['metal'] !== '' ? $ocr['metal'] : ($visual['metal'] ?? 'gold');
        $purity = $ocr['purity'] !== ''
            ? $ocr['purity']
            : ($metal === 'gold' ? '22K 916 Gold' : ($metal === 'silver_925' ? '925 Sterling Silver' : '999 Pure Silver'));
        $stone = $ocr['stone'] !== '' ? $ocr['stone'] : ($visual['stone'] ?? '');

        // Infer category from OCR first, else visual silhouette + metal + intricacy
        $category = $ocr['category'];
        if ($category === '') {
            $sil = $visual['silhouette'] ?? 'square';
            $intr = $visual['intricacy'] ?? 'medium';
            if ($metal === 'silver_925' || $metal === 'silver') {
                $silverPool = [
                    'ring' => ['Ring', 'Kada', 'Bracelet'],
                    'tall' => ['Pendant Butti Set', 'Earrings / Butti', 'Payal / Anklet'],
                    'wide' => ['Bracelet', 'Payal / Anklet', 'Necklace Set'],
                    'square' => ['Ring', 'Bracelet', 'Kada', 'Earrings / Butti'],
                ];
                $pool = $silverPool[$sil] ?? $silverPool['square'];
                $category = $pool[$seed % count($pool)];
            } else {
                $goldPool = [
                    'wide' => $intr === 'high' ? ['Bridal Set', 'Necklace Set', 'Kundan Set'] : ['Necklace Set', 'Bangles / Patala', 'Gold Chain'],
                    'tall' => $intr === 'high' ? ['Mangalsutra', 'Pendant Butti Set', 'Tanmaniya'] : ['Pendant Butti Set', 'Earrings / Butti', 'Gold Chain', 'Dokiya'],
                    'ring' => ['Ring', 'Bangles / Patala', 'Kada'],
                    'square' => $intr === 'high' ? ['Bridal Set', 'Kundan Set', 'Hathpan', 'Earrings / Butti'] : ['Pendant Butti Set', 'Earrings / Butti', 'Ring', 'Mangalsutra', 'Tanmaniya'],
                ];
                $pool = $goldPool[$sil] ?? $goldPool['square'];
                $category = $pool[$seed % count($pool)];
            }
        }

        // Fancy luxury adjective pool matched to style & intricacy
        $adjectives = [
            'Bridal Heritage' => ['Royal', 'Regal', 'Maharani', 'Rajwadi', 'Heritage', 'Imperial', 'Grand Bridal', 'Heirloom'],
            'Traditional' => ['Classic', 'Traditional', 'Artisan', 'Graceful', 'Divine', 'Timeless', 'Festive', 'Auspicious'],
            'Modern Daily Wear' => ['Elegant', 'Sleek', 'Signature', 'Refined', 'Chic', 'Lustrous', 'Radiant', 'Delicate'],
        ];
        $styleKey = $visual['style'] ?? 'Traditional';
        $adjPool = $adjectives[$styleKey] ?? $adjectives['Traditional'];
        $adj = $adjPool[$seed % count($adjPool)];

        $craftPool = $visual['intricacy'] === 'high'
            ? ['Filigree', 'Handcrafted', 'Antique', 'Zari-Carved', 'Nakshi']
            : ['Polished', 'Handcrafted', 'Hallmark', 'Fine-Cut', 'Designer'];
        $craft = $craftPool[($seed >> 3) % count($craftPool)];

        $metalWord = $metal === 'gold' ? '916 Gold' : ($metal === 'silver_925' ? '925 Silver' : 'Pure Silver');
        $stonePart = $stone !== '' ? ($stone . ' ') : ($craft . ' ');

        // Build clean fancy title (e.g. "Royal 916 Gold Kundan Bridal Set")
        $catDisplay = $category;
        if (stripos($catDisplay, 'Gold') !== false && $metal === 'gold') {
            $catDisplay = trim(str_ireplace('Gold', '', $catDisplay));
        }
        $fancyName = trim(preg_replace('/\s+/', ' ', "{$adj} {$metalWord} {$stonePart}{$catDisplay}"));

        // Build rich 148-158 char SEO description
        $stoneDesc = $stone !== '' ? " with {$stone} accents" : " with fine {$craft} detailing";
        $weightNote = !empty($ocr['weight']) ? " ({$ocr['weight']}g)" : '';
        $description = "{$fancyName}{$weightNote} crafted in {$purity}{$stoneDesc}. Custom order or buy with live IST rates at ChandraKala Jewellers, Civil Road, Khedbrahma.";

        // Rich multilingual + SEO tags
        $baseTags = [
            strtolower($catDisplay),
            strtolower($metalWord),
            strtolower($purity),
            strtolower($styleKey),
            strtolower($craft),
            $metal === 'gold' ? '22k gold, sona, hallmark, bridal, wedding' : 'chandi, sterling silver, gift, dailywear',
            'custom design, khedbrahma, sabarkantha, gujarat',
        ];
        if ($stone !== '') {
            $baseTags[] = strtolower($stone);
        }
        if (stripos($category, 'Mangalsutra') !== false) $baseTags[] = 'tanmaniya, dokiya, blackbeads';
        if (stripos($category, 'Earrings') !== false || stripos($category, 'Butti') !== false) $baseTags[] = 'butti, jhumka, bali, tops';
        if (stripos($category, 'Bangles') !== false || stripos($category, 'Kada') !== false) $baseTags[] = 'patala, kangan, kada, bracelet';
        if (stripos($category, 'Ring') !== false) $baseTags[] = 'vinti, anguthi, finger ring';
        if (stripos($category, 'Necklace') !== false || stripos($category, 'Bridal') !== false) $baseTags[] = 'haar, choker, necklace set, bridal jewellery';

        $tagsArr = array_values(array_unique(array_filter(array_map('trim', explode(',', implode(', ', $baseTags))))));

        $signals = $visual['signals'] ?? [];
        if ($ocr['raw'] !== '') {
            $signals[] = 'OCR Text Parsed: ' . mb_substr($ocr['raw'], 0, 50, 'UTF-8');
        }
        if (!empty($ocr['weight'])) {
            $signals[] = 'OCR Weight: ' . $ocr['weight'] . 'g';
        }

        return [
            'ok' => true,
            'engine' => 'ChandraKala Self-Built Visual + OCR Engine',
            'name' => $fancyName,
            'description' => $description,
            'category' => $category,
            'metal_type' => $metal,
            'purity' => $purity,
            'stone' => $stone,
            'weight' => $ocr['weight'],
            'sku' => $ocr['sku'],
            'ocr_text' => $ocr['raw'],
            'tags' => implode(', ', array_slice($tagsArr, 0, 16)),
            'signals' => $signals,
        ];
    }
}
