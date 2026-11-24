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
require_once 'includes/reels.php';

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$standard_rates = cj_get_standard_rates($conn);
$manual_rates = cj_get_manual_rates($conn);

// Show 8 random products from inventory on initial load and refresh
$items = $conn->query("SELECT * FROM jewellery_items WHERE COALESCE(is_sold_out, 0) = 0 ORDER BY RAND() LIMIT 8");
if (!$items || $items->num_rows === 0) {
    $items = $conn->query("SELECT * FROM jewellery_items ORDER BY is_sold_out ASC, RAND() LIMIT 8");
}

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
    'title' => 'ChandraKala Jewellers | Top Ranking Jewellery in Gujarat — 916 BIS Gold, Silver & Live Rates',
    'description' => 'ChandraKala Jewellers, Gujarat: Shop 100% BIS 916 hallmarked gold, pure silver & 925 sterling silver with live daily IST rates, custom bridal sets & doorstep insured delivery across Gujarat. WhatsApp +91 9427080359.',
    'canonical' => cj_abs_url('index1'),
    'modified_iso' => cj_format_ist_iso($standard_rates['updated_at'] ?? null),
    'schema' => [
        cj_local_business_schema(),
        cj_website_schema(),
        cj_live_rates_schema($standard_rates, $conn),
        cj_speakable_schema(cj_abs_url('index1'), ['h1', '.rate-box', '.section-sub']),
        cj_faq_schema(cj_default_faqs($standard_rates)),
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
        <?php if (!empty($standard_rates['updated_at'])): ?>
            <p class="rate-updated-ist" style="font-size:12px;opacity:0.8;margin-top:8px;letter-spacing:0.01em;">Updated: <?= htmlspecialchars(cj_format_ist($standard_rates['updated_at'])) ?></p>
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
    <div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:16px;">
        <div>
            <h2 class="section-heading" style="margin:0;text-align:left;">Hot Favourite Items</h2>
            <p class="section-sub" style="margin:4px 0 0;text-align:left;">Handpicked pieces continuously shuffled from our inventory.</p>
        </div>
        <button type="button" id="cjShuffleProductsBtn" class="cj-shuffle-btn" onclick="shuffleHomeProducts()" title="Shuffle random products from inventory">
            <svg class="cj-shuffle-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>
            <span>Shuffle Pieces</span>
        </button>
    </div>
    <div class="product-grid cj-grid-crossfade" id="homeProductGrid">
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
        <a href="gallery" class="btn btn-secondary">View Full Collection (60+ Designs) &rarr;</a>
    </div>
</section>

<?php
$allActiveReels = cj_get_ranked_reels($conn, 30, true, 'top');
if (!empty($allActiveReels)) {
    shuffle($allActiveReels);
    $cjReelsList = array_slice($allActiveReels, 0, min(7, count($allActiveReels)));
} else {
    $cjReelsList = [];
}
if (!empty($cjReelsList)):
    $cjReelsContext = 'home';
?>
<section id="reels-showcase" class="hot-favourites-section reveal cj-lazy-section" style="margin-top:42px;margin-bottom:44px;">
    <div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:18px;">
        <div>
            <p style="font-size:11px;font-weight:700;letter-spacing:0.14em;text-transform:uppercase;color:var(--primary-gold-dark);margin:0 0 4px;">Live Showroom Cinema</p>
            <h2 class="section-heading" style="margin:0;text-align:left;">Sparkle in Motion</h2>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <button type="button" id="cjShuffleReelsBtn" class="cj-shuffle-btn" onclick="shuffleHomeReels()" title="Shuffle video reels showcase">
                <svg class="cj-shuffle-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>
                <span>Shuffle Reels</span>
            </button>
            <a href="/reels" style="font-size:13px;font-weight:700;color:var(--primary-gold-dark);text-decoration:none;">Explore All Reels &rarr;</a>
        </div>
    </div>
    <div id="homeReelsContainer" class="cj-grid-crossfade">
        <?php include __DIR__ . '/includes/reels-bento.php'; ?>
    </div>
    <div class="back-btn-container" style="margin-top:20px;">
        <a href="/reels" class="btn btn-secondary">Watch Full Reels Showcase &rarr;</a>
    </div>
</section>
<?php endif; ?>

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

<section id="gujarat-delivery" class="reveal cj-lazy-section" style="margin: 48px 0; background: linear-gradient(135deg, rgba(212,175,55,0.08) 0%, rgba(26,26,26,0.6) 100%); border: 1px solid rgba(212,175,55,0.3); border-radius: 20px; padding: 36px 28px;">
    <div style="max-width: 860px; margin: 0 auto; text-align: center;">
        <span style="display:inline-block; font-size: 11px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; color: var(--primary-gold-dark, #b8860b); background: rgba(212,175,55,0.15); padding: 5px 14px; border-radius: 999px; margin-bottom: 12px; border: 1px solid rgba(212,175,55,0.35);">
            Statewide Gujarat Service
        </span>
        <h2 style="font-family: 'Cinzel', serif; font-size: clamp(22px, 3.5vw, 32px); color: var(--text-primary, #fff); margin-bottom: 12px;">
            Top Ranking Jewellery Destination in Gujarat
        </h2>
        <p style="font-size: 14.5px; line-height: 1.65; color: var(--text-secondary, #cbd5e1); margin-bottom: 24px;">
            From Ahmedabad and Surat to Vadodara, Rajkot, and Sabarkantha — experience 100% BIS 916 HUID hallmarked gold, pure silver ornaments, and live bullion rate calculations delivered securely to your doorstep across Gujarat.
        </p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 26px; text-align: left;">
            <div style="background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px;">
                <div style="font-size: 20px; margin-bottom: 6px;">🛡️</div>
                <strong style="color: var(--primary-gold-light, #fef08a); font-size: 14px; display: block; margin-bottom: 4px;">100% Insured Delivery</strong>
                <span style="font-size: 12.5px; color: #94a3b8; line-height: 1.4; display: block;">Tamper-proof insured transit across all 33 districts of Gujarat.</span>
            </div>
            <div style="background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px;">
                <div style="font-size: 20px; margin-bottom: 6px;">📱</div>
                <strong style="color: var(--primary-gold-light, #fef08a); font-size: 14px; display: block; margin-bottom: 4px;">Live Video Concierge</strong>
                <span style="font-size: 12.5px; color: #94a3b8; line-height: 1.4; display: block;">Inspect weight, hallmarks & details on live WhatsApp video call.</span>
            </div>
            <div style="background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px;">
                <div style="font-size: 20px; margin-bottom: 6px;">⚖️</div>
                <strong style="color: var(--primary-gold-light, #fef08a); font-size: 14px; display: block; margin-bottom: 4px;">Direct Artisan Pricing</strong>
                <span style="font-size: 12.5px; color: #94a3b8; line-height: 1.4; display: block;">Transparent making charges from 6% with zero corporate markups.</span>
            </div>
            <div style="background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px;">
                <div style="font-size: 20px; margin-bottom: 6px;">🔍</div>
                <strong style="color: var(--primary-gold-light, #fef08a); font-size: 14px; display: block; margin-bottom: 4px;">BIS Care Verifiable</strong>
                <span style="font-size: 12.5px; color: #94a3b8; line-height: 1.4; display: block;">Verify 6-digit HUID code instantly on Govt BIS mobile app.</span>
            </div>
        </div>
        <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 12px;">
            <a href="top-jewellers-in-gujarat" class="btn" style="background: linear-gradient(135deg, #d4af37, #aa820a); color: #0a0b0e; font-weight: 700;">Explore Gujarat Jewellery Guide &rarr;</a>
            <a href="https://wa.me/919427080359?text=Hello%20ChandraKala%20Jewellers%2C%20I%20am%20interested%20in%20ordering%20jewellery%20in%20Gujarat" class="btn btn-outline" target="_blank" rel="noopener">Book WhatsApp Video Tour</a>
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

<style>
.cj-shuffle-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 15px;
    font-size: 13px;
    font-weight: 600;
    color: var(--primary-gold-dark, #b8860b);
    background: rgba(212, 175, 55, 0.08);
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 9999px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    outline: none;
    user-select: none;
}
.cj-shuffle-btn:hover {
    background: rgba(212, 175, 55, 0.18);
    border-color: rgba(212, 175, 55, 0.6);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.15);
}
.cj-shuffle-btn:active {
    transform: translateY(0);
}
.cj-shuffle-btn.is-shuffling .cj-shuffle-icon {
    animation: cjSpin 0.75s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}
