<?php
/**
 * District Pillar Landing Page — Top Jeweller in Sabarkantha District
 * Targets regional & district queries: "jewellers in sabarkantha", "best jeweller in sabarkantha",
 * "jewellers in himatnagar", "jewellers in idar", "jewellers in vadali", "today gold rate in sabarkantha", etc.
 */
$activePage = 'home';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/seo.php';

$b = cj_business_info();
$standard_rates = isset($conn) && $conn instanceof mysqli ? cj_get_standard_rates($conn) : [];

$gold24k_1g = (float)($standard_rates['gold_rate'] ?? 0);
$gold24k_10g = $gold24k_1g * 10;
// 22K (916) jewellery rate: 24K pure rate multiplied by 0.9166
$gold22k_1g = $gold24k_1g * 0.9166;
$gold22k_10g = $gold22k_1g * 10;
$gold1g = $gold22k_1g;
$gold10g = $gold22k_10g;
$silver1g = (float)($standard_rates['silver_rate'] ?? 0);
$silver1kg = $silver1g * 1000;
$silver925g = (float)($standard_rates['silver_925_rate'] ?? 0);

$rateUpdatedIst = !empty($standard_rates['updated_at'])
    ? cj_format_ist($standard_rates['updated_at'])
    : cj_format_ist(time());
$todayDateLabel = cj_format_ist(time(), 'd M Y', false);

// Sabarkantha District FAQs for rich result FAQPage schema
$sabarkanthaFaqs = [
    [
        'q' => 'Which is the top jeweller in Sabarkantha district for 100% BIS 916 hallmark gold?',
        'a' => 'ChandraKala Jewellers is a premier jeweller in Sabarkantha district, located on Civil Road in Khedbrahma. Trusted across three generations, we provide 100% BIS 916 HUID hallmarked gold jewellery, pure 999 silver, and 925 sterling silver ornaments with real-time daily IST rates and direct artisan workshop pricing.',
    ],
    [
        'q' => 'Do you serve customers from Himatnagar, Idar, and Vadali in Sabarkantha?',
        'a' => 'Yes. Customers travel regularly from Himatnagar (district headquarters), Idar, Vadali, Vijaynagar, Prantij, and Talod to our flagship showroom in Khedbrahma. We also offer WhatsApp video showroom shopping (+91 9427080359) with certified digital scale weighing and insured doorstep delivery across the entire Sabarkantha district.',
    ],
    [
        'q' => 'How can I check today\'s live gold rate in Sabarkantha?',
        'a' => 'You can check today\'s live gold rate in Sabarkantha directly on ChandraKala Jewellers website. We update live showroom board rates daily in Indian Standard Time (IST), reflecting 24K pure bullion rates and 22K (916) jewellery rates calculated transparently with zero hidden retail markups.',
    ],
    [
        'q' => 'How can I verify BIS Hallmark and HUID before buying in Sabarkantha?',
        'a' => 'Every gold piece at ChandraKala Jewellers has a laser-engraved 6-digit alphanumeric HUID code alongside the official BIS triangle hallmark. You can type this 6-digit code into the government\'s official BIS Care App on your phone to instantly verify purity (916 / 22K), assaying center, and registration details.',
    ],
    [
        'q' => 'What traditional Gujarati jewellery designs are available for Sabarkantha weddings and festivals?',
        'a' => 'We specialize in authentic North Gujarat bridal and festive jewellery, including handcrafted 22K Gold Dokiya, Patala and Pichodi bangles, Rani Haar, Chandan Haar, Kundan Butti, Hathpan, Silver Kandora, and heavy Gents silver kadas.',
    ],
    [
        'q' => 'How are making charges calculated at ChandraKala Jewellers in Sabarkantha?',
        'a' => 'At ChandraKala Jewellers, making charges are determined honestly and transparently based on the intricate craftsmanship and weight of each ornament. With our own artisan workshop, customers enjoy direct family jeweller pricing with complete itemized billing and zero unexpected markups.',
    ],
    [
        'q' => 'How do I visit ChandraKala Jewellers showroom from Himatnagar or Idar?',
        'a' => 'Our showroom is located Opposite Bhoomi Complex, Civil Road, Khedbrahma - 383255, Sabarkantha. It is conveniently situated along State Highway 9 / National Highway corridor connecting Himatnagar (approx. 50 km) and Idar (approx. 22 km) towards Ambaji.',
    ],
];

