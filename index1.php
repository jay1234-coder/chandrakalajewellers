<?php
require 'config/db.php';
if (function_exists('cj_is_local_host') && cj_is_local_host()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL);
ini_set('log_errors', '1');
require_once 'includes/img-helper.php';
require_once 'includes/pricing.php';

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$standard_rates = cj_get_standard_rates($conn);
$manual_rates = cj_get_manual_rates($conn);

$items = $conn->query("SELECT * FROM jewellery_items WHERE is_favourite = 1 ORDER BY id DESC LIMIT 3");

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

$activePage = 'home';
require_once 'includes/seo.php';
$seo = cj_merge_seo([
    'title' => 'ChandraKala Jewellers | Gold & Silver in Khedbrahma',
    'description' => 'Family gold & silver jewellers in Khedbrahma. Shop 916 gold, silver & 925 designs with live rates on Civil Road, Sabarkantha. Call +91 9427080359.',
    'keywords' => 'ChandraKala Jewellers, Chandrakala Jewellers, gold jewellery Khedbrahma, silver jewellery Khedbrahma, jewellers near me, 916 gold rate today Khedbrahma, gold shop Civil Road, silver jewellery Sabarkantha, hallmark gold Gujarat',
    'canonical' => cj_abs_url('index1'),
    'schema' => [
        cj_local_business_schema(),
        cj_website_schema(),
        cj_faq_schema(cj_default_faqs()),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
        ]),
    ],
]);
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
</head>
<body>

<?php include 'includes/header.php'; ?>

<main class="container">

<div class="app-greeting" id="app-greeting" aria-live="polite">
    <div class="app-greeting-sparkles" aria-hidden="true"><span></span><span></span><span></span></div>
    <p class="app-greeting-eyebrow" id="greeting-eyebrow">Welcome</p>
    <h2 id="greeting-title">Namaste</h2>
    <p id="greeting-sub">Explore gold &amp; silver jewellery crafted in Khedbrahma.</p>
</div>

<section id="hero">
    <h1>Gold &amp; Silver Jewellery Store in Khedbrahma</h1>
    <p>ChandraKala Jewellers — timeless 916 gold &amp; silver pieces crafted with passion on Civil Road.</p>

    <?php
    $show_gold = cj_setting('show_gold_rate', '1', $conn) === '1';
    $show_silver = cj_setting('show_silver_rate', '1', $conn) === '1';
    $show_silver_925 = cj_setting('show_silver_925_rate', '1', $conn) === '1';
    $show_copper = cj_setting('show_copper_rate', '1', $conn) === '1';
    
    if ($show_gold || $show_silver || $show_silver_925 || $show_copper):
    ?>
    <div class="rate-box">
        <h3>OUR TODAY'S RATES</h3>
        <?php if ($show_gold): ?>
            <p>GOLD: ₹<?= number_format($standard_rates['gold_rate'], 2) ?> per gram*</p>
        <?php endif; ?>
        <?php if ($show_silver): ?>
            <p>SILVER: ₹<?= number_format($standard_rates['silver_rate'], 2) ?> per gram*</p>
        <?php endif; ?>
        <?php if ($show_silver_925): ?>
            <p>925 SILVER: ₹<?= number_format($standard_rates['silver_925_rate'], 2) ?> per gram*</p>
        <?php endif; ?>
        <?php if ($show_copper): ?>
            <p>COPPER 999 FINE: ₹<?= number_format($standard_rates['copper_rate'], 2) ?>/KG</p>
            <h5><b>Copper delivered to you in 4 or 4+ working days</b></h5>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p class="hero-disclaimer">The jewellery product shown may vary from the actual product*</p>

    <div class="hero-ctas">
        <a href="gallery" class="btn">Shop Now</a>
        <a href="Catalogue" class="btn btn-outline">Catalogue</a>
    </div>
</section>

<section id="shop" class="reveal">
    <h2 class="section-heading">Hot Favourite Items</h2>
    <p class="section-sub">Handpicked pieces loved by our customers.</p>
    <div class="product-grid">
    <?php
    if ($items) {
        $delay = 0;
        $imgIndex = 0;
        while ($row = $items->fetch_assoc()):
            $rates_to_use = $row['use_manual_rates'] ? $manual_rates : $standard_rates;
            $final_price = calculate_price($row['metal_type'], $row['weight'], $rates_to_use);
            $rounded_final_price = round($final_price);
            $metal_display = get_metal_name($row['metal_type']);
            $delayClass = $delay > 0 ? ' reveal-delay-' . $delay : '';
            $delay = min($delay + 1, 3);
            $isFirstImg = ($imgIndex === 0);
            $imgIndex++;
    ?>
        <div class="product reveal<?= $delayClass ?><?= !empty($row['is_sold_out']) ? ' is-sold-out' : '' ?> metal-<?= htmlspecialchars($row['metal_type']) ?>">
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
                    <?= $isFirstImg
                        ? 'fetchpriority="high"'
                        : 'loading="lazy"' ?>
                >
            </a>
            <h3><?= htmlspecialchars($row['name']) ?></h3>
            <p><?= $metal_display ?> &bull; <?= htmlspecialchars($row['weight']) ?> g</p>
            <div class="price-container">
                <div class="price-val">
                    <?php if ($row['use_manual_rates']): ?>
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
        endwhile;
    } else {
        echo '<p style="text-align:center;color:var(--text-secondary);">No hot favourite items found.</p>';
    }
    ?>
    </div>
    <div class="back-btn-container">
        <a href="gallery" class="btn btn-secondary">View Full Collection</a>
    </div>
