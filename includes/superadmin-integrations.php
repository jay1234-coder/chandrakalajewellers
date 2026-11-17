<?php
/**
 * Super Admin → Integrations tab body.
 * Expects $conn, $msg, $_SESSION csrf. Optional $pendingReviews, $abandoned.
 */
$s = cj_settings_all($conn);
$rzpOn = function_exists('cj_razorpay_keys_ready') ? cj_razorpay_keys_ready() : (function_exists('cj_razorpay_configured') && cj_razorpay_configured());
$mask = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $len = strlen($value);
    if ($len <= 8) {
        return str_repeat('•', $len);
    }
    return substr($value, 0, 4) . str_repeat('•', min(18, $len - 8)) . substr($value, -4);
};
?>
<section class="sa-panel mb-6">
    <h2 class="font-serif text-lg font-bold text-gray-900 mb-1">Integrations</h2>
    <p class="text-xs text-gray-500 mb-4">Save keys here instead of editing files. Leave Razorpay blank to keep WhatsApp / pay-later checkout.</p>
    <?php if ($rzpOn): ?>
        <p class="text-sm text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-2 mb-4">Razorpay is connected. Customers can pay online at checkout.</p>
    <?php else: ?>
        <p class="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 mb-4">Razorpay is off. Checkout uses WhatsApp / pay-at-shop until you save a Key ID and Key Secret.</p>
    <?php endif; ?>

    <form method="post" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <fieldset class="border border-amber-100 rounded-xl p-4">
            <legend class="text-sm font-bold text-gray-900 px-1">Razorpay</legend>
            <p class="text-[11px] text-gray-500 mb-3">Dashboard → API Keys. Webhook URL: <code class="bg-gray-50 px-1 rounded">https://chandrakalajewellers.in/webhook-razorpay</code> (event: payment.captured)</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2">
                    <label class="text-xs uppercase text-gray-500 font-semibold">Key ID</label>
                    <input name="razorpay_key_id" value="<?= htmlspecialchars($s['razorpay_key_id']) ?>" class="sa-input w-full mt-1" placeholder="rzp_live_… or rzp_test_…">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">Key Secret</label>
                    <input name="razorpay_key_secret" type="password" autocomplete="new-password" class="sa-input w-full mt-1" placeholder="<?= $s['razorpay_key_secret'] !== '' ? $mask($s['razorpay_key_secret']) . ' — leave blank to keep' : 'Paste secret' ?>">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">Webhook secret</label>
                    <input name="razorpay_webhook_secret" type="password" autocomplete="new-password" class="sa-input w-full mt-1" placeholder="<?= $s['razorpay_webhook_secret'] !== '' ? $mask($s['razorpay_webhook_secret']) . ' — leave blank to keep' : 'Optional backup verify' ?>">
                </div>
            </div>
            <label class="flex items-center gap-2 mt-3 text-xs text-gray-600">
                <input type="checkbox" name="clear_razorpay" value="1"> Disconnect Razorpay (clear keys, WhatsApp fallback)
            </label>
            <button type="submit" name="test_razorpay" class="sa-btn-secondary mt-3 text-xs">Test Razorpay connection</button>
        </fieldset>

        <fieldset class="border border-gray-100 rounded-xl p-4">
            <legend class="text-sm font-bold text-gray-900 px-1">Shop &amp; WhatsApp</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">WhatsApp number (digits with country code)</label>
                    <input name="whatsapp_number" value="<?= htmlspecialchars($s['whatsapp_number']) ?>" class="sa-input w-full mt-1" placeholder="919427080359">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">Phone shown on site</label>
                    <input name="shop_phone_display" value="<?= htmlspecialchars($s['shop_phone_display']) ?>" class="sa-input w-full mt-1">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">Shop email</label>
                    <input name="shop_email" value="<?= htmlspecialchars($s['shop_email']) ?>" class="sa-input w-full mt-1">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">Instagram URL</label>
                    <input name="instagram_url" value="<?= htmlspecialchars($s['instagram_url']) ?>" class="sa-input w-full mt-1">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs uppercase text-gray-500 font-semibold">Google review URL</label>
                    <input name="google_review_url" value="<?= htmlspecialchars($s['google_review_url']) ?>" class="sa-input w-full mt-1">
                </div>
            </div>
        </fieldset>

        <fieldset class="border border-gray-100 rounded-xl p-4">
            <legend class="text-sm font-bold text-gray-900 px-1">SEO &amp; analytics</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">Google Tag Manager ID</label>
                    <input name="gtm_id" value="<?= htmlspecialchars($s['gtm_id']) ?>" class="sa-input w-full mt-1" placeholder="GTM-…">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">GA4 Measurement ID</label>
                    <input name="ga4_id" value="<?= htmlspecialchars($s['ga4_id']) ?>" class="sa-input w-full mt-1" placeholder="G-…">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">Google Search Console verification</label>
                    <input name="google_site_verification" value="<?= htmlspecialchars($s['google_site_verification']) ?>" class="sa-input w-full mt-1">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">Bing verification</label>
                    <input name="bing_site_verification" value="<?= htmlspecialchars($s['bing_site_verification']) ?>" class="sa-input w-full mt-1">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">Yandex verification</label>
                    <input name="yandex_verification" value="<?= htmlspecialchars($s['yandex_verification']) ?>" class="sa-input w-full mt-1">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">CDN base URL (optional)</label>
                    <input name="cdn_base_url" value="<?= htmlspecialchars($s['cdn_base_url']) ?>" class="sa-input w-full mt-1" placeholder="https://cdn.example.com">
                </div>
            </div>
        </fieldset>

        <fieldset class="border border-gray-100 rounded-xl p-4">
            <legend class="text-sm font-bold text-gray-900 px-1">Email / SMTP (for invoices when enabled)</legend>
            <p class="text-[11px] text-gray-500 mb-3">Leave host blank to use PHP mail(). InfinityFree often blocks outbound SMTP. Invoices stay off until you enable them under Site controls.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">SMTP host</label>
                    <input name="smtp_host" value="<?= htmlspecialchars($s['smtp_host'] ?? '') ?>" class="sa-input w-full mt-1" placeholder="smtp.example.com">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">Port</label>
                    <input name="smtp_port" value="<?= htmlspecialchars($s['smtp_port'] ?? '587') ?>" class="sa-input w-full mt-1" placeholder="587">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">SMTP user</label>
                    <input name="smtp_user" value="<?= htmlspecialchars($s['smtp_user'] ?? '') ?>" class="sa-input w-full mt-1">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">SMTP password</label>
                    <input name="smtp_pass" type="password" autocomplete="new-password" class="sa-input w-full mt-1" placeholder="<?= !empty($s['smtp_pass']) ? 'Saved — leave blank to keep' : 'Optional' ?>">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">From email</label>
                    <input name="smtp_from" value="<?= htmlspecialchars($s['smtp_from'] ?? '') ?>" class="sa-input w-full mt-1" placeholder="shop email if blank">
                </div>
                <div>
                    <label class="text-xs uppercase text-gray-500 font-semibold">Encryption</label>
                    <select name="smtp_secure" class="sa-input w-full mt-1">
                        <option value="tls" <?= (($s['smtp_secure'] ?? 'tls') === 'tls') ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                        <option value="ssl" <?= (($s['smtp_secure'] ?? '') === 'ssl') ? 'selected' : '' ?>>SSL</option>
                        <option value="" <?= (($s['smtp_secure'] ?? '') === '') ? 'selected' : '' ?>>None</option>
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset class="border border-gray-100 rounded-xl p-4">
            <legend class="text-sm font-bold text-gray-900 px-1">Reviews</legend>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="reviews_auto_approve" value="1" <?= $s['reviews_auto_approve'] === '1' ? 'checked' : '' ?>>
                Publish customer reviews immediately (otherwise approve below)
            </label>
        </fieldset>

        <button type="submit" name="save_integrations" class="sa-btn-primary">Save integrations</button>
    </form>
