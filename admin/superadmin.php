<?php
/**
 * Super Admin Control Center — superadmin role only.
 * Includes store admin (embedded), messaging, data, notifications, activity log.
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/push.php';
require_once dirname(__DIR__) . '/includes/visitor-analytics-schema.php';
require_once dirname(__DIR__) . '/includes/activity-log.php';
require_once dirname(__DIR__) . '/includes/payments.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

cj_auth_bootstrap($conn);
cj_admin_noindex_headers();

// Logout first — clear remember cookie/token even if PHP session already expired.
if (isset($_GET['logout'])) {
    if (!empty($_SESSION['loggedin'])) {
        cj_log_activity($conn, 'logout', 'Signed out from control center');
    }
    cj_invalidate_remember_tokens($conn, 0);
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: ../secret');
    exit;
}

// Restore session from remember-me cookie when PHP session expired.
if (empty($_SESSION['loggedin'])) {
    cj_try_remember_login($conn, ['superadmin']);
}

$role = (string)($_SESSION['role'] ?? '');
$loggedIn = !empty($_SESSION['loggedin']);

if (!$loggedIn || $role !== 'superadmin') {
    header("HTTP/1.1 404 Not Found");
    echo "<h1>404 Not Found</h1>";
    echo "The page you requested could not be found.";
    exit();
}

cj_ensure_visitor_analytics_schema($conn);
cj_ensure_activity_log_schema($conn);

$tab = (string)($_GET['tab'] ?? 'overview');
$allowedTabs = ['overview', 'store', 'integrations', 'controls', 'data', 'notifications', 'activity'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'overview';
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
        die('CSRF');
    }
    if (isset($_POST['save_integrations']) || isset($_POST['test_razorpay']) || isset($_POST['clear_razorpay'])) {
        $plain = [
            'whatsapp_number', 'shop_phone_display', 'shop_email',
            'instagram_url', 'google_review_url', 'gtm_id', 'ga4_id',
            'google_site_verification', 'bing_site_verification', 'yandex_verification', 'cdn_base_url',
        ];
        foreach ($plain as $key) {
            if (isset($_POST[$key])) {
                cj_setting_set($conn, $key, trim((string) $_POST[$key]));
            }
        }
        cj_setting_set($conn, 'reviews_auto_approve', isset($_POST['reviews_auto_approve']) ? '1' : '0');
        foreach (['smtp_host', 'smtp_port', 'smtp_user', 'smtp_from', 'smtp_secure'] as $smtpKey) {
            if (isset($_POST[$smtpKey])) {
                cj_setting_set($conn, $smtpKey, trim((string) $_POST[$smtpKey]));
            }
        }
        $smtpPass = trim((string) ($_POST['smtp_pass'] ?? ''));
        if ($smtpPass !== '') {
            cj_setting_set($conn, 'smtp_pass', $smtpPass);
        }
        $rzpId = trim((string) ($_POST['razorpay_key_id'] ?? ''));
        if ($rzpId !== '') {
            cj_setting_set($conn, 'razorpay_key_id', $rzpId);
        }
        foreach (['razorpay_key_secret', 'razorpay_webhook_secret'] as $secretKey) {
            $posted = trim((string) ($_POST[$secretKey] ?? ''));
            if ($posted !== '') {
                cj_setting_set($conn, $secretKey, $posted);
            }
        }
        if (!empty($_POST['clear_razorpay'])) {
            cj_setting_set($conn, 'razorpay_key_id', '');
            cj_setting_set($conn, 'razorpay_key_secret', '');
            cj_setting_set($conn, 'razorpay_webhook_secret', '');
            $msg = 'Razorpay disconnected. Checkout will use WhatsApp / pay later.';
        } elseif (isset($_POST['test_razorpay'])) {
            try {
                if (!cj_razorpay_keys_ready()) {
                    $msg = 'Save a Key ID and Key Secret first, then test.';
                } else {
                    cj_razorpay_api('GET', '/orders?count=1');
                    $msg = 'Razorpay connection succeeded.';
                }
            } catch (Throwable $e) {
                $msg = 'Razorpay test failed: ' . $e->getMessage();
            }
        } else {
            $msg = 'Integrations saved.';
        }
        cj_log_activity($conn, 'integrations', $msg);
        $tab = 'integrations';
    } elseif (isset($_POST['save_controls'])) {
        foreach (cj_settings_flag_keys() as $flagKey) {
            if ($flagKey === 'reviews_auto_approve') {
                continue;
            }
            cj_setting_set($conn, $flagKey, isset($_POST[$flagKey]) ? '1' : '0');
        }
        $gstPct = (float) ($_POST['gst_percent'] ?? 3);
        $gstPct = max(0, min(28, $gstPct));
        cj_setting_set($conn, 'gst_percent', (string) $gstPct);
        $recs = (int) ($_POST['recs_limit'] ?? 6);
        cj_setting_set($conn, 'recs_limit', (string) max(2, min(12, $recs)));
        $msg = 'Site controls saved. The shop now follows these on/off settings.';
        cj_log_activity($conn, 'site_controls', $msg);
        $tab = 'controls';
    } elseif (isset($_POST['approve_review']) || isset($_POST['reject_review'])) {
        $rid = (int) ($_POST['review_id'] ?? 0);
        if ($rid > 0 && isset($_POST['approve_review'])) {
            $conn->query('UPDATE product_reviews SET is_approved = 1 WHERE id = ' . $rid);
            $msg = 'Review approved.';
        } elseif ($rid > 0) {
            $conn->query('DELETE FROM product_reviews WHERE id = ' . $rid);
            $msg = 'Review removed.';
        }
        $tab = 'integrations';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF');
    }
    $to = (string)($_POST['to_role'] ?? 'admin');
    $subject = trim((string)$_POST['subject']);
    $body = trim((string)$_POST['body']);
    if (!in_array($to, ['admin', 'customer', 'all'], true)) {
        $to = 'admin';
    }
    if ($subject && $body) {
        $fromRole = (($_SESSION['role'] ?? '') === 'superadmin') ? 'superadmin' : 'admin';
        $stmt = $conn->prepare("INSERT INTO internal_messages (from_role, to_role, subject, body) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $fromRole, $to, $subject, $body);
        $stmt->execute();
        $stmt->close();
        $audience = $to === 'customer' ? 'customer' : ($to === 'all' ? 'all' : 'admin');
        $qid = cj_queue_push($conn, $subject, $body, $audience, $to === 'admin' ? 'admin' : 'index1', $_SESSION['username'] ?? $fromRole);
        cj_dispatch_push_queue($conn, $qid);
        cj_log_activity($conn, 'send_message', "To {$to}: {$subject}");
        $msg = 'Message saved and notification dispatched.';
    }
}

$dataTab = (string)($_GET['data_tab'] ?? 'products');
$dataAllowed = ['products', 'catalogue', 'visitors', 'push', 'messages'];
if (!in_array($dataTab, $dataAllowed, true)) {
    $dataTab = 'products';
}

$stats = [
    'products' => (int)(($conn->query('SELECT COUNT(*) c FROM jewellery_items')->fetch_assoc()['c']) ?? 0),
    'catalogue' => (int)(($conn->query('SELECT COUNT(*) c FROM products')->fetch_assoc()['c']) ?? 0),
    'sessions_today' => (int)(($conn->query("SELECT COUNT(*) c FROM visitor_sessions WHERE started_at >= CURDATE()")->fetch_assoc()['c']) ?? 0),
    'push_devices' => (int)(($conn->query('SELECT COUNT(*) c FROM push_subscriptions')->fetch_assoc()['c']) ?? 0),
    'unread_admin' => (int)(($conn->query("SELECT COUNT(*) c FROM internal_messages WHERE to_role IN ('admin','all') AND is_read = 0")->fetch_assoc()['c']) ?? 0),
];

$messages = [];
$r = $conn->query('SELECT * FROM internal_messages ORDER BY id DESC LIMIT 25');
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $messages[] = $row;
    }
}

$activityLogs = ($tab === 'activity') ? cj_fetch_activity_logs($conn, 150) : [];

$dataRows = [];
$dataTitle = '';
if ($tab === 'data') {
    if ($dataTab === 'products') {
        $dataTitle = 'Jewellery stock';
        $rq = $conn->query('SELECT id, name, metal_type, weight, is_sold_out, is_favourite, created_at FROM jewellery_items ORDER BY id DESC LIMIT 100');
    } elseif ($dataTab === 'catalogue') {
        $dataTitle = 'Catalogue designs';
        $rq = $conn->query('SELECT id, name, image_filename FROM products ORDER BY id DESC LIMIT 100');
    } elseif ($dataTab === 'visitors') {
        $dataTitle = 'Visitor sessions';
        $rq = $conn->query('SELECT session_key, started_at, last_seen_at, duration_seconds, page_count, geo_label, geo_status, landing_page FROM visitor_sessions ORDER BY last_seen_at DESC LIMIT 100');
    } elseif ($dataTab === 'push') {
        $dataTitle = 'Push subscriptions';
        $rq = $conn->query('SELECT id, audience, is_pwa, created_at, last_seen_at, LEFT(endpoint, 60) AS endpoint_preview FROM push_subscriptions ORDER BY id DESC LIMIT 100');
    } else {
        $dataTitle = 'Internal messages';
        $rq = $conn->query('SELECT id, from_role, to_role, subject, is_read, created_at FROM internal_messages ORDER BY id DESC LIMIT 100');
    }
    if (!empty($rq)) {
        while ($row = $rq->fetch_assoc()) {
            $dataRows[] = $row;
        }
    }
}

$nav = [
    'overview' => 'Overview',
    'store' => 'Store Admin',
    'integrations' => 'Integrations',
    'controls' => 'Site controls',
    'data' => 'All data',
    'notifications' => 'Notifications',
    'activity' => 'Activity log',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Control Center — ChandraKala Jewellers</title>
    <link rel="stylesheet" href="../css/tailwind.min.css?v=9">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <?php include dirname(__DIR__) . '/includes/admin-head-theme.php'; ?>
    <link rel="manifest" href="manifest.webmanifest">
</head>
<body class="admin-body sa-body<?= $tab === 'store' || $tab === 'notifications' ? ' sa-embed-mode' : '' ?>">
<header class="sa-header sticky top-0 z-40 px-4 sm:px-6 py-3 flex flex-wrap gap-3 justify-between items-center">
    <div class="flex items-center gap-3 min-w-0">
        <img src="images/logo.png" alt="" class="h-10 w-10 rounded-full border border-amber-200 bg-white object-contain p-0.5 shrink-0">
        <div class="min-w-0">
            <h1 class="text-lg font-bold font-serif text-gray-900 truncate">Control Center</h1>
            <p class="text-[10px] text-amber-700 tracking-widest uppercase font-semibold">
                <?= htmlspecialchars($_SESSION['display_name'] ?? 'User') ?>
                · <?= htmlspecialchars($_SESSION['role'] ?? 'admin') ?>
            </p>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <?php include dirname(__DIR__) . '/includes/admin-theme-toggle.php'; ?>
        <nav class="sa-nav flex flex-wrap gap-2 text-sm">
            <?php foreach ($nav as $key => $label): ?>
                <a class="sa-nav-link <?= $tab === $key ? 'is-active' : '' ?>" href="?tab=<?= urlencode($key) ?>"><?= htmlspecialchars($label) ?></a>
            <?php endforeach; ?>
            <a class="sa-nav-link" href="index.php" target="_blank" rel="noopener">Open store admin ↗</a>
            <a class="sa-nav-link sa-nav-danger" href="?logout">Log out</a>
        </nav>
    </div>
</header>

<?php if ($tab === 'store'): ?>
    <?php
    $storeFocus = (string)($_GET['focus'] ?? '');
    $storeSrc = 'index.php?embed=1';
    if ($storeFocus === 'follow') {
        $storeSrc .= '#followUsSection';
    }
    ?>
    <iframe class="sa-iframe" title="Store Admin" src="<?= htmlspecialchars($storeSrc) ?>"></iframe>
<?php elseif ($tab === 'notifications'): ?>
    <iframe class="sa-iframe" title="Notifications" src="notifications.php?embed=1"></iframe>
<?php else: ?>
<main class="admin-main max-w-7xl mx-auto p-4 sm:p-6">
    <?php if ($msg): ?>
        <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($tab === 'overview'): ?>
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
            <?php
            $cards = [
                ['Products', $stats['products'], '?tab=store'],
                ['Catalogue', $stats['catalogue'], '?tab=data&data_tab=catalogue'],
                ['Visitors today', $stats['sessions_today'], '?tab=data&data_tab=visitors'],
                ['Push devices', $stats['push_devices'], '?tab=notifications'],
                ['Unread → admin', $stats['unread_admin'], '#messages'],
            ];
            foreach ($cards as $c): ?>
                <a href="<?= htmlspecialchars($c[2]) ?>" class="sa-stat-card block">
                    <p class="text-[10px] uppercase tracking-wide text-gray-500 font-semibold"><?= htmlspecialchars($c[0]) ?></p>
                    <p class="text-2xl font-bold text-gray-900 mt-1 font-serif"><?= (int)$c[1] ?></p>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <section id="messages" class="sa-panel">
                <h2 class="font-serif text-lg font-bold mb-1 text-gray-900">Send message</h2>
                <p class="text-xs text-gray-500 mb-4">Message store admin and/or push customers.</p>
                <form method="post" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div>
                        <label class="text-xs uppercase text-gray-500 font-semibold">To</label>
                        <select name="to_role" class="sa-input w-full mt-1">
                            <option value="admin">Store Admin</option>
                            <option value="customer">Customers (push)</option>
                            <option value="all">Everyone</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs uppercase text-gray-500 font-semibold">Subject</label>
                        <input name="subject" required class="sa-input w-full mt-1" maxlength="200">
                    </div>
                    <div>
                        <label class="text-xs uppercase text-gray-500 font-semibold">Message</label>
                        <textarea name="body" required rows="4" class="sa-input w-full mt-1"></textarea>
                    </div>
                    <button type="submit" name="send_message" class="sa-btn-primary w-full">Send message + notify</button>
                </form>
            </section>

            <section class="sa-panel">
                <h2 class="font-serif text-lg font-bold mb-3 text-gray-900">Message history</h2>
                <div class="space-y-2 max-h-[420px] overflow-y-auto">
                    <?php if (!$messages): ?>
                        <p class="text-sm text-gray-500">No messages yet.</p>
                    <?php else: foreach ($messages as $m): ?>
                        <article class="sa-message">
                            <div class="flex justify-between gap-2 text-[11px] text-gray-500">
                                <span><?= htmlspecialchars($m['from_role']) ?> → <?= htmlspecialchars($m['to_role']) ?></span>
                                <span><?= htmlspecialchars(date('d M H:i', strtotime($m['created_at']))) ?></span>
                            </div>
                            <p class="font-semibold text-sm mt-1 text-gray-900"><?= htmlspecialchars($m['subject']) ?></p>
                            <p class="text-xs text-gray-600 mt-1 whitespace-pre-wrap"><?= htmlspecialchars($m['body']) ?></p>
                        </article>
                    <?php endforeach; endif; ?>
                </div>
            </section>
        </div>

        <section class="sa-panel mb-8">
            <h2 class="font-serif text-lg font-bold mb-2 text-gray-900">Quick actions</h2>
            <div class="flex flex-wrap gap-2">
                <a href="?tab=store" class="sa-btn-primary">Open Store Admin</a>
                <a href="?tab=integrations" class="sa-btn-secondary">Integrations / Razorpay</a>
                <a href="?tab=controls" class="sa-btn-secondary">Site controls / GST</a>
                <a href="?tab=store&amp;focus=follow" class="sa-btn-secondary">Follow Us / Reviews</a>
                <a href="?tab=activity" class="sa-btn-secondary">View activity log</a>
                <a href="?tab=notifications" class="sa-btn-secondary">Notifications</a>
                <a href="?tab=data" class="sa-btn-secondary">Browse all data</a>
            </div>
            <p class="text-xs text-gray-500 mt-4">Daily pushes (browser once/day): <code class="text-amber-800 bg-amber-50 px-1.5 py-0.5 rounded">cron/daily-push.php?key=…</code></p>
        </section>

    <?php elseif ($tab === 'data'): ?>
        <div class="flex flex-wrap gap-2 mb-4">
            <?php foreach ($dataAllowed as $t): ?>
                <a href="?tab=data&data_tab=<?= urlencode($t) ?>" class="sa-nav-link <?= $dataTab === $t ? 'is-active' : '' ?>"><?= htmlspecialchars(ucfirst($t)) ?></a>
            <?php endforeach; ?>
        </div>
        <h2 class="font-serif font-semibold mb-3 text-gray-900"><?= htmlspecialchars($dataTitle) ?> <span class="text-gray-400 font-normal text-sm font-sans">(latest 100)</span></h2>
        <div class="sa-table-wrap overflow-x-auto">
            <table class="min-w-full text-xs sm:text-sm">
                <thead>
                    <tr>
                        <?php if ($dataRows): foreach (array_keys($dataRows[0]) as $col): ?>
                            <th class="text-left p-3"><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; else: ?>
                            <th class="p-3">No rows</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dataRows as $row): ?>
                        <tr>
                            <?php foreach ($row as $val): ?>
                                <td class="p-3 whitespace-nowrap max-w-[220px] overflow-hidden text-ellipsis"><?= htmlspecialchars((string)$val) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($tab === 'activity'): ?>
        <section class="sa-panel mb-4">
            <h2 class="font-serif text-lg font-bold text-gray-900 mb-1">Activity log</h2>
            <p class="text-xs text-gray-500 mb-4">Who did what (Hasmukh vs Deven vs Jaykumar) — latest 150 actions.</p>
            <div class="sa-table-wrap overflow-x-auto">
                <table class="min-w-full text-xs sm:text-sm">
                    <thead>
                        <tr>
                            <th class="text-left p-3">When</th>
                            <th class="text-left p-3">User</th>
                            <th class="text-left p-3">Role</th>
                            <th class="text-left p-3">Action</th>
                            <th class="text-left p-3">Details</th>
                            <th class="text-left p-3">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$activityLogs): ?>
                            <tr><td class="p-4 text-gray-500" colspan="6">No activity recorded yet. Actions in Store Admin and Control Center will appear here.</td></tr>
                        <?php else: foreach ($activityLogs as $log): ?>
                            <tr>
                                <td class="p-3 whitespace-nowrap"><?= htmlspecialchars(date('d M Y H:i:s', strtotime($log['created_at']))) ?></td>
                                <td class="p-3 font-semibold"><?= htmlspecialchars($log['username']) ?></td>
                                <td class="p-3"><span class="sa-role-pill"><?= htmlspecialchars($log['role']) ?></span></td>
                                <td class="p-3 font-medium text-amber-900"><?= htmlspecialchars($log['action']) ?></td>
                                <td class="p-3 max-w-[280px] truncate" title="<?= htmlspecialchars((string)$log['details']) ?>"><?= htmlspecialchars((string)$log['details']) ?></td>
                                <td class="p-3 text-gray-500"><?= htmlspecialchars((string)$log['ip']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($tab === 'integrations'): ?>
        <?php
        $pendingReviews = [];
        $pr = $conn->query('SELECT * FROM product_reviews WHERE is_approved = 0 ORDER BY id DESC LIMIT 40');
        if ($pr) {
            while ($row = $pr->fetch_assoc()) {
                $pendingReviews[] = $row;
            }
        }
        $abandoned = [];
        $ar = $conn->query('SELECT * FROM abandoned_carts ORDER BY last_seen_at DESC LIMIT 40');
        if ($ar) {
            while ($row = $ar->fetch_assoc()) {
                $abandoned[] = $row;
            }
        }
        include dirname(__DIR__) . '/includes/superadmin-integrations.php';
        ?>
    <?php elseif ($tab === 'controls'): ?>
        <?php include dirname(__DIR__) . '/includes/superadmin-controls.php'; ?>
    <?php endif; ?>
</main>
<?php endif; ?>

<script src="../js/pwa.js" defer></script>
<script>
setTimeout(function(){
  if (window.CJPwa) window.CJPwa.subscribe(<?= json_encode(($_SESSION['role'] ?? '') === 'superadmin' ? 'superadmin' : 'admin') ?>);
}, 3000);
</script>
</body>
</html>
