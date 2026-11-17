<?php
/**
 * Follow Us — Instagram, Google reviews, and shareable QR codes
 */
$activePage = 'follow';
require_once 'includes/seo.php';
$b = cj_business_info();
$siteUrl = 'https://chandrakalajewellers.in';
$instagramUrl = $b['instagram'];
$instagramHandle = '@chandra.kalajewellers';
/* Canonical leave-a-review short link */
$googleReviewUrl = $b['google_review'] ?? 'https://g.page/r/CWqeb0I3tG0hEAI/review';
/* Knowledge Graph / Google Business panel */
$googleListingUrl = $b['google_listing'] ?? 'https://www.google.co.in/search?kgmid=/g/11q360mjpq&q=Chandrakala+jewellers';
$mapsUrl = $b['maps_share'];

$seo = cj_merge_seo([
    'title' => 'Follow & Reviews | ChandraKala Jewellers Instagram & Google',
    'description' => 'Follow ChandraKala Jewellers on Instagram @chandra.kalajewellers, leave a Google review, and scan QR codes for our website — gold & silver in Khedbrahma.',
    'keywords' => 'ChandraKala Jewellers Instagram, chandra.kalajewellers, Chandrakala Jewellers Google reviews, leave a review ChandraKala, jewellery shop Khedbrahma Google, website QR code ChandraKala',
    'canonical' => cj_abs_url('follow-us'),
    'og_type' => 'website',
    'schema' => [
        cj_local_business_schema(),
        cj_website_schema(),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Follow Us', 'url' => cj_abs_url('follow-us')],
        ]),
        cj_faq_schema([
            [
                'q' => 'What is ChandraKala Jewellers Instagram?',
                'a' => 'Follow us at @chandra.kalajewellers on Instagram for new jewellery, store updates, and gold & silver designs from Khedbrahma.',
            ],
            [
                'q' => 'How do I leave a Google review for ChandraKala Jewellers?',
                'a' => 'Use our Google review link https://g.page/r/CWqeb0I3tG0hEAI/review or scan the Google review QR on this page. You can also open our Google Business listing for photos, hours, and directions in Khedbrahma.',
            ],
            [
                'q' => 'How do I open the ChandraKala Jewellers website with a QR code?',
                'a' => 'Scan the website QR on this page — it opens https://chandrakalajewellers.in. Instagram and Google review QRs are also available.',
            ],
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

<main class="container follow-us-page">
    <div class="page-hero reveal">
        <img src="images/logo.png" alt="ChandraKala Jewellers" class="follow-brand-mark" width="72" height="72" decoding="async">
        <h1>Follow ChandraKala Jewellers</h1>
        <p>Instagram, Google reviews, and QR codes for our website — from Civil Road, Khedbrahma.</p>
    </div>

    <section class="follow-showcase reveal" aria-labelledby="ig-heading">
        <div class="follow-showcase-visual" aria-hidden="true">
            <div class="follow-ig-glow"></div>
            <div class="follow-ig-frame">
                <svg class="follow-ig-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <rect x="2.5" y="2.5" width="19" height="19" rx="5.5"/>
                    <circle cx="12" cy="12" r="4.2"/>
                    <circle cx="17.4" cy="6.6" r="1.1" fill="currentColor" stroke="none"/>
                </svg>
            </div>
        </div>
        <div class="follow-showcase-copy">
            <p class="follow-eyebrow">Instagram</p>
            <h2 id="ig-heading"><?= htmlspecialchars($instagramHandle) ?></h2>
            <p>See hallmark jewellery, festive looks, and behind-the-counter craft from ChandraKala Jewellers. Tap follow, then visit the store or shop online when you are ready.</p>
            <div class="follow-cta-row">
                <a class="btn" href="<?= htmlspecialchars($instagramUrl) ?>" target="_blank" rel="noopener me">Open Instagram</a>
                <a class="btn btn-outline" href="<?= htmlspecialchars($siteUrl) ?>">Visit website</a>
            </div>
        </div>
    </section>

    <section class="follow-google reveal reveal-delay-1" aria-labelledby="google-heading">
        <div class="follow-google-card follow-review-cta">
            <div class="follow-google-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="28" height="28" focusable="false">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
            </div>
            <div class="follow-google-copy">
                <p class="follow-eyebrow">Google · Leave a review</p>
                <h2 id="google-heading">Rate ChandraKala Jewellers</h2>
                <p>Share your visit on Google — it helps neighbours in Khedbrahma find us. Tap the button or scan the QR; Google’s review page opens in a new tab (embeds are blocked by Google).</p>
                <div class="follow-review-layout">
                    <div class="follow-cta-row">
                        <a class="btn" href="<?= htmlspecialchars($googleReviewUrl) ?>" target="_blank" rel="noopener">Leave a Google review</a>
                        <a class="btn btn-outline" href="<?= htmlspecialchars($googleListingUrl) ?>" target="_blank" rel="noopener">View listing</a>
                        <a class="btn btn-outline" href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank" rel="noopener">Open in Maps</a>
                    </div>
                    <div class="follow-review-qr-wrap">
                        <div id="qr-google-review-hero" class="follow-qr-canvas follow-qr-canvas-review" role="img" aria-label="QR code to leave a Google review for ChandraKala Jewellers"></div>
                        <p class="follow-qr-url">Scan to review</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="follow-qr-section reveal reveal-delay-2" aria-labelledby="qr-heading">
        <h2 id="qr-heading" class="section-heading">Scan to connect</h2>
        <p class="section-sub">Website QR is primary — Instagram and Google review codes sit beside it for sharing in-store or in print.</p>

        <div class="follow-qr-download" role="group" aria-label="Download printable QR sheet">
            <p class="follow-qr-download-label">Download QR</p>
            <p class="follow-qr-download-hint">Landscape A4 PDF with all three codes — for colour or black &amp; white shop print.</p>
            <div class="follow-cta-row follow-qr-download-actions">
                <button type="button" class="btn" data-qr-pdf="color">Download QR (Colour)</button>
                <button type="button" class="btn btn-outline" data-qr-pdf="bw">Download QR (B&amp;W)</button>
            </div>
        </div>

        <div class="follow-qr-grid follow-qr-grid-3">
            <article class="follow-qr-card follow-qr-card-primary">
                <span class="follow-qr-badge">Primary</span>
                <h3>Website</h3>
                <p class="follow-qr-url"><?= htmlspecialchars($siteUrl) ?></p>
                <div id="qr-website" class="follow-qr-canvas" role="img" aria-label="QR code for ChandraKala Jewellers website"></div>
                <a class="btn" href="<?= htmlspecialchars($siteUrl) ?>">Open website</a>
            </article>
            <article class="follow-qr-card">
                <h3>Instagram</h3>
                <p class="follow-qr-url"><?= htmlspecialchars($instagramHandle) ?></p>
                <div id="qr-instagram" class="follow-qr-canvas" role="img" aria-label="QR code for ChandraKala Jewellers Instagram"></div>
                <a class="btn btn-outline" href="<?= htmlspecialchars($instagramUrl) ?>" target="_blank" rel="noopener me">Open Instagram</a>
            </article>
            <article class="follow-qr-card">
                <h3>Google review</h3>
                <p class="follow-qr-url">Leave a review</p>
                <div id="qr-google" class="follow-qr-canvas" role="img" aria-label="QR code for ChandraKala Jewellers Google review"></div>
                <a class="btn btn-outline" href="<?= htmlspecialchars($googleReviewUrl) ?>" target="_blank" rel="noopener">Leave a review</a>
            </article>
        </div>
    </section>

    <section class="follow-more reveal">
        <h2 class="section-heading">Explore more</h2>
        <p class="section-sub">Shop jewellery, check today’s gold rate context, or plan a visit to Civil Road.</p>
        <div class="follow-cta-row follow-cta-row-center">
            <a class="btn" href="gallery">Shop jewellery</a>
            <a class="btn btn-secondary" href="contact">Visit &amp; contact</a>
            <a class="btn btn-whatsapp" href="<?= htmlspecialchars($b['whatsapp']) ?>" target="_blank" rel="noopener">WhatsApp</a>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
<script>
window.CJ_FOLLOW_QR = {
    brandName: <?= json_encode($b['name'], JSON_UNESCAPED_UNICODE) ?>,
    tagline: 'Gold & silver jewellery · Civil Road, Khedbrahma',
    siteUrl: <?= json_encode($siteUrl, JSON_UNESCAPED_SLASHES) ?>,
    instagramUrl: <?= json_encode($instagramUrl, JSON_UNESCAPED_SLASHES) ?>,
    instagramHandle: <?= json_encode($instagramHandle, JSON_UNESCAPED_UNICODE) ?>,
    googleReviewUrl: <?= json_encode($googleReviewUrl, JSON_UNESCAPED_SLASHES) ?>,
    phoneDisplay: <?= json_encode($b['phone_display'], JSON_UNESCAPED_UNICODE) ?>,
    addressLine: <?= json_encode($b['street'] . ', ' . $b['locality'] . ', ' . $b['region'] . ' ' . $b['postal'], JSON_UNESCAPED_UNICODE) ?>,
    logoUrl: 'images/logo.png'
};
</script>
<script src="js/vendor/qrcode.min.js" defer></script>
<script src="js/vendor/jspdf.umd.min.js" defer></script>
<script src="js/follow-us-qr-pdf.js" defer></script>
<script>
(function () {
    var siteUrl = window.CJ_FOLLOW_QR.siteUrl;
    var igUrl = window.CJ_FOLLOW_QR.instagramUrl;
    var googleReviewUrl = window.CJ_FOLLOW_QR.googleReviewUrl;

    function paint(id, text, size) {
        var el = document.getElementById(id);
        if (!el || typeof QRCode === 'undefined') return;
        el.innerHTML = '';
        new QRCode(el, {
            text: text,
            width: size,
            height: size,
            colorDark: '#111827',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
    }

    function run() {
        paint('qr-website', siteUrl, 184);
        paint('qr-instagram', igUrl, 156);
        paint('qr-google', googleReviewUrl, 156);
        paint('qr-google-review-hero', googleReviewUrl, 148);
    }

    function whenReady(fn) {
        if (typeof QRCode !== 'undefined') {
            fn();
            return;
        }
        var s = document.querySelector('script[src*="qrcode"]');
        if (s) {
            s.addEventListener('load', fn);
            setTimeout(function () {
                if (typeof QRCode !== 'undefined') fn();
            }, 400);
        } else {
            setTimeout(fn, 80);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { whenReady(run); });
    } else {
        whenReady(run);
    }
})();
</script>
</body>
</html>
