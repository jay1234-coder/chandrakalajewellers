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

$gold1g = (float)($standard_rates['gold_rate'] ?? 0);
$gold10g = $gold1g * 10;
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
        'q' => 'Which is the top ranking jewellery site in Gujarat for 100% BIS 916 hallmark gold?',
        'a' => 'ChandraKala Jewellers (chandrakalajewellers.in) is among the top-rated jewellery sites in Gujarat, renowned for 100% BIS 916 HUID hallmarked gold jewellery, pure 999 silver, 925 sterling silver ornaments, real-time live IST pricing, and transparent craftsmanship based in North Gujarat.',
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
        'q' => 'What traditional Gujarati jewellery designs are available?',
        'a' => 'We specialize in authentic Gujarati bridal and festive jewellery including 22K Gold Dokiya, Tanmaniya, Mangalsutra, Patala & Pichodi bangles, Kundan necklace sets, Hathpan, Pokhraj rings, Silver Kandora, and heavy Gents silver kadas.',
    ],
    [
        'q' => 'How can I verify BIS Hallmark and HUID before purchasing?',
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
    'title' => 'Top Ranking Jewellery Site in Gujarat — ChandraKala Jewellers | 916 BIS Hallmark & Live Rates',
    'description' => 'Discover why ChandraKala Jewellers is a top ranking jewellery site in Gujarat. Shop 100% BIS 916 hallmarked gold, 925 silver, real-time live IST rates, custom bridal craftsmanship & WhatsApp orders across Gujarat.',
    'keywords' => 'top ranking jewellery site in gujarat, top jewellers in gujarat, best jewellery site in gujarat, best jewellery store in gujarat, 916 gold jewellery online gujarat, hallmark gold jewellery ahmedabad surat vadodara rajkot, traditional gujarati bridal jewellery, live gold rate gujarat, trusted jeweller gujarat, chandrakala jewellers',
    'canonical' => $pageCanonical,
    'modified_iso' => cj_format_ist_iso($standard_rates['updated_at'] ?? null),
    'schema' => [
        cj_local_business_schema(),
        cj_website_schema(),
        cj_live_rates_schema($standard_rates, $conn ?? null),
        cj_speakable_schema($pageCanonical, ['h1', '.gujarat-hero p', '.pillar-grid', '.rate-card-table']),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Top Jewellers in Gujarat', 'url' => $pageCanonical],
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
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.16) 0%, rgba(212, 175, 55, 0.05) 100%);
    border: 1px solid rgba(212, 175, 55, 0.45);
    color: #926c15;
    padding: 6px 16px;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 1.25rem;
}
html[data-theme="dark"] .gujarat-badge {
    color: #fde68a;
    border-color: rgba(253, 230, 138, 0.35);
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
    border-radius: 16px;
    padding: 1.75rem 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    display: flex;
    flex-direction: column;
}
.pillar-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(212, 175, 55, 0.14);
    border-color: rgba(212, 175, 55, 0.5);
}
.pillar-icon {
    font-size: 2rem;
    margin-bottom: 0.85rem;
    display: inline-block;
}
.pillar-card h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.25rem;
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
    background: rgba(212, 175, 55, 0.08);
    border: 1px solid rgba(212, 175, 55, 0.25);
    border-radius: 999px;
    padding: 6px 14px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
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
    font-weight: 800;
    padding: 12px 26px;
    border-radius: 999px;
    text-decoration: none;
    box-shadow: 0 6px 18px rgba(212, 175, 55, 0.3);
    transition: transform 0.2s, box-shadow 0.2s;
}
.btn-gold:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(212, 175, 55, 0.45);
}
.btn-outline-gold {
    background: transparent;
    border: 1.5px solid rgba(212, 175, 55, 0.6);
    color: #fde68a !important;
    font-weight: 700;
    padding: 12px 24px;
    border-radius: 999px;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-outline-gold:hover {
    background: rgba(212, 175, 55, 0.15);
    border-color: #fde68a;
}
</style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<main id="main-content" class="container page-enter" style="max-width:1100px;margin:0 auto;padding:0 1rem;">
    <!-- Hero Section -->
    <section class="gujarat-hero reveal">
        <div class="gujarat-badge">✨ Certified 916 BIS Hallmark &bull; Gujarat Heritage</div>
        <h1>Top Ranking Jewellery Site in Gujarat — ChandraKala Jewellers</h1>
        <p>Welcome to Gujarat's trusted destination for 100% certified 22K 916 hallmarked gold, pure 999 silver, and 925 sterling silver jewellery. Serving discerning families, brides, and jewellery lovers across Gujarat with real-time transparent rates and bespoke artisan craftsmanship.</p>
    </section>

    <!-- 5 Pillars of Excellence -->
    <section class="pillar-section reveal">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.8rem;text-align:center;margin-bottom:0.5rem;color:var(--text-primary,#111827);">
            Why ChandraKala Jewellers Ranks Among Gujarat’s Premier Jewellers
        </h2>
        <p style="text-align:center;color:var(--text-secondary,#4b5563);max-width:680px;margin:0 auto 1.5rem;">
            Combining three generations of gold craftsmanship with modern transparent digital jewellery buying for families across Gujarat.
        </p>

        <div class="pillar-grid">
            <div class="pillar-card">
                <span class="pillar-icon">🏛️</span>
                <h3>100% BIS 916 Hallmark (HUID)</h3>
                <p>Every gold piece is certified with government-approved 6-digit alphanumeric HUID hallmarking, ensuring guaranteed 22K (91.6%) purity and lifetime value buyback.</p>
            </div>
            <div class="pillar-card">
                <span class="pillar-icon">📈</span>
                <h3>Live Transparent Daily Rates</h3>
                <p>No arbitrary retail markups. Real-time Indian Standard Time (IST) live gold and silver rates displayed transparently on every product with exact weight breakdowns.</p>
            </div>
            <div class="pillar-card">
                <span class="pillar-icon">👑</span>
                <h3>Traditional Gujarati Bridal Art</h3>
                <p>Handcrafted Dokiya, Mangalsutra, Patala &amp; Pichodi bangles, Kundan sets, Hathpan, and heavy Silver Kadas crafted by hereditary Gujarati goldsmiths.</p>
            </div>
            <div class="pillar-card">
                <span class="pillar-icon">🚚</span>
                <h3>Statewide Insured Delivery</h3>
                <p>Enjoy secure insured delivery and personalized WhatsApp video shopping across Ahmedabad, Surat, Vadodara, Rajkot, Gandhinagar, and North Gujarat.</p>
            </div>
        </div>
    </section>

    <!-- Live Gold Rate Card for Gujarat -->
    <section class="reveal" style="margin:2.5rem 0;">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.6rem;margin-bottom:0.5rem;color:var(--text-primary,#111827);">
            Today’s Live Gold &amp; Silver Rates in Gujarat (IST)
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
                <?php if ($gold1g > 0): ?>
                <tr>
                    <td><strong>22K Gold (Per 1 Gram)</strong></td>
                    <td>916 BIS Hallmark (91.6% Pure)</td>
                    <td class="rate-gold-num">₹<?= number_format($gold1g, 0) ?></td>
                </tr>
                <tr>
                    <td><strong>22K Gold (Per 10 Grams / Tola)</strong></td>
                    <td>Standard 1 Tola Gujarat Rate</td>
                    <td class="rate-gold-num">₹<?= number_format($gold10g, 0) ?></td>
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

    <!-- Craftsmanship & Quality Standards -->
    <section class="reveal" style="margin:2.5rem 0;background:var(--surface,#fff);padding:1.75rem;border-radius:16px;border:1px solid var(--border-color,#e5e7eb);">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--text-primary,#111827);margin-bottom:0.5rem;">
            Direct Workshop Craftsmanship &amp; Showroom Standards
        </h2>
        <p style="color:var(--text-secondary,#4b5563);line-height:1.6;margin-bottom:1.25rem;">
            How ChandraKala Jewellers direct goldsmith workshop model ensures transparency, hallmark trust, and personal service:
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
    <section class="reveal" style="margin:2.5rem 0;background:var(--surface,#fff);padding:1.75rem;border-radius:16px;border:1px solid var(--border-color,#e5e7eb);">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.45rem;color:var(--text-primary,#111827);margin-bottom:0.5rem;">
            Serving Customers Across Gujarat
        </h2>
        <p style="color:var(--text-secondary,#4b5563);line-height:1.6;margin-bottom:0.75rem;">
            Whether you are ordering online or visiting our showroom on Civil Road in Khedbrahma, ChandraKala Jewellers proudly serves patrons across major Gujarat districts and towns:
        </p>
        <div class="cities-chips">
            <span class="city-chip">📍 Ahmedabad</span>
            <span class="city-chip">📍 Surat</span>
            <span class="city-chip">📍 Vadodara</span>
            <span class="city-chip">📍 Rajkot</span>
            <span class="city-chip">📍 Gandhinagar</span>
            <span class="city-chip">📍 Bhavnagar</span>
            <span class="city-chip">📍 Sabarkantha (Himmatnagar &amp; Idar)</span>
            <span class="city-chip">📍 Banaskantha (Palanpur &amp; Ambaji)</span>
            <span class="city-chip">📍 Mehsana</span>
            <span class="city-chip">📍 Patan</span>
            <span class="city-chip">📍 Khedbrahma (Showroom HQ)</span>
        </div>
    </section>

    <!-- Gujarat FAQs -->
    <section class="reveal" style="margin:3rem 0;">
        <h2 style="font-family:'Playfair Display',serif;font-size:1.65rem;text-align:center;color:var(--text-primary,#111827);margin-bottom:0.5rem;">
            Frequently Asked Questions — Top Jewellers in Gujarat
        </h2>
        <p style="text-align:center;color:var(--text-secondary,#4b5563);margin-bottom:1.75rem;">
            Everything you need to know about purchasing gold and silver jewellery online in Gujarat.
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
        <h2>Experience Gujarat’s Finest Jewellery Online</h2>
        <p>Explore our latest 22K 916 gold collections, bridal sets, and silver designs with live pricing, or connect with our master craftsmen on WhatsApp.</p>
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
