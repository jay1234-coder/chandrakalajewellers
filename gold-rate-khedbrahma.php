<?php
require_once 'config/db.php';
require_once 'includes/pricing.php';
require_once 'includes/seo.php';

$activePage = 'shop';
$b = cj_business_info();
$standard_rates = cj_get_standard_rates($conn);

$show_gold = cj_setting('show_gold_rate', '1', $conn) === '1';
$show_silver = cj_setting('show_silver_rate', '1', $conn) === '1';
$show_silver_925 = cj_setting('show_silver_925_rate', '1', $conn) === '1';
$show_copper = cj_setting('show_copper_rate', '1', $conn) === '1';

$rateUpdatedIst = !empty($standard_rates['updated_at'])
    ? cj_format_ist($standard_rates['updated_at'])
    : cj_format_ist(time());
$todayDateLabel = cj_format_ist(time(), 'd M Y', false);

$gold1g = (float)($standard_rates['gold_rate'] ?? 0);
$gold10g = $gold1g * 10;
$silver1g = (float)($standard_rates['silver_rate'] ?? 0);
$silver1kg = $silver1g * 1000;
$silver925g = (float)($standard_rates['silver_925_rate'] ?? 0);
$copper1kg = (float)($standard_rates['copper_rate'] ?? 0);

$rateSummaryDesc = $gold1g > 0
    ? "Today's gold rate in Khedbrahma ({$todayDateLabel}): ₹" . number_format($gold1g, 0) . "/g (₹" . number_format($gold10g, 0) . "/10g). Live 916 gold & silver rates updated in IST at ChandraKala Jewellers, Civil Road, Sabarkantha."
    : "Today's live 916 gold & silver rates in Khedbrahma at ChandraKala Jewellers. Transparent pricing on Civil Road, Sabarkantha. Call +91 9427080359.";

