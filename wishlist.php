<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/img-helper.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/customers.php';
require_once __DIR__ . '/includes/catalogue-wishlist.php';

if (function_exists('cj_feature') && !cj_feature('feat_wishlist')) {
    header('Location: gallery');
    exit;
}

$customer = cj_customer_session();
$activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'catalogue') ? 'catalogue' : 'shop';
$catalogueWishlist = $customer ? cj_get_catalogue_wishlist_items($conn, (int)$customer['id']) : [];
$waNumber = function_exists('cj_shop_whatsapp_digits') ? cj_shop_whatsapp_digits() : '919427080359';

$seo = cj_merge_seo([
    'title' => 'Wishlist | ChandraKala Jewellers',
    'description' => 'Your saved jewellery pieces and custom catalogue designs at ChandraKala Jewellers, Khedbrahma.',
    'canonical' => cj_abs_url('wishlist'),
    'robots' => 'noindex, nofollow',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
<style>
    .wishlist-tab-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 18px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        border: 1.5px solid var(--border-color, #e5e7eb);
        background: var(--surface, #ffffff);
        color: var(--text-secondary, #4b5563);
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .wishlist-tab-pill:hover {
        border-color: #d4af37;
        color: #b45309;
    }
    .wishlist-tab-pill.is-active {
        background: linear-gradient(135deg, #d4af37, #b8972c);
        border-color: #b8972c;
        color: #111827;
        box-shadow: 0 4px 14px rgba(184, 151, 44, 0.22);
    }
    .cat-wish-card {
        position: relative;
        border-radius: 16px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        border: 1px solid #f1f5f9;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        display: flex;
        flex-direction: column;
        aspect-ratio: 1 / 1;
    }
    .cat-wish-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.12);
    }
    .cat-wish-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s ease;
    }
    .cat-wish-card:hover img {
        transform: scale(1.04);
    }
    .cat-wish-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0,0,0,0.72) 0%, rgba(0,0,0,0.15) 50%, transparent 100%);
        opacity: 0;
        transition: opacity 0.25s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 12px;
    }
    .cat-wish-card:hover .cat-wish-overlay {
        opacity: 1;
    }
    @media (hover: none) {
        .cat-wish-overlay {
            opacity: 1;
            background: linear-gradient(to top, rgba(0,0,0,0.65) 0%, transparent 60%);
        }
    }
</style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">
<?php $activePage = 'wishlist'; include 'includes/header.php'; ?>

