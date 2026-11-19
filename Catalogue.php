<?php
require_once 'config/db.php';
require_once 'includes/seo.php';
require_once 'includes/img-helper.php';
require_once 'includes/gallery-search.php';

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

function cj_catalogue_card(array $product, int $pi, bool $lazyForce = false): string
{
    $full = htmlspecialchars(cj_img_url($product['image_filename'], 1400, 'catalogue'));
    $thumb = htmlspecialchars(cj_img_url($product['image_filename'], 600, 'catalogue'));
    $absImg = $full;
    $id = (int)$product['id'];
    $cat = trim((string)($product['category'] ?? ''));
    $eager = !$lazyForce && $pi < 4;
    $img = $eager
        ? '<img src="' . $thumb . '" alt="Custom Jewellery Design #' . $id . '" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out pointer-events-none" decoding="async" width="500" height="500" fetchpriority="' . ($pi === 0 ? 'high' : 'auto') . '">'
        : '<img class="cj-lazy-img w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out pointer-events-none" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="' . $thumb . '" alt="Custom Jewellery Design #' . $id . '" decoding="async" loading="lazy" width="500" height="500">';

    $catPill = $cat !== ''
        ? '<span class="absolute top-2.5 left-2.5 z-10 px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide uppercase bg-black/60 text-amber-200 backdrop-blur-md shadow-sm">' . htmlspecialchars($cat) . '</span>'
        : '';

    return '<div class="group catalogue-item relative rounded-2xl overflow-hidden bg-white shadow-md hover:shadow-2xl border border-gray-100 transition-all duration-300 hover:-translate-y-1.5 cursor-pointer flex flex-col aspect-square"
        data-sc-item data-catalogue-open
        data-id="' . $id . '"
        data-name="Design #' . $id . ($cat !== '' ? ' (' . htmlspecialchars($cat) . ')' : '') . '"
        data-src="' . $full . '"
        data-image="' . htmlspecialchars($absImg) . '"
        data-category="' . htmlspecialchars($cat) . '"
        role="button" tabindex="0" aria-label="View Custom Design #' . $id . '">
        ' . $catPill . '
        <div class="relative w-full h-full overflow-hidden flex items-center justify-center bg-gray-50/60">
            ' . $img . '
            <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end justify-between p-3.5 pointer-events-none">
                <span class="text-white text-xs font-semibold flex items-center gap-1.5 drop-shadow">
                    <svg class="w-3.5 h-3.5 text-amber-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/></svg> Tap to View &amp; Share
                </span>
                <span class="text-[11px] font-mono font-bold text-amber-200 drop-shadow">#' . $id . '</span>
            </div>
        </div>
    </div>';
}

