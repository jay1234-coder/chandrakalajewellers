<?php
require_once 'config/db.php';
require_once 'includes/seo.php';
require_once 'includes/img-helper.php';
require_once 'includes/gallery-search.php';
require_once 'includes/customers.php';
require_once 'includes/catalogue-wishlist.php';

$tStart = microtime(true);
$per_page = 24;
$page = max(1, (int) ($_GET['page'] ?? 1));
$is_ajax = isset($_GET['ajax']) && (string) $_GET['ajax'] === '1';
$search_q = trim((string) ($_GET['q'] ?? ''));
$filter_cat = trim((string) ($_GET['cat'] ?? 'all'));
if ($filter_cat === '') {
    $filter_cat = 'all';
}

// Fetch catalogue rows (gracefully supports schemas with or without OCR columns)
$hasOcrCols = false;
$colChk = @$conn->query("SHOW COLUMNS FROM products LIKE 'category'");
if ($colChk && $colChk->num_rows > 0) {
    $hasOcrCols = true;
}

$all_products = [];
$cat_counts = ['all' => 0];
$selectSql = $hasOcrCols
    ? 'SELECT id, name, description, image_filename, category, tags, ocr_text FROM products ORDER BY id DESC'
    : 'SELECT id, name, description, image_filename FROM products ORDER BY id DESC';
$allRes = $conn->query($selectSql);
if ($allRes) {
    while ($row = $allRes->fetch_assoc()) {
        $all_products[] = $row;
        $cat_counts['all']++;
        $cName = trim((string)($row['category'] ?? ''));
        if ($cName !== '') {
            $cat_counts[$cName] = ($cat_counts[$cName] ?? 0) + 1;
        }
    }
}

$aiIntents = [];
if ($search_q !== '' || ($filter_cat !== '' && strtolower($filter_cat) !== 'all')) {
    $aiRes = cj_catalogue_ai_search($all_products, $search_q, $filter_cat);
    $filtered = $aiRes['items'];
    $aiIntents = $aiRes['intents'];
} else {
    $filtered = $all_products;
}

// Deep-link support for individual catalogue designs (?id=45 or ?item=45)
$targetItemId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['item']) ? (int)$_GET['item'] : 0);
$targetProduct = null;
if ($targetItemId > 0) {
    foreach ($all_products as $p) {
        if ((int)$p['id'] === $targetItemId) {
            $targetProduct = $p;
            $filtered = array_values(array_filter($filtered, function($item) use ($targetItemId) {
                return (int)$item['id'] !== $targetItemId;
            }));
            array_unshift($filtered, $targetProduct);
            break;
        }
    }
}

