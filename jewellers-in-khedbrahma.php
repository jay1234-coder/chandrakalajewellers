<?php
/**
 * Local SEO landing page — jewellers / jewellery in Khedbrahma
 */
$activePage = 'home';
require_once 'config/db.php';
require_once 'includes/pricing.php';
require_once 'includes/seo.php';
$b = cj_business_info();
$standard_rates = isset($conn) && $conn instanceof mysqli ? cj_get_standard_rates($conn) : [];
$seo = cj_merge_seo([
    'title' => 'Best Jewellers in Khedbrahma & Sabarkantha | ChandraKala Jewellers',
    'description' => 'Best jewellers in Khedbrahma? Visit ChandraKala Jewellers on Civil Road for 916 gold, pure silver, 925 silver, live IST rates & WhatsApp orders in Sabarkantha.',
    'canonical' => cj_abs_url('jewellers-in-khedbrahma'),
    'modified_iso' => cj_format_ist_iso($standard_rates['updated_at'] ?? null),
    'schema' => [
        cj_local_business_schema(),
        cj_website_schema(),
        cj_live_rates_schema($standard_rates, $conn ?? null),
        cj_speakable_schema(cj_abs_url('jewellers-in-khedbrahma'), ['h1', 'article p']),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Jewellers in Khedbrahma', 'url' => cj_abs_url('jewellers-in-khedbrahma')],
        ]),
        cj_faq_schema(cj_default_faqs($standard_rates)),
    ],
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
</head>
<body>
<?php include 'includes/header.php'; ?>

<main class="container">
    <div class="page-hero reveal">
        <h1>Jewellers in Khedbrahma — ChandraKala Jewellers</h1>
        <p>Your local gold &amp; silver jewellery shop on Civil Road, Sabarkantha. Search “jewellers near me” in Khedbrahma and find Chandrakala Jewellers on Google Maps.</p>
    </div>

    <article class="reveal" style="max-width:760px;margin:0 auto;line-height:1.75;">
        <p>If you typed <strong>jewellers</strong>, <strong>jewellery</strong>, <strong>Chandrakala</strong>, <strong>ChandraKala Jewellers</strong>, or <strong>jewellers near me</strong> looking for a shop in <strong>Khedbrahma</strong>, you are in the right place.</p>

        <h2 style="font-family:'Playfair Display',serif;font-size:1.4rem;margin:1.75rem 0 0.75rem;">Gold &amp; silver jewellery shop in Khedbrahma</h2>
        <p>ChandraKala Jewellers stocks <a href="gallery?metal_type=gold">916 gold jewellery</a>, <a href="gallery?metal_type=silver">silver jewellery</a>, and <a href="gallery?metal_type=silver_925">925 silver</a> with prices linked to today’s metal rates. Browse the full <a href="gallery">online shop</a> or our <a href="Catalogue">custom design catalogue</a>.</p>

        <h2 style="font-family:'Playfair Display',serif;font-size:1.4rem;margin:1.75rem 0 0.75rem;">Store location — Civil Road, Khedbrahma</h2>
        <p><strong><?= htmlspecialchars($b['name']) ?></strong><br>
        <?= htmlspecialchars($b['street']) ?><br>
        <?= htmlspecialchars($b['locality']) ?>-<?= htmlspecialchars($b['postal']) ?>, <?= htmlspecialchars($b['district']) ?>, <?= htmlspecialchars($b['region']) ?><br>
        Phone: <a href="tel:<?= htmlspecialchars($b['phone']) ?>"><?= htmlspecialchars($b['phone_display']) ?></a></p>
        <p><a class="btn" href="<?= htmlspecialchars($b['maps_directions']) ?>" target="_blank" rel="noopener">Get Google Maps directions</a>
        <a class="btn btn-secondary" href="contact" style="margin-left:8px;">Contact page</a></p>

        <h2 style="font-family:'Playfair Display',serif;font-size:1.4rem;margin:1.75rem 0 0.75rem;">Serving Sabarkantha &amp; Statewide Gujarat</h2>
        <p>Customers visit us from Khedbrahma town, surrounding villages, and across the district as a trusted <a href="jewellers-in-sabarkantha" style="color:#d4af37;font-weight:700;">jeweller in Sabarkantha</a> (serving Himatnagar, Idar, Vadali, and Vijaynagar). Beyond North Gujarat, ChandraKala Jewellers is recognized as a leading <a href="top-jewellers-in-gujarat" style="color:#d4af37;font-weight:700;">top jeweller in Gujarat</a>, delivering certified 100% BIS 916 hallmarked ornaments with insured transit across Ahmedabad, Surat, Vadodara, Rajkot, and all 33 districts.</p>

        <h2 style="font-family:'Playfair Display',serif;font-size:1.4rem;margin:1.75rem 0 0.75rem;">How to buy</h2>
        <ol style="padding-left:1.2rem;">
            <li>Shop online on this website</li>
            <li>Add to cart or use Buy Now</li>
            <li>Confirm on WhatsApp — <a href="<?= htmlspecialchars($b['whatsapp']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($b['phone_display']) ?></a></li>
            <li>Or visit the showroom opposite Bhoomi Complex</li>
        </ol>

        <section style="margin-top:2.5rem;" aria-label="Frequently asked questions">
            <h2 style="font-family:'Playfair Display',serif;font-size:1.4rem;margin-bottom:1rem;">FAQs — jewellers &amp; jewellery in Khedbrahma</h2>
            <?php foreach (cj_default_faqs($standard_rates) as $faq): ?>
                <details style="margin-bottom:0.75rem;padding:0.75rem 0;border-bottom:1px solid #eee;">
                    <summary style="font-weight:600;cursor:pointer;"><?= htmlspecialchars($faq['q']) ?></summary>
                    <p style="margin:0.5rem 0 0;color:#555;"><?= htmlspecialchars($faq['a']) ?></p>
                </details>
            <?php endforeach; ?>
        </section>
    </article>
</main>

<?php include 'includes/footer.php'; ?>
</body>
</html>
