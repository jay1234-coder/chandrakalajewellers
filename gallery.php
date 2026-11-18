<?php
require 'config/db.php';
require_once 'includes/img-helper.php';
require_once 'includes/gallery-search.php';
require_once 'includes/pricing.php';
require_once 'includes/orders.php';

if (function_exists('cj_is_local_host') && cj_is_local_host()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL);
ini_set('log_errors', '1');

$standard_rates = cj_get_standard_rates($conn);
$manual_rates = cj_get_manual_rates($conn);

$filter_metal = $_GET['metal_type'] ?? 'all';
$allowed_filters = ['all', 'gold', 'silver', 'silver_925'];
if (!in_array($filter_metal, $allowed_filters, true)) {
    $filter_metal = 'all';
}

$sort = $_GET['sort'] ?? 'newest';
$allowed_sorts = ['newest', 'price_asc', 'price_desc', 'name_asc'];
if (!in_array($sort, $allowed_sorts, true)) {
    $sort = 'newest';
}

$search_q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($search_q, 'UTF-8') > 120) {
    $search_q = mb_substr($search_q, 0, 120, 'UTF-8');
}

$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? max(0, (float)$_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? max(0, (float)$_GET['max_price']) : null;
if ($min_price !== null && $max_price !== null && $min_price > $max_price) {
    $tmp = $min_price;
    $min_price = $max_price;
    $max_price = $tmp;
}

// NLP price hints from search text (do not overwrite explicit filter fields)
$filter_min = $min_price;
$filter_max = $max_price;
if ($search_q !== '') {
    $parsedQ = cj_gallery_parse_query($search_q);
    if ($filter_min === null && $parsedQ['min'] !== null) {
        $filter_min = $parsedQ['min'];
    }
    if ($filter_max === null && $parsedQ['max'] !== null) {
        $filter_max = $parsedQ['max'];
    }
}

$per_page = 24;
$page = max(1, intval($_GET['page'] ?? 1));
$is_ajax = isset($_GET['ajax']) && (string)$_GET['ajax'] === '1';

cj_ensure_orders_schema($conn);
cj_expire_reservations($conn);
cj_ensure_price_cache($conn);

function get_metal_name($metal) {
    $map = [
        'gold' => 'Gold',
        'silver' => 'Silver',
        'silver_925' => '925 Silver'
    ];
    return $map[$metal] ?? 'Unknown';
}

function gallery_url($metal, $pageNum, $sort = 'newest', $min = null, $max = null, $q = '') {
    $params = ['metal_type' => $metal];
    if ($sort !== 'newest') {
        $params['sort'] = $sort;
    }
    if ($min !== null && $min !== '') {
        $params['min_price'] = $min;
    }
    if ($max !== null && $max !== '') {
        $params['max_price'] = $max;
    }
    if ($q !== null && trim((string)$q) !== '') {
        $params['q'] = trim((string)$q);
    }
    if ($pageNum > 1) {
        $params['page'] = $pageNum;
    }
    return 'gallery?' . http_build_query($params);
}

function cj_gallery_render_card(array $row, int $i = 0, bool $lazyForce = false): string {
    $metal_display = get_metal_name($row['metal_type']);
    $rounded_final_price = (int)$row['_price'];
    $delay = min($i % 3, 3);
    $absImg = cj_img_url($row['image'], 1200);
    $productUrl = cj_abs_url('product?id=' . (int)$row['id']);
    $is_sold = !empty($row['is_sold_out']);
    $eager = !$lazyForce && $i < 2;
    $imgUrl = htmlspecialchars(cj_img_url($row['image'], 600));
    $nameEsc = htmlspecialchars($row['name']);
    $nameAttr = htmlspecialchars($row['name'], ENT_QUOTES);
    $descAttr = htmlspecialchars(substr(strip_tags($row['description'] ?? ''), 0, 120), ENT_QUOTES);
    $metalAttr = htmlspecialchars($metal_display, ENT_QUOTES);
    $metalClass = htmlspecialchars($row['metal_type']);
    $weightAttr = htmlspecialchars((string)$row['weight'], ENT_QUOTES);
    $priceAttr = htmlspecialchars((string)$rounded_final_price, ENT_QUOTES);
    $urlAttr = htmlspecialchars($productUrl, ENT_QUOTES);
    $imgAttr = htmlspecialchars($absImg, ENT_QUOTES);
    $id = (int)$row['id'];
    $soldClass = $is_sold ? ' is-sold-out' : '';
    $delayClass = $delay ? ' reveal-delay-' . $delay : '';
    $anim = ($i % 6) * 0.05;
    $priceHtml = !empty($row['use_manual_rates'])
        ? '<span style="color: #059669; font-weight: 700;">₹' . number_format($rounded_final_price, 0) . '</span>'
        : '₹' . number_format($rounded_final_price, 0);
    $badge = $is_sold ? '<span class="sold-out-badge">Sold Out</span>' : '';
    $wishBtn = (function_exists('cj_feature') && cj_feature('feat_wishlist') && !$is_sold)
        ? '<button type="button" class="cj-wish-btn" data-product-id="' . $id . '" onclick="event.preventDefault(); event.stopPropagation(); toggleWishlist(' . $id . ', \'' . $nameAttr . '\');" aria-label="Add to wishlist">♥</button>'
        : '';
    $actions = $is_sold
        ? '<span class="btn btn-sold-out" aria-disabled="true">Sold Out</span>
           <a href="product?id=' . $id . '" class="btn btn-secondary" data-sc-nav>View Details</a>'
        : '<button type="button" class="btn btn-secondary" onclick="addToCart(' . $id . ', \'' . $nameAttr . '\')">Add to Cart</button>
           <a href="buynow?id=' . $id . '" class="btn" data-sc-nav>Buy Now!</a>';
    $loading = $eager ? 'eager' : 'lazy';
    $fetch = ($eager && $i === 0) ? ' fetchpriority="high"' : '';
    $imgTag = $eager
        ? '<img src="' . $imgUrl . '" alt="' . $nameEsc . ' jewellery for sale in Khedbrahma" width="400" height="400" decoding="async" loading="' . $loading . '"' . $fetch . ' style="margin-bottom: 0;">'
        : '<img class="cj-lazy-img" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="' . $imgUrl . '" alt="' . $nameEsc . ' jewellery for sale in Khedbrahma" width="400" height="400" decoding="async" loading="lazy" style="margin-bottom: 0;">';

    return '<div class="product' . $soldClass . ' metal-' . $metalClass . '" data-sc-item data-id="' . $id . '" data-name="' . $nameAttr . '" data-desc="' . $descAttr . '" data-image="' . $imgAttr . '" data-metal="' . $metalAttr . '" data-weight="' . $weightAttr . '" data-price="' . $priceAttr . '" data-url="' . $urlAttr . '" data-sold="' . ($is_sold ? '1' : '0') . '">
        <a href="product?id=' . $id . '" class="product-media-link" data-sc-nav>
            ' . $badge . '
            ' . $wishBtn . '
            <span class="metal-badge metal-badge-' . $metalClass . '">' . htmlspecialchars($metal_display) . '</span>
            ' . $imgTag . '
        </a>
        <h3>' . $nameEsc . '</h3>
        <p>' . htmlspecialchars($metal_display) . ' &bull; ' . htmlspecialchars((string)$row['weight']) . ' g</p>
        <div class="price-container"><div class="price-val">' . $priceHtml . '</div></div>
        <div class="product-actions">' . $actions . '</div>
    </div>';
}

// Counts for sidebar badges
$metal_counts = ['all' => 0, 'gold' => 0, 'silver' => 0, 'silver_925' => 0];
$count_q = $conn->query("SELECT metal_type, COUNT(*) AS c FROM jewellery_items GROUP BY metal_type");
if ($count_q) {
    while ($cr = $count_q->fetch_assoc()) {
        $mt = $cr['metal_type'];
        $metal_counts[$mt] = (int)$cr['c'];
        $metal_counts['all'] += (int)$cr['c'];
    }
}
$raw_silver = (int)($metal_counts['silver'] ?? 0);
$raw_925 = (int)($metal_counts['silver_925'] ?? 0);
$metal_counts['silver'] = $raw_silver + $raw_925;

$needs_php_search = ($search_q !== '');
$page_items = [];
$total_items = 0;

if ($needs_php_search) {
    $where_sql = '';
    if ($filter_metal === 'silver') {
        $where_sql = " WHERE metal_type IN ('silver', 'silver_925')";
    } elseif ($filter_metal !== 'all') {
        $filter_metal_safe = $conn->real_escape_string($filter_metal);
        $where_sql = " WHERE metal_type = '{$filter_metal_safe}'";
    }
    $result = $conn->query("SELECT id, name, description, tags, image, weight, metal_type, is_sold_out, use_manual_rates, cached_price FROM jewellery_items" . $where_sql . " ORDER BY id DESC");
    $catalog = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rates_to_use = !empty($row['use_manual_rates']) ? $manual_rates : $standard_rates;
            $rounded = (int) round(!empty($row['cached_price']) ? $row['cached_price'] : calculate_price($row['metal_type'], $row['weight'], $rates_to_use));
            if ($filter_min !== null && $rounded < $filter_min) {
                continue;
            }
            if ($filter_max !== null && $rounded > $filter_max) {
                continue;
            }
            $row['_price'] = $rounded;
            $catalog[] = $row;
        }
    }
    $catalog = cj_gallery_filter_catalog($catalog, $search_q, 'get_metal_name');
    $total_items = count($catalog);
    $total_pages = max(1, (int) ceil($total_items / $per_page));
    if ($page > $total_pages) {
        $page = $total_pages;
    }
    $page_items = array_slice($catalog, ($page - 1) * $per_page, $per_page);
} else {
    $where = ['1=1'];
    $types = '';
    $params = [];
    if ($filter_metal === 'silver') {
        $where[] = "metal_type IN ('silver', 'silver_925')";
    } elseif ($filter_metal !== 'all') {
        $where[] = 'metal_type = ?';
        $types .= 's';
        $params[] = $filter_metal;
    }
    if ($filter_min !== null) {
        $where[] = 'cached_price >= ?';
        $types .= 'd';
        $params[] = $filter_min;
    }
    if ($filter_max !== null) {
        $where[] = 'cached_price <= ?';
        $types .= 'd';
        $params[] = $filter_max;
    }
    $whereSql = implode(' AND ', $where);
    $orderSql = 'id DESC';
    if ($sort === 'price_asc') {
        $orderSql = 'cached_price ASC, id DESC';
    } elseif ($sort === 'price_desc') {
        $orderSql = 'cached_price DESC, id DESC';
    } elseif ($sort === 'name_asc') {
        $orderSql = 'name ASC';
    }

    $countSql = "SELECT COUNT(*) AS c FROM jewellery_items WHERE $whereSql";
    if ($types === '') {
        $cq2 = $conn->query($countSql);
        $total_items = $cq2 ? (int) ($cq2->fetch_assoc()['c'] ?? 0) : 0;
    } else {
        $cst = $conn->prepare($countSql);
        $cst->bind_param($types, ...$params);
        $cst->execute();
        $total_items = (int) ($cst->get_result()->fetch_assoc()['c'] ?? 0);
        $cst->close();
    }

    $total_pages = max(1, (int) ceil($total_items / $per_page));
    if ($page > $total_pages) {
        $page = $total_pages;
    }
    $offset = ($page - 1) * $per_page;
    $listSql = "SELECT id, name, description, tags, image, weight, metal_type, is_sold_out, use_manual_rates, cached_price FROM jewellery_items WHERE $whereSql ORDER BY $orderSql LIMIT ? OFFSET ?";
    $listTypes = $types . 'ii';
    $listParams = array_merge($params, [$per_page, $offset]);
    $lst = $conn->prepare($listSql);
    $lst->bind_param($listTypes, ...$listParams);
    $lst->execute();
    $lr = $lst->get_result();
    while ($row = $lr->fetch_assoc()) {
        $row['_price'] = (int) round((float) ($row['cached_price'] ?? 0));
        $page_items[] = $row;
    }
    $lst->close();
}