if ($is_ajax) {
    $html = '';
    foreach ($products as $i => $product) {
        $html .= cj_catalogue_card($product, $i, true);
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
    <link rel="stylesheet" href="css/style.css?v=9">
    <link rel="stylesheet" href="css/admin-theme.css?v=9">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <?php include 'includes/theme-boot.php'; ?>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-light); }
        .ai-search-shell {
            max-width: 760px;
            margin: 0 auto 1.5rem;
        }
        .ai-search-box {
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px 6px 14px;
            border-radius: 999px;
            background: var(--surface, #ffffff);
            border: 1.5px solid rgba(212, 175, 55, 0.45);
            box-shadow: 0 10px 30px rgba(184, 151, 44, 0.12), 0 0 0 4px rgba(212, 175, 55, 0.06);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .ai-search-box:focus-within {
            border-color: #d4af37;
            box-shadow: 0 12px 34px rgba(184, 151, 44, 0.2), 0 0 0 4px rgba(212, 175, 55, 0.16);
        }
        .ai-search-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: 999px;
            background: linear-gradient(135deg, #f59e0b, #b45309);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            white-space: nowrap;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(180, 83, 9, 0.28);
        }
        .ai-search-input {
            flex: 1;
            min-width: 0;
            border: 0;
            outline: 0;
            background: transparent;
            color: var(--text-primary, #111827);
            font-size: 14px;
            padding: 9px 4px;
        }
        .ai-search-input::placeholder {
            color: var(--text-light, #9ca3af);
        }
        .ai-search-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 999px;
            border: 0;
            background: transparent;
            color: var(--text-secondary, #6b7280);
            cursor: pointer;
            flex-shrink: 0;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .ai-search-icon-btn:hover {
            background: rgba(212, 175, 55, 0.12);
            color: #b45309;
        }
        .ai-search-icon-btn.is-listening {
            background: #fee2e2;
            color: #dc2626;
            animation: aiPulse 1.1s infinite;
        }
        @keyframes aiPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.35); }
            50% { box-shadow: 0 0 0 8px rgba(220, 38, 38, 0); }
        }
        .ai-search-submit {
            padding: 9px 18px;
            border-radius: 999px;
            border: 0;
            background: linear-gradient(135deg, #d4af37, #b8972c);
            color: #111827;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            flex-shrink: 0;
            transition: transform 0.15s ease, filter 0.15s ease;
        }
        .ai-search-submit:hover { filter: brightness(1.06); transform: translateY(-1px); }
        .ai-chips-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 7px;
            margin-top: 12px;
        }
        .ai-chip {
            padding: 6px 13px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid var(--border-color, #e5e7eb);
            background: var(--surface, #ffffff);
            color: var(--text-secondary, #4b5563);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .ai-chip:hover {
            border-color: #d4af37;
            color: #92400e;
        }
        .ai-chip.is-active {
            background: linear-gradient(135deg, #d4af37, #b8972c);
            border-color: #b8972c;
            color: #111827;
            font-weight: 700;
        }
        .ai-search-meta {
            margin-top: 10px;
            text-align: center;
            font-size: 12px;
            color: var(--text-secondary, #6b7280);
            min-height: 18px;
        }
        .catalogue-cat-pill {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 2;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
            background: rgba(17, 24, 39, 0.72);
            color: #fde68a;
            backdrop-filter: blur(4px);
            pointer-events: none;
        }
        html[data-theme="dark"] .ai-search-box {
            background: #161b26;
            border-color: rgba(240, 215, 140, 0.35);
        }
        html[data-theme="dark"] .ai-search-input { color: #f8fafc; }
        html[data-theme="dark"] .ai-chip {
            background: #161b26;
            border-color: #293244;
            color: #cbd5e1;
        }
        html[data-theme="dark"] .ai-chip.is-active {
            color: #111827;
        }
        @media (max-width: 640px) {
            .ai-search-badge span { display: none; }
            .ai-search-submit { padding: 8px 13px; font-size: 12px; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

    <?php $activePage = 'catalogue'; include 'includes/header.php'; ?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 py-8 sm:py-12 max-w-7xl catalogue-page">
        <div class="page-hero text-center max-w-2xl mx-auto mb-6 sm:mb-8 reveal">
            <span class="inline-block px-3.5 py-1 rounded-full text-xs font-semibold tracking-wider uppercase bg-amber-100 text-amber-900 border border-amber-200/80 mb-3">Exclusive Bespoke Jewellery</span>
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 font-serif mb-2 sm:mb-3">Custom Jewellery Design Catalogue</h1>
            <p class="text-sm sm:text-base text-gray-500 max-w-xl mx-auto leading-relaxed">Handcrafted 916 gold &amp; 925 silver designs. Tap any piece to view, zoom, share direct link, or inquire on WhatsApp.</p>
        </div>

        <!-- Category Filter Pills (1-tap filtering without search bar) -->
        <section class="mb-6 reveal" aria-label="Filter designs by category">
            <?php
            $chipList = ['all' => 'All (' . (int)$cat_counts['all'] . ')'];
            $sortedCats = $cat_counts;
            unset($sortedCats['all']);
            arsort($sortedCats);
            foreach (array_slice($sortedCats, 0, 12, true) as $cName => $cQty) {
                $chipList[$cName] = $cName . ' (' . (int)$cQty . ')';
            }
            ?>
            <div class="ai-chips-row flex flex-wrap justify-center gap-2 max-w-4xl mx-auto" id="catalogueAiChips" role="group" aria-label="Filter designs by category">
                <?php foreach ($chipList as $cKey => $cLabel): ?>
                    <button type="button"
                            class="ai-chip px-3.5 py-1.5 rounded-full text-xs sm:text-sm font-medium border transition-all cursor-pointer<?= strcasecmp($filter_cat, (string)$cKey) === 0 ? ' is-active' : '' ?>"
                            data-ai-cat="<?= htmlspecialchars((string)$cKey) ?>">
                        <?= htmlspecialchars($cLabel) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <p class="ai-search-meta text-center text-xs text-gray-400 mt-2" id="catalogueAiMeta" aria-live="polite">
                Showing <strong><?= (int)$total_items ?></strong> custom jewellery design<?= $total_items === 1 ? '' : 's' ?>
            </p>
        </section>

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
                echo cj_catalogue_card($product, $pi, false);
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
                <div class="flex items-center justify-center gap-2 mb-1 flex-wrap">
                    <span id="catalogueLbBadge" class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-100 text-amber-900 border border-amber-200" hidden></span>
                    <h3 id="catalogueLbTitle" class="text-lg sm:text-xl font-bold font-serif text-gray-900 m-0">Design</h3>
                </div>
                <p id="catalogueLbDesc" class="text-xs text-gray-500 m-0 mb-3">Handcrafted 916 Gold &amp; 925 Silver Design · ChandraKala Jewellers</p>
                
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-2.5 max-w-xl mx-auto mt-2">
                    <button type="button" id="catalogueLbShareBtn" class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-semibold text-xs sm:text-sm shadow-sm transition-all hover:scale-[1.02] cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        <span>Share Link</span>
                    </button>
                    <a id="catalogueLbWhatsAppShare" class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-semibold text-xs sm:text-sm shadow-sm transition-all hover:scale-[1.02] text-decoration-none" href="#" target="_blank" rel="noopener">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.044-1.076-.07-.249-.074-.572-.187-1.042-.39-1.996-.864-3.298-2.884-3.398-3.018-.099-.134-.813-1.082-.813-2.064 0-.982.515-1.464.7-1.663.185-.2.404-.25.539-.25.134 0 .269.002.387.008.125.006.291-.047.456.349.168.404.572 1.396.622 1.498.05.101.083.22.016.353-.067.133-.101.217-.201.334-.1.118-.21.263-.3.353-.1.1-.205.209-.089.408.117.199.519.855 1.113 1.385.767.683 1.413.896 1.614.996.2.1.317.084.434-.05.117-.134.502-.584.636-.784.134-.2.269-.167.453-.1.184.067 1.17.552 1.372.653.202.101.336.151.386.235.05.084.05.488-.094.893z"/></svg>
                        <span>WhatsApp</span>
                    </a>
                    <a id="catalogueLbOrder" class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs sm:text-sm shadow-sm transition-all hover:scale-[1.02] text-decoration-none" href="#" target="_blank" rel="noopener">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <span>Inquire</span>
                    </a>
                    <button type="button" id="catalogueLbAddSelect" class="flex items-center justify-center gap-1 px-3 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold text-xs sm:text-sm transition-all hover:scale-[1.02] cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        <span>Compare</span>
                    </button>
                </div>
                <p class="text-[11px] text-gray-400 mt-2.5 mb-0">Direct design link <code class="bg-gray-100 text-amber-800 px-1.5 py-0.5 rounded font-mono">Catalogue?id=...</code> allows easy sharing &amp; social previews.</p>
            </div>
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
        var catalogueUrl = <?= json_encode($cataloguePageUrl) ?>;
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
        var badge = document.getElementById('catalogueLbBadge');
        var orderBtn = document.getElementById('catalogueLbOrder');
        var shareBtn = document.getElementById('catalogueLbShareBtn');
        var waShareBtn = document.getElementById('catalogueLbWhatsAppShare');
        var addBtn = document.getElementById('catalogueLbAddSelect');
        var lastFocus = null;
        var closeTimer = null;
        var currentItem = null;

        function showToast(msg) {
            var toast = document.getElementById('catalogueToast');
            if (!toast) return;
            toast.textContent = msg || 'Link copied to clipboard!';
            toast.classList.remove('opacity-0', 'translate-y-3');
            toast.classList.add('opacity-100', 'translate-y-0');
            clearTimeout(toast._timer);
            toast._timer = setTimeout(function () {
                toast.classList.remove('opacity-100', 'translate-y-0');
                toast.classList.add('opacity-0', 'translate-y-3');
            }, 2400);
        }

        function openViewer(el) {
            lastFocus = el;
            var id = el.getAttribute('data-id') || '';
            var cat = el.getAttribute('data-category') || '';
            currentItem = {
                id: id,
                name: 'Design #' + id + (cat ? ' (' + cat + ')' : ''),
                category: cat,
                desc: el.getAttribute('data-desc') || '',
                image: el.getAttribute('data-image') || el.getAttribute('data-src') || '',
                url: catalogueUrl + '?id=' + encodeURIComponent(id)
            };
            var src = el.getAttribute('data-src') || '';
            img.src = src;
            img.alt = 'Custom Jewellery Design #' + id;

            if (title) title.textContent = 'Design #' + id;
            if (badge) {
                if (cat) {
                    badge.textContent = cat;
                    badge.hidden = false;
                } else {
                    badge.hidden = true;
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

            if (window.history && window.history.replaceState && id) {
                window.history.replaceState(null, '', 'Catalogue?id=' + encodeURIComponent(id));
            }
            lb.hidden = false;
            lb.setAttribute('aria-hidden', 'false');
            document.body.classList.add('catalogue-lightbox-open');
            requestAnimationFrame(function () { lb.classList.add('is-open'); });
            var closeBtn = lb.querySelector('.catalogue-lightbox-close');
            if (closeBtn) closeBtn.focus({ preventScroll: true });
        }

        function closeViewer() {
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', catalogueUrl);
            }
            lb.classList.remove('is-open');
            document.body.classList.remove('catalogue-lightbox-open');
            clearTimeout(closeTimer);
            closeTimer = setTimeout(function () {
                lb.hidden = true;
                lb.setAttribute('aria-hidden', 'true');
                img.removeAttribute('src');
                if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
            }, 320);
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
            gridEl.addEventListener('keydown', function (e) {
                var el = e.target.closest('[data-catalogue-open]');
                if (!el || (e.key !== 'Enter' && e.key !== ' ')) return;
                e.preventDefault();
                el.click();
            });
        }

        lb.querySelectorAll('[data-catalogue-close]').forEach(function (el) {
            el.addEventListener('click', closeViewer);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && lb.classList.contains('is-open')) closeViewer();
        });

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
