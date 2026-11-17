<?php
require 'config/db.php';
require_once 'includes/img-helper.php';
require_once 'includes/pricing.php';
require_once 'includes/orders.php';
require_once 'includes/catalog.php';
require_once 'includes/recommend.php';
require_once 'includes/customers.php';

if (function_exists('cj_is_local_host') && cj_is_local_host()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL);
ini_set('log_errors', '1');

cj_expire_reservations($conn);

// Validate and get product ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("Invalid Product.");
}

// Fetch product details
$product = $conn->query("SELECT * FROM jewellery_items WHERE id = $id")->fetch_assoc();
if (!$product) {
    die("Product not found.");
}

$extras = cj_product_extra_images($conn, $id);
$gallery = cj_product_gallery_filenames($product, $extras);
$related = [];
$relatedHeading = 'Related pieces';
if (cj_feature('feat_ai_recs')) {
    $related = cj_recommend_products($conn, $product, cj_recs_limit());
    $relatedHeading = 'You may also like';
} elseif (cj_feature('feat_related')) {
    $related = cj_related_products($conn, $product, 4);
}
$reviews = cj_feature('feat_reviews') ? cj_approved_reviews($conn, $id) : [];
$reviewStats = cj_feature('feat_reviews') ? cj_review_stats($conn, $id) : ['count' => 0, 'avg' => 0.0];
$reviewFlash = (string) ($_SESSION['review_flash'] ?? '');
unset($_SESSION['review_flash']);
$cjCustomer = cj_customer_session();

// Fetch latest rates
$rate_result = $conn->query("SELECT * FROM rates ORDER BY updated_at DESC LIMIT 1");
$rate = $rate_result->fetch_assoc();

// Fetch latest manual rates
$manual_rate_result = $conn->query("SELECT * FROM manual_rates ORDER BY updated_at DESC LIMIT 1");
$manual_rate = $manual_rate_result->fetch_assoc();

// Determine which set of rates to use based on the 'use_manual_rates' flag
$rates_to_use = $product['use_manual_rates'] ?
    [
        'gold_rate' => $manual_rate['gold_rate'] ?? 0,
        'silver_rate' => $manual_rate['silver_rate'] ?? 0,
        'silver_925_rate' => $manual_rate['silver_925_rate'] ?? 0,
        'making_charges' => $manual_rate['manual_making_charges_percent'] ?? 0,
        'apply_making_to_silver' => $manual_rate['manual_apply_making_to_silver'] ?? 0
    ] :
    [
        'gold_rate' => $rate['gold_rate'] ?? 0,
        'silver_rate' => $rate['silver_rate'] ?? 0,
        'silver_925_rate' => $rate['silver_925_rate'] ?? 0,
        'making_charges' => $rate['making_charges_percent'] ?? 0,
        'apply_making_to_silver' => $rate['apply_making_to_silver'] ?? 0
    ];

// Assign readable metal name
$metal_display = '';
switch ($product['metal_type']) {
    case 'gold':
        $metal_display = 'Gold';
        break;
    case 'silver':
        $metal_display = 'Silver';
        break;
    case 'silver_925':
        $metal_display = '925 Silver';
        break;
    default:
        $metal_display = 'Unknown';
}

// Price from shared includes/pricing.php (per 1g, same as gallery/cart)

// Calculate Auto Price (using standard rates for comparison)
$auto_price = calculate_price(
    $product['metal_type'],
    $product['weight'],
    [
        'gold_rate' => $rate['gold_rate'] ?? 0,
        'silver_rate' => $rate['silver_rate'] ?? 0,
        'silver_925_rate' => $rate['silver_925_rate'] ?? 0,
        'making_charges' => $rate['making_charges_percent'] ?? 0,
        'apply_making_to_silver' => $rate['apply_making_to_silver'] ?? 0
    ]
);
$rounded_auto_price = round($auto_price ?? 0);

// Determine the final price to display
$final_price_data = calculate_price(
    $product['metal_type'],
    $product['weight'],
    $rates_to_use
);
$final_price = round($final_price_data ?? 0);