if ($is_ajax) {
    require_once __DIR__ . '/includes/seo.php';
    $html = '';
    foreach ($page_items as $i => $row) {
        $html .= cj_gallery_render_card($row, $i, true);
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
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$activePage = 'shop';
require_once 'includes/seo.php';
$filterLabel = $filter_metal === 'all' ? 'Gold & Silver' : ($filter_metal === 'silver' ? 'Silver' : get_metal_name($filter_metal));
$seoTitleQ = $search_q !== '' ? ('Search: ' . $search_q . ' | ') : '';
$seo = cj_merge_seo([
    'title' => $seoTitleQ . ($filter_metal === 'all'
        ? 'Shop 916 Gold & Silver Jewellery in Khedbrahma | ChandraKala Jewellers'
        : $filterLabel . ' Jewellery in Khedbrahma | ChandraKala Jewellers'),
    'description' => 'Browse ' . strtolower($filterLabel) . ' jewellery (' . (int)$total_items . ' live-priced designs) from ChandraKala Jewellers, Khedbrahma. Live metal rates in IST, hallmark 916 gold, pure silver & 925 sterling silver. Order online or visit Civil Road.',
    'canonical' => cj_abs_url(gallery_url($filter_metal, $page > 1 ? $page : 1, $sort, $min_price, $max_price, $search_q)),
    'modified_iso' => cj_format_ist_iso($standard_rates['updated_at'] ?? null),
    'schema' => [
        cj_local_business_schema(),
        cj_item_list_schema($page_items, $filterLabel . ' Jewellery — ChandraKala Jewellers Khedbrahma', cj_abs_url(gallery_url($filter_metal, $page > 1 ? $page : 1, $sort, $min_price, $max_price, $search_q))),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Shop', 'url' => cj_abs_url('gallery')],
        ]),
    ],
]);

