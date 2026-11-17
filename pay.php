<?php
/**
 * Online payment for a reserved order (Razorpay Checkout.js).
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/payments.php';
require_once __DIR__ . '/includes/seo.php';

$orderNo = preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($_GET['ref'] ?? '')) ?? '';
cj_ensure_orders_schema($conn);
cj_expire_reservations($conn);

$packed = $orderNo !== '' ? cj_fetch_order_by_no($conn, $orderNo) : null;
if (!$packed) {
    header('Location: checkout');
    exit;
}

$order = $packed['order'];
$items = $packed['items'];
$status = (string) ($order['status'] ?? '');
$payStatus = (string) ($order['payment_status'] ?? 'unpaid');

if ($payStatus === 'paid' || $status === 'confirmed') {
    header('Location: success?ref=' . rawurlencode($orderNo));
    exit;
}

if (in_array($status, ['cancelled', 'expired'], true)) {
    $payError = 'This reservation has ended. Please checkout again.';
    $checkoutReady = false;
    $rzp = null;
} elseif (!cj_razorpay_configured()) {
    header('Location: success?ref=' . rawurlencode($orderNo));
    exit;
} else {
    $payError = '';
    $checkoutReady = true;
    $rzp = null;
    try {
        $rzp = cj_ensure_razorpay_order($conn, $order);
    } catch (Throwable $e) {
        $payError = $e->getMessage();
        $checkoutReady = false;
    }
}

$subtotal = (float) ($order['subtotal'] ?? 0);
$gstAmount = (float) ($order['gst_amount'] ?? 0);
$gstRate = (float) ($order['gst_rate'] ?? 0);
$grandTotal = function_exists('cj_order_payable_rupees') ? cj_order_payable_rupees($order) : $subtotal;
$customerName = (string) ($order['customer_name'] ?? '');
$customerPhone = (string) ($order['customer_phone'] ?? '');
$prefillContact = strlen($customerPhone) > 10 ? substr($customerPhone, -10) : $customerPhone;
$csrf = (string) ($_SESSION['csrf_token'] ?? '');
$whatsappUrl = cj_whatsapp_order_url($order, $items);

$seo = cj_merge_seo([
    'title' => 'Pay securely | ChandraKala Jewellers',
    'description' => 'Complete payment for your reserved jewellery order.',
    'canonical' => cj_abs_url('pay'),
    'robots' => 'noindex, nofollow',
]);
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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include 'includes/theme-boot.php'; ?>
<?php if ($checkoutReady && $rzp): ?>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<?php endif; ?>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col">
<?php $activePage = 'cart'; include 'includes/header.php'; ?>

<main class="flex-grow flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 max-w-lg w-full p-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700 mb-2">Step 4 of 4 · Payment</p>
        <h1 class="text-2xl font-bold text-gray-900 font-serif mb-2">Pay for order <?= htmlspecialchars($orderNo) ?></h1>
        <p class="text-sm text-gray-500 mb-6">Your pieces are reserved. Complete payment here — you will get an on-site confirmation.</p>

        <div class="bg-gray-50 rounded-xl p-5 text-left border border-gray-100 mb-6 text-sm">
            <ul class="space-y-1.5 text-gray-800">
                <?php foreach ($items as $item): ?>
                    <li class="flex justify-between font-medium gap-3">
                        <span><?= htmlspecialchars((string) ($item['product_name'] ?? 'Item')) ?></span>
                        <span class="text-gray-500">₹<?= number_format((float) ($item['unit_price'] ?? 0), 0) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="flex justify-between font-extrabold text-amber-700 mt-3 pt-3 border-t border-gray-200">
                <span>Total</span>
                <span>₹<?= number_format($grandTotal, 0) ?></span>
            </p>
            <?php if ($gstAmount > 0): ?>
            <p class="text-[11px] text-gray-500 mt-1">Includes GST <?= htmlspecialchars((string) $gstRate) ?>% · ₹<?= number_format($gstAmount, 2) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($payError !== ''): ?>
            <p class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl p-3 mb-4"><?= htmlspecialchars($payError) ?></p>
            <a href="<?= htmlspecialchars($whatsappUrl) ?>" class="block w-full text-center bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3.5 px-6 rounded-xl mb-3">Continue on WhatsApp</a>
        <?php elseif ($checkoutReady && $rzp): ?>
            <p id="pay-status" class="text-sm text-gray-500 mb-4 hidden"></p>
            <button type="button" id="pay-now" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-3.5 px-6 rounded-xl transition-all duration-200 shadow-sm">
                Pay ₹<?= number_format($grandTotal, 0) ?> securely
            </button>
            <p class="text-[11px] text-gray-400 mt-3 text-center">UPI, cards, and netbanking via Razorpay. Do not close this page until payment finishes.</p>
        <?php endif; ?>

        <a href="<?= htmlspecialchars($whatsappUrl) ?>" class="block text-center text-xs text-gray-500 hover:text-amber-600 font-semibold mt-5">Message the shop on WhatsApp instead</a>
        <a href="checkout" class="block text-center text-xs text-gray-400 mt-2">&larr; Back to checkout</a>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

<?php if ($checkoutReady && $rzp): ?>
<script>
(function () {
    const btn = document.getElementById('pay-now');
    const statusEl = document.getElementById('pay-status');
    const options = {
        key: <?= json_encode($rzp['key_id']) ?>,
        amount: <?= (int) $rzp['amount'] ?>,
        currency: 'INR',
        name: 'ChandraKala Jewellers',
        description: <?= json_encode('Order ' . $orderNo) ?>,
        order_id: <?= json_encode($rzp['razorpay_order_id']) ?>,
        prefill: {
            name: <?= json_encode($customerName) ?>,
            contact: <?= json_encode($prefillContact) ?>
        },
        theme: { color: '#d97706' },
        modal: {
            ondismiss: function () {
                if (statusEl) {
                    statusEl.classList.remove('hidden');
                    statusEl.textContent = 'Payment window closed. Your pieces stay reserved — tap Pay to try again.';
                }
                if (btn) btn.disabled = false;
            }
        },
        handler: async function (response) {
            if (statusEl) {
                statusEl.classList.remove('hidden');
                statusEl.textContent = 'Verifying payment…';
            }
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Verifying…';
            }
            try {
                const fd = new FormData();
                fd.set('csrf_token', <?= json_encode($csrf) ?>);
                fd.set('order_no', <?= json_encode($orderNo) ?>);
                fd.set('razorpay_payment_id', response.razorpay_payment_id || '');
                fd.set('razorpay_order_id', response.razorpay_order_id || '');
                fd.set('razorpay_signature', response.razorpay_signature || '');
                const res = await fetch('verify-payment', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (!data || !data.ok) {
                    throw new Error((data && data.message) || 'Verification failed.');
                }
                window.location.href = data.redirect || ('success?ref=' + encodeURIComponent(<?= json_encode($orderNo) ?>));
            } catch (err) {
                if (statusEl) statusEl.textContent = err.message || 'Could not verify payment.';
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Retry verification';
                }
            }
        }
    };

    btn.addEventListener('click', function () {
        btn.disabled = true;
        const rzp = new Razorpay(options);
        rzp.on('payment.failed', function () {
            if (statusEl) {
                statusEl.classList.remove('hidden');
                statusEl.textContent = 'Payment failed. You can try again — the pieces stay reserved.';
            }
            btn.disabled = false;
        });
        rzp.open();
    });
})();
</script>
<?php endif; ?>
</body>
</html>
