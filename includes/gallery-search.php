<?php
/**
 * Gallery "super search" — synonyms, filter intents, typo-tolerant matching.
 */

if (!function_exists('cj_gallery_normalize')) {
    function cj_gallery_normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = str_replace(['₹', ',', '—', '–'], ['rs ', ' ', ' ', ' '], $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }
}

if (!function_exists('cj_gallery_tokens')) {
    /** @return list<string> */
    function cj_gallery_tokens(string $text): array
    {
        $text = cj_gallery_normalize($text);
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/[^a-z0-9.]+/u', $text) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p, '.');
            if ($p !== '' && mb_strlen($p, 'UTF-8') >= 1) {
                $out[] = $p;
            }
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('cj_gallery_synonym_map')) {
    /** @return array<string, list<string>> */
    function cj_gallery_synonym_map(): array
    {
        return [
            'gold' => ['gold', 'golden', 'sona', 'gld', 'golde', 'hallmark', '22k', '18k', '14k'],
            'silver' => ['silver', 'silvar', 'sliver', 'silvr', 'chandi', 'silbery'],
            'silver_925' => ['925', '92.5', 'sterling', 'sterling925', 'silver925', 'oxidised', 'oxidized'],
            'ring' => ['ring', 'rings', 'anguthi', 'angoothi', 'anguthiyu', 'finger'],
            'chain' => ['chain', 'chains', 'haar', 'necklace', 'necklaces', 'pendant', 'pendants', 'locket'],
            'earring' => ['earring', 'earrings', 'ear', 'bali', 'jhumka', 'jhumkas', 'tops'],
            'bangle' => ['bangle', 'bangles', 'kada', 'kadas', 'bracelet', 'bracelets', 'kangan'],
            'mangalsutra' => ['mangalsutra', 'mangal', 'thali', 'blackbeads'],
            'anklet' => ['anklet', 'anklets', 'payal', 'pajeb'],
            'nose' => ['nose', 'nath', 'nosering', 'nose-pin', 'nosepin'],
            'kids' => ['kids', 'kid', 'baby', 'children', 'child'],
            'mens' => ['mens', 'men', 'man', 'gents', 'gentleman'],
            'womens' => ['womens', 'women', 'woman', 'ladies', 'lady'],
            'light' => ['light', 'lightweight', 'daily', 'dailywear', 'simple'],
            'heavy' => ['heavy', 'bridal', 'wedding', 'party', 'occasion'],
            'sold' => ['sold', 'soldout', 'unavailable'],
            'available' => ['available', 'instock', 'stock'],
        ];
    }
}

if (!function_exists('cj_gallery_token_close')) {
    function cj_gallery_token_close(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if ($a === '' || $b === '') {
            return false;
        }
        // Prefix / contains for short jewellery stems
        if (mb_strlen($a, 'UTF-8') >= 3 && (str_starts_with($b, $a) || str_starts_with($a, $b))) {
            return true;
        }
        if (function_exists('levenshtein')) {
            $la = strlen($a);
            $lb = strlen($b);
            if ($la <= 2 || $lb <= 2) {
                return false;
            }
            $maxLen = max($la, $lb);
            $dist = levenshtein($a, $b);
            if ($maxLen <= 4) {
                return $dist <= 1;
            }
            if ($maxLen <= 7) {
                return $dist <= 2;
            }
            return $dist <= 3;
        }
        similar_text($a, $b, $pct);
        return $pct >= 78;
    }
}

if (!function_exists('cj_gallery_parse_query')) {
    /**
     * @return array{raw:string,tokens:list<string>,metal:?string,min:?float,max:?float,want_sold:?bool,boosts:list<string>}
     */
    function cj_gallery_parse_query(string $q): array
    {
        $raw = trim($q);
        $norm = cj_gallery_normalize($raw);
        $metal = null;
        $min = null;
        $max = null;
        $wantSold = null;
        $boosts = [];

        // Price intents: under/below/less than / up to / max
        if (preg_match('/\b(?:under|below|less\s*than|upto|up\s*to|max|within)\s*(?:rs\.?|₹)?\s*(\d+(?:\.\d+)?)\s*(k|thousand)?\b/u', $norm, $m)) {
            $val = (float)$m[1];
            if (!empty($m[2])) {
                $val *= 1000;
            }
            $max = $val;
        }
        if (preg_match('/\b(?:above|over|more\s*than|min|from)\s*(?:rs\.?|₹)?\s*(\d+(?:\.\d+)?)\s*(k|thousand)?\b/u', $norm, $m)) {
            $val = (float)$m[1];
            if (!empty($m[2])) {
                $val *= 1000;
            }
            $min = $val;
        }
        if (preg_match('/\b(?:rs\.?|₹)\s*(\d+(?:\.\d+)?)\s*(k|thousand)?\b/u', $norm, $m) && $max === null && $min === null) {
            $val = (float)$m[1];
            if (!empty($m[2])) {
                $val *= 1000;
            }
            // bare amount → treat as soft max ±20% band via scoring later; store as max hint
            $max = $val * 1.15;
            $min = $val * 0.55;
        }
        if (preg_match('/\b(cheap|budget|affordable|low\s*price)\b/u', $norm)) {
            $boosts[] = 'cheap';
        }
        if (preg_match('/\b(expensive|premium|costly|heavy)\b/u', $norm)) {
            $boosts[] = 'expensive';
        }

        $syn = cj_gallery_synonym_map();
        foreach ($syn['gold'] as $w) {
            if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $norm)) {
                $metal = 'gold';
                break;
            }
        }
        if ($metal === null) {
            foreach ($syn['silver_925'] as $w) {
                if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $norm)) {
                    $metal = 'silver_925';
                    break;
                }
            }
        }
        if ($metal === null) {
            foreach ($syn['silver'] as $w) {
                if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $norm)) {
                    $metal = 'silver';
                    break;
                }
            }
        }

        if (preg_match('/\b(sold\s*out|unavailable)\b/u', $norm)) {
            $wantSold = true;
        } elseif (preg_match('/\b(available|in\s*stock)\b/u', $norm)) {
            $wantSold = false;
        }

        foreach ($syn as $key => $words) {
            if (in_array($key, ['gold', 'silver', 'silver_925', 'sold', 'available'], true)) {
                continue;
            }
            foreach ($words as $w) {
                if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $norm)) {
                    $boosts[] = $key;
                    break;
                }
            }
        }

        return [
            'raw' => $raw,
            'tokens' => cj_gallery_tokens($norm),
            'metal' => $metal,
            'min' => $min,
            'max' => $max,
            'want_sold' => $wantSold,
            'boosts' => array_values(array_unique($boosts)),
        ];
    }
}

