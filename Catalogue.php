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
    $thumb = htmlspecialchars(cj_img_url($product['image_filename'], 480, 'catalogue'));
    $absImg = $full;
    $name = htmlspecialchars($product['name']);
    $desc = htmlspecialchars($product['description'] ?? '');
    $cat = trim((string)($product['category'] ?? ''));
    $eager = !$lazyForce && $pi < 2;
    $img = $eager
        ? '<img src="' . $thumb . '" alt="' . $name . '" class="max-h-full max-w-full object-contain group-hover:scale-[1.03] transition-transform duration-300 pointer-events-none" decoding="async" width="400" height="400" fetchpriority="' . ($pi === 0 ? 'high' : 'auto') . '">'
        : '<img class="cj-lazy-img max-h-full max-w-full object-contain group-hover:scale-[1.03] transition-transform duration-300 pointer-events-none" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="' . $thumb . '" alt="' . $name . '" decoding="async" loading="lazy" width="400" height="400">';

    $catPill = $cat !== ''
        ? '<span class="catalogue-cat-pill">' . htmlspecialchars($cat) . '</span>'
        : '';

    return '<div class="group catalogue-item bg-white overflow-hidden text-left w-full"
        data-sc-item data-catalogue-open
        data-id="' . (int) $product['id'] . '"
        data-src="' . $full . '"
        data-image="' . htmlspecialchars($absImg) . '"
        data-name="' . $name . '"
        data-desc="' . $desc . '"
        role="button" tabindex="0" aria-label="View ' . $name . '">
        <div class="relative aspect-square overflow-hidden bg-gray-50/50 flex items-center justify-center p-2 sm:p-4">'
        . $catPill . $img .
        '<span class="catalogue-zoom-hint" aria-hidden="true">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16zM11 8v6M8 11h6"/></svg>
        </span>
        </div>
        <div class="p-2.5 sm:p-5 border-t border-gray-50">
            <h3 class="font-bold text-gray-900 font-serif text-xs sm:text-base text-center line-clamp-2 sm:line-clamp-1 mb-0.5 sm:mb-1 leading-snug">' . $name . '</h3>'
        . ($desc !== '' ? '<p class="text-[10px] sm:text-xs text-gray-400 text-center line-clamp-2 hidden sm:block">' . $desc . '</p>' : '') .
        '</div>
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
    ? (htmlspecialchars($targetProduct['name']) . ' | Custom Design Catalogue | ChandraKala Jewellers') 
    : 'Custom Design Catalogue | ChandraKala Jewellers';
$catalogueDesc = $targetProduct && !empty($targetProduct['description']) 
    ? htmlspecialchars($targetProduct['description']) 
    : 'Explore 138+ custom 916 gold & silver jewellery design photos at ChandraKala Jewellers, Khedbrahma. Compare pieces side-by-side & order directly on WhatsApp.';
$catalogueOgImage = $targetProduct && !empty($targetProduct['image_filename']) 
    ? cj_img_url($targetProduct['image_filename'], 1200, 'catalogue') 
    : null;

