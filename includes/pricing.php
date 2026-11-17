<?php
/**
 * Single source of truth for metal price calculation.
 *
 * Admin rate fields are labelled "per 1g". Customer-facing PHP already used:
 *   metal_value = counted_rate_per_gram * weight_grams
 *   + making % for gold, or for silver when apply_making_to_silver is on
 *
 * Counted rate is the board/market rate after a per-metal operator chain
 * (default gold: rate × 0.9166 ÷ 1.03 for 22K after 3% GST). Empty steps
 * keep the full board rate.
 *
 * JavaScript previously divided by 10 (per-10g assumption). That is wrong for
 * this store and made admin previews ~10× lower than gallery/cart prices.
 */

require_once __DIR__ . '/settings.php';

if (!function_exists('cj_rate_making_percent')) {
    function cj_rate_making_percent(array $rate_data): float
    {
        if (array_key_exists('making_charges', $rate_data)) {
            return (float) $rate_data['making_charges'];
        }
        if (array_key_exists('making_charges_percent', $rate_data)) {
            return (float) $rate_data['making_charges_percent'];
        }
        if (array_key_exists('manual_making_charges_percent', $rate_data)) {
            return (float) $rate_data['manual_making_charges_percent'];
        }
        return 0.0;
    }
}

if (!function_exists('cj_rate_apply_making_to_silver')) {
    function cj_rate_apply_making_to_silver(array $rate_data): bool
    {
        return !empty($rate_data['apply_making_to_silver'])
            || !empty($rate_data['manual_apply_making_to_silver']);
    }
}

if (!function_exists('cj_metal_base_rate')) {
    function cj_metal_base_rate(string $metal, array $rate_data): float
    {
        $metal = strtolower(trim($metal));
        if ($metal === 'gold') {
            return (float) ($rate_data['gold_rate'] ?? 0);
        }
        if ($metal === 'silver') {
            return (float) ($rate_data['silver_rate'] ?? 0);
        }
        if ($metal === 'silver_925') {
            return (float) ($rate_data['silver_925_rate'] ?? 0);
        }
        if ($metal === 'copper') {
            return (float) ($rate_data['copper_rate'] ?? 0);
        }
        return 0.0;
    }
}

if (!function_exists('cj_rate_formula_metals')) {
    /** @return list<string> */
    function cj_rate_formula_metals(): array
    {
        return ['gold', 'silver', 'silver_925', 'copper'];
    }
}

if (!function_exists('cj_rate_formula_defaults')) {
    /**
     * Gold jewellery is 22K (91.66%) and board rates often include 3% GST.
     * Counted gold rate = board × 0.9166 ÷ 1.03
     *
     * @return array<string, list<array{op:string,value:float}>>
     */
    function cj_rate_formula_defaults(): array
    {
        return [
            'gold' => [
                ['op' => '*', 'value' => 0.9166],
                ['op' => '/', 'value' => 1.03],
            ],
            'silver' => [],
            'silver_925' => [],
            'copper' => [],
        ];
    }
}

if (!function_exists('cj_rate_formula_default_json')) {
    function cj_rate_formula_default_json(): string
    {
        return json_encode(cj_rate_formula_defaults()) ?: '{}';
    }
}

if (!function_exists('cj_normalize_rate_formula')) {
    /**
     * @param mixed $raw
     * @return array<string, list<array{op:string,value:float}>>
     */
    function cj_normalize_rate_formula($raw): array
    {
        $out = [
            'gold' => [],
            'silver' => [],
            'silver_925' => [],
            'copper' => [],
        ];
        if (!is_array($raw)) {
            return $out;
        }
        $allowedOps = ['+', '-', '*', '/'];
        foreach ($out as $metal => $_) {
            $steps = $raw[$metal] ?? null;
            if (!is_array($steps)) {
                continue;
            }
            foreach ($steps as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $op = (string) ($step['op'] ?? '');
                if (!in_array($op, $allowedOps, true)) {
                    continue;
                }
                $out[$metal][] = [
                    'op' => $op,
                    'value' => (float) ($step['value'] ?? 0),
                ];
            }
        }
        return $out;
    }
}