@keyframes cjSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.cj-grid-crossfade {
    transition: opacity 0.22s ease;
}
.cj-grid-crossfade.is-fading {
    opacity: 0.15;
}
</style>
<script>
function shuffleHomeProducts() {
    var btn = document.getElementById('cjShuffleProductsBtn');
    var grid = document.getElementById('homeProductGrid');
    if (!grid || (btn && btn.classList.contains('is-shuffling'))) return;
    if (btn) btn.classList.add('is-shuffling');
    grid.classList.add('is-fading');

    fetch('/api/shuffle-products')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data && data.ok && data.html) {
                grid.innerHTML = data.html;
            }
        })
        .catch(function(err) {
            console.error('Product shuffle error:', err);
        })
        .finally(function() {
            setTimeout(function() {
                grid.classList.remove('is-fading');
                if (btn) btn.classList.remove('is-shuffling');
            }, 180);
        });
}

function shuffleHomeReels() {
    var btn = document.getElementById('cjShuffleReelsBtn');
    var container = document.getElementById('homeReelsContainer');
    if (!container || (btn && btn.classList.contains('is-shuffling'))) return;
    if (btn) btn.classList.add('is-shuffling');
    container.classList.add('is-fading');

    fetch('/api/shuffle-reels')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data && data.ok && data.html) {
                var bento = container.querySelector('.cj-reels-bento');
                if (bento) {
                    bento.innerHTML = data.html;
                } else {
                    container.innerHTML = '<div class="cj-reels-bento">' + data.html + '</div>';
                }
                if (typeof window.CJReelsInit === 'function') {
                    window.CJReelsInit();
                }
            }
        })
        .catch(function(err) {
            console.error('Reels shuffle error:', err);
        })
        .finally(function() {
            setTimeout(function() {
                container.classList.remove('is-fading');
                if (btn) btn.classList.remove('is-shuffling');
            }, 180);
        });
}
</script>

</main>

<?php include 'includes/footer.php'; ?>

</body>
</html>