</section>

<section class="sa-panel mb-6">
    <h2 class="font-serif text-lg font-bold text-gray-900 mb-2">Product reviews to approve</h2>
    <?php if (empty($pendingReviews)): ?>
        <p class="text-sm text-gray-500">No pending reviews.</p>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($pendingReviews as $rev): ?>
                <article class="border border-gray-100 rounded-xl p-3 text-sm">
                    <p class="font-semibold"><?= htmlspecialchars($rev['customer_name']) ?> · <?= (int) $rev['rating'] ?>/5 · product #<?= (int) $rev['product_id'] ?></p>
                    <p class="text-gray-600 mt-1"><?= htmlspecialchars($rev['comment']) ?></p>
                    <form method="post" class="flex gap-2 mt-2">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="review_id" value="<?= (int) $rev['id'] ?>">
                        <button name="approve_review" class="sa-btn-primary text-xs">Approve</button>
                        <button name="reject_review" class="sa-btn-secondary text-xs" onclick="return confirm('Delete this review?');">Delete</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="sa-panel mb-6">
    <h2 class="font-serif text-lg font-bold text-gray-900 mb-2">Abandoned carts (browser)</h2>
    <p class="text-xs text-gray-500 mb-3">Shoppers who left items in the cart on this device session. Reserved unpaid orders are also in Store Admin → Orders.</p>
    <?php if (empty($abandoned)): ?>
        <p class="text-sm text-gray-500">None recorded yet.</p>
    <?php else: ?>
        <div class="sa-table-wrap overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead><tr><th class="text-left p-2">Last seen</th><th class="text-left p-2">Items</th><th class="text-left p-2">IDs</th></tr></thead>
                <tbody>
                <?php foreach ($abandoned as $ab): ?>
                    <tr>
                        <td class="p-2 whitespace-nowrap"><?= htmlspecialchars($ab['last_seen_at']) ?></td>
                        <td class="p-2"><?= (int) $ab['item_count'] ?></td>
                        <td class="p-2"><?= htmlspecialchars($ab['product_ids']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
