<?php
/**
 * Super Admin → Site controls (feature flags + display metrics).
 * Expects $conn and csrf session.
 */
$s = cj_settings_all($conn);
$groups = [
    'Shop display' => [
        'feat_reviews' => 'Reviews on product pages',
        'feat_wishlist' => 'Wishlist (heart + saved list)',
        'feat_related' => 'Related pieces (same metal)',
        'feat_ai_recs' => 'AI-style recommendations (metal, type, price, also-bought)',
        'feat_zoom' => 'Tap-to-zoom on product photos',
        'feat_product_specs' => 'Specifications table on product pages',
        'feat_compare' => 'Select / compare on shop',
        'feat_abandoned_banner' => 'Abandoned-cart reminder banner',
    ],
    'Customer accounts' => [
        'feat_customer_accounts' => 'Allow optional customer login (never forced at checkout)',
        'feat_order_history' => 'Order history on My account',
        'feat_account_prompt' => 'Ask once when adding to wishlist or writing a review',
    ],
    'Checkout & payments' => [
        'feat_online_pay' => 'Online pay (Razorpay) when keys are saved',
        'feat_refunds' => 'Refunds from the admin orders panel',
        'perm_admin_refunds' => 'Store admin may issue refunds (otherwise super admin only)',
    ],
    'GST & invoices (off until you enable)' => [
        'feat_gst' => 'Show GST breakup on checkout, pay, and invoices',
        'gst_inclusive' => 'GST is already included in displayed prices (uncheck to add GST on top)',
        'feat_email_invoices' => 'Email invoices when an order is paid or confirmed (needs customer email + SMTP/mail)',
    ],
];
?>
<section class="sa-panel mb-6">
    <h2 class="font-serif text-lg font-bold text-gray-900 mb-1">Site controls</h2>
    <p class="text-xs text-gray-500 mb-4">Turn features on or off for the live shop. Disabled items are hidden or skipped — guests can still buy without an account.</p>
    <form method="post" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <?php foreach ($groups as $title => $flags): ?>
            <fieldset class="border border-amber-100 rounded-xl p-4">
                <legend class="text-sm font-bold text-gray-900 px-1"><?= htmlspecialchars($title) ?></legend>
                <div class="space-y-2 mt-1">
                    <?php foreach ($flags as $key => $label): ?>
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="<?= htmlspecialchars($key) ?>" value="1" class="mt-1" <?= (($s[$key] ?? '0') === '1') ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endforeach; ?>

        <fieldset class="border border-gray-100 rounded-xl p-4">
            <legend class="text-sm font-bold text-gray-900 px-1">Numbers</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">GST % (used only if GST is enabled)</label>
                    <input name="gst_percent" type="number" min="0" max="28" step="0.1" value="<?= htmlspecialchars($s['gst_percent']) ?>" class="sa-input w-full mt-1">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">Recommendations to show</label>
                    <input name="recs_limit" type="number" min="2" max="12" step="1" value="<?= htmlspecialchars($s['recs_limit']) ?>" class="sa-input w-full mt-1">
                </div>
            </div>
        </fieldset>

        <button type="submit" name="save_controls" class="sa-btn-primary">Save site controls</button>
    </form>
</section>