$pageCanonical = cj_abs_url('jewellers-in-sabarkantha');

$seo = cj_merge_seo([
    'title' => 'Jewellers in Sabarkantha — ChandraKala Jewellers | Himatnagar, Idar & Khedbrahma',
    'description' => 'ChandraKala Jewellers is a leading jeweller in Sabarkantha district (serving Himatnagar, Idar, Khedbrahma & Vadali). 100% BIS 916 HUID hallmark gold, pure silver & live IST rates.',
    'keywords' => 'jewellers in sabarkantha, best jeweller in sabarkantha, top jewellers in sabarkantha, jewellers in himatnagar, jewellers in idar, jewellers in vadali, jewellers in khedbrahma, gold rate in sabarkantha, today gold rate himatnagar, 916 hallmark gold sabarkantha, chandrakala jewellers',
    'canonical' => $pageCanonical,
    'modified_iso' => cj_format_ist_iso($standard_rates['updated_at'] ?? null),
    'schema' => [
        cj_local_business_schema(),
        cj_website_schema(),
        cj_live_rates_schema($standard_rates, $conn ?? null),
        cj_speakable_schema($pageCanonical, ['h1', '.sabarkantha-hero p', '.district-grid', '.rate-card-table']),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Top Jeweller in Gujarat', 'url' => cj_abs_url('top-jewellers-in-gujarat')],
            ['name' => 'Jewellers in Sabarkantha', 'url' => $pageCanonical],
        ]),
        cj_faq_schema($sabarkanthaFaqs),
    ],
]);
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
<style>
/* ── Sabarkantha District Page Luxury Styles ── */
.sabarkantha-hero {
    text-align: center;
    padding: 3rem 1rem 2.2rem;
    max-width: 900px;
    margin: 0 auto;
}
.sabarkantha-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(146, 108, 21, 0.08);
    border: 1px solid rgba(146, 108, 21, 0.25);
    color: #926c15;
    padding: 5px 12px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.3px;
    margin-bottom: 1.25rem;
}
html[data-theme="dark"] .sabarkantha-badge {
    color: #fde68a;
    background: rgba(253, 230, 138, 0.08);
    border-color: rgba(253, 230, 138, 0.25);
}
.sabarkantha-hero h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: clamp(2rem, 4.5vw, 2.85rem);
    line-height: 1.22;
    color: var(--text-primary, #111827);
    margin-bottom: 1rem;
    font-weight: 700;
}
.sabarkantha-hero p {
    font-size: clamp(1rem, 2vw, 1.15rem);
    color: var(--text-secondary, #4b5563);
    line-height: 1.72;
    margin: 0 auto;
}

/* ── Regional Hubs Grid ── */
.district-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.25rem;
    margin-top: 1.5rem;
}
.district-card {
    background: var(--surface, #ffffff);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    display: flex;
    flex-direction: column;
}
.district-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(17, 24, 39, 0.06);
    border-color: rgba(212, 175, 55, 0.4);
}
.district-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    background: rgba(212, 175, 55, 0.1);
    border: 1px solid rgba(212, 175, 55, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    color: #926c15;
}
html[data-theme="dark"] .district-icon-box {
    background: rgba(253, 230, 138, 0.08);
    border-color: rgba(253, 230, 138, 0.2);
    color: #fde68a;
}
.district-card h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.2rem;
    color: var(--text-primary, #111827);
    margin-bottom: 0.5rem;
}
.district-card p {
    font-size: 0.92rem;
    color: var(--text-secondary, #4b5563);
    line-height: 1.6;
    margin: 0;
}

/* ── Rate Card Table ── */
.rate-card-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--surface, #ffffff);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    border: 1px solid var(--border-color, #e5e7eb);
    margin: 1.5rem 0;
}
.rate-card-table th, .rate-card-table td {
    padding: 14px 18px;
    text-align: left;
    border-bottom: 1px solid var(--border-color, #e5e7eb);
    font-size: 0.95rem;
}
.rate-card-table th {
    background: rgba(212, 175, 55, 0.08);
    font-weight: 700;
    color: var(--text-primary, #111827);
}
.rate-card-table td.rate-gold-num {
    font-weight: 700;
    color: #926c15;
    font-size: 1.05rem;
}
html[data-theme="dark"] .rate-card-table td.rate-gold-num {
    color: #fde68a;
}

/* ── Towns Chips ── */
.towns-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 1rem;
}
.town-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--surface, #ffffff);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 6px;
    padding: 7px 14px;
    font-size: 0.88rem;
    font-weight: 500;
    color: var(--text-primary, #1f2937);
    transition: all 0.2s ease;
}
.town-chip:hover {
    border-color: rgba(212, 175, 55, 0.4);
    background: rgba(212, 175, 55, 0.04);
}
.city-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #d4af37;
    display: inline-block;
    flex-shrink: 0;
}