// Display logic
$is_manual_rate = $product['use_manual_rates'];
$is_discounted = false;
$displayed_price = $final_price;

// If manual rates are used, check if the calculated manual price is less than the auto price
if ($is_manual_rate) {
    if ($final_price < $rounded_auto_price) {
        $is_discounted = true;
    }
}

require_once 'includes/seo.php';
$productUrl = cj_abs_url('product?id=' . (int)$product['id']);
$rawImage = trim((string)($product['image'] ?? ''));
$productImage = $rawImage !== ''
    ? cj_abs_url('uploads/' . ltrim($rawImage, '/'))
    : cj_abs_url('images/logo.png');

// Schema price: prefer display price, then auto rate price, then stored manual_price.
$manualDbPrice = isset($product['manual_price']) ? (float)$product['manual_price'] : 0;
$schemaPrice = (float)$final_price;
if ($schemaPrice <= 0 && $rounded_auto_price > 0) {
    $schemaPrice = (float)$rounded_auto_price;
}
if ($schemaPrice <= 0 && $manualDbPrice > 0) {
    $schemaPrice = $manualDbPrice;
}
$schemaFallbackPrice = max((float)$rounded_auto_price, $manualDbPrice, (float)$final_price);

$seoDesc = trim(preg_replace('/\s+/', ' ', strip_tags($product['description'] ?? '')));
if ($seoDesc === '') {
    $seoDesc = $product['name'] . ' — ' . $metal_display . ' jewellery from ChandraKala Jewellers, Khedbrahma.';
}
$descLen = function_exists('mb_strlen') ? mb_strlen($seoDesc) : strlen($seoDesc);
$descShort = function_exists('mb_substr') ? mb_substr($seoDesc, 0, 155) : substr($seoDesc, 0, 155);
$seo = cj_merge_seo([
    'title' => $product['name'] . ' | ' . $metal_display . ' Jewellery Khedbrahma',
    'description' => $descShort . ($descLen > 155 ? '…' : ''),
    'keywords' => $product['name'] . ', ' . $metal_display . ' jewellery Khedbrahma, buy ' . strtolower($metal_display) . ' jewellery Gujarat, ChandraKala Jewellers',
    'canonical' => $productUrl,
    'og_type' => 'product',
    'og_image' => $productImage,
    'schema' => [
        cj_product_schema([
            'id' => $product['id'],
            'name' => $product['name'],
            'description' => $seoDesc,
            'metal' => $metal_display,
            'price' => $schemaPrice,
            'fallback_price' => $schemaFallbackPrice,
            'url' => $productUrl,
            'image' => $productImage,
            'weight' => $product['weight'] ?? null,
            'sku' => $product['sku'] ?? '',
            'availability' => !empty($product['is_sold_out'])
                ? 'https://schema.org/OutOfStock'
                : ($schemaPrice > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/PreOrder'),
            'aggregate_count' => $reviewStats['count'],
            'aggregate_avg' => $reviewStats['avg'],
        ]),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Shop', 'url' => cj_abs_url('gallery')],
            ['name' => $product['name'], 'url' => $productUrl],
        ]),
        cj_local_business_schema(),
    ],
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
    <style>
        .product-details-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 40px;
            padding: 20px 0;
        }
        @media (min-width: 768px) {
            .product-details-container {
                grid-template-columns: 1fr 1fr;
                text-align: left;
                align-items: start;
            }
        }
        .image-wrapper {
            background-color: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
        .image-wrapper img {
            max-width: 100%;
            max-height: 420px;
            object-fit: contain;
            border-radius: 8px;
            cursor: zoom-in;
        }
        .product-thumbs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
            justify-content: center;
        }
        .product-thumbs button {
            border: 2px solid var(--border-color);
            background: #fff;
            padding: 0;
            border-radius: 8px;
            cursor: pointer;
        }
        .product-thumbs button.is-active,
        .product-thumbs button:focus-visible {
            border-color: var(--primary-gold);
        }
        .product-thumbs img {
            width: 56px;
            height: 56px;
            object-fit: cover;
            border-radius: 6px;
            display: block;
        }
        .product-zoom-dialog {
            border: 0;
            padding: 0;
            background: transparent;
            max-width: min(92vw, 900px);
        }
        .product-zoom-dialog::backdrop {
            background: rgba(0,0,0,.72);
        }
        .product-zoom-dialog img {
            max-width: 92vw;
            max-height: 88vh;
            display: block;
            margin: 0 auto;
            border-radius: 8px;
        }
        .specs-table {
            width: 100%;
            font-size: 14px;
            border-collapse: collapse;
        }
        .specs-table th,
        .specs-table td {
            text-align: left;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .details-info {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .product-title {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        .product-meta {
            font-size: 14px;
            color: var(--text-secondary);
            display: flex;
            gap: 12px;
        }
        .product-description {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin: 10px 0;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 0;
        }
        .price-badge {
            display: flex;
            align-items: baseline;
            gap: 10px;
        }
        .price-current {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
        }
        .price-old {
            font-size: 20px;
            text-decoration: line-through;
            color: var(--text-light);
        }
        .discount-badge {
            background-color: #def7ec;
            color: #03543f;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
            margin-left: 8px;
        }
    </style>
</head>
<body class="has-sticky-cta">

<?php $activePage = 'shop'; include 'includes/header.php'; ?>

<main id="main-content" class="container page-enter">
    <nav class="page-trail" aria-label="Breadcrumb">
        <a href="index1">Home</a>
        <span aria-hidden="true">/</span>
        <a href="gallery">Shop</a>
        <span aria-hidden="true">/</span>
        <span><?= htmlspecialchars($product['name']) ?></span>
    </nav>
    <a href="gallery" class="back-link">&larr; Back to Shop</a>
    <div class="product-details-container reveal<?= !empty($product['is_sold_out']) ? ' is-sold-out' : '' ?>">
        <div class="image-wrapper">
            <?php if (!empty($product['is_sold_out'])): ?>
                <span class="sold-out-badge sold-out-badge-lg">Sold Out</span>
            <?php endif; ?>
            <span class="metal-badge metal-badge-<?= htmlspecialchars($product['metal_type']) ?>"><?= $metal_display ?></span>
            <?php $hero = $gallery[0] ?? $product['image']; ?>
            <img
                id="product-hero"
                class="product-hero<?= cj_feature('feat_zoom') ? ' is-zoomable' : '' ?>"
                src="<?= htmlspecialchars(cj_img_url($hero, 900)) ?>"
                data-full="<?= htmlspecialchars(cj_img_url($hero, 1400)) ?>"
                alt="<?= htmlspecialchars($product['name']) ?> — <?= htmlspecialchars($metal_display) ?> jewellery Khedbrahma"
                width="600"
                height="600"
                decoding="async"
                fetchpriority="high"
            >
            <?php if (count($gallery) > 1): ?>
            <div class="product-thumbs" role="list">
                <?php foreach ($gallery as $i => $fn): ?>
                    <button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" data-src="<?= htmlspecialchars(cj_img_url($fn, 900)) ?>" data-full="<?= htmlspecialchars(cj_img_url($fn, 1400)) ?>" aria-label="View photo <?= $i + 1 ?>">
                        <img src="<?= htmlspecialchars(cj_img_url($fn, 200)) ?>" alt="" width="56" height="56" loading="lazy" decoding="async">
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (cj_feature('feat_zoom')): ?>
            <p class="text-xs text-gray-400 mt-2">Tap the photo to zoom</p>
            <?php endif; ?>
        </div>

        <div class="details-info">
            <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
            <div class="product-meta">
                <span>Metal: <strong><?= $metal_display ?></strong></span>
                <span>&bull;</span>
                <span>Weight: <strong><?= htmlspecialchars($product['weight']) ?> g</strong></span>
                <?php if (!empty($product['is_sold_out'])): ?>
                    <span>&bull;</span>
                    <span class="text-sold-out"><strong>Sold Out</strong></span>
                <?php endif; ?>
            </div>

            <p class="product-description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

            <?php if (cj_feature('feat_product_specs')): ?>
            <table class="specs-table" aria-label="Product specifications">
                <tr><th>Metal</th><td><?= htmlspecialchars($metal_display) ?></td></tr>
                <tr><th>Weight</th><td><?= htmlspecialchars((string) $product['weight']) ?> g</td></tr>
                <?php if (trim((string) ($product['purity'] ?? '')) !== ''): ?>
                <tr><th>Purity</th><td><?= htmlspecialchars($product['purity']) ?></td></tr>
                <?php endif; ?>
                <?php if (trim((string) ($product['stone'] ?? '')) !== ''): ?>
                <tr><th>Stone</th><td><?= htmlspecialchars($product['stone']) ?></td></tr>
                <?php endif; ?>
                <?php if (trim((string) ($product['category'] ?? '')) !== ''): ?>
                <tr><th>Type</th><td><?= htmlspecialchars($product['category']) ?></td></tr>
                <?php endif; ?>
                <?php if (trim((string) ($product['dimensions'] ?? '')) !== ''): ?>
                <tr><th>Size</th><td><?= htmlspecialchars($product['dimensions']) ?></td></tr>
                <?php endif; ?>
                <?php if (trim((string) ($product['sku'] ?? '')) !== ''): ?>
                <tr><th>SKU</th><td><?= htmlspecialchars($product['sku']) ?></td></tr>
                <?php endif; ?>
            </table>
            <?php endif; ?>

            <div class="price-badge">
                <?php if ($final_price > 0): ?>
                    <span class="price-current">₹<?= number_format($final_price, 0) ?></span>
                    <?php if ($is_discounted): ?>
                        <span class="price-old">₹<?= number_format($rounded_auto_price, 0) ?></span>
                        <span class="discount-badge">Special Rate</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="price-current">Price not available</span>
                <?php endif; ?>
            </div>

            <div class="product-actions" style="margin-top: 20px;">
                <?php if (!empty($product['is_sold_out'])): ?>
                    <span class="btn btn-sold-out" aria-disabled="true">Sold Out</span>
                    <a href="contact" class="btn btn-secondary">Ask for similar piece</a>
                <?php else: ?>
                    <button type="button" class="btn btn-secondary" onclick="addToCart(<?= (int)$product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>')">Add to Cart</button>
                    <?php if (cj_feature('feat_wishlist')): ?>
                    <button type="button" id="wishlist-btn-detail" data-product-id="<?= (int)$product['id'] ?>" class="btn btn-secondary" onclick="toggleWishlist(<?= (int)$product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>')">Wishlist</button>
                    <?php endif; ?>
                    <a href="buynow?id=<?= (int)$product['id'] ?>" class="btn">Buy Now!</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($related): ?>
    <section class="related-section" aria-labelledby="related-heading" style="margin-top:48px;">
        <h2 id="related-heading" class="product-title" style="font-size:24px;"><?= htmlspecialchars($relatedHeading) ?></h2>
        <div class="related-grid">
            <?php foreach ($related as $rel): ?>
                <a href="product?id=<?= (int) $rel['id'] ?>" class="cj-rec-card reveal" style="text-decoration:none;color:inherit;">
                    <img src="<?= htmlspecialchars(cj_img_url($rel['image'], 400)) ?>" alt="<?= htmlspecialchars($rel['name']) ?>" width="200" height="200" loading="lazy" decoding="async">
                    <p class="cj-rec-name"><?= htmlspecialchars($rel['name']) ?></p>
                    <p class="cj-rec-meta"><?= htmlspecialchars((string) $rel['weight']) ?> g<?= !empty($rel['cached_price']) ? ' · ₹' . number_format((float) $rel['cached_price'], 0) : '' ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (cj_feature('feat_reviews')): ?>
    <section class="reviews-section" aria-labelledby="reviews-heading" style="margin-top:48px;margin-bottom:40px;">
        <h2 id="reviews-heading" class="product-title" style="font-size:24px;">Reviews</h2>
        <?php if ($reviewStats['count'] > 0): ?>
            <p style="color:var(--text-secondary);"><?= htmlspecialchars((string) $reviewStats['avg']) ?> / 5 from <?= (int) $reviewStats['count'] ?> review<?= $reviewStats['count'] === 1 ? '' : 's' ?></p>
        <?php endif; ?>
        <?php if ($reviewFlash !== ''): ?>
            <p class="text-sm" style="color:#047857;margin:12px 0;"><?= htmlspecialchars($reviewFlash) ?></p>
        <?php endif; ?>
        <div style="display:grid;gap:12px;margin:16px 0;">
            <?php if (!$reviews): ?>
                <p style="color:var(--text-secondary);">No reviews yet. Be the first to share how this piece looks.</p>
            <?php else: foreach ($reviews as $rev): ?>
                <article style="border:1px solid var(--border-color);border-radius:12px;padding:12px 16px;">
                    <p style="font-weight:600;"><?= htmlspecialchars($rev['customer_name']) ?> · <?= str_repeat('★', (int) $rev['rating']) ?><?= str_repeat('☆', 5 - (int) $rev['rating']) ?></p>
                    <p style="margin-top:6px;color:var(--text-secondary);"><?= htmlspecialchars($rev['comment']) ?></p>
                </article>
            <?php endforeach; endif; ?>
        </div>
        <form method="post" action="review-submit" id="review-form" style="max-width:480px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true">
            <label class="block text-xs font-semibold" style="display:block;margin-bottom:6px;">Your name
                <input name="customer_name" required maxlength="80" class="p-3 border rounded-xl w-full" style="display:block;width:100%;margin-top:4px;padding:10px;border:1px solid var(--border-color);border-radius:10px;" value="<?= htmlspecialchars($cjCustomer['name'] ?? '') ?>">
            </label>
            <label class="block text-xs font-semibold" style="display:block;margin:12px 0 6px;">Rating
                <select name="rating" style="display:block;margin-top:4px;padding:10px;border-radius:10px;border:1px solid var(--border-color);">
                    <option value="5">5 — Excellent</option>
                    <option value="4">4 — Very good</option>
                    <option value="3">3 — Good</option>
                    <option value="2">2 — Fair</option>
                    <option value="1">1 — Poor</option>
                </select>
            </label>
            <label class="block text-xs font-semibold" style="display:block;margin:12px 0 6px;">Review
                <textarea name="comment" required maxlength="800" rows="3" style="display:block;width:100%;margin-top:4px;padding:10px;border:1px solid var(--border-color);border-radius:10px;"></textarea>
            </label>
            <button type="submit" class="btn" style="margin-top:12px;">Submit review</button>
        </form>
    </section>
    <?php endif; ?>
</main>

<dialog id="product-zoom" class="product-zoom-dialog" aria-label="Zoomed product photo">
    <img id="product-zoom-img" alt="">
</dialog>

<?php if (empty($product['is_sold_out'])): ?>
<div class="product-sticky-cta" aria-label="Quick actions">
    <button type="button" class="btn btn-secondary" onclick="addToCart(<?= (int)$product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>')">Add to Cart</button>
    <a href="buynow?id=<?= (int)$product['id'] ?>" class="btn">Buy Now!</a>
</div>
<?php else: ?>
<div class="product-sticky-cta" aria-label="Sold out actions">
    <span class="btn btn-sold-out" aria-disabled="true">Sold Out</span>
    <a href="gallery" class="btn btn-secondary">Browse Shop</a>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
<script>
(function () {
    var hero = document.getElementById('product-hero');
    var dialog = document.getElementById('product-zoom');
    var zoomImg = document.getElementById('product-zoom-img');
    if (hero && dialog && zoomImg && hero.classList.contains('is-zoomable')) {
        hero.addEventListener('click', function () {
            zoomImg.src = hero.getAttribute('data-full') || hero.src;
            zoomImg.alt = hero.alt;
            if (typeof dialog.showModal === 'function') dialog.showModal();
        });
        dialog.addEventListener('click', function () { dialog.close(); });
    }
    document.querySelectorAll('.product-thumbs button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.product-thumbs button').forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            if (hero) {
                hero.src = btn.getAttribute('data-src');
                hero.setAttribute('data-full', btn.getAttribute('data-full') || '');
            }
        });
    });
})();
</script>
</body>
</html>