$total_items = count($filtered);
$total_pages = max(1, (int) ceil($total_items / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;
$products = array_slice($filtered, $offset, $per_page);
$elapsedMs = max(1, (int)round((microtime(true) - $tStart) * 1000));

$currentCustomer = cj_customer_session();
$customerLikedIds = $currentCustomer ? cj_get_customer_catalogue_wishlist_ids($conn, (int)$currentCustomer['id']) : [];

function cj_catalogue_card(array $product, int $pi, bool $lazyForce = false, array $likedIds = []): string
{
    $full = htmlspecialchars(cj_img_url($product['image_filename'], 1400, 'catalogue'));
    $thumb = htmlspecialchars(cj_img_url($product['image_filename'], 600, 'catalogue'));
    $absImg = $full;
    $id = (int)$product['id'];
    $isLiked = in_array($id, $likedIds, true);
    $eager = !$lazyForce && $pi < 4;
    $img = $eager
        ? '<img src="' . $thumb . '" alt="Custom Jewellery Design #' . $id . '" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out pointer-events-none" decoding="async" width="500" height="500" fetchpriority="' . ($pi === 0 ? 'high' : 'auto') . '">'
        : '<img class="cj-lazy-img w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out pointer-events-none" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="' . $thumb . '" alt="Custom Jewellery Design #' . $id . '" decoding="async" loading="lazy" width="500" height="500">';

    return '<div class="group catalogue-item relative rounded-2xl overflow-hidden bg-white shadow-md hover:shadow-2xl border border-gray-100 transition-all duration-300 hover:-translate-y-1.5 cursor-pointer flex flex-col aspect-square"
        data-sc-item data-catalogue-open
        data-id="' . $id . '"
        data-name="Design #' . $id . '"
        data-src="' . $full . '"
        data-image="' . htmlspecialchars($absImg) . '"
        role="button" tabindex="0" aria-label="View Custom Design #' . $id . '">
        
        <button type="button" class="catalogue-heart-btn ' . ($isLiked ? 'is-liked' : '') . '"
                data-catalogue-like
                data-id="' . $id . '"
                title="' . ($isLiked ? 'Saved in Wishlist' : 'Save to Wishlist') . '"
                aria-label="Save design to wishlist">
            <svg class="w-5 h-5 heart-svg ' . ($isLiked ? 'fill-current text-rose-500' : 'fill-none text-white') . '" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
        </button>

        <div class="relative w-full h-full overflow-hidden flex items-center justify-center bg-gray-50/60">
            ' . $img . '
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end justify-between p-3 pointer-events-none">
                <span class="text-white text-xs font-semibold flex items-center gap-1.5 drop-shadow">
                    <svg class="w-3.5 h-3.5 text-amber-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/></svg> View
                </span>
                <div class="flex items-center gap-1.5 pointer-events-auto">
                    <button type="button" class="p-1.5 rounded-lg bg-black/50 hover:bg-black/80 text-white hover:text-amber-300 transition-all cursor-pointer shadow-sm" data-quick-share data-id="' . $id . '" title="Share design">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    </button>
                    <button type="button" class="p-1.5 rounded-lg bg-black/50 hover:bg-black/80 text-white hover:text-amber-300 transition-all cursor-pointer shadow-sm" data-quick-compare data-id="' . $id . '" title="Compare design">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>';
}

if ($is_ajax) {
    $html = '';
    foreach ($products as $i => $product) {
        $html .= cj_catalogue_card($product, $i, true, $customerLikedIds);
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => true,
        'html' => $html,
        'page' => $page,
        'total_pages' => $total_pages,
        'total_items' => $total_items,
        'has_more' => $page < $total_pages,
        'q' => $search_q,
        'cat' => $filter_cat,
        'intents' => $aiIntents,
        'elapsed_ms' => $elapsedMs,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$catalogueCanonical = $targetItemId > 0 ? cj_abs_url('Catalogue?id=' . $targetItemId) : cj_abs_url('Catalogue');
$catalogueTitle = $targetProduct 
    ? ('Custom Jewellery Design #' . (int)$targetProduct['id'] . ' | ChandraKala Jewellers Khedbrahma') 
    : 'Custom Gold & Silver Jewellery Design Catalogue | ChandraKala Jewellers';
$catalogueDesc = 'Explore handcrafted 916 gold and 925 silver custom jewellery designs from ChandraKala Jewellers, Khedbrahma, Gujarat. Compare pieces and order directly on WhatsApp.';
$catalogueOgImage = $targetProduct && !empty($targetProduct['image_filename']) 
    ? cj_img_url($targetProduct['image_filename'], 1200, 'catalogue') 
    : cj_abs_url('images/logo.png');

$schemas = [
    cj_local_business_schema(),
    cj_breadcrumb_schema([
        ['name' => 'Home', 'url' => cj_abs_url('index1')],
        ['name' => 'Catalogue', 'url' => cj_abs_url('Catalogue')],
    ]),
];

if ($targetProduct) {
    $designId = (int)$targetProduct['id'];
    $designCat = trim((string)($targetProduct['category'] ?? 'Jewellery'));
    $designImg = cj_img_url($targetProduct['image_filename'], 1200, 'catalogue');
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'ImageObject',
        'name' => 'Custom ' . ($designCat ?: 'Jewellery') . ' Design #' . $designId,
        'contentUrl' => $designImg,
        'thumbnailUrl' => cj_img_url($targetProduct['image_filename'], 500, 'catalogue'),
        'caption' => 'Handcrafted 916 gold & silver custom jewellery design #' . $designId . ' from ChandraKala Jewellers Khedbrahma',
        'representativeOfPage' => true,
        'creator' => [
            '@type' => 'JewelryStore',
            'name' => 'ChandraKala Jewellers',
            'url' => cj_abs_url(''),
            'telephone' => '+91-9427080359'
        ]
    ];
}

$seo = cj_merge_seo([
    'title' => $catalogueTitle,
    'description' => $catalogueDesc,
    'keywords' => 'custom gold jewellery designs Khedbrahma, silver design catalogue Gujarat, jewellery photo catalogue Sabarkantha',
    'canonical' => $catalogueCanonical,
    'image' => $catalogueOgImage,
    'og_image' => $catalogueOgImage,
    'schema' => $schemas,
]);
$waNumber = function_exists('cj_shop_whatsapp_digits') ? cj_shop_whatsapp_digits() : '919427080359';
$cataloguePageUrl = cj_abs_url('Catalogue');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'includes/seo-head.php'; ?>
    <link rel="stylesheet" href="css/tailwind.min.css?v=9">
    <link rel="stylesheet" href="css/style.css?v=12">
    <link rel="stylesheet" href="css/admin-theme.css?v=9">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <?php include 'includes/theme-boot.php'; ?>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-light); }
        .catalogue-heart-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 20;
            width: 36px;
            height: 36px;
            border-radius: 999px;
            background: rgba(17, 24, 39, 0.45);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .catalogue-heart-btn:hover {
            background: rgba(17, 24, 39, 0.8);
            transform: scale(1.12);
        }
        .catalogue-heart-btn.is-liked {
            background: rgba(255, 255, 255, 0.95);
            color: #e11d48;
            border-color: rgba(225, 29, 72, 0.3);
            box-shadow: 0 4px 12px rgba(225, 29, 72, 0.25);
        }
        .catalogue-heart-btn.is-liked .heart-svg {
            fill: #e11d48;
            stroke: #e11d48;
            animation: heartPop 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes heartPop {
            0% { transform: scale(0.7); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }
        .catalogue-auth-modal {
            position: fixed;
            inset: 0;
            z-index: 400;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .catalogue-auth-modal.is-open {
            opacity: 1;
            visibility: visible;
        }
        .catalogue-auth-modal[hidden] {
            display: none !important;
        }
        .catalogue-auth-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(10, 12, 16, 0.65);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }
        .catalogue-auth-card {
            position: relative;
            z-index: 1;
            width: min(440px, 100%);
            background: #ffffff;
            border-radius: 20px;
            padding: 28px 24px 22px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: translateY(20px) scale(0.96);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .catalogue-auth-modal.is-open .catalogue-auth-card {
            transform: translateY(0) scale(1);
        }
        .catalogue-auth-close {
            position: absolute;
            top: 12px;
            right: 14px;
            background: transparent;
            border: none;
            font-size: 24px;
            color: #9ca3af;
            cursor: pointer;
            line-height: 1;
        }
        .catalogue-auth-icon-wrap {
            width: 56px;
            height: 56px;
            border-radius: 999px;
            background: #ffe4e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

    <?php $activePage = 'catalogue'; include 'includes/header.php'; ?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 py-8 sm:py-12 max-w-7xl catalogue-page">
        <div class="page-hero text-center max-w-2xl mx-auto mb-6 sm:mb-8 reveal">
            <span class="inline-block px-3.5 py-1 rounded-full text-xs font-semibold tracking-wider uppercase bg-amber-100 text-amber-900 border border-amber-200/80 mb-3">Exclusive Bespoke Jewellery</span>
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 font-serif mb-2 sm:mb-3">Custom Jewellery Design Catalogue</h1>
            <p class="text-sm sm:text-base text-gray-500 max-w-xl mx-auto leading-relaxed">Handcrafted 916 gold &amp; 925 silver designs. Tap any piece to view, zoom, share direct link, compare, or save to your wishlist.</p>
        </div>

        <?php if (!empty($products) || $search_q !== '' || $filter_cat !== 'all'): ?>
        <div class="sc-toolbar reveal" role="toolbar" aria-label="Catalogue tools">
            <button type="button" class="sc-tool-btn" id="scEnterSelect">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Select
            </button>
            <button type="button" class="sc-tool-btn" id="scEnterCompare">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                Compare
            </button>
            <a href="wishlist?tab=catalogue" class="sc-tool-btn" id="scSavedDesignsBtn" title="View your saved catalogue wishlist">
                <svg width="15" height="15" class="heart-svg <?= empty($customerLikedIds) ? 'fill-none stroke-current' : 'fill-current text-rose-500' ?>" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                <span>Saved Designs</span>
                <span id="scSavedCountBadge" class="sc-tool-badge" <?= empty($customerLikedIds) ? 'style="display:none;"' : '' ?>><?= count($customerLikedIds) ?></span>
            </a>
            <button type="button" class="sc-tool-btn sc-tool-muted" id="scClearAll">Clear</button>
            <p class="sc-toolbar-hint">Compare starts at 2 designs — keep adding as many as you like.</p>
        </div>
        <?php endif; ?>

        <div id="catalogueEmptyState" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-10 sm:p-16 text-center text-gray-500 max-w-md mx-auto reveal" <?= empty($products) ? '' : 'hidden' ?>>
            <p class="font-semibold text-gray-800">No matching jewellery designs found.</p>
            <button type="button" id="catalogueResetSearch" class="mt-4 px-5 py-2 rounded-full bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700 cursor-pointer">Show All Designs</button>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6 md:gap-8" id="catalogueGrid"
             data-page="<?= (int) $page ?>"
             data-total-pages="<?= (int) $total_pages ?>"
             data-q="<?= htmlspecialchars($search_q) ?>"
             data-cat="<?= htmlspecialchars($filter_cat) ?>">
            <?php foreach ($products as $pi => $product) {
                echo cj_catalogue_card($product, $pi, false, $customerLikedIds);
            } ?>
        </div>
        <p id="catalogueLazyStatus" class="text-center text-xs text-gray-400 mt-4" hidden>Loading designs…</p>
        <div id="catalogueLazySentinel" class="shop-lazy-sentinel" aria-hidden="true"></div>
        <div class="text-center mt-8 mb-4" id="catalogueLoadMoreWrap" <?= $total_pages > 1 ? '' : 'hidden' ?>>
            <button type="button" id="catalogueLoadMoreBtn" class="inline-flex items-center gap-2 px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-medium text-sm rounded-full shadow-sm transition-all hover:scale-105 cursor-pointer">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                <span id="catalogueLoadMoreLabel">Load More Designs (<?= (int)$total_items ?> Total)</span>
            </button>
        </div>
    </main>

    <div class="catalogue-lightbox" id="catalogueLightbox" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="catalogueLbTitle">
        <div class="catalogue-lightbox-backdrop" data-catalogue-close></div>
        <div class="catalogue-lightbox-dialog" role="document">
            <button type="button" class="catalogue-lightbox-close" data-catalogue-close aria-label="Close image viewer">&times;</button>
            <div class="catalogue-lightbox-media">
                <img id="catalogueLbImg" src="" alt="" decoding="async">
            </div>
            <div class="catalogue-lightbox-meta">
                <h3 id="catalogueLbTitle" class="text-xl sm:text-2xl font-bold font-serif text-gray-900 m-0 mb-1">Design</h3>
                <p id="catalogueLbDesc" class="text-xs text-gray-500 m-0 mb-3">Handcrafted 916 Gold &amp; 925 Silver Design · ChandraKala Jewellers</p>
                
                <div class="catalogue-actions-grid">
                    <button type="button" id="catalogueLbLikeBtn" class="btn-cat-action btn-cat-wishlist" title="Save to wishlist">
                        <svg class="w-4 h-4 heart-svg fill-none stroke-current" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        <span id="catalogueLbLikeLabel">Wishlist</span>
                    </button>
                    <button type="button" id="catalogueLbShareBtn" class="btn-cat-action btn-cat-share" title="Share direct design link">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        <span>Share Link</span>
                    </button>
                    <a id="catalogueLbOrder" class="btn-cat-action btn-cat-whatsapp" href="#" target="_blank" rel="noopener" title="Inquire on WhatsApp">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.044-1.076-.07-.249-.074-.572-.187-1.042-.39-1.996-.864-3.298-2.884-3.398-3.018-.099-.134-.813-1.082-.813-2.064 0-.982.515-1.464.7-1.663.185-.2.404-.25.539-.25.134 0 .269.002.387.008.125.006.291-.047.456.349.168.404.572 1.396.622 1.498.05.101.083.22.016.353-.067.133-.101.217-.201.334-.1.118-.21.263-.3.353-.1.1-.205.209-.089.408.117.199.519.855 1.113 1.385.767.683 1.413.896 1.614.996.2.1.317.084.434-.05.117-.134.502-.584.636-.784.134-.2.269-.167.453-.1.184.067 1.17.552 1.372.653.202.101.336.151.386.235.05.084.05.488-.094.893z"/></svg>
                        <span>WhatsApp</span>
                    </a>
                    <button type="button" id="catalogueLbAddSelect" class="btn-cat-action btn-cat-compare" title="Add design to comparison tray">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <span>Compare</span>
                    </button>
                </div>
                <p class="text-[11px] text-gray-400 mt-2.5 mb-0">Direct design link <code class="bg-gray-100 text-amber-800 px-1.5 py-0.5 rounded font-mono">Catalogue?id=...</code> allows easy sharing &amp; social previews.</p>
            </div>
        </div>
    </div>

    <!-- Auth Prompt Modal (Shown when guest clicks Like) -->
    <div class="catalogue-auth-modal" id="catalogueAuthModal" hidden aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="catAuthTitle">
        <div class="catalogue-auth-backdrop" data-auth-close></div>
        <div class="catalogue-auth-card">
            <button type="button" class="catalogue-auth-close" data-auth-close aria-label="Close modal">&times;</button>
            <div class="catalogue-auth-icon-wrap">
                <svg class="w-8 h-8 text-rose-500 fill-current" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            </div>
            <h3 id="catAuthTitle" class="text-xl font-bold font-serif text-gray-900 mb-2">Save to Wishlist</h3>
            <p class="text-xs sm:text-sm text-gray-500 mb-6 leading-relaxed">Create an account or sign in to save your favourite custom jewellery designs and view them anytime in your personal wishlist across all your devices.</p>
            <div class="flex flex-col sm:flex-row gap-2.5 w-full mb-3">
                <a href="account?tab=register&redirect=Catalogue" class="flex-1 py-3 px-4 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs sm:text-sm text-center shadow-sm transition-all hover:scale-[1.02]">
                    Create Account
                </a>
                <a href="account?tab=login&redirect=Catalogue" class="flex-1 py-3 px-4 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-gray-800 font-semibold text-xs sm:text-sm text-center transition-all hover:scale-[1.02]">
                    Sign In
                </a>
            </div>
            <button type="button" class="text-xs text-gray-400 hover:text-gray-600 py-1 transition-colors cursor-pointer" data-auth-close>
                Continue browsing
            </button>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="catalogueToast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[300] bg-gray-900/90 text-white text-xs sm:text-sm font-medium px-4 py-2.5 rounded-full shadow-lg backdrop-blur pointer-events-none transition-all duration-300 opacity-0 translate-y-3">
        Link copied to clipboard!
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="js/select-compare-share.js?v=7"></script>
    <script src="js/catalogue-lazy.js?v=8" defer></script>
    <script>
    (function () {
        var waNumber = <?= json_encode($waNumber) ?>;
        var catalogueUrl = window.location.origin + window.location.pathname;
        window.CJ_CUSTOMER_LOGGED_IN = <?= json_encode(!empty($currentCustomer)) ?>;
        window.CJ_LIKED_CATALOGUE_IDS = <?= json_encode(array_values(array_map('intval', $customerLikedIds))) ?>;
        var likedSet = new Set(window.CJ_LIKED_CATALOGUE_IDS);

        var sc = window.CJSelectCompare.create({
            phone: waNumber,
            pageUrl: catalogueUrl,
            pageLabel: 'Catalogue page',
            itemSelector: '#catalogueGrid [data-sc-item]',
            minCompare: 2,
            storageKey: 'cj_catalogue_selection'
        });

        var lb = document.getElementById('catalogueLightbox');
        if (!lb) return;
        var img = document.getElementById('catalogueLbImg');
        var title = document.getElementById('catalogueLbTitle');
        var desc = document.getElementById('catalogueLbDesc');
        var orderBtn = document.getElementById('catalogueLbOrder');
        var shareBtn = document.getElementById('catalogueLbShareBtn');
        var waShareBtn = document.getElementById('catalogueLbWhatsAppShare');
        var addBtn = document.getElementById('catalogueLbAddSelect');
        var likeBtn = document.getElementById('catalogueLbLikeBtn');
        var likeLabel = document.getElementById('catalogueLbLikeLabel');
        var authModal = document.getElementById('catalogueAuthModal');
        var lastFocus = null;
        var closeTimer = null;
        var currentItem = null;

        function showToast(msg) {
            var toast = document.getElementById('catalogueToast');
            if (!toast) return;
            toast.innerHTML = msg || 'Link copied to clipboard!';
            toast.classList.remove('opacity-0', 'translate-y-3');
            toast.classList.add('opacity-100', 'translate-y-0');
            clearTimeout(toast._timer);
            toast._timer = setTimeout(function () {
                toast.classList.remove('opacity-100', 'translate-y-0');
                toast.classList.add('opacity-0', 'translate-y-3');
            }, 3000);
        }

        function openAuthModal() {
            if (!authModal) return;
            authModal.hidden = false;
            authModal.setAttribute('aria-hidden', 'false');
            requestAnimationFrame(function () { authModal.classList.add('is-open'); });
        }

        function closeAuthModal() {
            if (!authModal) return;
            authModal.classList.remove('is-open');
            setTimeout(function () {
                authModal.hidden = true;
                authModal.setAttribute('aria-hidden', 'true');
            }, 250);
        }

        document.querySelectorAll('[data-auth-close]').forEach(function (btn) {
            btn.addEventListener('click', closeAuthModal);
        });

        function updateLikeUI(id, isLiked) {
            var numericId = parseInt(id, 10);
            if (isLiked) {
                likedSet.add(numericId);
            } else {
                likedSet.delete(numericId);
            }

            // Update matching cards in grid
            document.querySelectorAll('[data-catalogue-like][data-id="' + numericId + '"]').forEach(function (btn) {
                var svg = btn.querySelector('.heart-svg');
                if (isLiked) {
                    btn.classList.add('is-liked');
                    btn.title = 'Saved in Wishlist';
                    if (svg) {
                        svg.classList.remove('fill-none', 'text-white');
                        svg.classList.add('fill-current', 'text-rose-500');
                    }
                } else {
                    btn.classList.remove('is-liked');
                    btn.title = 'Save to Wishlist';
                    if (svg) {
                        svg.classList.remove('fill-current', 'text-rose-500');
                        svg.classList.add('fill-none', 'text-white');
                    }
                }
            });

            // Update Toolbar Saved Designs count badge
            var scBadge = document.getElementById('scSavedCountBadge');
            var scBtn = document.getElementById('scSavedDesignsBtn');
            if (scBadge) {
                scBadge.textContent = String(likedSet.size);
                scBadge.style.display = likedSet.size > 0 ? 'inline-flex' : 'none';
            }
            if (scBtn) {
                var sSvg = scBtn.querySelector('.heart-svg');
                if (sSvg) {
                    if (likedSet.size > 0) {
                        sSvg.classList.remove('fill-none');
                        sSvg.classList.add('fill-current', 'text-rose-500');
                    } else {
                        sSvg.classList.remove('fill-current', 'text-rose-500');
                        sSvg.classList.add('fill-none');
                    }
                }
            }

            // Update Lightbox like button if currently viewing this item
            if (currentItem && parseInt(currentItem.id, 10) === numericId && likeBtn) {
                var lbSvg = likeBtn.querySelector('.heart-svg');
                if (isLiked) {
                    likeBtn.classList.add('is-liked');
                    if (likeLabel) likeLabel.textContent = 'Saved';
                    if (lbSvg) {
                        lbSvg.classList.remove('fill-none');
                        lbSvg.classList.add('fill-current');
                    }
                } else {
                    likeBtn.classList.remove('is-liked');
                    if (likeLabel) likeLabel.textContent = 'Wishlist';
                    if (lbSvg) {
                        lbSvg.classList.remove('fill-current');
                        lbSvg.classList.add('fill-none');
                    }
                }
            }
        }

        function handleLikeClick(id) {
            if (!id) return;
            var numericId = parseInt(id, 10);
            if (!window.CJ_CUSTOMER_LOGGED_IN) {
                openAuthModal();
                return;
            }

            fetch('api/catalogue-wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ action: 'toggle', catalogue_id: numericId })
            }).then(function (res) {
                if (res.status === 401) {
                    window.CJ_CUSTOMER_LOGGED_IN = false;
                    openAuthModal();
                    throw new Error('Auth required');
                }
                return res.json();
            }).then(function (data) {
                if (!data || !data.ok) return;
                updateLikeUI(numericId, !!data.liked);
                if (data.liked) {
                    showToast('Saved to your Wishlist! <a href="wishlist?tab=catalogue" class="underline font-bold text-amber-300 ml-1">View in Wishlist</a>');
                } else {
                    showToast('Design removed from your Wishlist.');
                }
            }).catch(function () {});
        }

        function openViewer(el) {
            lastFocus = el;
            var id = el.getAttribute('data-id') || '';
            currentItem = {
                id: id,
                name: 'Design #' + id,
                image: el.getAttribute('data-image') || el.getAttribute('data-src') || '',
                url: catalogueUrl + '?id=' + encodeURIComponent(id)
            };
            var src = el.getAttribute('data-src') || '';
            img.src = src;
            img.alt = 'Custom Jewellery Design #' + id;

            if (title) title.textContent = 'Design #' + id;
            var isLiked = likedSet.has(parseInt(id, 10));
            if (likeBtn) {
                var lbSvg = likeBtn.querySelector('.heart-svg');
                if (isLiked) {
                    likeBtn.classList.add('is-liked');
                    if (likeLabel) likeLabel.textContent = 'Saved';
                    if (lbSvg) { lbSvg.classList.remove('fill-none'); lbSvg.classList.add('fill-current'); }
                } else {
                    likeBtn.classList.remove('is-liked');
                    if (likeLabel) likeLabel.textContent = 'Wishlist';
                    if (lbSvg) { lbSvg.classList.remove('fill-current'); lbSvg.classList.add('fill-none'); }
                }
            }

            var directUrl = window.location.origin + '/Catalogue?id=' + encodeURIComponent(id);

            if (orderBtn) {
                var orderMsg = 'Hello ChandraKala Jewellers, I would like to inquire about Custom Design #' + id + ':\n' + directUrl;
                orderBtn.href = 'https://wa.me/' + waNumber + '?text=' + encodeURIComponent(orderMsg);
            }
            if (waShareBtn) {
                var shareMsg = 'Check out this handcrafted jewellery design #' + id + ' at ChandraKala Jewellers:\n' + directUrl;
                waShareBtn.href = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(shareMsg);
            }

            try {
                if (window.history && window.history.replaceState && id) {
                    window.history.replaceState(null, '', 'Catalogue?id=' + encodeURIComponent(id));
                }
            } catch (histErr) {}

            lb.hidden = false;
            lb.setAttribute('aria-hidden', 'false');
            document.body.classList.add('catalogue-lightbox-open');
            requestAnimationFrame(function () { lb.classList.add('is-open'); });
            var closeBtn = lb.querySelector('.catalogue-lightbox-close');
            if (closeBtn && typeof closeBtn.focus === 'function') {
                try { closeBtn.focus({ preventScroll: true }); } catch (fErr) {}
            }
        }

        function closeViewer(e) {
            if (e) {
                if (typeof e.preventDefault === 'function') e.preventDefault();
                if (typeof e.stopPropagation === 'function') e.stopPropagation();
            }
            try {
                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', window.location.pathname);
                }
            } catch (histErr) {}

            lb.classList.remove('is-open');
            document.body.classList.remove('catalogue-lightbox-open');
            clearTimeout(closeTimer);
            closeTimer = setTimeout(function () {
                lb.hidden = true;
                lb.setAttribute('aria-hidden', 'true');
                img.removeAttribute('src');
                if (lastFocus && typeof lastFocus.focus === 'function') {
                    try { lastFocus.focus({ preventScroll: true }); } catch (fErr) {}
                }
            }, 250);
        }

        sc.bindItemClicks(function (el) {
            return function () { openViewer(el); };
        });
        window.CJCatalogueBindNew = function () {
            if (typeof sc.refreshItems === 'function') sc.refreshItems();
            sc.bindItemClicks(function (el) {
                return function () { openViewer(el); };
            });
        };

        var gridEl = document.getElementById('catalogueGrid');
        if (gridEl) {
            gridEl.addEventListener('click', function (e) {
                var likeTarget = e.target.closest('[data-catalogue-like]');
                if (likeTarget) {
                    e.preventDefault();
                    e.stopPropagation();
                    var lId = likeTarget.getAttribute('data-id');
                    handleLikeClick(lId);
                    return;
                }
                var shareTarget = e.target.closest('[data-quick-share]');
                if (shareTarget) {
                    e.preventDefault();
                    e.stopPropagation();
                    var sId = shareTarget.getAttribute('data-id');
                    var shareUrl = window.location.origin + '/Catalogue?id=' + encodeURIComponent(sId);
                    if (navigator.share) {
                        navigator.share({ title: 'Design #' + sId, url: shareUrl }).catch(function () {});
                    } else if (navigator.clipboard) {
                        navigator.clipboard.writeText(shareUrl).then(function () {
                            showToast('Design link copied to clipboard!');
                        });
                    }
                    return;
                }
                var compTarget = e.target.closest('[data-quick-compare]');
                if (compTarget) {
                    e.preventDefault();
                    e.stopPropagation();
                    var cardEl = compTarget.closest('[data-sc-item]');
                    if (cardEl) {
                        sc.ensureSelected(cardEl);
                        showToast('Added Design #' + compTarget.getAttribute('data-id') + ' to compare');
                    }
                    return;
                }
            });

            gridEl.addEventListener('keydown', function (e) {
                var el = e.target.closest('[data-catalogue-open]');
                if (!el || (e.key !== 'Enter' && e.key !== ' ')) return;
                e.preventDefault();
                el.click();
            });
        }

        lb.querySelectorAll('[data-catalogue-close]').forEach(function (el) {
            el.addEventListener('click', closeViewer);
            el.addEventListener('touchend', function (e) {
                closeViewer(e);
            }, { passive: false });
        });

        // Extra defensive delegation on lightbox container itself
        lb.addEventListener('click', function (e) {
            if (e.target && e.target.matches('[data-catalogue-close], .catalogue-lightbox-close, .catalogue-lightbox-backdrop')) {
                closeViewer(e);
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (authModal && authModal.classList.contains('is-open')) {
                    closeAuthModal();
                    return;
                }
                if (lb.classList.contains('is-open')) closeViewer();
            }
        });

        if (likeBtn) {
            likeBtn.addEventListener('click', function () {
                if (!currentItem || !currentItem.id) return;
                handleLikeClick(currentItem.id);
            });
        }

        if (shareBtn) {
            shareBtn.addEventListener('click', function () {
                if (!currentItem || !currentItem.id) return;
                var directUrl = window.location.origin + '/Catalogue?id=' + encodeURIComponent(currentItem.id);
                var shareData = {
                    title: 'Design #' + currentItem.id + ' | ChandraKala Jewellers',
                    text: 'Check out this handcrafted jewellery design from ChandraKala Jewellers:',
                    url: directUrl
                };
                if (navigator.share && navigator.canShare && navigator.canShare(shareData)) {
                    navigator.share(shareData).catch(function () {});
                } else if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(directUrl).then(function () {
                        showToast('Design link copied to clipboard!');
                    }).catch(function () {
                        prompt('Copy design link:', directUrl);
                    });
                } else {
                    prompt('Copy design link:', directUrl);
                }
            });
        }
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                if (!lastFocus) return;
                sc.ensureSelected(lastFocus);
                showToast('Added Design #' + (currentItem ? currentItem.id : '') + ' to compare');
                closeViewer();
            });
        }

        // Auto-open design from deep-link (?id=... or ?item=...)
        var targetId = <?= json_encode($targetItemId > 0 ? (string)$targetItemId : '') ?>;
        if (targetId) {
            var targetEl = document.querySelector('[data-sc-item][data-id="' + targetId + '"]');
            if (targetEl) {
                setTimeout(function () { openViewer(targetEl); }, 280);
            }
        }
    })();
    </script>
</body>
</html>
