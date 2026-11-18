<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/customers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/orders.php';

if (!cj_feature('feat_customer_accounts')) {
    header('Location: gallery.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['logout_account'])) {
    if (function_exists('cj_validate_csrf') && cj_validate_csrf()) {
        cj_customer_logout();
    }
    header('Location: /account');
    exit;
}

$customer = cj_customer_session();
$flash = '';
$flashType = 'error';
$oldRegName = '';
$oldRegPhone = '';
$oldRegEmail = '';
$oldLoginPhone = '';

if (!$customer && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['account_action'])) {
    $act = (string) ($_POST['account_action'] ?? '');
    if ($act === 'register') {
        $oldRegName = trim((string) ($_POST['name'] ?? ''));
        $oldRegPhone = trim((string) ($_POST['phone'] ?? ''));
        $oldRegEmail = trim((string) ($_POST['email'] ?? ''));
    } elseif ($act === 'login') {
        $oldLoginPhone = trim((string) ($_POST['phone'] ?? ''));
    }

    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        $flash = 'Session expired. Please try submitting again.';
        $flashType = 'error';
    } elseif ($act === 'login') {
        $result = cj_customer_login($conn, (string) ($_POST['phone'] ?? ''), (string) ($_POST['password'] ?? ''));
        $flash = $result['message'];
        $flashType = !empty($result['ok']) ? 'success' : 'error';
        if (!empty($result['ok'])) {
            header('Location: /account');
            exit;
        }
    } elseif ($act === 'register') {
        $result = cj_customer_register(
            $conn,
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['phone'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['email'] ?? '')
        );
        $flash = $result['message'];
        $flashType = !empty($result['ok']) ? 'success' : 'error';
        if (!empty($result['ok'])) {
            header('Location: /account');
            exit;
        }
    }
    $customer = cj_customer_session();
}

$history = [];
if ($customer && cj_feature('feat_order_history')) {
    cj_ensure_orders_schema($conn);
    $history = cj_customer_orders($conn, (int) $customer['id'], (string) $customer['phone']);
}

$seo = cj_merge_seo([
    'title' => 'My account | ChandraKala Jewellers',
    'description' => 'Optional customer account and order history at ChandraKala Jewellers, Khedbrahma.',
    'canonical' => cj_abs_url('account'),
    'robots' => 'noindex, nofollow',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'includes/assets-head.php'; ?>
<?php include 'includes/seo-head.php'; ?>
</head>
<body>
<?php $activePage = ''; include 'includes/header.php'; ?>
<main id="main-content" class="container page-enter" style="padding:24px 16px 80px;max-width:720px;">
    <h1 class="product-title" style="font-size:28px;">My account</h1>
    <?php if ($flash !== ''): ?>
        <div role="alert" style="margin:12px 0 18px;padding:12px 14px;border-radius:12px;font-size:13px;font-weight:600;border:1px solid <?= $flashType === 'success' ? 'rgba(16,185,129,0.4)' : 'rgba(239,68,68,0.4)' ?>;background:<?= $flashType === 'success' ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)' ?>;color:<?= $flashType === 'success' ? '#047857' : '#dc2626' ?>;">
            <?= htmlspecialchars($flash) ?>
        </div>
    <?php endif; ?>

    <?php if ($customer): ?>
        <p style="margin:8px 0 6px;color:var(--text-secondary);">Hello, <strong style="color:var(--text-primary);"><?= htmlspecialchars($customer['name']) ?></strong></p>
        <p style="margin:0 0 20px;font-size:13px;color:var(--text-secondary);">
            Mobile: <strong><?= htmlspecialchars($customer['phone']) ?></strong>
            <?php if (!empty($customer['email'])): ?>
                &nbsp;·&nbsp; Email: <strong><?= htmlspecialchars($customer['email']) ?></strong>
            <?php endif; ?>
        </p>
        <form method="post" action="/account" style="margin-bottom:32px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" name="logout_account" value="1" class="btn btn-secondary">Sign out</button>
        </form>
        <?php if (cj_feature('feat_order_history')): ?>
            <h2 class="product-title" style="font-size:22px;">Order history</h2>
            <?php if (!$history): ?>
                <p style="color:var(--text-secondary);margin-top:12px;">No orders on this account yet. Guest checkout still works — orders appear here when placed while signed in, or with the same mobile number.</p>
            <?php else: foreach ($history as $pack):
                $o = $pack['order'];
                $total = function_exists('cj_order_payable_rupees') ? cj_order_payable_rupees($o) : (float) ($o['subtotal'] ?? 0);
            ?>
                <article class="cj-order-card">
                    <p><strong><?= htmlspecialchars((string) $o['order_no']) ?></strong> · <?= htmlspecialchars((string) $o['status']) ?> · <?= htmlspecialchars((string) ($o['payment_status'] ?? '')) ?></p>
                    <p style="font-size:13px;color:var(--text-secondary);"><?= htmlspecialchars(cj_format_ist($o['created_at'] ?? '')) ?> · ₹<?= number_format($total, 0) ?></p>
                    <ul>
                        <?php foreach ($pack['items'] as $line): ?>
                            <li><?= htmlspecialchars((string) $line['product_name']) ?> · ₹<?= number_format((float) $line['unit_price'], 0) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin-top:8px;"><a href="success?ref=<?= urlencode((string) $o['order_no']) ?>">View order</a></p>
                </article>
            <?php endforeach; endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <p style="color:var(--text-secondary);margin:8px 0 24px;">Optional — you can always checkout as a guest. Sign in only if you want order history on this device.</p>
        <div class="cj-account-page-grid">
            <form method="post" action="/account" class="cj-account-page-form">
                <h2>Sign in</h2>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="account_action" value="login">
                <label>Mobile or Email <input name="phone" type="text" value="<?= htmlspecialchars($oldLoginPhone) ?>" required maxlength="160" placeholder="10-digit mobile or email" autocomplete="username"></label>
                <label>Password <input name="password" type="password" required autocomplete="current-password"></label>
                <button type="submit" class="btn">Sign in</button>
            </form>
            <form method="post" action="/account" class="cj-account-page-form" id="cjRegisterForm">
                <h2>Create account</h2>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="account_action" value="register">
                <label>Name <input name="name" type="text" value="<?= htmlspecialchars($oldRegName) ?>" required maxlength="120" placeholder="Your full name" autocomplete="name"></label>
                <label>Mobile <input name="phone" type="tel" value="<?= htmlspecialchars($oldRegPhone) ?>" required inputmode="numeric" maxlength="15" placeholder="10-digit mobile number" autocomplete="tel"></label>
                <label>Email <span>(optional)</span> <input name="email" type="text" inputmode="email" value="<?= htmlspecialchars($oldRegEmail) ?>" maxlength="160" placeholder="name@example.com" autocomplete="email" autocapitalize="off" spellcheck="false"></label>
                <label>Password <input name="password" type="password" required minlength="6" placeholder="Min 6 characters" autocomplete="new-password"></label>
                <button type="submit" class="btn">Create account</button>
            </form>
        </div>
        <script>
        (function () {
            var f = document.getElementById('cjRegisterForm');
            if (!f) return;
            f.addEventListener('submit', function () {
                var em = f.querySelector('input[name="email"]');
                if (em && em.value) em.value = em.value.replace(/\s+/g, '').trim();
            });
        })();
        </script>
    <?php endif; ?>
</main>
<?php include 'includes/footer.php'; ?>
</body>
</html>
