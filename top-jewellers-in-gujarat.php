<?php
/**
 * Pillar Landing Page — Top Ranking Jewellery Site in Gujarat
 * Targets statewide search queries: "top ranking jewellery site in gujarat",
 * "best jewellers in gujarat", "top jewellery store gujarat", etc.
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

// Statewide Gujarat FAQs for rich result FAQPage schema
$gujaratFaqs = [
    [
        'q' => 'Which is the top jeweller in Gujarat for 100% BIS 916 hallmark gold?',
        'a' => 'ChandraKala Jewellers is a top jeweller in Gujarat, trusted across three generations for 100% BIS 916 HUID hallmarked gold jewellery, pure 999 fine silver, 925 sterling silver ornaments, real-time live IST bullion rates, and authentic Gujarati artisan craftsmanship.',
    ],
    [
        'q' => 'How can I choose a trusted jeweller in Gujarat for bridal and daily jewellery?',
        'a' => 'When choosing a trusted jeweller in Gujarat, verify three factors: 1) 100% BIS Hallmark with a laser-engraved 6-digit alphanumeric HUID verifiable on the government BIS Care app, 2) Transparent live market bullion pricing with zero hidden retail markups, and 3) Video call inspection with certified digital scale weighing before purchase.',
    ],
    [
        'q' => 'Can I buy certified gold & silver jewellery online with doorstep delivery across Gujarat?',
        'a' => 'Yes. Customers across Gujarat (including Ahmedabad, Surat, Vadodara, Rajkot, Gandhinagar, Bhavnagar, Jamnagar, Junagadh, Sabarkantha, and all 33 districts) can browse our 60+ ready stock items and 138+ custom catalogue designs online, place orders with secure online checkout, or request insured delivery via direct WhatsApp video concierge at +91 9427080359.',
    ],
    [
        'q' => 'How does ChandraKala Jewellers ensure transparent daily gold rates in Gujarat?',
        'a' => 'ChandraKala Jewellers updates live showroom board rates daily in Indian Standard Time (IST) reflecting international bullion markets and Gujarat state trade rates. All prices on our website calculate transparently from exact metal weight, making charges, and hallmarking without hidden markups.',
    ],
    [
        'q' => 'What traditional Gujarati jewellery designs are available from this Gujarat jeweller?',
        'a' => 'We specialize in authentic Gujarati bridal and festive jewellery including 22K Gold Dokiya, Tanmaniya, Mangalsutra, Patala & Pichodi bangles, Kundan necklace sets, Hathpan, Pokhraj rings, Silver Kandora, and heavy Gents silver kadas.',
    ],
    [
        'q' => 'How can I verify BIS Hallmark and HUID before purchasing from a jeweller in Gujarat?',
        'a' => 'Every gold ornament at ChandraKala Jewellers features a laser-engraved 6-digit alphanumeric HUID code along with the BIS hallmark triangle stamp. You can input this HUID code directly into the government\'s official BIS Care App on Android or iOS to verify exact purity, assaying laboratory, and certification date instantly.',
    ],
    [
        'q' => 'How are making charges calculated at ChandraKala Jewellers?',
        'a' => 'At ChandraKala Jewellers, making charges are determined honestly and transparently based on the intricate craftsmanship, design complexity, and metal weight of each ornament. As an artisan-led family jewellery showroom with our own workshop, we provide clear itemized billing with zero hidden fees.',
    ],
    [
        'q' => 'Can I schedule a live video call showroom inspection from Ahmedabad, Surat, or Rajkot?',
        'a' => 'Yes! We offer a complimentary WhatsApp Video Showroom Tour (+91 9427080359). Our jewellers will display any piece under high-definition lighting, demonstrate weight on certified digital scales, show hallmark engravings, and explain custom modifications in Gujarati or Hindi.',
    ],
    [
        'q' => 'What is your gold exchange policy?',
        'a' => 'We offer complete transparency for gold exchange. Your gold is tested and weighed digitally in front of you (or on video call), with clear calculations based on verified purity and prevailing market board rates.',
    ],
    [
        'q' => 'Where is ChandraKala Jewellers showroom located in Gujarat?',
        'a' => 'Our flagship showroom is located Opposite Bhoomi Complex, Civil Road, Khedbrahma - 383255, Sabarkantha district, Gujarat. We serve visitors from across Gujarat and accept WhatsApp orders with video inspection at +91 9427080359.',
    ],
];

$pageCanonical = cj_abs_url('top-jewellers-in-gujarat');

$seo = cj_merge_seo([
    'title' => 'Top Jeweller in Gujarat — ChandraKala Jewellers | 100% BIS 916 Gold, Silver & Live Rates',
    'description' => 'ChandraKala Jewellers is a premier jeweller in Gujarat, trusted for 100% BIS 916 HUID hallmarked gold, pure 999 silver, live daily IST rates & insured statewide delivery across Gujarat.',
    'keywords' => 'top jeweller in gujarat, jeweller in gujarat, top jewellers in gujarat, jewellers in gujarat, best jeweller in gujarat, best jewellers in gujarat, trusted gold jeweller gujarat, 916 hallmark jewellery gujarat, ahmedabad surat vadodara rajkot jeweller, chandrakala jewellers',
    'canonical' => $pageCanonical,
    'modified_iso' => cj_format_ist_iso($standard_rates['updated_at'] ?? null),
    'schema' => [
        cj_local_business_schema(),
        cj_website_schema(),
        cj_live_rates_schema($standard_rates, $conn ?? null),
        cj_speakable_schema($pageCanonical, ['h1', '.gujarat-hero p', '.pillar-grid', '.rate-card-table']),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Top Jeweller in Gujarat', 'url' => $pageCanonical],
        ]),
        cj_faq_schema($gujaratFaqs),
    ],
]);
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
<style>
/* ── Top Jewellers in Gujarat Luxury Page Styles ── */
.gujarat-hero {
    text-align: center;
    padding: 3rem 1rem 2.2rem;
    max-width: 880px;
    margin: 0 auto;
}
.gujarat-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(212, 175, 55, 0.08);
    border: 1px solid rgba(212, 175, 55, 0.28);
    color: var(--primary-gold-dark, #926c15);
    padding: 5px 14px;
    border-radius: 4px;
    font-size: 0.82rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    margin-bottom: 1.25rem;
}
html[data-theme="dark"] .gujarat-badge {
    color: #fde68a;
    border-color: rgba(253, 230, 138, 0.25);
}
.gujarat-hero h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: clamp(2rem, 4.5vw, 2.9rem);
    line-height: 1.2;
    color: var(--text-primary, #111827);
    margin-bottom: 1rem;
    font-weight: 700;
}
.gujarat-hero p {
    font-size: clamp(1rem, 2vw, 1.15rem);
    color: var(--text-secondary, #4b5563);
    line-height: 1.7;
    margin: 0 auto;
}

/* ── 5 Pillars of Excellence ── */
.pillar-section {
    margin: 2.5rem 0 3.5rem;
}
.pillar-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.25rem;
    margin-top: 1.5rem;
}
.pillar-card {
    background: var(--surface, #ffffff);
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 10px;
    padding: 1.75rem 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: transform 0.2s ease, border-color 0.2s ease;
    display: flex;
    flex-direction: column;
}
.pillar-card:hover {
    transform: translateY(-1px);
    border-color: rgba(212, 175, 55, 0.5);
}
.pillar-icon-box {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    background: rgba(212, 175, 55, 0.1);
    color: #926c15;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
}
html[data-theme="dark"] .pillar-icon-box {
    color: #fde68a;
    background: rgba(253, 230, 138, 0.12);
}
.pillar-card h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.22rem;
    color: var(--text-primary, #111827);
    margin-bottom: 0.5rem;
}
.pillar-card p {
    font-size: 0.92rem;
    color: var(--text-secondary, #4b5563);
    line-height: 1.6;
    margin: 0;
}
    color: var(--text-primary, #111827);
    margin-bottom: 0.5rem;
}
.pillar-card p {
    font-size: 0.92rem;
    color: var(--text-secondary, #4b5563);
    line-height: 1.6;
    margin: 0;
}

/* ── Live Rates Table Card ── */
.rate-card-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--surface, #ffffff);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
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
    background: rgba(212, 175, 55, 0.12);
    font-weight: 700;
    color: var(--text-primary, #111827);
}
.rate-card-table td.rate-gold-num {
    font-weight: 800;
    color: #926c15;
    font-size: 1.1rem;
}
html[data-theme="dark"] .rate-card-table td.rate-gold-num {
    color: #fde68a;
}

/* ── Gujarat City Hubs ── */
.cities-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 1rem;
}
.city-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(212, 175, 55, 0.06);
    border: 1px solid rgba(212, 175, 55, 0.22);
    border-radius: 4px;
    padding: 5px 12px;
    font-size: 0.84rem;
    font-weight: 500;
    color: var(--text-primary, #1f2937);
    transition: all 0.2s ease;
}
.city-chip:hover {
    background: rgba(212, 175, 55, 0.15);
    border-color: rgba(212, 175, 55, 0.5);
}
.city-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #d4af37;
    display: inline-block;
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
    border-radius: 14px;
    padding: 1.5rem;
    transition: all 0.25s ease;
}
.ornament-card:hover {
    border-color: rgba(212, 175, 55, 0.55);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(212, 175, 55, 0.12);
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
    border-radius: 12px;
    margin-bottom: 0.85rem;
    overflow: hidden;
    transition: border-color 0.2s ease;
}
.faq-item summary {
    font-family: 'Playfair Display', serif;
    font-size: 1.08rem;
    font-weight: 700;
    padding: 1.1rem 1.4rem;
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
    padding: 0 1.4rem 1.25rem;
    color: var(--text-secondary, #4b5563);
    line-height: 1.7;
    margin: 0;
    font-size: 0.95rem;
}

/* ── CTA Banner ── */
.gujarat-cta-banner {
    background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
    border: 1px solid rgba(212, 175, 55, 0.35);
    border-radius: 20px;
    padding: 2.75rem 1.5rem;
    text-align: center;
    color: #ffffff;
    margin: 3.5rem 0 2rem;
}
.gujarat-cta-banner h2 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.5rem, 3.5vw, 2.2rem);
    color: #fde68a;
    margin-bottom: 0.75rem;
}
.gujarat-cta-banner p {
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
    background: linear-gradient(135deg, #d4af37 0%, #aa820a 100%);
    color: #0b0c10 !important;
    font-weight: 700;
    padding: 11px 22px;
    border-radius: 6px;
    text-decoration: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.btn-gold:hover {
    transform: translateY(-1px);
    opacity: 0.95;
}
.btn-outline-gold {
    background: transparent;
    border: 1px solid rgba(212, 175, 55, 0.6);
    color: #fde68a !important;
    font-weight: 600;
    padding: 11px 20px;
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
    <section class="gujarat-hero reveal">
        <div class="gujarat-badge">Certified 100% BIS 916 HUID &bull; Heritage Jeweller in Gujarat</div>
        <h1>Top Jeweller in Gujarat — ChandraKala Jewellers</h1>
        <p>Welcome to ChandraKala Jewellers, recognized as a top jeweller in Gujarat for 100% certified 22K 916 hallmarked gold, pure 999 fine silver, and 925 sterling silver jewellery. Trusted across three generations, we serve families, brides, and jewellery lovers across Gujarat with real-time transparent rates, direct artisan workshop pricing, and tamper-proof insured delivery.</p>
    </section>

    <!-- 5 Pillars of Excellence -->
    <section class="pillar-section reveal">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.8rem;text-align:center;margin-bottom:0.5rem;color:var(--text-primary,#111827);">
            Why ChandraKala Jewellers Ranks Among the Top Jewellers in Gujarat
        </h2>
        <p style="text-align:center;color:var(--text-secondary,#4b5563);max-width:720px;margin:0 auto 1.5rem;">
            As an artisan-led jeweller in Gujarat, we combine hereditary goldsmith craftsmanship with 100% digital transparency for families in Ahmedabad, Surat, Vadodara, Rajkot, and all 33 districts.
        </p>

        <div class="pillar-grid">
            <div class="pillar-card">
                <div class="pillar-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4.5v6.5c0 5-3.5 9-8 10-4.5-1-8-5-8-10V6.5L12 2z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <h3>100% BIS 916 Hallmark (HUID)</h3>
                <p>Every gold piece is certified with government-approved 6-digit alphanumeric HUID hallmarking, ensuring guaranteed 22K (91.6%) purity and verified authentication on the official BIS Care app.</p>
            </div>
            <div class="pillar-card">
                <div class="pillar-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h18"/></svg>
                </div>
                <h3>Live Transparent Daily Rates</h3>
                <p>No arbitrary retail markups. Real-time Indian Standard Time (IST) live gold and silver rates displayed transparently on every product with exact weight breakdowns.</p>
            </div>
            <div class="pillar-card">
                <div class="pillar-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <h3>Traditional Gujarati Bridal Art</h3>
                <p>Handcrafted Dokiya, Mangalsutra, Patala &amp; Pichodi bangles, Kundan sets, Hathpan, and heavy Silver Kadas crafted by hereditary Gujarati goldsmiths.</p>
            </div>
            <div class="pillar-card">
                <div class="pillar-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                </div>
                <h3>Statewide Insured Delivery</h3>
                <p>Enjoy secure insured delivery and personalized WhatsApp video shopping across Ahmedabad, Surat, Vadodara, Rajkot, Gandhinagar, and North Gujarat.</p>
            </div>
        </div>
    </section>

    <!-- Live Gold Rate Card for Gujarat -->
    <section class="reveal" style="margin:2.5rem 0;">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.6rem;margin-bottom:0.5rem;color:var(--text-primary,#111827);">
            Today’s Live Gold &amp; Silver Rates from a Leading Jeweller in Gujarat (IST)
        </h2>
        <p style="color:var(--text-secondary,#4b5563);margin-bottom:1rem;">
            Updated daily as per Gujarat bullion market benchmarks. Live rates valid as of <strong><?= htmlspecialchars($rateUpdatedIst) ?></strong>.
        </p>

        <table class="rate-card-table">
            <thead>
                <tr>
                    <th>Precious Metal Category</th>
                    <th>Purity / Hallmark</th>
                    <th>Live Showroom Rate (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($gold24k_1g > 0): ?>
                <tr>
                    <td><strong>24K Pure Gold (Per 10 Grams)</strong></td>
                    <td>999 Fine Bullion Rate</td>
                    <td class="rate-gold-num">₹<?= number_format($gold24k_10g, 0) ?></td>
                </tr>
                <tr>
                    <td><strong>22K Gold (Per 1 Gram)</strong></td>
                    <td>916 BIS Hallmark (22K)</td>
                    <td class="rate-gold-num">₹<?= number_format($gold22k_1g, 0) ?></td>
                </tr>
                <tr>
                    <td><strong>22K Gold (Per 10 Grams / Tola)</strong></td>
                    <td>Standard 1 Tola Gujarat 916 Rate</td>
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
            View full historical rates, charts, and calculation breakdowns on our <a href="gold-rate-khedbrahma" style="color:#d4af37;font-weight:700;">Live Gold Rate Page &rarr;</a>
        </p>
    </section>

    <!-- Traditional Gujarati Ornaments Showcase -->
    <section class="reveal" style="margin:2.5rem 0;background:var(--surface,#fff);padding:1.75rem;border-radius:16px;border:1px solid var(--border-color,#e5e7eb);">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--text-primary,#111827);margin-bottom:0.5rem;">
            Traditional Gujarati Bridal &amp; Festive Ornaments (ગુજરાતી પરંપરાગત ઘરેણાં)
        </h2>
        <p style="color:var(--text-secondary,#4b5563);line-height:1.6;margin-bottom:1.25rem;">
            As an authentic jeweller in Gujarat, ChandraKala Jewellers preserves hereditary goldsmith traditions, crafting cultural ornaments revered in Gujarati weddings, baby showers (Shrimant), and festivals:
        </p>

        <div class="ornament-grid">
            <div class="ornament-card">
                <h4>Dokiya &amp; Tanmaniya (ડોકિયા)</h4>
                <p>Signature 22K gold Dokiya and Mangalsutra designs with fine filigree, meenakari accents, and auspicious black beads for Gujarati brides.</p>
            </div>
            <div class="ornament-card">
                <h4>Patala &amp; Pichodi (પાટલા અને પીછોડી)</h4>
                <p>Authentic Gujarati solid gold wedding bangles handcrafted in pair sets, featuring embossed floral and royal geometric motifs.</p>
            </div>
            <div class="ornament-card">
                <h4>Rani Haar &amp; Chandan Haar</h4>
                <p>Grand multi-layered 22K bridal gold necklaces, exquisitely styled with peacock emblems and heritage temple craftsmanship.</p>
            </div>
            <div class="ornament-card">
                <h4>Kundan Butti &amp; Jhumki</h4>
                <p>Antique matte-finish and Kundan earrings adorned with certified freshwater pearls, matching bridal ensembles seamlessly.</p>
            </div>
            <div class="ornament-card">
                <h4>Silver Kandora (ચાંદી કંદોરો)</h4>
                <p>Traditional chandi waist belts intricately designed with delicate ghunghroo bells and antique filigree links for festive occasions.</p>
            </div>
            <div class="ornament-card">
                <h4>Gents Silver Kada (જેન્ટ્સ કડા)</h4>
                <p>Heavyweight 925 and 999 fine silver kadas for gentlemen, featuring lion-head (Sher-mukh), chisel carved, and solid royal styles.</p>
            </div>
        </div>

        <p style="margin-top:1rem;font-size:0.92rem;color:var(--text-secondary,#4b5563);">
            Browse designs: <a href="gallery?metal_type=gold" style="color:#d4af37;font-weight:700;">22K Gold Collection &rarr;</a> | <a href="gallery?metal_type=silver" style="color:#d4af37;font-weight:700;">Silver Ornaments &rarr;</a> | <a href="Catalogue" style="color:#d4af37;font-weight:700;">138+ Custom Design Catalogue &rarr;</a>
        </p>
    </section>

    <!-- Craftsmanship & Quality Standards -->
    <section class="reveal" style="margin:2.5rem 0;background:var(--surface,#fff);padding:1.75rem;border-radius:16px;border:1px solid var(--border-color,#e5e7eb);">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--text-primary,#111827);margin-bottom:0.5rem;">
            Direct Workshop Craftsmanship &amp; Standards of a Trusted Jeweller in Gujarat
        </h2>
        <p style="color:var(--text-secondary,#4b5563);line-height:1.6;margin-bottom:1.25rem;">
            How ChandraKala Jewellers direct goldsmith workshop model ensures hallmark trust, artisan honesty, and personalized service unmatched by impersonal commercial retail chains:
        </p>
        <div style="overflow-x:auto;">
            <table class="rate-card-table" style="width:100%;font-size:0.92rem;">
                <thead>
                    <tr>
                        <th>Quality &amp; Service Metric</th>
                        <th>ChandraKala Jewellers</th>
                        <th>Corporate Retail Chains</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Making Charges</strong></td>
                        <td style="color:#059669;font-weight:700;">Direct artisan pricing based on design complexity</td>
                        <td style="color:var(--text-secondary,#4b5563);font-weight:600;">Standard retail catalogue rates with showroom overheads</td>
                    </tr>
                    <tr>
                        <td><strong>BIS Hallmark &amp; HUID</strong></td>
                        <td>100% Guaranteed 6-Digit HUID (BIS Care App Verifiable)</td>
                        <td>Standard Hallmark</td>
                    </tr>
                    <tr>
                        <td><strong>Weight &amp; Purity Verification</strong></td>
                        <td>Exact digital weight scale verified on live video call</td>
                        <td>Counter billing scales</td>
                    </tr>
                    <tr>
                        <td><strong>Old Gold Valuation</strong></td>
                        <td style="color:#059669;font-weight:700;">Transparent testing &amp; live market board rate valuation</td>
                        <td>Varies by chain policy</td>
                    </tr>
                    <tr>
                        <td><strong>Customer Consultation</strong></td>
                        <td style="color:#059669;font-weight:700;">Direct consultation with experienced family jewellers</td>
                        <td>Sales floor representatives</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Geographic Coverage Across Gujarat -->
    <section class="reveal" style="margin:2.5rem 0;background:var(--surface,#fff);padding:1.75rem;border-radius:12px;border:1px solid var(--border-color,#e5e7eb);">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.45rem;color:var(--text-primary,#111827);margin-bottom:0.5rem;">
            A Jeweller in Gujarat Serving Patrons Across Every Major District
        </h2>
        <p style="color:var(--text-secondary,#4b5563);line-height:1.6;margin-bottom:0.75rem;">
            Whether you order online with insured transit or visit our showroom on Civil Road in Khedbrahma, ChandraKala Jewellers proudly delivers hallmarked gold and silver across all Gujarat regions:
        </p>
        <div class="cities-chips">
            <span class="city-chip"><span class="city-dot"></span>Ahmedabad</span>
            <span class="city-chip"><span class="city-dot"></span>Surat</span>
            <span class="city-chip"><span class="city-dot"></span>Vadodara</span>
            <span class="city-chip"><span class="city-dot"></span>Rajkot</span>
            <span class="city-chip"><span class="city-dot"></span>Gandhinagar</span>
            <span class="city-chip"><span class="city-dot"></span>Bhavnagar</span>
            <a href="jewellers-in-sabarkantha" class="city-chip" style="text-decoration:none;font-weight:600;color:#926c15;"><span class="city-dot"></span>Sabarkantha District (Himatnagar &amp; Idar) &rarr;</a>
            <span class="city-chip"><span class="city-dot"></span>Banaskantha (Palanpur &amp; Ambaji)</span>
            <span class="city-chip"><span class="city-dot"></span>Mehsana</span>
            <span class="city-chip"><span class="city-dot"></span>Patan</span>
            <a href="jewellers-in-khedbrahma" class="city-chip" style="text-decoration:none;font-weight:600;color:#926c15;"><span class="city-dot"></span>Khedbrahma (Showroom HQ) &rarr;</a>
        </div>
    </section>

    <!-- Gujarat FAQs -->
    <section class="reveal" style="margin:3rem 0;">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.65rem;text-align:center;color:var(--text-primary,#111827);margin-bottom:0.5rem;">
            Frequently Asked Questions — Top Jeweller in Gujarat
        </h2>
        <p style="text-align:center;color:var(--text-secondary,#4b5563);margin-bottom:1.75rem;">
            Everything you need to know about purchasing certified gold and silver ornaments from a trusted jeweller in Gujarat.
        </p>

        <div class="faq-accordion">
            <?php foreach ($gujaratFaqs as $f): ?>
            <details class="faq-item">
                <summary><?= htmlspecialchars($f['q']) ?> <span style="font-size:1.2rem;color:#d4af37;">+</span></summary>
                <p><?= htmlspecialchars($f['a']) ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Call to Action Banner -->
    <section class="gujarat-cta-banner reveal">
        <h2>Experience Gujarat’s Finest Jeweller Online</h2>
        <p>Explore our certified 22K 916 gold collections, bridal sets, and pure silver ornaments with live IST pricing, or connect directly with our master goldsmiths on WhatsApp.</p>
        <div class="cta-actions-row">
            <a href="gallery" class="btn-gold">Shop Online Collection &rarr;</a>
            <a href="Catalogue" class="btn-outline-gold">Custom Design Catalogue</a>
            <a href="<?= htmlspecialchars($b['whatsapp']) ?>" target="_blank" rel="noopener" class="btn-outline-gold">Chat on WhatsApp</a>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
</body>
</html>
