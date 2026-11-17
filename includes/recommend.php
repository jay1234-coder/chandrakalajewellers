<?php
/**
 * On-site "AI" recommendations — scored by metal, category, price band, and also-bought.
 * No external ML API.
 */
require_once __DIR__ . '/settings.php';

if (!function_exists('cj_recommend_score')) {
    /**
     * @param array<string,mixed> $candidate
     * @param array<string,mixed> $source
     * @param array<int,int> $alsoBoughtCounts product_id => count
     */
    function cj_recommend_score(array $candidate, array $source, array $alsoBoughtCounts = []): int
    {
        $score = 0;
        $srcMetal = (string) ($source['metal_type'] ?? '');
        $candMetal = (string) ($candidate['metal_type'] ?? '');
        if ($srcMetal !== '' && $candMetal === $srcMetal) {
            $score += 20;
        }
        $srcCat = strtolower(trim((string) ($source['category'] ?? '')));
        $candCat = strtolower(trim((string) ($candidate['category'] ?? '')));
        if ($srcCat !== '' && $candCat !== '' && $srcCat === $candCat) {
            $score += 40;
        }
        $srcPrice = (float) ($source['cached_price'] ?? $source['_price'] ?? 0);
        $candPrice = (float) ($candidate['cached_price'] ?? $candidate['_price'] ?? 0);
        if ($srcPrice > 0 && $candPrice > 0) {
            $delta = abs($candPrice - $srcPrice) / $srcPrice;
            if ($delta <= 0.25) {
                $score += 15;
            } elseif ($delta <= 0.5) {
                $score += 8;
            }
        }
        $srcW = (float) ($source['weight'] ?? 0);
        $candW = (float) ($candidate['weight'] ?? 0);
        if ($srcW > 0 && $candW > 0) {
            $wDelta = abs($candW - $srcW) / $srcW;
            if ($wDelta <= 0.2) {
                $score += 10;
            }
        }
        $cid = (int) ($candidate['id'] ?? 0);
        if ($cid > 0 && isset($alsoBoughtCounts[$cid])) {
            $score += min(25, 8 + (int) $alsoBoughtCounts[$cid] * 3);
        }
        if (empty($candidate['is_sold_out'])) {
            $score += 5;
        }
        return $score;
    }
}

if (!function_exists('cj_recommend_products')) {
    /**
     * @param array<string,mixed> $product
     * @return list<array<string,mixed>>
     */
    function cj_recommend_products(mysqli $conn, array $product, int $limit = 6): array
    {
        $id = (int) ($product['id'] ?? 0);
        if ($id <= 0) {
            return [];
        }
        $limit = max(2, min(12, $limit));
        $also = [];
        $ab = $conn->query(
            'SELECT oi2.product_id AS pid, COUNT(*) AS c
             FROM order_items oi1
             INNER JOIN order_items oi2 ON oi1.order_id = oi2.order_id AND oi2.product_id <> oi1.product_id
             WHERE oi1.product_id = ' . $id . '
             GROUP BY oi2.product_id'
        );
        if ($ab) {
            while ($row = $ab->fetch_assoc()) {
                $also[(int) $row['pid']] = (int) $row['c'];
            }
        }

        $metal = $conn->real_escape_string((string) ($product['metal_type'] ?? ''));
        $cat = $conn->real_escape_string((string) ($product['category'] ?? ''));
        $sql = "SELECT id, name, image, weight, metal_type, category, cached_price, is_sold_out
                FROM jewellery_items
                WHERE id <> {$id} AND is_sold_out = 0";
        if ($metal !== '') {
            $sql .= " AND (metal_type = '{$metal}'";
            if ($cat !== '') {
                $sql .= " OR category = '{$cat}'";
            }
            $sql .= ')';
        }
        $sql .= ' ORDER BY id DESC LIMIT 48';
        $res = $conn->query($sql);
        if (!$res) {
            return [];
        }
        $scored = [];
        while ($row = $res->fetch_assoc()) {
            $row['_score'] = cj_recommend_score($row, $product, $also);
            $scored[] = $row;
        }
        usort($scored, static function ($a, $b) {
            $d = ((int) $b['_score']) <=> ((int) $a['_score']);
            if ($d !== 0) {
                return $d;
            }
            return ((int) $b['id']) <=> ((int) $a['id']);
        });
        return array_slice($scored, 0, $limit);
    }
}