<main id="main-content" class="flex-grow container mx-auto px-4 sm:px-6 py-8 sm:py-12 max-w-6xl">
    <div class="text-center max-w-xl mx-auto mb-6">
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold font-serif text-gray-900 mb-2">My Wishlist</h1>
        <p class="text-xs sm:text-sm text-gray-500">Your favourite pieces and bespoke designs saved in one place.</p>
    </div>

    <!-- Wishlist Navigation Tabs -->
    <div class="flex items-center justify-center gap-2 sm:gap-3 mb-8" role="tablist" aria-label="Wishlist sections">
        <button type="button" class="wishlist-tab-pill <?= $activeTab === 'shop' ? 'is-active' : '' ?>" id="tabBtnShop" role="tab" aria-selected="<?= $activeTab === 'shop' ? 'true' : 'false' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            <span>Shop Products</span>
            <span id="shopWishlistCountBadge" class="ml-1 px-2 py-0.5 rounded-full text-[11px] bg-black/10 font-bold">0</span>
        </button>
        <button type="button" class="wishlist-tab-pill <?= $activeTab === 'catalogue' ? 'is-active' : '' ?>" id="tabBtnCatalogue" role="tab" aria-selected="<?= $activeTab === 'catalogue' ? 'true' : 'false' ?>">
            <svg class="w-4 h-4 fill-current text-rose-500" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            <span>Catalogue Images</span>
            <span id="catWishlistCountBadge" class="ml-1 px-2 py-0.5 rounded-full text-[11px] bg-black/10 font-bold"><?= count($catalogueWishlist) ?></span>
        </button>
    </div>

    <!-- TAB 1: Shop Products View -->
    <section id="tabContentShop" class="<?= $activeTab === 'shop' ? '' : 'hidden' ?>" role="tabpanel" aria-labelledby="tabBtnShop">
        <div id="wishlist-empty" class="hidden text-center py-16 bg-white rounded-2xl border border-gray-100 p-8 max-w-md mx-auto shadow-sm">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            <h3 class="text-base font-bold text-gray-800 mb-1">Your shop wishlist is empty</h3>
            <p class="text-xs text-gray-400 mb-4">Saved ready-to-order pieces will appear here.</p>
            <a href="gallery" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs shadow-sm transition-all hover:scale-105">Browse Shop</a>
        </div>
        <div id="wishlist-grid" class="related-grid"></div>
    </section>

    <!-- TAB 2: Catalogue Images View -->
    <section id="tabContentCatalogue" class="<?= $activeTab === 'catalogue' ? '' : 'hidden' ?>" role="tabpanel" aria-labelledby="tabBtnCatalogue">
        <?php if (!$customer): ?>
            <div class="text-center py-16 bg-white rounded-2xl border border-gray-100 p-8 max-w-md mx-auto shadow-sm">
                <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-1">Save &amp; View Catalogue Images</h3>
                <p class="text-xs text-gray-500 mb-6 leading-relaxed">Sign in or create an account to view and sync your favourite custom jewellery designs across all your devices.</p>
                <div class="flex items-center justify-center gap-3">
                    <a href="account?tab=register&redirect=wishlist?tab=catalogue" class="px-5 py-2.5 rounded-full bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs shadow-sm transition-all hover:scale-105">Create Account</a>
                    <a href="account?tab=login&redirect=wishlist?tab=catalogue" class="px-5 py-2.5 rounded-full border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 font-semibold text-xs transition-all">Sign In</a>
                </div>
            </div>
        <?php elseif (empty($catalogueWishlist)): ?>
            <div id="catWishlistEmpty" class="text-center py-16 bg-white rounded-2xl border border-gray-100 p-8 max-w-md mx-auto shadow-sm">
                <svg class="w-12 h-12 text-rose-200 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                <h3 class="text-base font-bold text-gray-800 mb-1">No saved catalogue images yet</h3>
                <p class="text-xs text-gray-400 mb-5">Tap the heart icon on any design in our catalogue to save it here.</p>
                <a href="Catalogue" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs shadow-sm transition-all hover:scale-105">Explore Catalogue</a>
            </div>
            <div id="catWishlistGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6 hidden"></div>
        <?php else: ?>
            <div id="catWishlistGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-6">
                <?php foreach ($catalogueWishlist as $item): 
                    $cId = (int)$item['id'];
                    $thumbUrl = htmlspecialchars(cj_img_url($item['image_filename'], 600, 'catalogue'));
                    $directUrl = cj_abs_url('Catalogue?id=' . $cId);
                    $waInquireText = 'Hello ChandraKala Jewellers, I am inquiring about my saved Wishlist Design #' . $cId . ":\n" . $directUrl;
                    $waInquireLink = 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($waInquireText);
                ?>
                <div class="cat-wish-card group" id="catWishCard-<?= $cId ?>">
                    <img src="<?= $thumbUrl ?>" alt="Custom Design #<?= $cId ?>" loading="lazy" width="500" height="500">
                    <div class="cat-wish-overlay">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-mono font-bold text-amber-200 drop-shadow">#<?= $cId ?></span>
                            <button type="button" class="p-1.5 rounded-full bg-black/50 hover:bg-red-600 text-white transition-all cursor-pointer remove-cat-wish-btn" data-id="<?= $cId ?>" title="Remove from wishlist">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="flex items-center gap-1.5 mt-auto">
                            <a href="Catalogue?id=<?= $cId ?>" class="flex-1 py-1.5 px-2 rounded-lg bg-white/90 hover:bg-white text-gray-900 font-semibold text-[11px] sm:text-xs text-center transition-all shadow-sm">
                                View
                            </a>
                            <a href="<?= $waInquireLink ?>" target="_blank" rel="noopener" class="p-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white transition-all" title="Inquire on WhatsApp">
                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.044-1.076-.07-.249-.074-.572-.187-1.042-.39-1.996-.864-3.298-2.884-3.398-3.018-.099-.134-.813-1.082-.813-2.064 0-.982.515-1.464.7-1.663.185-.2.404-.25.539-.25.134 0 .269.002.387.008.125.006.291-.047.456.349.168.404.572 1.396.622 1.498.05.101.083.22.016.353-.067.133-.101.217-.201.334-.1.118-.21.263-.3.353-.1.1-.205.209-.089.408.117.199.519.855 1.113 1.385.767.683 1.413.896 1.614.996.2.1.317.084.434-.05.117-.134.502-.584.636-.784.134-.2.269-.167.453-.1.184.067 1.17.552 1.372.653.202.101.336.151.386.235.05.084.05.488-.094.893z"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="catWishlistEmpty" class="hidden text-center py-16 bg-white rounded-2xl border border-gray-100 p-8 max-w-md mx-auto shadow-sm">
                <svg class="w-12 h-12 text-rose-200 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                <h3 class="text-base font-bold text-gray-800 mb-1">No saved catalogue images yet</h3>
                <p class="text-xs text-gray-400 mb-5">Tap the heart icon on any design in our catalogue to save it here.</p>
                <a href="Catalogue" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs shadow-sm transition-all hover:scale-105">Explore Catalogue</a>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    // 1. Tab Switching
    var tabShop = document.getElementById('tabBtnShop');
    var tabCatalogue = document.getElementById('tabBtnCatalogue');
    var contentShop = document.getElementById('tabContentShop');
    var contentCatalogue = document.getElementById('tabContentCatalogue');

    function switchTab(target) {
        if (target === 'catalogue') {
            tabCatalogue.classList.add('is-active');
            tabCatalogue.setAttribute('aria-selected', 'true');
            tabShop.classList.remove('is-active');
            tabShop.setAttribute('aria-selected', 'false');
            contentCatalogue.classList.remove('hidden');
            contentShop.classList.add('hidden');
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', 'wishlist?tab=catalogue');
            }
        } else {
            tabShop.classList.add('is-active');
            tabShop.setAttribute('aria-selected', 'true');
            tabCatalogue.classList.remove('is-active');
            tabCatalogue.setAttribute('aria-selected', 'false');
            contentShop.classList.remove('hidden');
            contentCatalogue.classList.add('hidden');
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', 'wishlist?tab=shop');
            }
        }
    }

    if (tabShop && tabCatalogue) {
        tabShop.addEventListener('click', function () { switchTab('shop'); });
        tabCatalogue.addEventListener('click', function () { switchTab('catalogue'); });
    }

    // 2. Load Shop Products Wishlist from localStorage
    function getMetalDisplay(metal) {
        var map = { 'gold': 'Gold', 'silver': 'Silver', 'silver_925': '925 Silver' };
        return map[metal] || 'Unknown';
    }

    function escapeHtml(text) {
        if (!text) return '';
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    var ids = [];
    try { ids = JSON.parse(localStorage.getItem('wishlist') || '[]'); } catch (e) { ids = []; }
    var shopBadge = document.getElementById('shopWishlistCountBadge');
    if (shopBadge) shopBadge.textContent = String(ids.length);

    var empty = document.getElementById('wishlist-empty');
    var grid = document.getElementById('wishlist-grid');
    if (!ids.length) {
        if (empty) empty.classList.remove('hidden');
    } else {
        try {
            var res = await fetch('get_cart_items.php?ids=' + ids.join(','));
            var data = await res.json();
            if (!data.length) {
                if (empty) empty.classList.remove('hidden');
            } else {
                data.forEach(function (item) {
                    var a = document.createElement('div');
                    a.className = 'product';
                    
                    var metalDisplay = getMetalDisplay(item.metal_type);
                    var priceFormatted = Math.round(item.final_price).toLocaleString('en-IN');
                    
                    var html = '';
                    html += '<a href="product?id=' + item.id + '" class="product-media-link">';
                    if (item.is_sold_out) {
                        html += '  <span class="sold-out-badge">Sold Out</span>';
                    }
                    html += '  <img src="' + (item.thumb || ('uploads/' + item.image)) + '" alt="" width="400" height="400" loading="lazy" style="width:100%;height:160px;object-fit:cover;border-radius:10px;">';
                    html += '</a>';
                    html += '<h3 style="font-size:14px;margin-top:8px;font-weight:600;">' + escapeHtml(item.name) + '</h3>';
                    html += '<p style="font-size:12px;color:var(--text-secondary);">' + metalDisplay + ' &bull; ' + item.weight + ' g</p>';
                    html += '<div class="price-container">';
                    html += '  <div class="price-val" style="font-size:16px;font-weight:700;color:var(--primary-gold-dark);">₹' + priceFormatted + '</div>';
                    html += '</div>';
                    html += '<div class="product-actions" style="margin-top:8px;">';
                    html += '  <button type="button" class="btn btn-secondary remove-btn" style="width:100%;padding:8px;font-size:12px;">Remove</button>';
                    html += '</div>';
                    
                    a.innerHTML = html;
                    
                    a.querySelector('.remove-btn').addEventListener('click', function () {
                        if (typeof toggleWishlist === 'function') toggleWishlist(item.id, item.name);
                        a.remove();
                        var cur = [];
                        try { cur = JSON.parse(localStorage.getItem('wishlist') || '[]'); } catch (e) {}
                        if (shopBadge) shopBadge.textContent = String(cur.length);
                        if (!grid.children.length && empty) empty.classList.remove('hidden');
                    });
                    grid.appendChild(a);
                });
            }
        } catch (err) {
            if (empty) empty.classList.remove('hidden');
        }
    }

    // 3. Remove from Catalogue Wishlist
    var catGrid = document.getElementById('catWishlistGrid');
    var catEmpty = document.getElementById('catWishlistEmpty');
    var catBadge = document.getElementById('catWishlistCountBadge');

    document.querySelectorAll('.remove-cat-wish-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cid = parseInt(btn.getAttribute('data-id'), 10);
            if (!cid) return;

            var card = document.getElementById('catWishCard-' + cid);
            if (card) {
                card.style.opacity = '0.3';
                card.style.pointerEvents = 'none';
            }

            fetch('api/catalogue-wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ action: 'remove', id: cid })
            }).then(function (res) { return res.json(); }).then(function (res) {
                if (card) card.remove();
                if (catGrid) {
                    var remaining = catGrid.querySelectorAll('.cat-wish-card').length;
                    if (catBadge) catBadge.textContent = String(remaining);
                    if (remaining === 0 && catEmpty) {
                        catEmpty.classList.remove('hidden');
                        catGrid.classList.add('hidden');
                    }
                }
            }).catch(function () {
                if (card) {
                    card.style.opacity = '1';
                    card.style.pointerEvents = 'auto';
                }
            });
        });
    });
});
</script>
</body>
</html>