$sort_labels = [
    'newest' => 'Newest first',
    'price_asc' => 'Price: Low to High',
    'price_desc' => 'Price: High to Low',
    'name_asc' => 'Name A–Z',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
</head>
<body>

<?php include 'includes/header.php'; ?>

<main class="container shop-page page-enter">
    <nav class="page-trail" aria-label="Breadcrumb">
        <a href="index1">Home</a>
        <span aria-hidden="true">/</span>
        <span>Shop</span>
    </nav>
    <div class="page-hero">
        <p class="app-greeting-inline" id="shop-greeting" style="display:none;margin:0 0 8px;font-size:13px;font-weight:600;color:var(--primary-gold-dark);"></p>
        <h1><?= $filter_metal === 'all' ? 'Gold &amp; Silver Jewellery Collection in Khedbrahma' : htmlspecialchars($filterLabel) . ' Jewellery Collection' ?></h1>
        <p>The jewellery product shown may vary from the actual product*</p>
        <?php if (!empty($standard_rates['updated_at'])): ?>
            <p style="font-size:12px;color:var(--text-secondary,#666);margin-top:4px;">Live rates updated: <strong><?= htmlspecialchars(cj_format_ist($standard_rates['updated_at'])) ?></strong></p>
        <?php endif; ?>
    </div>

    <div class="shop-search-wrap">
        <form method="get" action="gallery" class="shop-super-search" id="shopSuperSearch" role="search">
            <input type="hidden" name="metal_type" value="<?= htmlspecialchars($filter_metal) ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <?php if ($min_price !== null): ?><input type="hidden" name="min_price" value="<?= htmlspecialchars((string)$min_price) ?>"><?php endif; ?>
            <?php if ($max_price !== null): ?><input type="hidden" name="max_price" value="<?= htmlspecialchars((string)$max_price) ?>"><?php endif; ?>
            <label class="shop-super-search-label" for="shopSearchInput">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/></svg>
                <span class="sr-only">Search jewellery</span>
            </label>
            <input
                type="search"
                id="shopSearchInput"
                name="q"
                value="<?= htmlspecialchars($search_q) ?>"
                placeholder="Search anything — gold ring, 925, under 5000, bridal…"
                autocomplete="off"
                enterkeyhint="search"
                aria-label="Super search jewellery"
            >
            <?php if ($search_q !== ''): ?>
                <a class="shop-search-clear" href="<?= htmlspecialchars(gallery_url($filter_metal, 1, $sort, $min_price, $max_price, '')) ?>" aria-label="Clear search">&times;</a>
            <?php endif; ?>
            <button type="submit" class="shop-search-btn">Search</button>
        </form>
        <p class="shop-search-hint">Typos, metal, weight, price phrases &amp; jewellery words all work.</p>
    </div>

    <div class="shop-toolbar">
        <button type="button" class="shop-filter-toggle" id="shopFilterToggle" aria-expanded="false" aria-controls="shopFilters">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
            Filters
        </button>
        <div class="sc-toolbar sc-toolbar-inline" role="toolbar" aria-label="Shop tools">
            <button type="button" class="sc-tool-btn" id="scEnterSelect">Select</button>
            <button type="button" class="sc-tool-btn" id="scEnterCompare">Compare</button>
            <button type="button" class="sc-tool-btn sc-tool-muted" id="scClearAll">Clear</button>
        </div>
        <p class="shop-result-count" id="shopResultCount"><?= (int)$total_items ?> item<?= $total_items === 1 ? '' : 's' ?><?= $search_q !== '' ? ' · “' . htmlspecialchars($search_q) . '”' : '' ?><?= $filter_metal !== 'all' ? ' · ' . htmlspecialchars($filterLabel) : '' ?><?= $sort !== 'newest' && $search_q === '' ? ' · ' . htmlspecialchars($sort_labels[$sort]) : '' ?></p>
    </div>

    <div class="shop-layout">
        <aside class="shop-filters" id="shopFilters" aria-label="Shop filters">
            <div class="shop-filters-inner">
                <div class="shop-filters-header">
                    <h2>Filters</h2>
                    <button type="button" class="shop-filters-close" id="shopFilterClose" aria-label="Close filters">&times;</button>
                </div>

                <form method="get" action="gallery" class="shop-filter-form" id="shopFilterForm">
                    <?php if ($search_q !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search_q) ?>">
                    <?php endif; ?>
                    <section class="filter-group reveal">
                        <h3>Metal</h3>
                        <div class="filter-options">
                            <?php
                            $metals = [
                                'all' => 'All collection',
                                'gold' => 'Gold',
                                'silver' => 'All Silver',
                                'silver_925' => '925 Sterling Silver',
                            ];
                            foreach ($metals as $key => $label):
                            ?>
                                <label class="filter-radio<?= $filter_metal === $key ? ' is-active' : '' ?>">
                                    <input type="radio" name="metal_type" value="<?= htmlspecialchars($key) ?>" <?= $filter_metal === $key ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="filter-radio-ui"></span>
                                    <span class="filter-radio-label"><?= htmlspecialchars($label) ?></span>
                                    <span class="filter-count"><?= (int)($metal_counts[$key] ?? 0) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="filter-group reveal reveal-delay-1">
                        <h3>Sort by</h3>
                        <div class="filter-options">
                            <?php foreach ($sort_labels as $key => $label): ?>
                                <label class="filter-radio<?= $sort === $key ? ' is-active' : '' ?>">
                                    <input type="radio" name="sort" value="<?= htmlspecialchars($key) ?>" <?= $sort === $key ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="filter-radio-ui"></span>
                                    <span class="filter-radio-label"><?= htmlspecialchars($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="filter-group reveal reveal-delay-2">
                        <h3>Price range (₹)</h3>
                        <div class="filter-price-row">
                            <label>
                                <span>Min</span>
                                <input type="number" name="min_price" min="0" step="100" placeholder="0" value="<?= $min_price !== null ? htmlspecialchars((string)$min_price) : '' ?>">
                            </label>
                            <span class="filter-price-dash">–</span>
                            <label>
                                <span>Max</span>
                                <input type="number" name="max_price" min="0" step="100" placeholder="Any" value="<?= $max_price !== null ? htmlspecialchars((string)$max_price) : '' ?>">
                            </label>
                        </div>
                        <button type="submit" class="btn filter-apply-btn">Apply price</button>
                    </section>

                    <?php if ($filter_metal !== 'all' || $sort !== 'newest' || $min_price !== null || $max_price !== null || $search_q !== ''): ?>
                        <a href="gallery" class="filter-clear">Clear all filters</a>
                    <?php endif; ?>
                </form>
            </div>
        </aside>

        <div class="shop-main">
            <div class="gallery-container" id="shopGrid"
                 data-page="<?= (int)$page ?>"
                 data-total-pages="<?= (int)$total_pages ?>"
                 data-metal="<?= htmlspecialchars($filter_metal) ?>"
                 data-sort="<?= htmlspecialchars($sort) ?>"
                 data-min="<?= $min_price !== null ? htmlspecialchars((string)$min_price) : '' ?>"
                 data-max="<?= $max_price !== null ? htmlspecialchars((string)$max_price) : '' ?>"
                 data-q="<?= htmlspecialchars($search_q) ?>">
                <?php if (!empty($page_items)): ?>
                    <?php foreach ($page_items as $i => $row): ?>
                        <?= cj_gallery_render_card($row, $i, false) ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="shop-empty" id="shopEmpty">
                        <p><?= $search_q !== '' ? 'No jewellery matched your search. Try another word, metal, or price.' : 'No jewellery items match these filters.' ?></p>
                        <a href="gallery" class="btn btn-secondary">Reset filters</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="shop-lazy-status" id="shopLazyStatus" hidden>
                <span class="shop-lazy-spinner" aria-hidden="true"></span>
                <span>Loading more jewellery…</span>
            </div>
            <div id="shopLazySentinel" class="shop-lazy-sentinel" aria-hidden="true"></div>

            <?php if ($total_pages > 1): ?>
                <nav class="pagination" id="shopPagination" aria-label="Shop pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= htmlspecialchars(gallery_url($filter_metal, $page - 1, $sort, $min_price, $max_price, $search_q)) ?>">&larr; Prev</a>
                    <?php else: ?>
                        <span class="disabled">&larr; Prev</span>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="current"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars(gallery_url($filter_metal, $p, $sort, $min_price, $max_price, $search_q)) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?= htmlspecialchars(gallery_url($filter_metal, $page + 1, $sort, $min_price, $max_price, $search_q)) ?>">Next &rarr;</a>
                    <?php else: ?>
                        <span class="disabled">Next &rarr;</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

            <div class="back-btn-container">
                <a href="index1" class="btn btn-secondary">&larr; Back to Home</a>
            </div>
        </div>
    </div>
</main>

<div class="shop-filters-backdrop" id="shopFilterBackdrop" hidden></div>

<?php include 'includes/footer.php'; ?>

<?php if (function_exists('cj_feature') && cj_feature('feat_compare')): ?>
<script src="js/select-compare-share.js"></script>
<?php endif; ?>
<script src="js/gallery-shop.js"></script>
<script>
(function () {
    if (!(window.CJSelectCompare && typeof window.CJSelectCompare.create === 'function')) return;
    var sc = window.CJSelectCompare.create({
        phone: (window.CJ_SHOP && window.CJ_SHOP.wa) ? window.CJ_SHOP.wa : '919427080359',
        pageUrl: <?= json_encode(cj_abs_url('gallery')) ?>,
        pageLabel: 'Shop page',
        itemSelector: '#shopGrid [data-sc-item]',
        minCompare: 2,
        storageKey: 'cj_gallery_selection'
    });
    window.CJGallerySelect = sc;

    function bindCardNav(root) {
        (root || document).querySelectorAll('#shopGrid [data-sc-item]').forEach(function (card) {
            if (card.getAttribute('data-sc-bound') === '1') return;
            card.setAttribute('data-sc-bound', '1');
            card.querySelectorAll('[data-sc-nav]').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    if (document.body.classList.contains('sc-mode-select') || document.body.classList.contains('sc-mode-compare')) {
                        e.preventDefault();
                        e.stopPropagation();
                        var wrap = card.querySelector('.sc-check-wrap');
                        if (wrap) wrap.click();
                    }
                });
            });
            card.addEventListener('click', function (e) {
                if (!(document.body.classList.contains('sc-mode-select') || document.body.classList.contains('sc-mode-compare'))) return;
                if (e.target.closest('.sc-check-wrap') || e.target.closest('.product-actions button')) return;
                e.preventDefault();
                var wrap = card.querySelector('.sc-check-wrap');
                if (wrap) wrap.click();
            });
        });
    }

    bindCardNav(document);
    window.CJGalleryBindNav = bindCardNav;
})();
</script>
<script>
(function () {
    var toggle = document.getElementById('shopFilterToggle');
    var panel = document.getElementById('shopFilters');
    var backdrop = document.getElementById('shopFilterBackdrop');
    var closeBtn = document.getElementById('shopFilterClose');
    var layout = document.querySelector('.shop-layout');
    var mq = window.matchMedia('(max-width: 960px)');
    if (!toggle || !panel) return;

    function placeOverlays() {
        if (mq.matches) {
            if (panel.parentElement !== document.body) {
                document.body.appendChild(panel);
            }
            if (backdrop && backdrop.parentElement !== document.body) {
                document.body.appendChild(backdrop);
            }
        } else if (layout) {
            if (panel.parentElement !== layout) {
                layout.insertBefore(panel, layout.firstChild);
            }
            panel.classList.remove('is-open');
            panel.removeAttribute('aria-hidden');
            if (backdrop) {
                backdrop.classList.remove('is-visible');
                backdrop.hidden = true;
            }
            toggle.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('shop-filters-open');
        }
    }

    function openFilters() {
        if (!mq.matches) return;
        placeOverlays();
        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');
        if (backdrop) {
            backdrop.hidden = false;
            backdrop.classList.add('is-visible');
        }
        toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('shop-filters-open');
    }

    function closeFilters() {
        panel.classList.remove('is-open');
        if (mq.matches) {
            panel.setAttribute('aria-hidden', 'true');
        } else {
            panel.removeAttribute('aria-hidden');
        }
        if (backdrop) {
            backdrop.classList.remove('is-visible');
            backdrop.hidden = true;
        }
        toggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('shop-filters-open');
    }

    placeOverlays();
    if (mq.matches) {
        panel.setAttribute('aria-hidden', 'true');
    } else {
        panel.removeAttribute('aria-hidden');
    }

    toggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (panel.classList.contains('is-open')) closeFilters();
        else openFilters();
    });
    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeFilters();
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', closeFilters);
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('is-open')) closeFilters();
    });

    if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', placeOverlays);
    } else if (typeof mq.addListener === 'function') {
        mq.addListener(placeOverlays);
    }

    document.querySelectorAll('.filter-radio').forEach(function (label) {
        label.addEventListener('change', function () {
            label.classList.add('is-active');
        });
    });
})();
</script>
</body>
</html>
