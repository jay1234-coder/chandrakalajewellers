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
            'gold' => ['gold', 'golden', 'sona', 'sonu', 'gld', 'golde', 'hallmark', '916', '22k', '22kt', '20k', '18k', '14k', 'yellow'],
            'silver' => ['silver', 'silvar', 'sliver', 'silvr', 'chandi', 'silbery', 'pure'],
            'silver_925' => ['925', '92.5', 'sterling', 'sterling925', 'silver925', 'oxidised', 'oxidized', 'italian'],
            'ring' => ['ring', 'rings', 'anguthi', 'angoothi', 'anguthiyu', 'vinti', 'viti', 'finger', 'solitaire', 'band'],
            'chain' => ['chain', 'chains', 'haar', 'necklace', 'necklaces', 'pendant', 'pendent', 'pendants', 'locket', 'choker', 'dokiya', 'magmala', 'kanthi', 'set'],
            'earring' => ['earring', 'earrings', 'ear', 'bali', 'baliya', 'jhumka', 'jhumkas', 'tops', 'butti', 'buti', 'kanchain', 'stud', 'studs'],
            'bangle' => ['bangle', 'bangles', 'kada', 'kadas', 'bracelet', 'bracelets', 'kangan', 'patala', 'patla', 'chudi', 'hathpan', 'pandora'],
            'mangalsutra' => ['mangalsutra', 'mangal', 'sutra', 'tanmaniya', 'thali', 'blackbeads', 'dokiya'],
            'anklet' => ['anklet', 'anklets', 'payal', 'pajeb', 'zanzar', 'jhanjhar'],
            'nose' => ['nose', 'nath', 'nosering', 'nose-pin', 'nosepin', 'chuni'],
            'kundan' => ['kundan', 'polki', 'jadau', 'meenakari', 'antique', 'temple', 'royal', 'heritage', 'filigree'],
            'diamond' => ['diamond', 'ad', 'cz', 'crystal', 'stone', 'pearl', 'moti', 'ruby', 'emerald', 'gem'],
            'kids' => ['kids', 'kid', 'baby', 'children', 'child'],
            'mens' => ['mens', 'men', 'man', 'gents', 'gentleman', 'boys', 'bmw', 'mercedes'],
            'womens' => ['womens', 'women', 'woman', 'ladies', 'lady', 'bridal', 'dulhan', 'girls'],
            'light' => ['light', 'lightweight', 'daily', 'dailywear', 'simple', 'minimal', 'modern', 'sleek'],
            'heavy' => ['heavy', 'bridal', 'wedding', 'shadi', 'party', 'occasion', 'festive', 'traditional', 'grand'],
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
        // Prefix match only for meaningful stems (min 4 chars and >=50% length ratio)
        // so short words like "man" never falsely match "mangalsutra"
        $la = mb_strlen($a, 'UTF-8');
        $lb = mb_strlen($b, 'UTF-8');
        if ($la >= 4 && $lb >= 4 && (str_starts_with($b, $a) || str_starts_with($a, $b))) {
            $ratio = min($la, $lb) / max($la, $lb);
            if ($ratio >= 0.50) {
                return true;
            }
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

if (!function_exists('cj_catalogue_ai_search')) {
    /**
     * Regex + Synonym + Fuzzy AI Search for the Custom Design Catalogue (`products` table).
     *
     * @param list<array<string,mixed>> $items
     * @return array{items:list<array<string,mixed>>,intents:list<string>,tokens:list<string>}
     */
    function cj_catalogue_ai_search(array $items, string $query, string $categoryFilter = ''): array
    {
        $normQ = cj_gallery_normalize($query);
        $catFilterNorm = mb_strtolower(trim($categoryFilter), 'UTF-8');

        // Pre-filter by category chip if selected
        if ($catFilterNorm !== '' && $catFilterNorm !== 'all') {
            $synMap = cj_gallery_synonym_map();
            $catKeywords = [$catFilterNorm];
            foreach ($synMap as $key => $words) {
                if (str_contains($catFilterNorm, $key) || in_array($catFilterNorm, $words, true)) {
                    $catKeywords = array_merge($catKeywords, $words);
                }
            }
            $catKeywords = array_values(array_unique(array_filter($catKeywords)));
            $catRegex = '/(?:' . implode('|', array_map(static fn($w) => preg_quote($w, '/'), $catKeywords)) . ')/iu';

            $items = array_values(array_filter($items, static function ($row) use ($catRegex, $catFilterNorm) {
                $cat = mb_strtolower(trim((string)($row['category'] ?? '')), 'UTF-8');
                if ($cat !== '' && ($cat === $catFilterNorm || str_contains($cat, $catFilterNorm) || str_contains($catFilterNorm, $cat))) {
                    return true;
                }
                $hay = implode(' ', [
                    (string)($row['name'] ?? ''),
                    (string)($row['description'] ?? ''),
                    (string)($row['category'] ?? ''),
                    (string)($row['tags'] ?? ''),
                    (string)($row['ocr_text'] ?? ''),
                ]);
                return (bool)preg_match($catRegex, $hay);
            }));
        }

        if ($normQ === '') {
            return ['items' => $items, 'intents' => [], 'tokens' => []];
        }

        // Strip common conversational filler words ("show me", "looking for", "i want", "best", "designs")
        $cleanQ = preg_replace('/\b(?:show|me|find|search|looking|for|want|need|buy|get|give|please|some|best|latest|new|nice|good|design|designs|photo|photos|image|images|jewellery|jewelry|piece|pieces|in|of|with|and|or|the|a|an|for)\b/iu', ' ', $normQ) ?? $normQ;
        $tokens = cj_gallery_tokens($cleanQ);
        if (empty($tokens)) {
            $tokens = cj_gallery_tokens($normQ);
        }

        $synMap = cj_gallery_synonym_map();
        $intents = [];
        $expandedGroups = [];
        foreach ($tokens as $tok) {
            $group = [$tok];
            foreach ($synMap as $key => $words) {
                foreach ($words as $w) {
                    if ($tok === $w || (mb_strlen($tok, 'UTF-8') >= 4 && mb_strlen($w, 'UTF-8') >= 4 && cj_gallery_token_close($tok, $w))) {
                        $group = array_merge($group, $words);
                        $intents[] = ucfirst(str_replace('_', ' ', $key));
                        break;
                    }
                }
            }
            $expandedGroups[$tok] = array_values(array_unique($group));
        }
        $intents = array_values(array_unique($intents));

        $scored = [];
        foreach ($items as $row) {
            $name = cj_gallery_normalize((string)($row['name'] ?? ''));
            $cat = cj_gallery_normalize((string)($row['category'] ?? ''));
            $tags = cj_gallery_normalize((string)($row['tags'] ?? ''));
            $desc = cj_gallery_normalize((string)($row['description'] ?? ''));
            $ocr = cj_gallery_normalize((string)($row['ocr_text'] ?? ''));
            $file = cj_gallery_normalize((string)($row['image_filename'] ?? ''));
            $hay = trim("$name $cat $tags $desc $ocr $file");
            $hayTokens = cj_gallery_tokens($hay);

            $score = 0.0;
            $matchedTokens = 0;

            // Exact full phrase regex boost
            $phraseRegex = '/' . preg_quote($normQ, '/') . '/iu';
            if (preg_match($phraseRegex, $name)) {
                $score += 65;
            } elseif (preg_match($phraseRegex, $hay)) {
                $score += 35;
            }

            foreach ($tokens as $tok) {
                $group = $expandedGroups[$tok] ?? [$tok];
                $regex = '/\b(?:' . implode('|', array_map(static fn($w) => preg_quote($w, '/'), $group)) . ')/iu';
                $hit = false;

                if (preg_match($regex, $name)) {
                    $score += 32;
                    $hit = true;
                } elseif (preg_match($regex, $cat)) {
                    $score += 28;
                    $hit = true;
                } elseif (preg_match($regex, $tags)) {
                    $score += 22;
                    $hit = true;
                } elseif (preg_match($regex, $ocr)) {
                    $score += 20;
                    $hit = true;
                } elseif (preg_match($regex, $desc)) {
                    $score += 14;
                    $hit = true;
                } elseif (str_contains($hay, $tok)) {
                    $score += 12;
                    $hit = true;
                } else {
                    // Typo-tolerant fuzzy fallback
                    foreach ($hayTokens as $ht) {
                        if (cj_gallery_token_close($tok, $ht)) {
                            $score += 10;
                            $hit = true;
                            break;
                        }
                    }
                }

                if ($hit) {
                    $matchedTokens++;
                }
            }

            if ($matchedTokens === 0 || $score < 10) {
                continue;
            }

            // Bonus when ALL user query tokens matched
            if ($matchedTokens === count($tokens) && count($tokens) > 1) {
                $score += 25;
            }

            $row['_ai_score'] = $score;
            $scored[] = $row;
        }

        usort($scored, static function ($a, $b) {
            $cmp = ($b['_ai_score'] <=> $a['_ai_score']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
        });

        return ['items' => $scored, 'intents' => $intents, 'tokens' => $tokens];
    }
}
