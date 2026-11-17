<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/img-helper.php';
require_once __DIR__ . '/includes/seo.php';

if (function_exists('cj_feature') && !cj_feature('feat_wishlist')) {
    header('Location: gallery');
    exit;
}

$seo = cj_merge_seo([
    'title' => 'Wishlist | ChandraKala Jewellers',
    'description' => 'Your saved jewellery pieces at ChandraKala Jewellers, Khedbrahma.',
    'canonical' => cj_abs_url('wishlist'),
    'robots' => 'noindex, nofollow',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
</head>
<body>
<?php $activePage = 'wishlist'; include 'includes/header.php'; ?>
<main id="main-content" class="container page-enter" style="padding:24px 16px 80px;">
    <h1 class="product-title" style="font-size:28px;">Wishlist</h1>
    <p class="text-sm text-gray-500" style="margin:8px 0 24px;">Saved on this device. Add to cart when you are ready.</p>
    <div id="wishlist-empty" class="hidden" style="text-align:center;padding:48px 0;">
        <p>No saved pieces yet.</p>
        <a href="gallery" class="btn" style="margin-top:12px;">Browse shop</a>
    </div>
    <div id="wishlist-grid" class="related-grid"></div>
</main>
<?php include 'includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    function getMetalDisplay(metal) {
        var map = {
            'gold': 'Gold',
            'silver': 'Silver',
            'silver_925': '925 Silver'
        };
        return map[metal] || 'Unknown';
    }

    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    var ids = [];
    try { ids = JSON.parse(localStorage.getItem('wishlist') || '[]'); } catch (e) { ids = []; }
    var empty = document.getElementById('wishlist-empty');
    var grid = document.getElementById('wishlist-grid');
    if (!ids.length) {
        empty.classList.remove('hidden');
        return;
    }
    
    empty.classList.add('hidden'); // Ensure empty state is hidden while loading
    
    try {
        var res = await fetch('get_cart_items.php?ids=' + ids.join(','));
        var data = await res.json();
        if (!data.length) {
            empty.classList.remove('hidden');
            return;
        }
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
                if (!grid.children.length) empty.classList.remove('hidden');
            });
            grid.appendChild(a);
        });
    } catch (err) {
        empty.classList.remove('hidden');
    }
});
</script>
</body>
</html>
