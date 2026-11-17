<?php
/**
 * Lightweight checks (no PHPUnit). CLI: php tests/run.php
 * Superadmin can also open /tests/run.php while logged in.
 */
$root = dirname(__DIR__);
require_once $root . '/includes/pricing.php';
require_once $root . '/includes/img-helper.php';
require_once $root . '/includes/payments.php';
require_once $root . '/includes/settings.php';
require_once $root . '/includes/recommend.php';
require_once $root . '/includes/orders.php';

$fromWeb = PHP_SAPI !== 'cli';
if ($fromWeb) {
    require_once $root . '/config/db.php';
    require_once $root . '/includes/auth.php';
    cj_auth_bootstrap($conn);
    if (empty($_SESSION['loggedin']) || ($_SESSION['role'] ?? '') !== 'superadmin') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$failed = 0;
$passed = 0;
function cj_assert(string $name, bool $ok, string $detail = ''): void
{
    global $failed, $passed;
    if ($ok) {
        $passed++;
        echo "PASS  {$name}\n";
    } else {
        $failed++;
        echo "FAIL  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

$rates = [
    'gold_rate' => 1000,
    'silver_rate' => 100,
    'silver_925_rate' => 80,
    'making_charges' => 10,
    'apply_making_to_silver' => 0,
    'rate_formula' => [],
];
cj_assert('gold price per gram + making', abs(cj_calculate_price('gold', 2, $rates) - 2200) < 0.01);
cj_assert('silver without making', abs(cj_calculate_price('silver', 10, $rates) - 1000) < 0.01);
$rates['apply_making_to_silver'] = 1;
cj_assert('silver with making', abs(cj_calculate_price('silver', 10, $rates) - 1100) < 0.01);
cj_assert('empty weight is zero', cj_calculate_price('gold', 0, $rates) === 0.0);

cj_assert('formula add', abs(cj_apply_rate_formula(10, [['op' => '+', 'value' => 2]]) - 12) < 0.01);
cj_assert('formula sub', abs(cj_apply_rate_formula(10, [['op' => '-', 'value' => 2]]) - 8) < 0.01);
cj_assert('formula mul', abs(cj_apply_rate_formula(10, [['op' => '*', 'value' => 2]]) - 20) < 0.01);
cj_assert('formula div', abs(cj_apply_rate_formula(10, [['op' => '/', 'value' => 2]]) - 5) < 0.01);
cj_assert('formula skip divide by zero', abs(cj_apply_rate_formula(10, [['op' => '/', 'value' => 0]]) - 10) < 0.01);

$gold22k = [
    'gold_rate' => 15100,
    'making_charges' => 0,
    'rate_formula' => cj_rate_formula_defaults(),
];
$counted15100 = 15100 * 0.9166 / 1.03;
cj_assert('gold counted rate 15100×0.9166÷1.03', abs(cj_effective_metal_rate('gold', $gold22k) - $counted15100) < 0.01);
cj_assert('gold counted 1g price', abs(cj_calculate_price('gold', 1, $gold22k) - $counted15100) < 0.01);
cj_assert('gold formula label', cj_rate_formula_label($gold22k['rate_formula']['gold']) === '× 0.9166 ÷ 1.03');
cj_assert('silver formula stays full rate', cj_effective_metal_rate('silver', ['silver_rate' => 100, 'rate_formula' => cj_rate_formula_defaults()]) === 100.0);

cj_assert('paise rounding', cj_razorpay_amount_paise(199.5) === 19950);
cj_assert('img helper builds query', str_contains(cj_img_url('ring.jpg', 400), 'img.php?f=ring.jpg'));
if (!cj_razorpay_configured()) {
    cj_assert('razorpay off uses WhatsApp fallback', cj_normalize_payment_method('razorpay') === 'whatsapp');
} else {
    cj_assert('razorpay on accepted at checkout', cj_normalize_payment_method('razorpay') === 'razorpay');
}
cj_assert('unknown pay method becomes whatsapp', cj_normalize_payment_method('bitcoin') === 'whatsapp');

if (function_exists('cj_shop_whatsapp_digits')) {
    cj_assert('whatsapp digits are numeric', ctype_digit(cj_shop_whatsapp_digits()));
}

$gstOff = cj_gst_compute(1000, 3, false, false);
cj_assert('GST off keeps total', $gstOff['grand_total'] === 1000.0 && $gstOff['gst_amount'] === 0.0);
$gstInc = cj_gst_compute(103, 3, true, true);
cj_assert('GST inclusive peels tax', abs($gstInc['gst_amount'] - 3.0) < 0.02 && $gstInc['grand_total'] === 103.0);
$gstEx = cj_gst_compute(1000, 3, true, false);
cj_assert('GST exclusive adds on top', $gstEx['gst_amount'] === 30.0 && $gstEx['grand_total'] === 1030.0);

cj_assert('GST feature defaults off', cj_setting('feat_gst', '0') === '0' || cj_setting('feat_gst') === '0');
cj_assert('invoices default off', (cj_settings_defaults()['feat_email_invoices'] ?? '') === '0');
cj_assert('reviews default on', (cj_settings_defaults()['feat_reviews'] ?? '') === '1');
cj_assert('wishlist default on', (cj_settings_defaults()['feat_wishlist'] ?? '') === '1');
cj_assert('flag keys include refunds', in_array('feat_refunds', cj_settings_flag_keys(), true));
cj_assert('flag keys include admin refund permission', in_array('perm_admin_refunds', cj_settings_flag_keys(), true));

$src = ['id' => 1, 'metal_type' => 'gold', 'category' => 'ring', 'cached_price' => 20000, 'weight' => 4];
$cand = ['id' => 2, 'metal_type' => 'gold', 'category' => 'ring', 'cached_price' => 21000, 'weight' => 4.2, 'is_sold_out' => 0];
$score = cj_recommend_score($cand, $src, [2 => 3]);
cj_assert('recommend scores similar gold ring', $score >= 70);
$other = ['id' => 3, 'metal_type' => 'silver', 'category' => 'chain', 'cached_price' => 800, 'weight' => 20, 'is_sold_out' => 1];
cj_assert('recommend scores unrelated silver lower', cj_recommend_score($other, $src) < $score);

cj_assert('paid confirmed order can refund', cj_order_can_refund(['payment_status' => 'paid', 'status' => 'confirmed']));
cj_assert('already refunded cannot refund', !cj_order_can_refund(['payment_status' => 'refunded', 'status' => 'refunded']));
cj_assert('unpaid reserved cannot refund', !cj_order_can_refund(['payment_status' => 'unpaid', 'status' => 'reserved']));

cj_assert('payable uses grand_total', cj_order_payable_rupees(['subtotal' => 1000, 'grand_total' => 1030]) === 1030.0);
cj_assert('payable falls back to subtotal', cj_order_payable_rupees(['subtotal' => 500, 'grand_total' => 0]) === 500.0);

$defs = cj_settings_defaults();
foreach (['feat_gst', 'feat_email_invoices', 'feat_reviews', 'feat_wishlist', 'feat_ai_recs', 'feat_zoom', 'feat_online_pay', 'feat_refunds', 'feat_customer_accounts', 'feat_account_prompt', 'feat_order_history', 'feat_related', 'feat_abandoned_banner', 'feat_product_specs', 'feat_compare', 'perm_admin_refunds', 'gst_percent', 'gst_inclusive', 'recs_limit', 'rate_formula_json'] as $metric) {
    cj_assert('metric exists: ' . $metric, array_key_exists($metric, $defs));
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
