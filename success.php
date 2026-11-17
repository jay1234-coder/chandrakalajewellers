<?php
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
$cart_items = $packed['items'];
$customer_name = (string) ($order['customer_name'] ?? '');
$customer_address = (string) ($order['customer_address'] ?? '');
$customer_phone = (string) ($order['customer_phone'] ?? '');
$customer_pincode = (string) ($order['customer_pincode'] ?? '');
$order_no = (string) $order['order_no'];
$subtotal = (float) ($order['subtotal'] ?? 0);
$gstAmount = (float) ($order['gst_amount'] ?? 0);
$gstRate = (float) ($order['gst_rate'] ?? 0);
$grandTotal = function_exists('cj_order_payable_rupees') ? cj_order_payable_rupees($order) : $subtotal;
$payStatus = (string) ($order['payment_status'] ?? 'unpaid');
$orderStatus = (string) ($order['status'] ?? 'reserved');
$paid = $payStatus === 'paid' || $orderStatus === 'confirmed';
$closed = in_array($orderStatus, ['cancelled', 'expired'], true);
$whatsapp_url = cj_whatsapp_order_url($order, $cart_items);

$seo = cj_merge_seo([
    'title' => ($paid ? 'Order confirmed' : 'Order reserved') . ' | ChandraKala Jewellers',
    'description' => 'Your ChandraKala Jewellers order summary.',
    'canonical' => cj_abs_url('success'),
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
    <style>
        .animate-check {
            animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes scaleIn {
            from { transform: scale(0.6); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col">

<?php $activePage = ''; include 'includes/header.php'; ?>

<main class="flex-grow flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 max-w-lg w-full p-8 text-center animate-check">
        
        <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-6 text-emerald-500 shadow-inner">
            <svg style="width: 32px; height: 32px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <?php if ($paid): ?>
            <h1 class="text-2xl font-bold text-gray-900 font-serif mb-2">Order confirmed</h1>
            <p class="text-sm text-gray-500 mb-2">Order <strong><?= htmlspecialchars($order_no) ?></strong> is paid and confirmed. We will contact you for delivery or pickup.</p>
        <?php elseif ($closed): ?>
            <h1 class="text-2xl font-bold text-gray-900 font-serif mb-2">Reservation ended</h1>
            <p class="text-sm text-gray-500 mb-2">Order <strong><?= htmlspecialchars($order_no) ?></strong> is no longer held. Please checkout again if you still want the piece.</p>
        <?php else: ?>
            <h1 class="text-2xl font-bold text-gray-900 font-serif mb-2">Order reserved</h1>
            <p class="text-sm text-gray-500 mb-2">Order <strong><?= htmlspecialchars($order_no) ?></strong> is held for 24 hours. Pay at the shop or message us on WhatsApp to complete.</p>
        <?php endif; ?>
        <?php if (!$closed): ?>
        <p class="text-xs text-gray-400 mb-6">Save this order number. These pieces are locked for you.</p>
        <?php else: ?>
        <p class="text-xs text-gray-400 mb-6">This order number is closed. Stock has been released.</p>
        <?php endif; ?>

        <div class="bg-gray-50 rounded-xl p-5 text-left border border-gray-100 mb-6 text-sm">
            <h3 class="font-bold text-gray-800 mb-3 border-b border-gray-200/60 pb-2 font-serif text-center">Order summary</h3>
            
            <?php if ($customer_name !== '' || $customer_phone !== ''): ?>
                <div class="mb-3">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Customer</p>
                    <?php if ($customer_name !== ''): ?><p class="text-gray-800 font-medium">Name: <span class="text-gray-900"><?= htmlspecialchars($customer_name) ?></span></p><?php endif; ?>
                    <?php if ($customer_phone !== ''): ?><p class="text-gray-800 font-medium">Phone: <span class="text-gray-900"><?= htmlspecialchars($customer_phone) ?></span></p><?php endif; ?>
                    <?php if ($customer_address !== ''): ?><p class="text-gray-800 font-medium">Address: <span class="text-gray-900"><?= htmlspecialchars($customer_address) ?></span></p><?php endif; ?>
                    <?php if ($customer_pincode !== ''): ?><p class="text-gray-800 font-medium">PIN: <span class="text-gray-900"><?= htmlspecialchars($customer_pincode) ?></span></p><?php endif; ?>
                </div>
            <?php endif; ?>

            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Payment</p>
            <p class="text-gray-800 font-medium mb-3"><?php
                if ($paid) {
                    echo (($order['payment_method'] ?? '') === 'razorpay') ? 'Paid online' : 'Paid / confirmed at shop';
                } else {
                    echo 'Unpaid — pay at shop or via WhatsApp';
                }
            ?></p>
            
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Items</p>
                <ul class="space-y-1.5 text-gray-800">
                    <?php if (!empty($cart_items)): ?>
                        <?php foreach ($cart_items as $item): ?>
                            <li class="flex justify-between font-medium">
                                <span>&bull; <?= htmlspecialchars($item['product_name'] ?? $item['name'] ?? 'Item') ?></span>
                                <span class="text-gray-500">₹<?= number_format((float) ($item['unit_price'] ?? 0), 0) ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="text-gray-400 italic">No products found</li>
                    <?php endif; ?>
                </ul>
                <p class="flex justify-between text-sm text-gray-600 mt-3 pt-3 border-t border-gray-200">
                    <span>Subtotal</span>
                    <span>₹<?= number_format($subtotal, 0) ?></span>
                </p>
                <?php if ($gstAmount > 0): ?>
                <p class="flex justify-between text-sm text-gray-600">
                    <span>GST <?= htmlspecialchars((string) $gstRate) ?>%</span>
                    <span>₹<?= number_format($gstAmount, 2) ?></span>
                </p>
                <?php endif; ?>
                <p class="flex justify-between font-extrabold text-amber-700 mt-1">
                    <span>Total</span>
                    <span>₹<?= number_format($grandTotal, 0) ?></span>
                </p>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <?php if (!$paid && !$closed && cj_razorpay_configured()): ?>
            <a href="pay?ref=<?= htmlspecialchars($order_no) ?>" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-3.5 px-6 rounded-xl transition-all duration-200 shadow-sm text-sm">
                Pay online now
            </a>
            <?php endif; ?>
            <a id="whatsappButton" href="<?= htmlspecialchars($whatsapp_url) ?>" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3.5 px-6 rounded-xl transition-all duration-200 shadow-sm text-sm flex items-center justify-center gap-2">
                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.458L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436 0 9.86-4.413 9.864-9.83.002-2.623-1.01-5.09-2.855-6.94S14.14 1.002 11.522 1c-5.44 0-9.866 4.417-9.87 9.836-.002 1.802.49 3.55 1.422 5.11L2.002 22l6.147-1.616zM17.486 14.4c-.3-.15-1.774-.875-2.048-.975s-.475-.15-.675.15-.775.975-.95 1.175-.35.225-.65.075c-.3-.15-1.267-.467-2.413-1.488-.892-.796-1.493-1.78-1.668-2.08s-.019-.462.131-.611c.135-.135.3-.35.45-.525s.2-.3.3-.5.05-.375-.025-.525-.675-1.625-.925-2.225c-.244-.589-.491-.51-.675-.52l-.575-.01c-.2 0-.525.075-.8 1.01S7 9.7 7 9.825c0 .125.125.25.125.375s2.215 3.393 5.362 4.757c.75.325 1.333.52 1.787.664.752.24 1.437.206 1.978.125.602-.09 1.774-.725 2.024-1.425s.25-1.3.175-1.425-.275-.225-.575-.375z"/>
                </svg>
                <?= $paid ? 'Share on WhatsApp' : 'Message the shop' ?>
            </a>
            <a href="gallery" class="text-xs text-gray-500 hover:text-amber-500 font-semibold transition-colors duration-150 mt-2">Continue shopping</a>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            localStorage.removeItem('cart');
            if (typeof updateCartCount === 'function') updateCartCount();
        });
    </script>
</body>
</html>
