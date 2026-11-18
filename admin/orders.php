<?php
/**
 * Store admin — reserved / confirmed orders.
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/orders.php';
require_once dirname(__DIR__) . '/includes/activity-log.php';

cj_auth_bootstrap($conn);
cj_ensure_orders_schema($conn);
cj_expire_reservations($conn);
cj_admin_noindex_headers();
cj_require_role(['admin', 'superadmin'], 'admin');

$embed = !empty($_GET['embed']) || !empty($_POST['embed']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        die('CSRF validation failed.');
    }
    $id = (int) ($_POST['order_id'] ?? 0);
    $action = (string) ($_POST['order_action'] ?? '');
    if ($action === 'confirm' && $id > 0) {
        $markSoldOut = !isset($_POST['mark_sold_out']) || (string)$_POST['mark_sold_out'] === '1';
        if (cj_confirm_order($conn, $id, $markSoldOut)) {
            $_SESSION['success_message'] = $markSoldOut
                ? 'Order confirmed & marked as Sold Out on the website.'
                : 'Order confirmed. Piece kept available on the website.';
            cj_log_activity($conn, 'order_confirm', 'Order #' . $id . ($markSoldOut ? ' (Marked Sold Out)' : ' (Kept Available)'));
        } else {
            $_SESSION['error_message'] = 'Could not confirm that order.';
        }
    } elseif ($action === 'toggle_sold_out' && $id > 0) {
        $markSold = (string)($_POST['sold_state'] ?? '1') === '1' ? 1 : 0;
        $pids = [];
        $piSt = $conn->prepare('SELECT DISTINCT product_id FROM order_items WHERE order_id = ?');
        if ($piSt) {
            $piSt->bind_param('i', $id);
            $piSt->execute();
            $piRes = $piSt->get_result();
            while ($pr = $piRes->fetch_assoc()) {
                if ((int)$pr['product_id'] > 0) $pids[] = (int)$pr['product_id'];
            }
            $piSt->close();
        }
        if (!empty($pids)) {
            $in = implode(',', $pids);
            $conn->query("UPDATE jewellery_items SET is_sold_out = {$markSold}, reserved_until = NULL WHERE id IN ({$in})");
            $_SESSION['success_message'] = $markSold ? 'Item(s) marked as Sold Out on the website.' : 'Item(s) marked as Available on the website.';
            cj_log_activity($conn, 'order_stock_toggle', 'Order #' . $id . ($markSold ? ' -> Sold Out' : ' -> Available'));
        }
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
    header('Location: /admin/orders.php' . ($embed ? '?embed=1' : ''));
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
$itemStockMap = [];
if ($orders) {
    $oids = array_map(static function ($o) { return (int) $o['id']; }, $orders);
    $in = implode(',', $oids);
    $ir = $conn->query("SELECT oi.*, ji.is_sold_out FROM order_items oi LEFT JOIN jewellery_items ji ON ji.id = oi.product_id WHERE oi.order_id IN ($in)");
    if ($ir) {
        while ($it = $ir->fetch_assoc()) {
            $oidKey = (int) $it['order_id'];
            $itemsByOrder[$oidKey][] = $it;
            if (!empty($it['is_sold_out'])) {
                $itemStockMap[$oidKey] = true;
            }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Orders — ChandraKala Jewellers</title>
    <link rel="stylesheet" href="/css/tailwind.min.css?v=9">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/admin.css">
    <?php include dirname(__DIR__) . '/includes/admin-head-theme.php'; ?>
</head>
<body class="admin-body bg-gray-50 text-gray-800 min-h-screen">
<?php
$cjShellPage     = 'orders';
$cjShellTitle    = 'Orders & Reservations';
$cjShellSubtitle = 'Confirm sales, manage sold-out website status & refunds';
$cjShellEmbed    = !empty($embed);
$cjShellMaxWidth = '1120px';
include dirname(__DIR__) . '/includes/admin-shell-start.php';
?>
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
            <a href="orders.php?status=<?= $key ?><?= $embed ? '&embed=1' : '' ?>"
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
                        <p class="text-xs text-gray-500"><?= htmlspecialchars(cj_format_ist($o['created_at'] ?? '')) ?> · <?= htmlspecialchars($o['status']) ?>
                            · <?= htmlspecialchars((string) ($o['payment_status'] ?? 'unpaid')) ?>
                            <?php if (!empty($o['payment_method'])): ?>
                                (<?= htmlspecialchars((string) $o['payment_method']) ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                    <p class="font-extrabold text-amber-700">₹<?= number_format(function_exists('cj_order_payable_rupees') ? cj_order_payable_rupees($o) : (float) $o['subtotal'], 0) ?></p>
                </div>
                <p class="text-sm text-gray-800"><strong><?= htmlspecialchars($o['customer_name']) ?></strong> · <?= htmlspecialchars($o['customer_phone']) ?></p>
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
                <?php if ($o['status'] === 'reserved'):
                    $itemTitles = array_map(static fn($l) => (string)$l['product_name'], $lines);
                    $itemsSummary = implode(', ', $itemTitles);
                ?>
                    <form method="post" class="mt-4 flex flex-wrap gap-2">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="order_id" value="<?= $oid ?>">
                        <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                        <button type="button"
                                onclick="openConfirmSaleModal(<?= $oid ?>, <?= htmlspecialchars(json_encode((string)$o['order_no']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($itemsSummary), ENT_QUOTES, 'UTF-8') ?>)"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2 rounded-lg">Confirm sale</button>
                        <?php if (($o['payment_status'] ?? '') !== 'paid'): ?>
                        <button name="order_action" value="cancel" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold px-4 py-2 rounded-lg border border-gray-200" onclick="return confirm('Release this piece back to the shop?');">Cancel / release</button>
                        <?php endif; ?>
                    </form>
                    <?php if (!empty($o['reserved_until'])): ?>
                        <p class="text-[11px] text-gray-400 mt-2">Held until <?= htmlspecialchars(cj_format_ist($o['reserved_until'])) ?></p>
                    <?php endif; ?>
                <?php elseif ($o['status'] === 'confirmed'):
                    $isMarkedSold = !empty($itemStockMap[$oid]);
                ?>
                    <form method="post" class="mt-3 flex flex-wrap items-center gap-2">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="order_id" value="<?= $oid ?>">
                        <input type="hidden" name="order_action" value="toggle_sold_out">
                        <input type="hidden" name="sold_state" value="<?= $isMarkedSold ? '0' : '1' ?>">
                        <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                        <span class="text-[11px] font-bold px-2.5 py-1 rounded-full <?= $isMarkedSold ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
                            <?= $isMarkedSold ? 'Website Status: Sold Out' : 'Website Status: Still Available' ?>
                        </span>
                        <button type="submit" class="text-[11px] font-semibold px-3 py-1 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-700">
                            <?= $isMarkedSold ? 'Mark Available Again' : 'Mark as Sold Out' ?>
                        </button>
                    </form>
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
<?php include dirname(__DIR__) . '/includes/admin-shell-end.php'; ?>

<!-- Confirm Sale: Ask Admin Whether to Mark Sold Out on Website -->
<div id="cjConfirmSaleModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,0.6);backdrop-filter:blur(3px);">
    <div class="bg-white border border-gray-200 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex justify-between items-start gap-2 mb-3">
            <div>
                <h3 class="text-lg font-bold font-serif text-gray-900">Confirm Sale</h3>
                <p id="cjCsOrderNo" class="text-xs font-semibold text-amber-600"></p>
            </div>
            <button type="button" onclick="closeConfirmSaleModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <p class="text-xs sm:text-sm text-gray-600 mb-2">Do you want to mark the ordered item(s) as <strong>Sold Out</strong> on the website?</p>
        <p id="cjCsItems" class="text-xs font-semibold text-gray-800 bg-gray-50 border border-gray-200 rounded-xl p-2.5 mb-5"></p>
        <form method="post" id="cjConfirmSaleForm" class="space-y-2.5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="order_id" id="cjCsOrderId" value="0">
            <input type="hidden" name="order_action" value="confirm">
            <input type="hidden" name="mark_sold_out" id="cjCsMarkSold" value="1">
            <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
            <button type="button" onclick="submitConfirmSale(1)" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm py-2.5 px-4 rounded-xl transition-colors">
                Yes — Confirm Sale &amp; Mark Sold Out
            </button>
            <button type="button" onclick="submitConfirmSale(0)" class="w-full bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-bold text-xs sm:text-sm py-2.5 px-4 rounded-xl transition-colors">
                No — Confirm Sale &amp; Keep Available on Site
            </button>
            <button type="button" onclick="closeConfirmSaleModal()" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold text-xs py-2 px-4 rounded-xl transition-colors">
                Cancel
            </button>
        </form>
    </div>
</div>
<script>
function openConfirmSaleModal(orderId, orderNo, itemsText) {
    document.getElementById('cjCsOrderId').value = String(orderId);
    document.getElementById('cjCsOrderNo').textContent = orderNo || ('Order #' + orderId);
    document.getElementById('cjCsItems').textContent = itemsText || 'Ordered jewellery item(s)';
    var m = document.getElementById('cjConfirmSaleModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeConfirmSaleModal() {
    var m = document.getElementById('cjConfirmSaleModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}
function submitConfirmSale(markSoldOut) {
    document.getElementById('cjCsMarkSold').value = markSoldOut ? '1' : '0';
    document.getElementById('cjConfirmSaleForm').submit();
}
</script>
</body>
</html>
