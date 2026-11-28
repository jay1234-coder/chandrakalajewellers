<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-section">
            <h3>ChandraKala Jewellers</h3>
            <p>Family-owned gold &amp; silver jewellery house in Gujarat. 100% BIS 916 hallmarked gold, pure silver, live IST rates &amp; insured delivery across all 33 districts of Gujarat.</p>
        </div>
        <div class="footer-section">
            <h3>Quick Links</h3>
            <ul>
                <li><a href="index1">Home</a></li>
                <li><a href="gallery">Shop</a></li>
                <li><a href="Catalogue">Design Catalogue</a></li>
                <li><a href="reels">Jewellery Reels</a></li>
                <li><a href="blog">Blog</a></li>
                <li><a href="jewellers-in-khedbrahma">Jewellers in Khedbrahma</a></li>
                <li><a href="gold-rate-khedbrahma">Gold Rate Khedbrahma</a></li>
                <li><a href="top-jewellers-in-gujarat">Top Jeweller in Gujarat</a></li>
                <li><a href="mcx">MCX Gold Calculator</a></li>
                <li><a href="follow-us">Follow Us &amp; Reviews</a></li>
                <?php if (function_exists('cj_feature') && cj_feature('feat_customer_accounts')): ?>
                <li><a href="account">My account</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="footer-section">
            <h3>Information</h3>
            <ul>
                <li><a href="about">About Us</a></li>
                <li><a href="contact">Contact Us</a></li>
                <li><a href="follow-us">Instagram &amp; Google</a></li>
                <li><a href="blog">Blog &amp; Care Tips</a></li>
                <li><a href="gallery?metal_type=gold">916 Gold Jewellery</a></li>
                <li><a href="gallery?metal_type=silver">Silver Jewellery</a></li>
            </ul>
        </div>
        <div class="footer-section">
            <h3>Visit Us</h3>
            <?php
            $cj_f = function_exists('cj_business_info') ? cj_business_info() : [];
            $cj_ig = $cj_f['instagram'] ?? 'https://www.instagram.com/chandra.kalajewellers?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==';
            $cj_review = $cj_f['google_review'] ?? 'https://g.page/r/CWqeb0I3tG0hEAI/review';
            $cj_maps_dir = $cj_f['maps_directions'] ?? 'https://www.google.com/maps/dir/?api=1&destination=Chandrakala+jewellers,+22JW%2BHG9,+Khedbrahma,+Gujarat+383255&destination_place_id=0x395d0b1a27d5a3df:0x216db437426f9e6a';
            ?>
            <p>Opp. Bhoomi Complex, Civil Road,<br>22JW+HG9, Khedbrahma-383255, Gujarat</p>
            <p style="margin-top: 12px;">
                <a href="<?= htmlspecialchars(function_exists('cj_shop_tel_href') ? cj_shop_tel_href() : 'tel:+919427080359') ?>"><?= htmlspecialchars(function_exists('cj_shop_phone_display') ? cj_shop_phone_display() : '+91 9427080359') ?></a>
            </p>
            <p style="margin-top: 10px;">
                <a href="<?= htmlspecialchars($cj_maps_dir) ?>" target="_blank" rel="noopener">Google Maps Directions</a>
            </p>
            <p style="margin-top: 10px;">
                <a href="<?= htmlspecialchars($cj_ig) ?>" target="_blank" rel="noopener me">Instagram</a>
                ·
                <a href="<?= htmlspecialchars($cj_review) ?>" target="_blank" rel="noopener">Google reviews</a>
            </p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> ChandraKala Jewellers · <a href="https://chandrakalajewellers.in/index1">chandrakalajewellers.in</a></p>
        <p>Designed with elegance &amp; passion.</p>
    </div>
</footer>
<?php include __DIR__ . '/bottom-nav.php'; ?>
<?php include __DIR__ . '/account-modal.php'; ?>
<?php
$cjShopFlags = [
    'wa' => function_exists('cj_shop_whatsapp_digits') ? cj_shop_whatsapp_digits() : '919427080359',
    'askAccount' => function_exists('cj_feature') && cj_feature('feat_customer_accounts') && cj_feature('feat_account_prompt') && !(function_exists('cj_customer_session') && cj_customer_session()),
    'loggedIn' => function_exists('cj_customer_session') && (bool) cj_customer_session(),
    'featWishlist' => function_exists('cj_feature') && cj_feature('feat_wishlist'),
    'featAbandoned' => function_exists('cj_feature') && cj_feature('feat_abandoned_banner'),
];
?>
<script>window.CJ_SHOP = <?= json_encode($cjShopFlags) ?>;</script>
<script src="js/lazy.js" defer></script>
<script src="js/main.js?v=10" defer></script>
<script src="js/visitor-analytics.js?v=10" defer></script>
<script src="js/pwa.js?v=12" defer></script>
<?php if (in_array($activePage ?? '', ['home', 'reels', 'showcase'], true)): ?>
<script src="js/reels-prefetch.js?v=2" defer></script>
<?php endif; ?>