if (!function_exists('cj_gallery_item_haystack')) {
    function cj_gallery_item_haystack(array $row, string $metalDisplay): string
    {
        $bits = [
            (string)($row['name'] ?? ''),
            (string)($row['description'] ?? ''),
            (string)($row['tags'] ?? ''),
            (string)($row['metal_type'] ?? ''),
            $metalDisplay,
            (string)($row['weight'] ?? '') . ' g',
            (string)($row['weight'] ?? '') . 'g',
            'rs ' . (string)($row['_price'] ?? ''),
            !empty($row['is_sold_out']) ? 'sold out' : 'available in stock',
        ];
        $syn = cj_gallery_synonym_map();
        $mt = (string)($row['metal_type'] ?? '');
        if (isset($syn[$mt])) {
            $bits = array_merge($bits, $syn[$mt]);
        }
        return cj_gallery_normalize(implode(' ', $bits));
    }
}

if (!function_exists('cj_gallery_score_item')) {
    /**
     * @param array{raw:string,tokens:list<string>,metal:?string,min:?float,max:?float,want_sold:?bool,boosts:list<string>} $parsed
     */
    function cj_gallery_score_item(array $row, array $parsed, string $metalDisplay): float
    {
        if ($parsed['raw'] === '') {
            return 1.0;
        }

        $hay = cj_gallery_item_haystack($row, $metalDisplay);
        $hayTokens = cj_gallery_tokens($hay);
        $score = 0.0;
        $name = cj_gallery_normalize((string)($row['name'] ?? ''));
        $normRaw = cj_gallery_normalize($parsed['raw']);

        if ($normRaw !== '' && str_contains($name, $normRaw)) {
            $score += 120;
        } elseif ($normRaw !== '' && str_contains($hay, $normRaw)) {
            $score += 70;
        }

        foreach ($parsed['tokens'] as $tok) {
            if ($tok === '' || is_numeric($tok) && (float)$tok > 50) {
                // skip bare large numbers already handled as price
                if (is_numeric($tok) && (float)$tok >= 100) {
                    continue;
                }
            }
            $best = 0.0;
            if (str_contains($name, $tok)) {
                $best = 40;
            } elseif (str_contains($hay, $tok)) {
                $best = 22;
            } else {
                foreach ($hayTokens as $ht) {
                    if (cj_gallery_token_close($tok, $ht)) {
                        $best = max($best, 16);
                    }
                }
                // synonym expand
                foreach (cj_gallery_synonym_map() as $canon => $words) {
                    $tokIsSyn = false;
                    foreach ($words as $w) {
                        if (cj_gallery_token_close($tok, $w)) {
                            $tokIsSyn = true;
                            break;
                        }
                    }
                    if ($tokIsSyn) {
                        foreach ($words as $w) {
                            if (str_contains($hay, $w) || in_array($w, $hayTokens, true)) {
                                $best = max($best, 18);
                                break;
                            }
                        }
                        if ($canon === ($row['metal_type'] ?? '')) {
                            $best = max($best, 28);
                        }
                    }
                }
            }
            $score += $best;
            if ($best <= 0 && mb_strlen($tok, 'UTF-8') >= 4) {
                // soft penalty for unmatched meaningful tokens
                $score -= 4;
            }
        }

        if ($parsed['metal'] !== null) {
            if (($row['metal_type'] ?? '') === $parsed['metal']) {
                $score += 35;
            } else {
                $score -= 25;
            }
        }

        $price = (float)($row['_price'] ?? 0);
        if ($parsed['min'] !== null && $price < $parsed['min']) {
            $score -= 40;
        }
        if ($parsed['max'] !== null && $price > $parsed['max']) {
            $score -= 40;
        }
        if ($parsed['min'] !== null && $parsed['max'] !== null && $price >= $parsed['min'] && $price <= $parsed['max']) {
            $score += 20;
        }

        $sold = !empty($row['is_sold_out']);
        if ($parsed['want_sold'] === true) {
            $score += $sold ? 30 : -50;
        } elseif ($parsed['want_sold'] === false) {
            $score += $sold ? -50 : 12;
        }

        foreach ($parsed['boosts'] as $b) {
            if ($b === 'cheap') {
                $score += max(0, 25 - ($price / 800));
            } elseif ($b === 'expensive') {
                $score += min(30, $price / 2000);
            } elseif (str_contains($hay, $b)) {
                $score += 14;
            } else {
                $syn = cj_gallery_synonym_map()[$b] ?? [];
                foreach ($syn as $w) {
                    if (str_contains($hay, $w)) {
                        $score += 14;
                        break;
                    }
                }
            }
        }

        return $score;
    }
}

if (!function_exists('cj_gallery_filter_catalog')) {
    /**
     * @param list<array<string,mixed>> $catalog
     * @return list<array<string,mixed>>
     */
    function cj_gallery_filter_catalog(array $catalog, string $q, callable $metalNameFn): array
    {
        $parsed = cj_gallery_parse_query($q);
        if ($parsed['raw'] === '') {
            return $catalog;
        }

        $scored = [];
        foreach ($catalog as $row) {
            $metalDisplay = $metalNameFn((string)($row['metal_type'] ?? ''));
            $score = cj_gallery_score_item($row, $parsed, $metalDisplay);
            if ($score < 12) {
                continue;
            }
            $row['_search_score'] = $score;
            $scored[] = $row;
        }

        usort($scored, static function ($a, $b) {
            $cmp = ($b['_search_score'] <=> $a['_search_score']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return (int)$b['id'] <=> (int)$a['id'];
        });

        return $scored;
    }
}
