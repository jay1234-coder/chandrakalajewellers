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
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($sessionToken !== '' && $postedToken !== '' && hash_equals($sessionToken, $postedToken)) {
        cj_customer_logout();
    }
    header('Location: account.php');
    exit;
}

$customer = cj_customer_session();
$flash = '';
if (!$customer && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['account_action'])) {
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
        $flash = 'Please refresh and try again.';
    } elseif ($_POST['account_action'] === 'login') {
        $result = cj_customer_login($conn, (string) ($_POST['phone'] ?? ''), (string) ($_POST['password'] ?? ''));
        $flash = $result['message'];
        if (!empty($result['ok'])) {
            header('Location: account.php');
            exit;
        }
    } elseif ($_POST['account_action'] === 'register') {
        $result = cj_customer_register(
            $conn,
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['phone'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['email'] ?? '')
        );
        $flash = $result['message'];
        if (!empty($result['ok'])) {
            header('Location: account.php');
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
        <p class="text-sm" style="margin:12px 0;color:#b45309;"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <?php if ($customer): ?>
        <p style="margin:8px 0 20px;color:var(--text-secondary);">Hello, <strong><?= htmlspecialchars($customer['name']) ?></strong></p>
        <form method="post" style="margin-bottom:32px;">
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
                    <p style="font-size:13px;color:var(--text-secondary);"><?= htmlspecialchars((string) $o['created_at']) ?> · ₹<?= number_format($total, 0) ?></p>
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
            <form method="post" class="cj-account-page-form">
                <h2>Sign in</h2>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="account_action" value="login">
                <label>Mobile <input name="phone" required inputmode="numeric" maxlength="15" autocomplete="tel"></label>
                <label>Password <input name="password" type="password" required autocomplete="current-password"></label>
                <button type="submit" class="btn">Sign in</button>
            </form>
            <form method="post" class="cj-account-page-form">
                <h2>Create account</h2>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="account_action" value="register">
                <label>Name <input name="name" required maxlength="120" autocomplete="name"></label>
                <label>Mobile <input name="phone" required inputmode="numeric" maxlength="15" autocomplete="tel"></label>
                <label>Email <span>(optional)</span> <input name="email" type="email" autocomplete="email"></label>
                <label>Password <input name="password" type="password" required minlength="6" autocomplete="new-password"></label>
                <button type="submit" class="btn">Create account</button>
            </form>
        </div>
    <?php endif; ?>
</main>
<?php include 'includes/footer.php'; ?>
</body>
</html>