$seo = cj_merge_seo([
    'title' => $catalogueTitle,
    'description' => $catalogueDesc,
    'keywords' => 'custom gold jewellery designs Khedbrahma, silver design catalogue Gujarat, jewellery photo catalogue Sabarkantha',
    'canonical' => $catalogueCanonical,
    'image' => $catalogueOgImage,
    'og_image' => $catalogueOgImage,
    'schema' => [
        cj_local_business_schema(),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Catalogue', 'url' => cj_abs_url('Catalogue')],
        ]),
    ],
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
        <div class="page-hero text-center max-w-2xl mx-auto mb-5 sm:mb-7 reveal">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 font-serif mb-2 sm:mb-3">Custom Gold &amp; Silver Design Catalogue</h2>
            <p class="text-sm text-gray-500 leading-relaxed px-1">Tap a design to view it. Use <strong>AI Search</strong> to find designs by style, metal, or local name — or <strong>Compare</strong> side-by-side and order directly on WhatsApp.</p>
        </div>

        <!-- Powerful AI Search Bar (Regex + Synonyms + Fuzzy Live AJAX) -->
        <section class="ai-search-shell reveal" aria-label="AI Jewellery Search">
            <form id="catalogueAiSearchForm" action="Catalogue" method="get" role="search" class="ai-search-box">
                <span class="ai-search-badge" aria-hidden="true">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.2L22 12l-7.6 2.8L12 22l-2.4-7.2L2 12l7.6-2.8L12 2z"/></svg>
                    <span>AI Search</span>
                </span>
                <input
                    type="search"
                    id="catalogueAiInput"
                    name="q"
                    value="<?= htmlspecialchars($search_q) ?>"
                    class="ai-search-input"
                    placeholder='Try "gold bridal necklace", "kundan", "mangalsutra", "925 silver ring"...'
                    autocomplete="off"
                    enterkeyhint="search"
                    aria-label="AI search custom jewellery catalogue"
                >
                <button type="button" id="catalogueAiClear" class="ai-search-icon-btn" title="Clear search" aria-label="Clear search" <?= $search_q === '' ? 'hidden' : '' ?>>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <button type="button" id="catalogueAiVoice" class="ai-search-icon-btn" title="Voice search (English / Hindi / Gujarati)" aria-label="Voice search">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/></svg>
                </button>
                <button type="submit" class="ai-search-submit">Search</button>
            </form>

            <?php
            $chipList = ['all' => 'All (' . (int)$cat_counts['all'] . ')'];
            $sortedCats = $cat_counts;
            unset($sortedCats['all']);
            arsort($sortedCats);
            foreach (array_slice($sortedCats, 0, 10, true) as $cName => $cQty) {
                $chipList[$cName] = $cName . ' (' . (int)$cQty . ')';
            }
            ?>
            <div class="ai-chips-row" id="catalogueAiChips" role="group" aria-label="Filter designs by category">
                <?php foreach ($chipList as $cKey => $cLabel): ?>
                    <button type="button"
                            class="ai-chip<?= strcasecmp($filter_cat, (string)$cKey) === 0 ? ' is-active' : '' ?>"
                            data-ai-cat="<?= htmlspecialchars((string)$cKey) ?>">
                        <?= htmlspecialchars($cLabel) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <p class="ai-search-meta" id="catalogueAiMeta" aria-live="polite">
                Showing <strong><?= (int)$total_items ?></strong> custom jewellery design<?= $total_items === 1 ? '' : 's' ?> · Understands typos, English, Gujarati &amp; Hindi jewellery terms.
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
            <p class="text-xs text-gray-400 mt-1">Try simpler words like <strong>gold</strong>, <strong>bridal</strong>, <strong>kundan</strong>, <strong>ring</strong>, or <strong>mangalsutra</strong>.</p>
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
        <p id="catalogueLazyStatus" class="text-center text-xs text-gray-400 mt-4" hidden>AI searching designs…</p>
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
                <h3 id="catalogueLbTitle"></h3>
                <p id="catalogueLbDesc"></p>
                <div class="catalogue-lb-actions">
                    <a id="catalogueLbOrder" class="catalogue-order-btn btn btn-whatsapp" href="#" target="_blank" rel="noopener">Order on WhatsApp</a>
                    <button type="button" class="btn btn-secondary" id="catalogueLbSharePhoto">Share photo</button>
                    <button type="button" class="btn btn-secondary" id="catalogueLbAddSelect">Add to selection</button>
                </div>
                <p class="catalogue-order-hint">WhatsApp message includes this design’s <strong>photo link</strong>. On supported phones, Share photo can send the actual image.</p>
            </div>
        </div>
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
        var orderBtn = document.getElementById('catalogueLbOrder');
        var shareBtn = document.getElementById('catalogueLbSharePhoto');
        var addBtn = document.getElementById('catalogueLbAddSelect');
        var lastFocus = null;
        var closeTimer = null;
        var currentItem = null;

        function openViewer(el) {
            lastFocus = el;
            currentItem = {
                id: el.getAttribute('data-id') || '',
                name: el.getAttribute('data-name') || '',
                desc: el.getAttribute('data-desc') || '',
                image: el.getAttribute('data-image') || el.getAttribute('data-src') || '',
                url: catalogueUrl
            };
            var src = el.getAttribute('data-src') || '';
            img.src = src;
            img.alt = currentItem.name;
            title.textContent = currentItem.name;
            desc.textContent = currentItem.desc;
            desc.hidden = !currentItem.desc;
            if (orderBtn) orderBtn.href = sc.buildSingleWhatsApp(currentItem, 'order');
            if (window.history && window.history.replaceState && currentItem.id) {
                window.history.replaceState(null, '', 'Catalogue?id=' + encodeURIComponent(currentItem.id));
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
                if (!currentItem) return;
                sc.shareSinglePhoto(currentItem);
            });
        }
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                if (!lastFocus) return;
                sc.ensureSelected(lastFocus);
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
