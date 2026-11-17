<?php
/**
 * Store admin — reserved / confirmed orders.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/activity-log.php';

cj_auth_bootstrap($conn);
cj_ensure_orders_schema($conn);
cj_expire_reservations($conn);
cj_admin_noindex_headers();
cj_require_role(['admin', 'superadmin'], 'admin');

$embed = !empty($_GET['embed']) || !empty($_POST['embed']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
        die('CSRF validation failed.');
    }
    $id = (int) ($_POST['order_id'] ?? 0);
    $action = (string) ($_POST['order_action'] ?? '');
    if ($action === 'confirm' && $id > 0 && cj_confirm_order($conn, $id)) {
        $_SESSION['success_message'] = 'Order confirmed. Piece stays sold.';
        cj_log_activity($conn, 'order_confirm', 'Order #' . $id);
    } elseif ($action === 'cancel' && $id > 0 && cj_release_order_stock($conn, $id, 'cancelled')) {
        $_SESSION['success_message'] = 'Order cancelled. Piece is available again.';
        cj_log_activity($conn, 'order_cancel', 'Order #' . $id);
    } elseif ($action === 'refund' && $id > 0) {
        $result = cj_refund_order($conn, $id, (string) ($_POST['refund_reason'] ?? ''));
        if (!empty($result['ok'])) {
            $_SESSION['success_message'] = $result['message'];
            cj_log_activity($conn, 'order_refund', 'Order #' . $id);
        } else {
            $_SESSION['error_message'] = $result['message'] ?? 'Could not refund.';
        }
    } else {
        $_SESSION['error_message'] = 'Could not update that order.';
    }
    header('Location: admin-orders.php' . ($embed ? '?embed=1' : ''));
    exit;
}

$filter = (string) ($_GET['status'] ?? 'reserved');
$allowed = ['reserved', 'confirmed', 'cancelled', 'expired', 'refunded', 'all'];
if (!in_array($filter, $allowed, true)) {
    $filter = 'reserved';
}

$sql = 'SELECT * FROM orders';
if ($filter !== 'all') {
    $sql .= ' WHERE status = ?';
}
$sql .= ' ORDER BY id DESC LIMIT 80';
$stmt = $conn->prepare($sql);
if ($filter !== 'all') {
    $stmt->bind_param('s', $filter);
}
$stmt->execute();
$orders = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

$itemsByOrder = [];
if ($orders) {
    $oids = array_map(static function ($o) { return (int) $o['id']; }, $orders);
    $in = implode(',', $oids);
    $ir = $conn->query("SELECT * FROM order_items WHERE order_id IN ($in)");
    if ($ir) {
        while ($it = $ir->fetch_assoc()) {
            $itemsByOrder[(int) $it['order_id']][] = $it;
        }
    }
}

$counts = ['reserved' => 0, 'confirmed' => 0, 'cancelled' => 0, 'expired' => 0, 'refunded' => 0];
$cq = $conn->query("SELECT status, COUNT(*) c FROM orders GROUP BY status");
if ($cq) {
    while ($c = $cq->fetch_assoc()) {
        $counts[$c['status']] = (int) $c['c'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Orders — ChandraKala Jewellers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <?php include __DIR__ . '/includes/admin-head-theme.php'; ?>
</head>
<body class="admin-body bg-gray-50 text-gray-800 min-h-screen">
<?php if (empty($embed)): ?>
<header class="bg-white border-b border-gray-100 px-4 sm:px-8 py-4 flex justify-between items-center">
    <div>
        <h1 class="text-lg font-bold font-serif">Orders</h1>
        <p class="text-xs text-amber-700 font-semibold uppercase tracking-wider">Reservations &amp; stock lock</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="admin-customers" class="text-xs sm:text-sm font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-3 sm:px-4 py-2 rounded-xl transition-colors">Customers</a>
        <a href="admin" class="text-xs sm:text-sm font-semibold text-gray-600 hover:text-gray-900 bg-gray-50 border border-gray-200 px-3 sm:px-4 py-2 rounded-xl transition-colors">&larr; Admin</a>
    </div>
</header>
<?php endif; ?>

<main class="max-w-5xl mx-auto p-4 sm:p-6">
    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3 rounded-xl mb-4 text-sm"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 p-3 rounded-xl mb-4 text-sm"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <nav class="flex flex-wrap gap-2 mb-5 text-xs font-semibold">
        <?php foreach (['reserved' => 'Reserved (' . $counts['reserved'] . ')', 'confirmed' => 'Confirmed', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled', 'expired' => 'Expired', 'all' => 'All'] as $key => $label): ?>
            <a href="admin-orders?status=<?= $key ?><?= $embed ? '&embed=1' : '' ?>"
               class="px-3 py-1.5 rounded-lg border <?= $filter === $key ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-600 border-gray-200' ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$orders): ?>
        <p class="text-sm text-gray-500 bg-white border border-gray-100 rounded-xl p-8 text-center">No orders in this list yet.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($orders as $o):
                $oid = (int) $o['id'];
                $lines = $itemsByOrder[$oid] ?? [];
            ?>
            <article class="bg-white border border-gray-100 rounded-xl p-4 sm:p-5 shadow-sm">
                <div class="flex flex-wrap justify-between gap-2 mb-3">
                    <div>
                        <p class="font-bold text-gray-900"><?= htmlspecialchars($o['order_no']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($o['created_at']) ?> · <?= htmlspecialchars($o['status']) ?>
                            · <?= htmlspecialchars((string) ($o['payment_status'] ?? 'unpaid')) ?>
                            <?php if (!empty($o['payment_method'])): ?>
                                (<?= htmlspecialchars((string) $o['payment_method']) ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                    <p class="font-extrabold text-amber-700">₹<?= number_format(function_exists('cj_order_payable_rupees') ? cj_order_payable_rupees($o) : (float) $o['subtotal'], 0) ?></p>
                </div>
                <p class="text-sm text-gray-800"><a href="admin-customers.php?search=<?= urlencode((string)$o['customer_phone']) ?>" class="hover:underline text-amber-700 font-bold" title="View Customer Profile"><?= htmlspecialchars($o['customer_name']) ?></a> · <a href="tel:<?= htmlspecialchars($o['customer_phone']) ?>" class="hover:underline"><?= htmlspecialchars($o['customer_phone']) ?></a></p>
                <?php if ($o['customer_address'] !== ''): ?>
                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($o['customer_address']) ?><?php if (!empty($o['customer_pincode'])): ?> · PIN <?= htmlspecialchars((string) $o['customer_pincode']) ?><?php endif; ?></p>
                <?php endif; ?>
                <?php if (!empty($o['gst_amount']) && (float) $o['gst_amount'] > 0): ?>
                    <p class="text-[11px] text-gray-500 mt-1">GST <?= htmlspecialchars((string) $o['gst_rate']) ?>% · ₹<?= number_format((float) $o['gst_amount'], 2) ?></p>
                <?php endif; ?>
                <?php if (!empty($o['razorpay_payment_id'])): ?>
                    <p class="text-[11px] text-gray-400 mt-1">Razorpay: <?= htmlspecialchars((string) $o['razorpay_payment_id']) ?></p>
                <?php endif; ?>
                <ul class="mt-3 text-sm text-gray-700 space-y-1">
                    <?php foreach ($lines as $line): ?>
                        <li><?= htmlspecialchars($line['product_name']) ?> · <?= htmlspecialchars((string) $line['weight']) ?> g · ₹<?= number_format((float) $line['unit_price'], 0) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($o['status'] === 'reserved'): ?>
                    <form method="post" class="mt-4 flex flex-wrap gap-2">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="order_id" value="<?= $oid ?>">
                        <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                        <button name="order_action" value="confirm" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2 rounded-lg">Confirm sale</button>
                        <?php if (($o['payment_status'] ?? '') !== 'paid'): ?>
                        <button name="order_action" value="cancel" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold px-4 py-2 rounded-lg border border-gray-200" onclick="return confirm('Release this piece back to the shop?');">Cancel / release</button>
                        <?php endif; ?>
                    </form>
                    <?php if (!empty($o['reserved_until'])): ?>
                        <p class="text-[11px] text-gray-400 mt-2">Held until <?= htmlspecialchars($o['reserved_until']) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (function_exists('cj_admin_can_refund') && cj_admin_can_refund() && cj_order_can_refund($o)): ?>
                    <form method="post" class="mt-3 flex flex-wrap gap-2 items-center">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="order_id" value="<?= $oid ?>">
                        <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                        <input type="text" name="refund_reason" maxlength="255" placeholder="Refund reason (optional)" class="text-xs border border-gray-200 rounded-lg px-3 py-2 flex-1 min-w-[160px]">
                        <button name="order_action" value="refund" class="bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold px-4 py-2 rounded-lg" onclick="return confirm('Refund this order and return the piece to the shop?');">Refund</button>
                    </form>
                <?php elseif (($o['payment_status'] ?? '') === 'refunded' || ($o['status'] ?? '') === 'refunded'): ?>
                    <p class="text-[11px] text-rose-700 mt-2">Refunded<?= !empty($o['refunded_at']) ? ' · ' . htmlspecialchars((string) $o['refunded_at']) : '' ?><?= !empty($o['refund_reason']) ? ' · ' . htmlspecialchars((string) $o['refund_reason']) : '' ?></p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
