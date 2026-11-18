<?php
require_once 'config/db.php';
require_once 'includes/seo.php';
require_once 'includes/img-helper.php';

$per_page = 24;
$page = max(1, (int) ($_GET['page'] ?? 1));
$is_ajax = isset($_GET['ajax']) && (string) $_GET['ajax'] === '1';

$total_items = 0;
$tq = $conn->query('SELECT COUNT(*) AS c FROM products');
if ($tq) {
    $total_items = (int) ($tq->fetch_assoc()['c'] ?? 0);
}
$total_pages = max(1, (int) ceil($total_items / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$products = [];
$stmt = $conn->prepare('SELECT id, name, description, image_filename FROM products ORDER BY name ASC LIMIT ? OFFSET ?');
if ($stmt) {
    $stmt->bind_param('ii', $per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
}

function cj_catalogue_card(array $product, int $pi, bool $lazyForce = false): string
{
    $delay = ($pi % 4) + 1;
    $full = htmlspecialchars(cj_img_url($product['image_filename'], 1400, 'catalogue'));
    $thumb = htmlspecialchars(cj_img_url($product['image_filename'], 480, 'catalogue'));
    $absImg = $full;
    $name = htmlspecialchars($product['name']);
    $desc = htmlspecialchars($product['description'] ?? '');
    $eager = !$lazyForce && $pi < 2;
    $img = $eager
        ? '<img src="' . $thumb . '" alt="' . $name . '" class="max-h-full max-w-full object-contain group-hover:scale-[1.03] transition-transform duration-300 pointer-events-none" decoding="async" width="400" height="400" fetchpriority="' . ($pi === 0 ? 'high' : 'auto') . '">'
        : '<img class="cj-lazy-img max-h-full max-w-full object-contain group-hover:scale-[1.03] transition-transform duration-300 pointer-events-none" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="' . $thumb . '" alt="' . $name . '" decoding="async" loading="lazy" width="400" height="400">';

    return '<div class="group catalogue-item bg-white overflow-hidden text-left w-full"
        data-sc-item data-catalogue-open
        data-id="' . (int) $product['id'] . '"
        data-src="' . $full . '"
        data-image="' . htmlspecialchars($absImg) . '"
        data-name="' . $name . '"
        data-desc="' . $desc . '"
        role="button" tabindex="0" aria-label="View ' . $name . '">
        <div class="relative aspect-square overflow-hidden bg-gray-50/50 flex items-center justify-center p-2 sm:p-4">'
        . $img .
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
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$seo = cj_merge_seo([
    'title' => 'Custom Design Catalogue | ChandraKala Jewellers',
    'description' => 'Explore 138+ custom 916 gold & silver jewellery design photos at ChandraKala Jewellers, Khedbrahma. Compare pieces side-by-side & order directly on WhatsApp.',
    'keywords' => 'custom gold jewellery designs Khedbrahma, silver design catalogue Gujarat, jewellery photo catalogue Sabarkantha',
    'canonical' => cj_abs_url('Catalogue'),
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
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

    <?php $activePage = 'catalogue'; include 'includes/header.php'; ?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 py-8 sm:py-12 max-w-7xl catalogue-page">
        <div class="page-hero text-center max-w-2xl mx-auto mb-6 sm:mb-8 reveal">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 font-serif mb-2 sm:mb-3">Custom Gold &amp; Silver Design Catalogue</h2>
            <p class="text-sm text-gray-500 leading-relaxed px-1">Tap a design to view it. Use <strong>Select</strong> to enquire about many pieces, or <strong>Compare</strong> (min 2) side-by-side — WhatsApp includes photo links, and you can share actual photos when your phone supports it.</p>
        </div>

        <?php if (!empty($products)): ?>
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

        <?php if (empty($products)): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-10 sm:p-16 text-center text-gray-500 max-w-md mx-auto reveal">
                <p class="font-medium">No custom designs found in our catalogue yet.</p>
                <p class="text-xs text-gray-400 mt-1">Please check back later for new uploads.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6 md:gap-8" id="catalogueGrid"
                 data-page="<?= (int) $page ?>" data-total-pages="<?= (int) $total_pages ?>">
                <?php foreach ($products as $pi => $product) {
                    echo cj_catalogue_card($product, $pi, false);
                } ?>
            </div>
            <p id="catalogueLazyStatus" class="text-center text-xs text-gray-400 mt-4" hidden>Loading more designs…</p>
            <div id="catalogueLazySentinel" class="shop-lazy-sentinel" aria-hidden="true"></div>
            <?php if ($total_pages > 1): ?>
            <div class="text-center mt-8 mb-4" id="catalogueLoadMoreWrap">
                <button type="button" id="catalogueLoadMoreBtn" class="inline-flex items-center gap-2 px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-medium text-sm rounded-full shadow-sm transition-all hover:scale-105 cursor-pointer">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    Load More Designs (<?= $total_items ?> Total)
                </button>
            </div>
            <?php endif; ?>
        <?php endif; ?>
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
    <script src="js/catalogue-lazy.js?v=7" defer></script>
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
            lb.hidden = false;
            lb.setAttribute('aria-hidden', 'false');
            document.body.classList.add('catalogue-lightbox-open');
            requestAnimationFrame(function () { lb.classList.add('is-open'); });
            var closeBtn = lb.querySelector('.catalogue-lightbox-close');
            if (closeBtn) closeBtn.focus({ preventScroll: true });
        }

        function closeViewer() {
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
    })();
    </script>
</body>
</html>