if (!function_exists('cj_rate_formula_map')) {
    /**
     * @param array<string,mixed>|null $override
     * @return array<string, list<array{op:string,value:float}>>
     */
    function cj_rate_formula_map(?array $override = null): array
    {
        if (is_array($override)) {
            return cj_normalize_rate_formula($override);
        }
        $json = function_exists('cj_setting') ? cj_setting('rate_formula_json', '') : '';
        if ($json === '') {
            return cj_rate_formula_defaults();
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return cj_rate_formula_defaults();
        }
        return cj_normalize_rate_formula($decoded);
    }
}

if (!function_exists('cj_apply_rate_formula')) {
    /**
     * @param list<array{op?:string,value?:float|int|string}> $steps
     */
    function cj_apply_rate_formula(float $base, array $steps): float
    {
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $op = (string) ($step['op'] ?? '');
            $value = (float) ($step['value'] ?? 0);
            if ($op === '+') {
                $base += $value;
            } elseif ($op === '-') {
                $base -= $value;
            } elseif ($op === '*') {
                $base *= $value;
            } elseif ($op === '/') {
                if (abs($value) < 1e-12) {
                    continue;
                }
                $base /= $value;
            }
        }
        return $base;
    }
}

if (!function_exists('cj_rate_formula_for_metal')) {
    /**
     * @param array<string,mixed> $rate_data
     * @return list<array{op:string,value:float}>
     */
    function cj_rate_formula_for_metal(string $metal, array $rate_data): array
    {
        $metal = strtolower(trim($metal));
        $map = array_key_exists('rate_formula', $rate_data)
            ? cj_normalize_rate_formula($rate_data['rate_formula'])
            : cj_rate_formula_map();
        return $map[$metal] ?? [];
    }
}

if (!function_exists('cj_effective_metal_rate')) {
    /**
     * Board/market rate after the configured operator chain.
     *
     * @param array<string,mixed> $rate_data
     */
    function cj_effective_metal_rate(string $metal, array $rate_data): float
    {
        $base = cj_metal_base_rate($metal, $rate_data);
        if ($base <= 0) {
            return 0.0;
        }
        return cj_apply_rate_formula($base, cj_rate_formula_for_metal($metal, $rate_data));
    }
}

if (!function_exists('cj_rate_formula_label')) {
    /**
     * @param list<array{op:string,value:float}> $steps
     */
    function cj_rate_formula_label(array $steps): string
    {
        if ($steps === []) {
            return 'full rate';
        }
        $symbols = ['*' => '×', '/' => '÷', '+' => '+', '-' => '−'];
        $parts = [];
        foreach ($steps as $step) {
            $sym = $symbols[$step['op']] ?? $step['op'];
            $num = rtrim(rtrim(number_format((float) $step['value'], 6, '.', ''), '0'), '.');
            $parts[] = $sym . ' ' . $num;
        }
        return implode(' ', $parts);
    }
}

if (!function_exists('cj_calculate_price')) {
    /**
     * @param array<string,mixed> $rate_data
     */
    function cj_calculate_price(string $metal, $weight, array $rate_data): float
    {
        $weight = (float) $weight;
        if ($weight <= 0) {
            return 0.0;
        }

        $metal = strtolower(trim($metal));
        $base = cj_effective_metal_rate($metal, $rate_data);
        if ($base <= 0) {
            return 0.0;
        }

        $price = $base * $weight;
        $applyMaking = ($metal === 'gold')
            || (($metal === 'silver' || $metal === 'silver_925') && cj_rate_apply_making_to_silver($rate_data));

        if ($applyMaking) {
            $price += $price * (cj_rate_making_percent($rate_data) / 100);
        }

        return $price;
    }
}

if (!function_exists('cj_calculate_price_rounded')) {
    function cj_calculate_price_rounded(string $metal, $weight, array $rate_data): int
    {
        return (int) round(cj_calculate_price($metal, $weight, $rate_data));
    }
}

if (!function_exists('calculate_price')) {
    /** @param array<string,mixed> $rate_data */
    function calculate_price($metal, $weight, $rate_data)
    {
        return cj_calculate_price((string) $metal, $weight, is_array($rate_data) ? $rate_data : []);
    }
}

if (!function_exists('calculate_item_price_from_rates')) {
    /** Admin helper: same formula, argument order used by admin.php. */
    function calculate_item_price_from_rates($weight, $metal_type, $rates)
    {
        return cj_calculate_price((string) $metal_type, $weight, is_array($rates) ? $rates : []);
    }
}