</section>

<section id="about" class="about-section reveal cj-lazy-section">
    <div class="about-grid">
        <div class="about-content">
            <h2>About ChandraKala Jewellers, Khedbrahma</h2>
            <p>We are a family-owned gold and silver jewellery store on Civil Road, Khedbrahma (Sabarkantha, Gujarat), dedicated to high-quality, handcrafted 916 gold and silver jewellery. Our passion for craftsmanship and attention to detail sets us apart.</p>
            <p>From everyday elegance to celebration pieces, every design is chosen with care — so customers across Khedbrahma and nearby areas receive jewellery that feels personal, lasting, and true to tradition.</p>
            <a href="contact" class="btn" style="margin-top: 8px;">Visit Our Store</a>
        </div>
        <div class="about-stats">
            <div class="about-stat reveal reveal-delay-1">
                <strong>Family</strong>
                <span>Owned &amp; Run</span>
            </div>
            <div class="about-stat reveal reveal-delay-2">
                <strong>916</strong>
                <span>Gold Hallmark</span>
            </div>
            <div class="about-stat reveal reveal-delay-1">
                <strong>Care</strong>
                <span>Custom Designs</span>
            </div>
            <div class="about-stat reveal reveal-delay-2">
                <strong>Local</strong>
                <span>Khedbrahma</span>
            </div>
        </div>
    </div>
</section>

<section id="contact" class="contact-section reveal cj-lazy-section">
    <h2 class="section-heading">Contact Us</h2>
    <p class="section-sub">We would love to help you find the perfect piece.</p>
    <div class="contact-cards">
        <div class="contact-card reveal reveal-delay-1">
            <div class="icon-wrap">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <h3>Visit Us</h3>
            <p>Opp. Bhoomi Complex, Civil Road, Khedbrahma-383255</p>
        </div>
        <div class="contact-card reveal reveal-delay-2">
            <div class="icon-wrap">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            </div>
            <h3>Call</h3>
            <p><a href="<?= htmlspecialchars(function_exists('cj_shop_tel_href') ? cj_shop_tel_href() : 'tel:+919427080359') ?>"><?= htmlspecialchars(function_exists('cj_shop_phone_display') ? cj_shop_phone_display() : '+91 9427080359') ?></a></p>
        </div>
        <div class="contact-card reveal reveal-delay-3">
            <div class="icon-wrap">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <h3>Email</h3>
            <p><a href="mailto:chandrakalajewellers849@gmail.com">chandrakalajewellers849@gmail.com</a></p>
        </div>
    </div>
    <div class="back-btn-container">
        <a href="contact" class="btn">Get in Touch</a>
    </div>
</section>

<section id="blog" class="blog-section reveal cj-lazy-section">
    <h2 class="section-heading">From Our Blog</h2>
    <p class="section-sub">Care tips and inspiration to keep your jewellery sparkling.</p>
    <div class="blog-grid">
        <article class="blog-card reveal reveal-delay-1">
            <div class="blog-card-media">
                <span class="media-fallback">Jewellery Care</span>
            </div>
            <div class="blog-card-body">
                <span class="tag">Care Tips</span>
                <h3>Jewellery Care Tips</h3>
                <p>Learn how to keep your jewellery sparkling with our expert care tips for gold and silver pieces.</p>
                <a href="blog-post?slug=jewellery-care-tips" class="btn btn-secondary">Read More</a>
            </div>
        </article>
        <article class="blog-card reveal reveal-delay-2">
            <div class="blog-card-media">
                <span class="media-fallback">Gold Guide</span>
            </div>
            <div class="blog-card-body">
                <span class="tag">Buying Guide</span>
                <h3>Choosing the Right Gold</h3>
                <p>Understand hallmarking, purity, and making charges so you shop with confidence.</p>
                <a href="blog-post?slug=choosing-the-right-gold" class="btn btn-secondary">Read More</a>
            </div>
        </article>
        <article class="blog-card reveal reveal-delay-3">
            <div class="blog-card-media">
                <span class="media-fallback">Occasions</span>
            </div>
            <div class="blog-card-body">
                <span class="tag">Style</span>
                <h3>Jewellery for Every Occasion</h3>
                <p>From daily wear to wedding celebrations — find pieces that match every moment.</p>
                <a href="blog-post?slug=jewellery-for-every-occasion" class="btn btn-secondary">Read More</a>
            </div>
        </article>
    </div>
    <div class="back-btn-container">
        <a href="blog" class="btn btn-outline">View All Posts</a>
    </div>
</section>

<section id="faq" class="faq-section reveal cj-lazy-section" aria-labelledby="faq-heading">
    <h2 id="faq-heading" class="section-heading">Frequently Asked Questions</h2>
    <p class="section-sub">Quick answers about our gold &amp; silver jewellery store in Khedbrahma.</p>
    <div class="faq-list">
        <?php foreach (cj_default_faqs() as $i => $faq): ?>
            <details class="faq-item reveal<?= $i ? ' reveal-delay-' . min($i, 3) : '' ?>">
                <summary><?= htmlspecialchars($faq['q']) ?></summary>
                <p><?= htmlspecialchars($faq['a']) ?></p>
            </details>
        <?php endforeach; ?>
    </div>
</section>

</main>

<?php include 'includes/footer.php'; ?>

</body>
</html>
