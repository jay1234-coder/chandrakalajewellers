<?php
$activePage = 'about';
require_once 'includes/seo.php';
$b = cj_business_info();
$seo = cj_merge_seo([
    'title' => 'About ChandraKala Jewellers | Khedbrahma Store',
    'description' => 'About ChandraKala Jewellers — family gold & silver shop on Civil Road, Khedbrahma. 916 gold, silver, custom designs and honest live rates.',
    'keywords' => 'about ChandraKala Jewellers, Chandrakala Jewellers Khedbrahma, family jewellers Sabarkantha, gold shop Civil Road history',
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
        <p>Gold and silver, crafted with care in Khedbrahma — your neighbourhood jewellers on Civil Road.</p>
    </div>

    <section class="reveal" style="max-width:720px;margin:0 auto 2rem;line-height:1.7;">
        <p><strong>ChandraKala Jewellers</strong> (also known as <strong>Chandrakala Jewellers</strong>) is a family-owned jewellery store in <strong>Khedbrahma, Sabarkantha, Gujarat</strong>. Customers across Civil Road, nearby villages, Idar and Himmatnagar visit us for 916 gold, silver and 925 silver jewellery with clear pricing based on today’s live rates.</p>
        <p>We believe jewellery should feel personal — from everyday wear to wedding sets and custom designs from our catalogue. Order online and confirm on WhatsApp, or walk into our showroom opposite Bhoomi Complex.</p>
        <h2 style="font-family:var(--font-serif, 'Playfair Display', serif);margin:1.5rem 0 0.75rem;font-size:1.35rem;">Why locals choose us</h2>
        <ul style="padding-left:1.2rem;margin:0 0 1.5rem;">
            <li>Transparent gold &amp; silver rates updated regularly</li>
            <li>916 gold, silver and 925 sterling designs in stock</li>
            <li>Custom design references via our catalogue</li>
            <li>WhatsApp ordering at <?= htmlspecialchars($b['phone_display']) ?></li>
            <li>Easy Google Maps location: <?= htmlspecialchars($b['street']) ?>, Khedbrahma-383255</li>
        </ul>
        <p>
            <a class="btn" href="gallery">Shop jewellery</a>
            <a class="btn btn-secondary" href="contact" style="margin-left:8px;">Visit &amp; contact</a>
        </p>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
</body>
</html>
