<?php
$activePage = 'shop';
require_once 'includes/seo.php';
$b = cj_business_info();
$seo = cj_merge_seo([
    'title' => 'Gold Rate Khedbrahma | ChandraKala Jewellers',
    'description' => 'Today\'s gold rate context and 916 gold jewellery in Khedbrahma at ChandraKala Jewellers. Transparent pricing on Civil Road. Call +91 9427080359.',
    'keywords' => 'gold rate Khedbrahma, gold rate today Sabarkantha, 916 gold rate, 22K gold jewellery Khedbrahma, buy gold Khedbrahma, ChandraKala gold rate',
    'canonical' => cj_abs_url('gold-rate-khedbrahma'),
    'schema' => [
        cj_local_business_schema(),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Gold Rate Khedbrahma', 'url' => cj_abs_url('gold-rate-khedbrahma')],
        ]),
        cj_faq_schema([
            [
                'q' => 'Where can I check gold rate in Khedbrahma?',
                'a' => 'ChandraKala Jewellers publishes live 916 gold rates on the homepage and sells hallmark-quality gold jewellery at Opp. Bhoomi Complex, Civil Road, Khedbrahma.',
            ],
            [
                'q' => 'Do you sell 22K / 916 gold in Khedbrahma?',
                'a' => 'Yes. We specialise in 916 (22K) gold jewellery with transparent making charges and WhatsApp ordering.',
            ],
        ]),
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
        <h1>Gold Rate &amp; 916 Gold Jewellery in Khedbrahma</h1>
        <p>Shop 22K gold with clear pricing at ChandraKala Jewellers — Civil Road, Sabarkantha.</p>
    </div>

    <article class="reveal" style="max-width:760px;margin:0 auto;line-height:1.75;">
        <p>Looking for <strong>today’s gold rate in Khedbrahma</strong> or a reliable place to buy <strong>916 gold jewellery</strong>? ChandraKala Jewellers updates store rates regularly and displays them on our <a href="index1">homepage</a>.</p>
        <p>Browse <a href="gallery?metal_type=gold">gold jewellery in stock</a>, request a custom design from the <a href="Catalogue">catalogue</a>, or visit us at <?= htmlspecialchars($b['street']) ?>, Khedbrahma-383255.</p>
        <p>
            <a class="btn" href="gallery?metal_type=gold">Shop gold jewellery</a>
            <a class="btn btn-secondary" href="index1" style="margin-left:8px;">See live rate on home</a>
        </p>
        <p style="margin-top:1.5rem;font-size:0.95rem;color:#666;">Call <a href="tel:<?= htmlspecialchars($b['phone']) ?>"><?= htmlspecialchars($b['phone_display']) ?></a> for today’s rate confirmation before purchase.</p>
    </article>
</main>

<?php include 'includes/footer.php'; ?>
</body>
</html>
