<?php
$activePage = 'blog';

$posts = [
    [
        'slug' => 'jewellery-care-tips',
        'tag' => 'Care Tips',
        'title' => 'Jewellery Care Tips',
        'excerpt' => 'Learn how to keep your jewellery sparkling with our expert care tips for gold and silver pieces.',
        'fallback' => 'Jewellery Care',
    ],
    [
        'slug' => 'choosing-the-right-gold',
        'tag' => 'Buying Guide',
        'title' => 'Choosing the Right Gold',
        'excerpt' => 'Understand hallmarking, purity, and making charges so you shop with confidence.',
        'fallback' => 'Gold Guide',
    ],
    [
        'slug' => 'jewellery-for-every-occasion',
        'tag' => 'Style',
        'title' => 'Jewellery for Every Occasion',
        'excerpt' => 'From daily wear to wedding celebrations — find pieces that match every moment.',
        'fallback' => 'Occasions',
    ],
    [
        'slug' => 'jewellers-in-khedbrahma-guide',
        'tag' => 'Local Guide',
        'title' => 'Finding Jewellers in Khedbrahma',
        'excerpt' => 'How to choose a trusted jewellery shop in Khedbrahma and Sabarkantha — rates, hallmark, and why locals visit ChandraKala Jewellers.',
        'fallback' => 'Khedbrahma',
    ],
];
require_once 'includes/seo.php';
$seo = cj_merge_seo([
    'title' => 'Jewellery Blog | ChandraKala Jewellers',
    'description' => 'Jewellery care tips, gold buying guides and local advice from ChandraKala Jewellers in Khedbrahma, Sabarkantha, Gujarat.',
    'keywords' => 'jewellery care tips, gold buying guide Khedbrahma, jewellers in Khedbrahma blog, silver care tips Gujarat, ChandraKala Jewellers',
    'canonical' => cj_abs_url('blog'),
    'schema' => [
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Blog', 'url' => cj_abs_url('blog')],
        ]),
        cj_local_business_schema(),
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
        <h1>Jewellery Blog &amp; Care Tips from Khedbrahma</h1>
        <p>Guides and inspiration from ChandraKala Jewellers to help you care for and choose gold &amp; silver jewellery with confidence.</p>
    </div>

    <div class="blog-grid">
        <?php foreach ($posts as $i => $post): ?>
            <article class="blog-card reveal<?= $i > 0 ? ' reveal-delay-' . min($i, 3) : '' ?>">
                <div class="blog-card-media">
                    <span class="media-fallback"><?= htmlspecialchars($post['fallback']) ?></span>
                </div>
                <div class="blog-card-body">
                    <span class="tag"><?= htmlspecialchars($post['tag']) ?></span>
                    <h3><?= htmlspecialchars($post['title']) ?></h3>
                    <p><?= htmlspecialchars($post['excerpt']) ?></p>
                    <a href="blog-post?slug=<?= urlencode($post['slug']) ?>" class="btn btn-secondary">Read More</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

</body>
</html>
