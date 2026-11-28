<?php
$activePage = 'about';
require_once 'includes/seo.php';
$b = cj_business_info();
$seo = cj_merge_seo([
    'title' => 'About ChandraKala Jewellers | Trusted Jeweller in Gujarat — Khedbrahma Showroom',
    'description' => 'About ChandraKala Jewellers — a trusted family jeweller in Gujarat offering 100% BIS 916 gold, pure silver & 925 sterling silver with live daily IST rates, custom artisan bridal crafting and statewide insured delivery.',
    'keywords' => 'about ChandraKala Jewellers, jeweller in gujarat, top jeweller in gujarat, Chandrakala Jewellers Khedbrahma, family jewellers Sabarkantha, gold shop Civil Road history',
    'canonical' => cj_abs_url('about'),
    'schema' => [
        cj_local_business_schema(),
        cj_website_schema(),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'About', 'url' => cj_abs_url('about')],
        ]),
        cj_faq_schema(cj_default_faqs()),
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
        <h1>About ChandraKala Jewellers</h1>
        <p>A trusted jeweller in Gujarat crafting pure 916 gold and silver with heritage and care.</p>
    </div>

    <section class="reveal" style="max-width:720px;margin:0 auto 2rem;line-height:1.7;">
        <p><strong>ChandraKala Jewellers</strong> (also known as <strong>Chandrakala Jewellers</strong>) is a premier family-owned <strong>jeweller in Gujarat</strong>, with our flagship showroom located on Civil Road, Khedbrahma, Sabarkantha. Customers from across Gujarat — including Ahmedabad, Surat, Vadodara, Rajkot, Idar, and Himmatnagar — trust us for 100% BIS 916 hallmarked gold, pure silver, and custom bridal ornaments with transparent pricing based on today’s live bullion rates.</p>
        <p>We believe jewellery should feel personal — from everyday wear to wedding sets and custom designs from our catalogue. Order online and confirm on WhatsApp, or walk into our showroom opposite Bhoomi Complex.</p>
        <h2 style="font-family:var(--font-serif, 'Playfair Display', serif);margin:1.5rem 0 0.75rem;font-size:1.35rem;">Why Customers Choose ChandraKala Jewellers</h2>
        <ul style="padding-left:1.2rem;margin:0 0 1.5rem;">
            <li>100% BIS 916 Hallmark with 6-digit HUID code verifiable on Govt BIS Care app</li>
            <li>Transparent daily gold &amp; silver rates updated regularly in Indian Standard Time</li>
            <li>Direct artisan craftsmanship with zero arbitrary retail markups</li>
            <li>Custom bridal designs &amp; references via our extensive catalogue</li>
            <li>Statewide insured doorstep delivery across all 33 districts of Gujarat</li>
            <li>WhatsApp video concierge &amp; ordering at <?= htmlspecialchars($b['phone_display']) ?></li>
            <li>Showroom HQ: <?= htmlspecialchars($b['street']) ?>, Khedbrahma-383255, Gujarat</li>
        </ul>
        <p>
            <a class="btn" href="gallery">Shop jewellery</a>
            <a class="btn btn-secondary" href="top-jewellers-in-gujarat" style="margin-left:8px;">Top Jeweller in Gujarat Guide &rarr;</a>
        </p>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
</body>
</html>
