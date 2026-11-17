<?php
$activePage = 'contact';
require_once 'config/db.php';
require_once 'includes/seo.php';
$whatsappNumber = function_exists('cj_shop_whatsapp_digits') ? cj_shop_whatsapp_digits() : '919427080359';
$b = cj_business_info();
$seo = cj_merge_seo([
    'title' => 'Contact ChandraKala Jewellers | Khedbrahma',
    'description' => 'Visit ChandraKala Jewellers at Opp. Bhoomi Complex, Civil Road, Khedbrahma-383255. Call +91 9427080359 or WhatsApp. Open in Google Maps.',
    'keywords' => 'ChandraKala Jewellers contact, jewellers Civil Road Khedbrahma, gold shop near me, silver jewellery store location Gujarat, Bhoomi Complex jewellers',
    'canonical' => cj_abs_url('contact'),
    'og_type' => 'website',
    'schema' => [
        cj_local_business_schema(),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Contact', 'url' => cj_abs_url('contact')],
        ]),
        cj_faq_schema([
            [
                'q' => 'How do I reach ChandraKala Jewellers?',
                'a' => 'We are at Opp. Bhoomi Complex, Civil Road, Khedbrahma-383255. Open our Google Maps listing or call +91 9427080359 for directions.',
            ],
            [
                'q' => 'What is the WhatsApp number for ChandraKala Jewellers?',
                'a' => 'Message us on WhatsApp at +91 9427080359 for jewellery inquiries and orders.',
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

<main class="container">
    <div class="page-hero reveal">
        <h1>Contact ChandraKala Jewellers in Khedbrahma</h1>
        <p>Visit our gold &amp; silver jewellery store, call us, or send a quick inquiry on WhatsApp.</p>
    </div>

    <div class="contact-layout">
        <div class="contact-info-panel reveal">
            <div class="contact-card" style="text-align: left;" itemscope itemtype="https://schema.org/JewelryStore">
                <meta itemprop="name" content="ChandraKala Jewellers">
                <div class="icon-wrap" style="margin: 0 0 14px;">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h3>Store Address</h3>
                <p itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
                    <strong>ChandraKala Jewellers</strong><br>
                    <span itemprop="streetAddress">22JW+HG9, Opp. Bhoomi Complex, Civil Road</span><br>
                    <span itemprop="addressLocality">Khedbrahma</span>-<span itemprop="postalCode">383255</span>,
                    <span itemprop="addressRegion">Gujarat</span>
                </p>
                <meta itemprop="latitude" content="<?= htmlspecialchars((string)$b['latitude']) ?>">
                <meta itemprop="longitude" content="<?= htmlspecialchars((string)$b['longitude']) ?>">
            </div>
            <div class="contact-card" style="text-align: left;">
                <div class="icon-wrap" style="margin: 0 0 14px;">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </div>
                <h3>Phone</h3>
                <p><a href="<?= htmlspecialchars($b['phone'] ? 'tel:' . $b['phone'] : 'tel:+919427080359') ?>" itemprop="telephone"><?= htmlspecialchars($b['phone_display'] ?? '+91 9427080359') ?></a></p>
            </div>
            <div class="contact-card" style="text-align: left;">
                <div class="icon-wrap" style="margin: 0 0 14px;">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <h3>Email</h3>
                <p><a href="mailto:chandrakalajewellers849@gmail.com">chandrakalajewellers849@gmail.com</a></p>
            </div>
            <a href="https://wa.me/<?= $whatsappNumber ?>" class="btn btn-whatsapp" target="_blank" rel="noopener">Chat on WhatsApp</a>
            <a href="<?= htmlspecialchars($b['instagram'] ?? 'https://www.instagram.com/chandra.kalajewellers?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==') ?>" class="btn btn-outline" target="_blank" rel="noopener me">Follow on Instagram</a>
            <a href="follow-us" class="btn btn-outline">Reviews &amp; QR codes</a>
            <a href="<?= htmlspecialchars($b['maps_share']) ?>" class="btn btn-outline" target="_blank" rel="noopener">Open in Google Maps</a>
            <a href="<?= htmlspecialchars($b['maps_directions']) ?>" class="btn" target="_blank" rel="noopener">Get Directions</a>
        </div>

        <div class="contact-form reveal reveal-delay-1">
            <h2>Send an Inquiry</h2>
            <p class="form-hint">Fill in your details and we will open WhatsApp with your message ready to send.</p>
            <form id="contact-inquiry-form">
                <div class="form-group">
                    <label for="inq-name">Your Name</label>
                    <input type="text" id="inq-name" name="name" required placeholder="Full name" autocomplete="name">
                </div>
                <div class="form-group">
                    <label for="inq-phone">Phone Number</label>
                    <input type="tel" id="inq-phone" name="phone" required placeholder="10-digit mobile number" autocomplete="tel" pattern="[0-9+\s\-]{10,15}">
                </div>
                <div class="form-group">
                    <label for="inq-message">Message</label>
                    <textarea id="inq-message" name="message" required placeholder="Tell us what you are looking for..."></textarea>
                </div>
                <button type="submit" class="btn btn-whatsapp">Send via WhatsApp</button>
            </form>
        </div>
    </div>

    <section class="maps-section reveal" aria-labelledby="maps-heading">
        <h2 id="maps-heading" class="section-heading">Find Us on Google Maps</h2>
        <p class="section-sub">ChandraKala Jewellers — 22JW+HG9, Khedbrahma, Gujarat 383255</p>
        <div class="maps-embed-wrap">
            <iframe
                title="ChandraKala Jewellers live location on Google Maps — Khedbrahma"
                src="<?= htmlspecialchars(cj_maps_embed_src()) ?>"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen
            ></iframe>
        </div>
        <div class="back-btn-container" style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center;">
            <a href="<?= htmlspecialchars($b['maps_directions']) ?>" class="btn" target="_blank" rel="noopener">Get Directions</a>
            <a href="<?= htmlspecialchars($b['maps_share']) ?>" class="btn btn-outline" target="_blank" rel="noopener">Open in Google Maps</a>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>

<script>
document.getElementById('contact-inquiry-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var name = document.getElementById('inq-name').value.trim();
    var phone = document.getElementById('inq-phone').value.trim();
    var message = document.getElementById('inq-message').value.trim();
    if (!name || !phone || !message) return;

    var text = encodeURIComponent(
        'Hello ChandraKala Jewellers,\n\n' +
        '*Name:* ' + name + '\n' +
        '*Phone:* ' + phone + '\n\n' +
        '*Message:*\n' + message
    );

    window.open('https://wa.me/<?= $whatsappNumber ?>?text=' + text, '_blank');
});
</script>
</body>
</html>
