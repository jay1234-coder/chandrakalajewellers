<?php
/**
 * ChandraKala Jewellers — Live Shuffle Products API
 * Returns 8 random inventory products for instant client-side reshuffling on index1.php
 */
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/img-helper.php';
require_once dirname(__DIR__, 2) . '/includes/pricing.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!function_exists('get_metal_name')) {
    function get_metal_name($metal) {
        $map = [
            'gold' => 'Gold',
            'silver' => 'Silver',
            'silver_925' => '925 Silver'
        ];
        return $map[$metal] ?? 'Unknown';
    }
}

try {
    $standard_rates = cj_get_standard_rates($conn);
    $manual_rates = cj_get_manual_rates($conn);

    // Pick 8 random products from live inventory (prioritizing in-stock items)
    $items = $conn->query("SELECT * FROM jewellery_items WHERE COALESCE(is_sold_out, 0) = 0 ORDER BY RAND() LIMIT 8");
    if (!$items || $items->num_rows === 0) {
        $items = $conn->query("SELECT * FROM jewellery_items ORDER BY is_sold_out ASC, RAND() LIMIT 8");
    }

    $cardsHtml = '';
    if ($items && $items->num_rows > 0) {
        $delay = 0;
        $imgIndex = 0;
        while ($row = $items->fetch_assoc()) {
            $rates_to_use = !empty($row['use_manual_rates']) ? $manual_rates : $standard_rates;
            $final_price = calculate_price($row['metal_type'], $row['weight'], $rates_to_use);
            $rounded_final_price = round($final_price);
            $metal_display = get_metal_name($row['metal_type']);
            $delayClass = $delay > 0 ? ' reveal-delay-' . $delay : '';
            $delay = min($delay + 1, 3);
            $imgIndex++;

            ob_start();
            ?>
            <div class="product reveal<?= $delayClass ?><?= !empty($row['is_sold_out']) ? ' is-sold-out' : '' ?> metal-<?= htmlspecialchars($row['metal_type']) ?>" style="animation:fadeIn 0.35s ease;">
                <a href="product?id=<?= (int)$row['id'] ?>" class="product-media-link">
                    <?php if (!empty($row['is_sold_out'])): ?>
                        <span class="sold-out-badge">Sold Out</span>
                    <?php endif; ?>
                    <span class="metal-badge metal-badge-<?= htmlspecialchars($row['metal_type']) ?>"><?= $metal_display ?></span>
                    <?php if (function_exists('cj_feature') && cj_feature('feat_wishlist') && empty($row['is_sold_out'])): ?>
                        <button type="button" class="cj-wish-btn" data-product-id="<?= (int)$row['id'] ?>" onclick="event.preventDefault(); event.stopPropagation(); toggleWishlist(<?= (int)$row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>');" aria-label="Add to wishlist">♥</button>
                    <?php endif; ?>
                    <img
                        src="<?= htmlspecialchars(cj_img_url($row['image'], 600)) ?>"
                        alt="<?= htmlspecialchars($row['name']) ?> — gold silver jewellery Khedbrahma"
                        width="400"
                        height="400"
                        decoding="async"
                        loading="lazy"
                    >
                </a>
                <h3><?= htmlspecialchars($row['name']) ?></h3>
                <p><?= $metal_display ?> &bull; <?= htmlspecialchars($row['weight']) ?> g</p>
                <div class="price-container">
                    <div class="price-val">
                        <?php if (!empty($row['use_manual_rates'])): ?>
                            <span style="color: #059669; font-weight: 700;">₹<?= number_format($rounded_final_price, 0) ?></span>
                        <?php else: ?>
                            ₹<?= number_format($rounded_final_price, 0) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="product-actions">
                    <?php if (!empty($row['is_sold_out'])): ?>
                        <span class="btn btn-sold-out" aria-disabled="true">Sold Out</span>
                        <a href="product?id=<?= (int)$row['id'] ?>" class="btn btn-secondary">View Details</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" onclick="addToCart(<?= (int)$row['id'] ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>')">Add to Cart</button>
                        <a href="buynow?id=<?= (int)$row['id'] ?>" class="btn">Buy Now!</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $cardsHtml .= ob_get_clean();
        }
    }

    echo json_encode([
        'ok' => true,
        'count' => $items ? $items->num_rows : 0,
        'html' => $cardsHtml,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
exit;