$seo = cj_merge_seo([
    'title' => "Today's Gold & Silver Rate in Khedbrahma ({$todayDateLabel}) | ChandraKala Jewellers",
    'description' => $rateSummaryDesc,
    'keywords' => 'gold rate today Khedbrahma, gold rate Sabarkantha, 916 gold rate today Gujarat, 22K gold price Khedbrahma, silver rate today Khedbrahma, sona no bhav Khedbrahma, chandi no bhav Sabarkantha, live gold price Idar Himmatnagar Vadali Ambaji, ChandraKala Jewellers live rate',
    'canonical' => cj_abs_url('gold-rate-khedbrahma'),
    'modified_iso' => cj_format_ist_iso($standard_rates['updated_at'] ?? null),
    'schema' => [
        cj_local_business_schema(),
        cj_live_rates_schema($standard_rates, $conn),
        cj_speakable_schema(cj_abs_url('gold-rate-khedbrahma'), ['h1', '.rate-box', '.live-rate-table']),
        cj_breadcrumb_schema([
            ['name' => 'Home', 'url' => cj_abs_url('index1')],
            ['name' => 'Gold Rate Khedbrahma', 'url' => cj_abs_url('gold-rate-khedbrahma')],
        ]),
        cj_faq_schema(cj_default_faqs($standard_rates)),
    ],
]);
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
<style>
.live-rate-table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; background: var(--surface, #fff); border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-sm, 0 2px 10px rgba(0,0,0,0.06)); border: 1px solid var(--border-color, #e5e7eb); }
.live-rate-table th, .live-rate-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid var(--border-color, #e5e7eb); font-size: 0.95rem; }
.live-rate-table th { background: rgba(212, 175, 55, 0.12); font-weight: 700; color: var(--text-primary, #111); }
.live-rate-table td.rate-val { font-weight: 700; color: var(--primary-gold-dark, #926c15); font-size: 1.05rem; }
.rate-ist-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; padding: 6px 14px; border-radius: 999px; background: rgba(212, 175, 55, 0.14); color: var(--text-primary, #1f2937); font-weight: 600; margin-top: 8px; }
</style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<main id="main-content" class="container page-enter">
    <div class="page-hero reveal">
        <h1>Today’s Gold &amp; Silver Rate in Khedbrahma, Sabarkantha</h1>
        <p>Live 916 Gold &amp; Silver showroom board rates at ChandraKala Jewellers — Opp. Bhoomi Complex, Civil Road, Khedbrahma.</p>
        <div class="rate-ist-badge">Last Updated: <?= htmlspecialchars($rateUpdatedIst) ?></div>
    </div>

    <article class="reveal" style="max-width:780px;margin:0 auto;line-height:1.75;">
        <table class="live-rate-table" aria-label="Today's live gold and silver rates in Khedbrahma">
            <thead>
                <tr>
                    <th>Metal</th>
                    <th>Per 1 Gram</th>
                    <th>Per 10 Grams (1 Tola) / 1 KG</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($show_gold && $gold1g > 0): ?>
                <tr>
                    <td><strong>Gold (Board Rate / 916 Reference)</strong></td>
                    <td class="rate-val">₹<?= number_format($gold1g, 2) ?> / g</td>
                    <td class="rate-val">₹<?= number_format($gold10g, 2) ?> / 10g</td>
                </tr>
                <?php endif; ?>
                <?php if ($show_silver && $silver1g > 0): ?>
                <tr>
                    <td><strong>Silver (Pure / Showroom Rate)</strong></td>
                    <td class="rate-val">₹<?= number_format($silver1g, 2) ?> / g</td>
                    <td class="rate-val">₹<?= number_format($silver1kg, 2) ?> / 1 kg</td>
                </tr>
                <?php endif; ?>
                <?php if ($show_silver_925 && $silver925g > 0): ?>
                <tr>
                    <td><strong>925 Sterling Silver</strong></td>
                    <td class="rate-val">₹<?= number_format($silver925g, 2) ?> / g</td>
                    <td class="rate-val">₹<?= number_format($silver925g * 10, 2) ?> / 10g</td>
                </tr>
                <?php endif; ?>
                <?php if ($show_copper && $copper1kg > 0): ?>
                <tr>
                    <td><strong>Copper 999 Fine</strong></td>
                    <td class="rate-val">—</td>
                    <td class="rate-val">₹<?= number_format($copper1kg, 2) ?> / 1 kg</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p>Looking for <strong>today’s gold rate in Khedbrahma</strong>, <strong>Sabarkantha</strong>, <strong>Idar</strong>, <strong>Himmatnagar</strong>, <strong>Vadali</strong>, or <strong>Ambaji</strong>? <strong>ChandraKala Jewellers</strong> updates board rates directly from our Civil Road showroom so every piece in our online shop reflects live weight-based pricing.</p>
        <p>Browse our <a href="gallery?metal_type=gold">916 gold jewellery in stock</a>, explore <a href="gallery?metal_type=silver">all silver &amp; 925 sterling silver designs</a>, request a custom piece from our <a href="Catalogue">138+ design catalogue</a>, or visit us at <?= htmlspecialchars($b['street']) ?>, Khedbrahma-383255.</p>
        <p style="margin-top:1.25rem;">
            <a class="btn" href="gallery?metal_type=gold">Shop 916 Gold Jewellery</a>
            <a class="btn btn-secondary" href="gallery?metal_type=silver" style="margin-left:8px;">Shop Silver &amp; 925</a>
            <a class="btn btn-secondary" href="index1" style="margin-left:8px;">Home</a>
        </p>
        <p style="margin-top:1.5rem;font-size:0.92rem;color:var(--text-secondary,#666);">*Live metal rates fluctuate with bullion markets. Call or WhatsApp <a href="tel:<?= htmlspecialchars($b['phone']) ?>"><?= htmlspecialchars($b['phone_display']) ?></a> for instant booking confirmation.</p>

        <section style="margin-top:2.5rem;" aria-label="Gold rate FAQs">
            <h2 style="font-family:'Playfair Display',serif;font-size:1.35rem;margin-bottom:1rem;">Frequently Asked Questions — Gold &amp; Silver Rates in Khedbrahma</h2>
            <?php foreach (cj_default_faqs($standard_rates) as $faq): ?>
                <details style="margin-bottom:0.75rem;padding:0.75rem 0;border-bottom:1px solid var(--border-color,#eee);">
                    <summary style="font-weight:600;cursor:pointer;"><?= htmlspecialchars($faq['q']) ?></summary>
                    <p style="margin:0.5rem 0 0;color:var(--text-secondary,#555);"><?= htmlspecialchars($faq['a']) ?></p>
                </details>
            <?php endforeach; ?>
        </section>
    </article>
</main>

<?php include 'includes/footer.php'; ?>
</body>
</html>
