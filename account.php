<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/customers.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/orders.php';

if (!cj_feature('feat_customer_accounts')) {
    header('Location: gallery');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['logout_account'])) {
    if (function_exists('cj_validate_csrf') && cj_validate_csrf()) {
        cj_customer_logout();
    }
    header('Location: account');
    exit;
}

$customer = cj_customer_session();
$flash = '';
$flashType = 'error';
$oldRegName = '';
$oldRegPhone = '';
$oldRegEmail = '';
$oldLoginPhone = '';
$activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'register') ? 'register' : 'login';

if (!$customer && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['account_action'])) {
    $act = (string) ($_POST['account_action'] ?? '');
    if ($act === 'register') {
        $activeTab = 'register';
        $oldRegName = trim((string) ($_POST['name'] ?? ''));
        $oldRegPhone = trim((string) ($_POST['phone'] ?? ''));
        $oldRegEmail = trim((string) ($_POST['email'] ?? ''));
    } elseif ($act === 'login') {
        $activeTab = 'login';
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
            header('Location: account');
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
            header('Location: account');
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
    'title' => 'My Account | ChandraKala Jewellers',
    'description' => 'Customer account, live metal orders, and purchase history at ChandraKala Jewellers, Khedbrahma.',
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
<main id="main-content" class="container page-enter" style="padding:24px 16px 80px;max-width:640px;margin:0 auto;box-sizing:border-box;">
    <h1 class="product-title" style="font-size:28px;margin-bottom:8px;">My Account</h1>
    
    <div id="cjAuthAlert" role="alert" style="<?= $flash === '' ? 'display:none;' : '' ?>margin:12px 0 18px;padding:12px 16px;border-radius:12px;font-size:14px;font-weight:600;border:1px solid <?= $flashType === 'success' ? 'rgba(16,185,129,0.4)' : 'rgba(239,68,68,0.4)' ?>;background:<?= $flashType === 'success' ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)' ?>;color:<?= $flashType === 'success' ? '#047857' : '#dc2626' ?>;">
        <?= htmlspecialchars($flash) ?>
    </div>

    <?php if ($customer): ?>
        <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:16px;padding:20px;margin-bottom:28px;box-shadow:var(--shadow-sm);">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                <div>
                    <p style="margin:0 0 4px;font-size:13px;text-transform:uppercase;letter-spacing:1px;color:var(--primary-gold-dark);font-weight:700;">Verified Customer</p>
                    <h2 style="margin:0;font-size:22px;color:var(--text-primary);font-family:'Playfair Display',serif;"><?= htmlspecialchars($customer['name']) ?></h2>
                    <p style="margin:6px 0 0;font-size:14px;color:var(--text-secondary);">
                        📱 <strong><?= htmlspecialchars($customer['phone']) ?></strong>
                        <?php if (!empty($customer['email'])): ?>
                            &nbsp;·&nbsp; ✉️ <?= htmlspecialchars($customer['email']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <form method="post" action="" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <button type="submit" name="logout_account" value="1" class="btn btn-secondary" style="padding:8px 18px;font-size:13px;border-radius:10px;">Sign Out</button>
                </form>
            </div>
        </div>

        <?php if (cj_feature('feat_order_history')): ?>
            <h2 class="product-title" style="font-size:22px;margin:24px 0 12px;">Order History</h2>
            <?php if (!$history): ?>
                <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;padding:20px;text-align:center;">
                    <p style="color:var(--text-secondary);margin:0 0 8px;">No orders found under this account yet.</p>
                    <p style="font-size:13px;color:var(--text-light);margin:0;">When you place an order with mobile <strong><?= htmlspecialchars($customer['phone']) ?></strong>, it will automatically appear here with live tracking.</p>
                </div>
            <?php else: foreach ($history as $pack):
                $o = $pack['order'];
                $total = function_exists('cj_order_payable_rupees') ? cj_order_payable_rupees($o) : (float) ($o['subtotal'] ?? 0);
            ?>
                <article class="cj-order-card" style="margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                        <div>
                            <p style="margin:0;font-weight:700;font-size:15px;">Order #<?= htmlspecialchars((string) $o['order_no']) ?></p>
                            <p style="font-size:12px;color:var(--text-secondary);margin:2px 0 0;"><?= htmlspecialchars(cj_format_ist($o['created_at'] ?? '')) ?></p>
                        </div>
                        <span style="font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px;background:rgba(212,175,55,0.15);color:var(--primary-gold-dark);text-transform:uppercase;">
                            <?= htmlspecialchars((string) $o['status']) ?>
                        </span>
                    </div>
                    <ul style="margin:10px 0;padding-left:20px;font-size:13px;color:var(--text-secondary);">
                        <?php foreach ($pack['items'] as $line): ?>
                            <li><?= htmlspecialchars((string) $line['product_name']) ?> · ₹<?= number_format((float) $line['unit_price'], 0) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border-color);padding-top:10px;margin-top:10px;">
                        <span style="font-size:14px;font-weight:700;">Total: ₹<?= number_format($total, 0) ?></span>
                        <a href="success?ref=<?= urlencode((string) $o['order_no']) ?>" style="font-size:13px;font-weight:600;color:var(--primary-gold-dark);text-decoration:none;">View Details →</a>
                    </div>
                </article>
            <?php endforeach; endif; ?>
        <?php endif; ?>

    <?php else: ?>
        <p style="color:var(--text-secondary);margin:4px 0 20px;font-size:14px;">Sign in to view your jewellery order history, or create a free account in seconds. You can always browse and order as a guest.</p>

        <!-- Segmented Tab Switcher -->
        <div class="cj-auth-tabs" role="tablist">
            <button type="button" class="cj-auth-tab <?= $activeTab === 'login' ? 'active' : '' ?>" id="tabBtnLogin" onclick="switchTab('login')">Sign In</button>
            <button type="button" class="cj-auth-tab <?= $activeTab === 'register' ? 'active' : '' ?>" id="tabBtnRegister" onclick="switchTab('register')">Create Account</button>
        </div>

        <!-- Sign In Form -->
        <div id="paneLogin" style="<?= $activeTab === 'login' ? '' : 'display:none;' ?>">
            <form method="post" action="" class="cj-account-page-form" id="cjLoginForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="account_action" value="login">
                
                <div>
                    <label style="display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:var(--text-primary);">Mobile Number or Email</label>
                    <input name="phone" type="text" value="<?= htmlspecialchars($oldLoginPhone) ?>" required maxlength="160" placeholder="10-digit mobile or email address" autocomplete="username" style="width:100%;">
                </div>

                <div>
                    <label style="display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:var(--text-primary);">Password</label>
                    <div class="cj-password-wrap">
                        <input name="password" type="password" required autocomplete="current-password" placeholder="Enter your password" id="loginPasswordInput">
                        <button type="button" class="cj-pwd-toggle" onclick="togglePasswordVisibility('loginPasswordInput', this)" aria-label="Toggle password visibility">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" id="loginSubmitBtn" class="btn" style="width:100%;padding:14px;font-size:15px;margin-top:8px;">Sign In</button>
                
                <p style="text-align:center;font-size:13px;color:var(--text-secondary);margin:14px 0 0;">
                    New to ChandraKala Jewellers? <a href="javascript:void(0)" onclick="switchTab('register')" style="color:var(--primary-gold-dark);font-weight:600;text-decoration:none;">Create an account</a>
                </p>
            </form>
        </div>

        <!-- Create Account Form -->
        <div id="paneRegister" style="<?= $activeTab === 'register' ? '' : 'display:none;' ?>">
            <form method="post" action="" class="cj-account-page-form" id="cjRegisterForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="account_action" value="register">

                <div>
                    <label style="display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:var(--text-primary);">Full Name</label>
                    <input name="name" type="text" value="<?= htmlspecialchars($oldRegName) ?>" required maxlength="120" placeholder="Enter your full name" autocomplete="name" style="width:100%;">
                </div>

                <div>
                    <label style="display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:var(--text-primary);">10-Digit Mobile Number</label>
                    <input name="phone" type="tel" value="<?= htmlspecialchars($oldRegPhone) ?>" required inputmode="numeric" maxlength="15" placeholder="e.g. 9876543210" autocomplete="tel" style="width:100%;">
                </div>

                <div>
                    <label style="display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:var(--text-primary);">Email Address <span style="font-weight:normal;color:var(--text-light);">(optional)</span></label>
                    <input name="email" type="text" inputmode="email" value="<?= htmlspecialchars($oldRegEmail) ?>" maxlength="160" placeholder="name@example.com" autocomplete="email" autocapitalize="off" spellcheck="false" style="width:100%;">
                </div>

                <div>
                    <label style="display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:var(--text-primary);">Create Password</label>
                    <div class="cj-password-wrap">
                        <input name="password" type="password" required minlength="6" placeholder="Min 6 characters" autocomplete="new-password" id="regPasswordInput">
                        <button type="button" class="cj-pwd-toggle" onclick="togglePasswordVisibility('regPasswordInput', this)" aria-label="Toggle password visibility">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" id="regSubmitBtn" class="btn" style="width:100%;padding:14px;font-size:15px;margin-top:8px;">Create Account</button>

                <p style="text-align:center;font-size:13px;color:var(--text-secondary);margin:14px 0 0;">
                    Already have an account? <a href="javascript:void(0)" onclick="switchTab('login')" style="color:var(--primary-gold-dark);font-weight:600;text-decoration:none;">Sign in</a>
                </p>
            </form>
        </div>

        <script>
        function switchTab(tab) {
            var pLogin = document.getElementById('paneLogin');
            var pReg = document.getElementById('paneRegister');
            var btnLogin = document.getElementById('tabBtnLogin');
            var btnReg = document.getElementById('tabBtnRegister');
            if (!pLogin || !pReg) return;
            if (tab === 'register') {
                pLogin.style.display = 'none';
                pReg.style.display = 'block';
                btnReg.classList.add('active');
                btnLogin.classList.remove('active');
            } else {
                pReg.style.display = 'none';
                pLogin.style.display = 'block';
                btnLogin.classList.add('active');
                btnReg.classList.remove('active');
            }
        }

        function togglePasswordVisibility(inputId, btn) {
            var inp = document.getElementById(inputId);
            if (!inp) return;
            var isPwd = inp.type === 'password';
            inp.type = isPwd ? 'text' : 'password';
            btn.innerHTML = isPwd
                ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
                : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        }

        function showAlert(msg, isSuccess) {
            var box = document.getElementById('cjAuthAlert');
            if (!box) return;
            box.textContent = msg;
            box.style.display = 'block';
            box.style.borderColor = isSuccess ? 'rgba(16,185,129,0.4)' : 'rgba(239,68,68,0.4)';
            box.style.background = isSuccess ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)';
            box.style.color = isSuccess ? '#047857' : '#dc2626';
            box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        (function () {
            var loginForm = document.getElementById('cjLoginForm');
            var regForm = document.getElementById('cjRegisterForm');

            function handleAuthSubmit(form, action, btnId, loadingText) {
                if (!form) return;
                form.addEventListener('submit', function (e) {
                    var btn = document.getElementById(btnId);
                    var origText = btn ? btn.textContent : '';
                    if (btn) {
                        btn.disabled = true;
                        btn.textContent = loadingText;
                    }
                    var fd = new FormData(form);
                    var payload = { action: action };
                    fd.forEach(function (v, k) {
                        if (k === 'email') v = (v || '').replace(/\s+/g, '').trim();
                        payload[k] = v;
                    });
                    var csrf = payload['csrf_token'] || '';

                    fetch('account-auth.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrf
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.ok) {
                            showAlert(res.message || 'Success! Redirecting...', true);
                            setTimeout(function () { location.href = 'account'; }, 400);
                        } else {
                            if (btn) {
                                btn.disabled = false;
                                btn.textContent = origText;
                            }
                            showAlert((res && res.message) ? res.message : 'Action failed. Please try again.', false);
                        }
                    })
                    .catch(function () {
                        // On network or unexpected parse failure, let native form submit proceed
                        form.submit();
                    });

                    e.preventDefault();
                });
            }

            handleAuthSubmit(loginForm, 'login', 'loginSubmitBtn', 'Signing in…');
            handleAuthSubmit(regForm, 'register', 'regSubmitBtn', 'Creating account…');
        })();
        </script>
    <?php endif; ?>
</main>
<?php include 'includes/footer.php'; ?>
</body>
</html>
