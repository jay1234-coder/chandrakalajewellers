<?php
$activePage = 'blog';

$posts = [
    'jewellery-care-tips' => [
        'tag' => 'Care Tips',
        'title' => 'Jewellery Care Tips',
        'lead' => 'Jewellery is not just an accessory; it is a reflection of your personality and style. To keep your jewellery looking its best, follow these essential care tips from ChandraKala Jewellers.',
        'body' => [
            ['Store Properly', 'Always store your jewellery in a dry place, preferably in a soft cloth or a jewellery box to prevent scratches.'],
            ['Avoid Chemicals', 'Keep your jewellery away from harsh chemicals, perfumes, and lotions that can tarnish or damage the metal and stones.'],
            ['Regular Cleaning', 'Clean your jewellery regularly with a soft cloth. For deeper cleaning, use a mild soap solution and a soft brush.'],
            ['Avoid Water', 'Remove your jewellery before swimming or showering to avoid exposure to chlorine or saltwater.'],
            ['Professional Maintenance', 'Consider taking your jewellery to a professional jeweller for maintenance and repairs at least once a year.'],
        ],
    ],
    'choosing-the-right-gold' => [
        'tag' => 'Buying Guide',
        'title' => 'Choosing the Right Gold',
        'lead' => 'Buying gold should feel clear and confident. Here is a simple guide to purity, hallmarking, and what affects the final price.',
        'body' => [
            ['Know the Purity', '916 gold (22K) is a popular choice for traditional jewellery — it balances purity with durability for everyday and occasion wear.'],
            ['Look for Hallmarking', 'Hallmarked jewellery confirms purity standards. Always ask about hallmark details when you purchase.'],
            ['Understand Making Charges', 'The final price includes metal value plus making charges. We are transparent about how rates are calculated in our store.'],
            ['Choose for Lifestyle', 'Daily wear pieces benefit from sturdy designs; statement pieces can be more ornate for celebrations.'],
            ['Ask Us', 'Our team in Khedbrahma is happy to help you compare options and find the right weight and design for your budget.'],
        ],
    ],
    'jewellery-for-every-occasion' => [
        'tag' => 'Style',
        'title' => 'Jewellery for Every Occasion',
        'lead' => 'The right piece elevates every moment — from a quiet day at work to a wedding celebration. Here is how to match jewellery to the occasion.',
        'body' => [
            ['Daily Elegance', 'Lightweight gold or silver designs keep you polished without weighing you down through a busy day.'],
            ['Festive Wear', 'Layered necklaces, statement earrings, and coordinated sets bring traditional sparkle to festivals.'],
            ['Weddings & Rituals', 'Heirloom-inspired sets and bridal favourites make lasting memories — visit our catalogue for design inspiration.'],
            ['Gifting', 'A thoughtfully chosen pendant or bangle is a timeless gift. Tell us the occasion and we will suggest options.'],
            ['Personal Touch', 'Custom design references from our catalogue can be crafted to suit your taste — ask us in store or on WhatsApp.'],
        ],
    ],
    'jewellers-in-khedbrahma-guide' => [
        'tag' => 'Local Guide',
        'title' => 'Finding Jewellers in Khedbrahma',
        'lead' => 'Searching for jewellers, jewellery shops, or “jewellers near me” in Khedbrahma? Here is how locals choose a trusted gold and silver store in Sabarkantha — and how ChandraKala Jewellers on Civil Road helps.',
        'body' => [
            ['Check the location', 'A clear Google Maps listing and Civil Road address (Opp. Bhoomi Complex) make visits easy for families across Khedbrahma.'],
            ['Ask about rates', 'Transparent 916 gold and silver rates — published and explained — build trust before you buy.'],
            ['See real stock', 'Browse our online gallery for gold, silver, and 925 pieces, then confirm on WhatsApp or in store.'],
            ['Custom designs', 'Use our design catalogue when you want something crafted for weddings or personal gifts.'],
            ['Same brand, many spellings', 'ChandraKala and Chandrakala Jewellers are the same family store — search either name on Maps to find us.'],
        ],
    ],
];

$slug = $_GET['slug'] ?? 'jewellery-care-tips';
if (!isset($posts[$slug])) {
    $slug = 'jewellery-care-tips';
}
$post = $posts[$slug];
require_once 'includes/seo.php';
$postUrl = cj_abs_url('blog-post?slug=' . urlencode($slug));
$leadLen = function_exists('mb_strlen') ? mb_strlen($post['lead']) : strlen($post['lead']);
$leadShort = function_exists('mb_substr') ? mb_substr($post['lead'], 0, 155) : substr($post['lead'], 0, 155);
$seo = cj_merge_seo([
    'title' => $post['title'] . ' | ChandraKala Jewellers Blog',
    'description' => $leadShort . ($leadLen > 155 ? '…' : ''),
    'keywords' => $post['title'] . ', jewellery tips Khedbrahma, gold silver care Gujarat, ChandraKala Jewellers blog',
    'canonical' => $postUrl,
    'og_type' => 'article',
    'schema' => [
        cj_article_schema([
            'title' => $post['title'],
            'description' => $post['lead'],
            'url' => $postUrl,
            'date' => '2024-06-01',
            'modified' => date('Y-m-d'),
        ]),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Blog', 'url' => cj_abs_url('blog')],
            ['name' => $post['title'], 'url' => $postUrl],
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
    <article class="blog-article reveal">
        <a href="blog" class="back-link">&larr; Back to Blog</a>
        <div class="article-meta">
            <span class="tag" style="color: var(--primary-gold-dark); font-weight: 600; letter-spacing: 1px; text-transform: uppercase; font-size: 11px;"><?= htmlspecialchars($post['tag']) ?></span>
            <span>ChandraKala Jewellers</span>
        </div>
        <h1><?= htmlspecialchars($post['title']) ?></h1>
        <p class="lead"><?= htmlspecialchars($post['lead']) ?></p>
        <div class="article-hero-img" role="img" aria-label="<?= htmlspecialchars($post['title']) ?>"></div>
        <ul>
            <?php foreach ($post['body'] as $item): ?>
                <li><strong><?= htmlspecialchars($item[0]) ?>:</strong> <?= htmlspecialchars($item[1]) ?></li>
            <?php endforeach; ?>
        </ul>
        <div style="margin-top: 36px; display: flex; flex-wrap: wrap; gap: 12px;">
            <a href="gallery" class="btn">Explore Shop</a>
            <a href="contact" class="btn btn-secondary">Contact Us</a>
        </div>
    </article>
</main>

<?php include 'includes/footer.php'; ?>

</body>
</html>