/* ── Ornament Showcase Grid ── */
.ornament-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.25rem;
    margin: 1.5rem 0;
}
.ornament-card {
    background: var(--surface, #ffffff);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 10px;
    padding: 1.5rem;
    transition: all 0.2s ease;
}
.ornament-card:hover {
    border-color: rgba(212, 175, 55, 0.4);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(17, 24, 39, 0.06);
}
.ornament-card h4 {
    font-family: 'Playfair Display', serif;
    font-size: 1.15rem;
    color: var(--text-primary, #111827);
    margin-bottom: 0.4rem;
}
.ornament-card p {
    font-size: 0.88rem;
    color: var(--text-secondary, #4b5563);
    line-height: 1.55;
    margin: 0;
}

/* ── FAQ Section ── */
.faq-accordion {
    margin: 2rem 0;
}
.faq-item {
    background: var(--surface, #ffffff);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 8px;
    margin-bottom: 0.85rem;
    overflow: hidden;
    transition: border-color 0.2s ease;
}
.faq-item summary {
    font-family: 'Playfair Display', serif;
    font-size: 1.05rem;
    font-weight: 600;
    padding: 1rem 1.25rem;
    cursor: pointer;
    color: var(--text-primary, #111827);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.faq-item summary::-webkit-details-marker {
    display: none;
}
.faq-item p {
    padding: 0 1.25rem 1.15rem;
    color: var(--text-secondary, #4b5563);
    line-height: 1.65;
    margin: 0;
    font-size: 0.92rem;
}

/* ── CTA Banner ── */
.sabarkantha-cta-banner {
    background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
    border: 1px solid rgba(212, 175, 55, 0.25);
    border-radius: 12px;
    padding: 2.5rem 1.5rem;
    text-align: center;
    color: #ffffff;
    margin: 3.5rem 0 2rem;
}
.sabarkantha-cta-banner h2 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.5rem, 3.5vw, 2.2rem);
    color: #fde68a;
    margin-bottom: 0.75rem;
}
.sabarkantha-cta-banner p {
    font-size: 1.05rem;
    color: #e5e7eb;
    max-width: 640px;
    margin: 0 auto 1.75rem;
    line-height: 1.6;
}
.cta-actions-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 12px;
}
.btn-gold {
    background: #d4af37;
    color: #0b0c10 !important;
    font-weight: 600;
    padding: 10px 22px;
    border-radius: 6px;
    text-decoration: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: background 0.2s, transform 0.15s;
}
.btn-gold:hover {
    background: #c59f27;
    transform: translateY(-1px);
}
.btn-outline-gold {
    background: transparent;
    border: 1px solid rgba(212, 175, 55, 0.6);
    color: #fde68a !important;
    font-weight: 600;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-outline-gold:hover {
    background: rgba(212, 175, 55, 0.12);
    border-color: #fde68a;
}
</style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<main id="main-content" class="container page-enter" style="max-width:1100px;margin:0 auto;padding:0 1rem;">
    <!-- Hero Section -->
    <section class="sabarkantha-hero reveal">
        <div class="sabarkantha-badge">Certified 100% BIS 916 HUID &bull; Sabarkantha District</div>
        <h1>Jewellers in Sabarkantha — ChandraKala Jewellers</h1>
        <p>Looking for the best jewellers in Sabarkantha? Welcome to ChandraKala Jewellers, the trusted 3-generation jewellery destination serving families across <strong>Himatnagar, Idar, Khedbrahma, Vadali, and all talukas of Sabarkantha district</strong>. Discover certified 100% BIS 916 HUID hallmarked gold ornaments, pure 999 silver, Italian 925 sterling silver, and live daily IST bullion rates with direct artisan workshop honesty.</p>
    </section>

    <!-- 4 District Pillars -->
    <section class="reveal" style="margin:2.5rem 0 3.5rem;">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.8rem;text-align:center;margin-bottom:0.5rem;color:var(--text-primary,#111827);">
            Why Sabarkantha Families Choose ChandraKala Jewellers
        </h2>
        <p style="text-align:center;color:var(--text-secondary,#4b5563);max-width:740px;margin:0 auto 1.5rem;">
            From Himatnagar and Idar to Vadali and Khedbrahma, our family showroom offers verified hallmark purity, transparent live weights, and hereditary craftsmanship.
        </p>

        <div class="district-grid">
            <div class="district-card">
                <div class="district-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <h3>100% BIS 916 HUID Certified</h3>
                <p>Every gold piece is stamped with the government-approved 6-digit alphanumeric HUID code, instantly verifiable on the official BIS Care app for guaranteed 22K (91.6%) purity.</p>
            </div>
            <div class="district-card">
                <div class="district-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>
                </div>
                <h3>Live Sabarkantha Daily Rates</h3>
                <p>Fair, transparent daily market rates in Indian Standard Time (IST) reflecting pure 24K bullion and 22K jewellery rates without arbitrary retail margins.</p>
            </div>
            <div class="district-card">
                <div class="district-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <h3>Traditional Gujarati Ornaments</h3>
                <p>Specialized bridal sets, Dokiya, Patala, Pichodi, Kundan Butti, Silver Kandora, and heavy Gents kadas crafted by hereditary goldsmiths.</p>
            </div>
            <div class="district-card">
                <div class="district-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                </div>
                <h3>WhatsApp Video Tour &amp; Delivery</h3>
                <p>Inspect any jewellery on a live WhatsApp video call (+91 9427080359) with certified digital scale weighing, or enjoy insured doorstep delivery across Sabarkantha.</p>
            </div>
        </div>
    </section>

    <!-- Live Gold Rate Card for Sabarkantha -->
    <section class="reveal" style="margin:2.5rem 0;">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.6rem;margin-bottom:0.5rem;color:var(--text-primary,#111827);">
            Today’s Live Gold &amp; Silver Rates in Sabarkantha (IST)
        </h2>
        <p style="color:var(--text-secondary,#4b5563);margin-bottom:1rem;">
            Showroom board rates updated daily as per Gujarat bullion benchmarks. Valid as of <strong><?= htmlspecialchars($rateUpdatedIst) ?></strong>.
        </p>

        <table class="rate-card-table">
            <thead>
                <tr>
                    <th>Metal Category</th>
                    <th>Purity / Hallmark</th>
                    <th>Live Showroom Rate (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($gold24k_1g > 0): ?>
                <tr>
                    <td><strong>24K Pure Gold (Per 10 Grams)</strong></td>
                    <td>999 Pure Bullion Standard</td>
                    <td class="rate-gold-num">₹<?= number_format($gold24k_10g, 0) ?></td>
                </tr>
                <tr>
                    <td><strong>22K Gold (Per 1 Gram)</strong></td>
                    <td>916 BIS Hallmark (22K)</td>
                    <td class="rate-gold-num">₹<?= number_format($gold22k_1g, 0) ?></td>
                </tr>
                <tr>
                    <td><strong>22K Gold (Per 10 Grams / 1 Tola)</strong></td>
                    <td>Standard 1 Tola Sabarkantha Rate</td>
                    <td class="rate-gold-num">₹<?= number_format($gold22k_10g, 0) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($silver1g > 0): ?>
                <tr>
                    <td><strong>Pure Silver (Per 100 Grams)</strong></td>
                    <td>999 Fine Silver</td>
                    <td class="rate-gold-num">₹<?= number_format($silver1g * 100, 0) ?></td>
                </tr>
                <tr>
                    <td><strong>Pure Silver (Per 1 Kilogram)</strong></td>
                    <td>Bullion Grade 999 Bar/Chandi</td>
                    <td class="rate-gold-num">₹<?= number_format($silver1kg, 0) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($silver925g > 0): ?>
                <tr>
                    <td><strong>925 Sterling Silver (Per 1 Gram)</strong></td>
                    <td>Italian / Chandi Ornaments</td>
                    <td class="rate-gold-num">₹<?= number_format($silver925g, 0) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="font-size:0.88rem;color:var(--text-secondary,#6b7280);">
            Track daily rate movements, bullion calculator, and historical charts on our <a href="gold-rate-khedbrahma" style="color:#d4af37;font-weight:700;">Live Gold Rate Page &rarr;</a>
        </p>
    </section>

    <!-- Traditional Gujarati Ornaments in Sabarkantha -->
    <section class="reveal" style="margin:2.5rem 0;background:var(--surface,#fff);padding:1.75rem;border-radius:16px;border:1px solid var(--border-color,#e5e7eb);">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--text-primary,#111827);margin-bottom:0.5rem;">
            Traditional Gujarati Bridal &amp; Festive Jewellery in Sabarkantha
        </h2>
        <p style="color:var(--text-secondary,#4b5563);line-height:1.6;margin-bottom:1.25rem;">
            Sabarkantha weddings and festive celebrations carry rich heritage. Our master karigars handcraft time-honored Gujarati ornaments with precision:
        </p>

        <div class="ornament-grid">
            <div class="ornament-card">
                <h4>Dokiya (ડોકિયા)</h4>
                <p>Authentic 22K gold Dokiya and Tanmaniya pendants crafted with delicate filigree work, cherished in Sabarkantha and North Gujarat weddings.</p>
            </div>
            <div class="ornament-card">
                <h4>Patala &amp; Pichodi (પાટલા અને પીછોડી)</h4>
                <p>Traditional solid gold Gujarati bridal bangles designed for auspicious bridal sets, available in customizable weight and sizing.</p>
            </div>
            <div class="ornament-card">
                <h4>Rani Haar &amp; Chandan Haar</h4>
                <p>Majestic multi-strand 22K bridal necklaces featuring intricate peacock, royal floral, and heritage temple motifs.</p>
            </div>
            <div class="ornament-card">
                <h4>Kundan Butti &amp; Jhumki</h4>
                <p>Handcrafted antique and Kundan earrings with certified pearls and colored stones for traditional celebrations.</p>
            </div>
            <div class="ornament-card">
                <h4>Silver Kandora (ચાંદી કંદોરો)</h4>
                <p>Heavy traditional silver waist belt intricately woven with floral bells and antique chains, popular across Sabarkantha communities.</p>
            </div>
            <div class="ornament-card">
                <h4>Gents Silver Kada (જેન્ટ્સ કડા)</h4>
                <p>Sturdy, solid 925 and 999 fine silver kadas for men, available in plain chisel, lion-face, and textured royal finishes.</p>
            </div>
        </div>

        <p style="margin-top:1rem;font-size:0.92rem;color:var(--text-secondary,#4b5563);">
            Browse our complete online collection: <a href="gallery?metal_type=gold" style="color:#d4af37;font-weight:700;">22K Gold Jewellery &rarr;</a> | <a href="gallery?metal_type=silver" style="color:#d4af37;font-weight:700;">Silver Collection &rarr;</a> | <a href="Catalogue" style="color:#d4af37;font-weight:700;">Custom Design Catalogue &rarr;</a>
        </p>
    </section>

    <!-- Geographic Coverage Across Sabarkantha District -->
    <section class="reveal" style="margin:2.5rem 0;background:var(--surface,#fff);padding:1.75rem;border-radius:12px;border:1px solid var(--border-color,#e5e7eb);">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.45rem;color:var(--text-primary,#111827);margin-bottom:0.5rem;">
            Serving Every Taluka &amp; Major Town Across Sabarkantha District
        </h2>
        <p style="color:var(--text-secondary,#4b5563);line-height:1.6;margin-bottom:0.75rem;">
            Our showroom on Civil Road in Khedbrahma welcomes customers travelling along State Highway 9 / National Highway corridors, while our WhatsApp video concierge provides direct doorstep access across:
        </p>
        <div class="towns-chips">
            <span class="town-chip"><span class="city-dot"></span>Himatnagar (District HQ)</span>
            <span class="town-chip"><span class="city-dot"></span>Idar</span>
            <span class="town-chip"><span class="city-dot"></span>Khedbrahma (Showroom HQ)</span>
            <span class="town-chip"><span class="city-dot"></span>Vadali</span>
            <span class="town-chip"><span class="city-dot"></span>Vijaynagar</span>
            <span class="town-chip"><span class="city-dot"></span>Prantij</span>
            <span class="town-chip"><span class="city-dot"></span>Talod</span>
            <span class="town-chip"><span class="city-dot"></span>Poshina</span>
            <span class="town-chip"><span class="city-dot"></span>Shamlaji</span>
        </div>
        <p style="margin-top:1.25rem;font-size:0.92rem;color:var(--text-secondary,#4b5563);line-height:1.6;">
            Looking beyond Sabarkantha? ChandraKala Jewellers is celebrated as a leading <a href="top-jewellers-in-gujarat" style="color:#d4af37;font-weight:700;">top jeweller in Gujarat</a>, serving customers across Ahmedabad, Surat, Vadodara, Rajkot, and all 33 districts. You can also explore our dedicated <a href="jewellers-in-khedbrahma" style="color:#d4af37;font-weight:700;">Khedbrahma Jewellery Showroom Guide</a>.
        </p>
    </section>

    <!-- Direct Artisan Workshop Transparency -->
    <section class="reveal" style="margin:2.5rem 0;background:var(--surface,#fff);padding:1.75rem;border-radius:12px;border:1px solid var(--border-color,#e5e7eb);">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--text-primary,#111827);margin-bottom:0.5rem;">
            Direct Workshop Honesty vs Corporate Retail Chains in Sabarkantha
        </h2>
        <p style="color:var(--text-secondary,#4b5563);line-height:1.6;margin-bottom:1.25rem;">
            Why families across Sabarkantha choose our family goldsmith showroom over corporate commercial chains:
        </p>
        <div style="overflow-x:auto;">
            <table class="rate-card-table" style="width:100%;font-size:0.92rem;">
                <thead>
                    <tr>
                        <th>Parameter</th>
                        <th>ChandraKala Jewellers (Sabarkantha)</th>
                        <th>Corporate Chain Stores</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Making Charges</strong></td>
                        <td style="color:#059669;font-weight:700;">Direct artisan workshop pricing with zero inflated showroom markups</td>
                        <td style="color:var(--text-secondary,#4b5563);font-weight:600;">High retail overhead markups included in billing</td>
                    </tr>
                    <tr>
                        <td><strong>BIS Hallmark &amp; HUID</strong></td>
                        <td>100% Guaranteed 6-Digit HUID (BIS Care App Verifiable)</td>
                        <td>Standard Hallmark</td>
                    </tr>
                    <tr>
                        <td><strong>Scale Verification</strong></td>
                        <td>Digital scale weighing verified live on video call or in showroom</td>
                        <td>Standard counter billing scale</td>
                    </tr>
                    <tr>
                        <td><strong>Old Gold Testing</strong></td>
                        <td style="color:#059669;font-weight:700;">Transparent digital purity testing and prevailing market board rate valuation</td>
                        <td>Policy varies with deduction rules</td>
                    </tr>
                    <tr>
                        <td><strong>Custom Ornaments</strong></td>
                        <td style="color:#059669;font-weight:700;">Direct access to master goldsmiths for bespoke heirloom modifications</td>
                        <td>Fixed catalogue pieces only</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Sabarkantha FAQs -->
    <section class="reveal" style="margin:3rem 0;">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.65rem;text-align:center;color:var(--text-primary,#111827);margin-bottom:0.5rem;">
            Frequently Asked Questions — Jewellers in Sabarkantha
        </h2>
        <p style="text-align:center;color:var(--text-secondary,#4b5563);margin-bottom:1.75rem;">
            Common questions answered for jewellery shoppers across Himatnagar, Idar, Khedbrahma, and Sabarkantha district.
        </p>

        <div class="faq-accordion">
            <?php foreach ($sabarkanthaFaqs as $f): ?>
            <details class="faq-item">
                <summary><?= htmlspecialchars($f['q']) ?> <span style="font-size:1.2rem;color:#d4af37;">+</span></summary>
                <p><?= htmlspecialchars($f['a']) ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Call to Action Banner -->
    <section class="sabarkantha-cta-banner reveal">
        <h2>Visit ChandraKala Jewellers in Sabarkantha</h2>
        <p>Explore our certified 22K 916 gold collections, bridal sets, and fine silver jewellery in showroom or via WhatsApp live video tour.</p>
        <div class="cta-actions-row">
            <a href="gallery" class="btn-gold">Shop Online Collection &rarr;</a>
            <a href="<?= htmlspecialchars($b['maps_directions']) ?>" target="_blank" rel="noopener" class="btn-outline-gold">Showroom Location &amp; Directions</a>
            <a href="<?= htmlspecialchars($b['whatsapp']) ?>" target="_blank" rel="noopener" class="btn-outline-gold">WhatsApp Video Concierge</a>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
</body>
</html>
