<?php
/**
 * Admin Notification Manager
 */
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/push.php';
require_once 'includes/activity-log.php';

cj_auth_bootstrap($conn);
cj_admin_noindex_headers();
if (empty($_SESSION['loggedin'])) {
    header('Location: admin.php');
    exit;
}
cj_require_role(['admin', 'superadmin'], 'admin');

$embed = !empty($_GET['embed']) || !empty($_POST['embed'])
    || (!empty($_SERVER['HTTP_REFERER']) && strpos((string)$_SERVER['HTTP_REFERER'], 'embed=1') !== false);

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF validation failed.');
    }
    $by = $_SESSION['username'] ?? ($_SESSION['role'] ?? 'admin');

    if (isset($_POST['send_custom'])) {
        $title = trim((string)$_POST['title']);
        $body = trim((string)$_POST['body']);
        $audience = (string)($_POST['audience'] ?? 'customer');
        $url = trim((string)($_POST['url_path'] ?? 'index1'));
        if (!in_array($audience, ['customer', 'admin', 'superadmin', 'all'], true)) $audience = 'customer';

        $scheduleType = (string)($_POST['schedule_type'] ?? 'now');
        $imageUrl = !empty($_POST['image_url']) ? trim((string)$_POST['image_url']) : null;

        $scheduledAt = null;
        $isConstant = 0;
        $constantTime = null;

        if ($scheduleType === 'scheduled' && !empty($_POST['scheduled_at'])) {
            $scheduledAt = date('Y-m-d H:i:s', strtotime($_POST['scheduled_at']));
        } elseif ($scheduleType === 'recurring' && !empty($_POST['constant_time'])) {
            $isConstant = 1;
            $constantTime = date('H:i:s', strtotime($_POST['constant_time']));
        }

        if ($title === '' || $body === '') {
            $message = 'Title and message are required.';
            $message_type = 'error';
        } else {
            $id = cj_queue_push($conn, $title, $body, $audience, $url ?: 'index1', $by, $imageUrl, $scheduledAt, $isConstant, $constantTime);
            if ($scheduleType === 'now') {
                $res = cj_dispatch_push_queue($conn, $id);
                $message = "Notification queued & sent. Delivered attempts OK: {$res['ok']}, failed: {$res['fail']}.";
            } else {
                $message = "Notification successfully scheduled.";
            }
            cj_log_activity($conn, 'send_notification', "{$title} → {$audience} (" . ucfirst($scheduleType) . ")");
        }
    }

    if (isset($_POST['send_template'])) {
        $slug = trim((string)$_POST['template_slug']);
        $id = cj_queue_template($conn, $slug, $by);
        if ($id) {
            $res = cj_dispatch_push_queue($conn, $id);
            $message = "Template sent. OK: {$res['ok']}, failed: {$res['fail']}.";
            cj_log_activity($conn, 'send_template', $slug);
        } else {
            $message = 'Template not found.';
            $message_type = 'error';
        }
    }
}

$templates = [];
$r = $conn->query('SELECT * FROM push_templates WHERE is_active = 1 ORDER BY id ASC');
if ($r) while ($row = $r->fetch_assoc()) $templates[] = $row;

$logs = [];
$r = $conn->query('SELECT * FROM push_logs ORDER BY id DESC LIMIT 30');
if ($r) while ($row = $r->fetch_assoc()) $logs[] = $row;

$subCounts = ['customer' => 0, 'admin' => 0, 'superadmin' => 0, 'all' => 0, 'pwa' => 0];
$r = $conn->query("SELECT audience, COUNT(*) c FROM push_subscriptions GROUP BY audience");
if ($r) while ($row = $r->fetch_assoc()) $subCounts[$row['audience']] = (int)$row['c'];
$r = $conn->query("SELECT COUNT(*) c FROM push_subscriptions WHERE is_pwa = 1");
if ($r) $subCounts['pwa'] = (int)($r->fetch_assoc()['c'] ?? 0);

$vapid = cj_vapid_config();
$home = (($_SESSION['role'] ?? '') === 'superadmin') ? 'superadmin' : 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Notification Manager — ChandraKala</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/admin.css">
    <?php include __DIR__ . '/includes/admin-head-theme.php'; ?>
    <link rel="manifest" href="manifest.webmanifest">
</head>
<body class="admin-body bg-gray-50 text-gray-800 min-h-screen">
<?php if (empty($embed)): ?>
<header class="admin-header bg-white shadow-sm border-b border-gray-100 py-4 px-4 sm:px-6 flex justify-between items-center sticky top-0 z-40">
    <div>
        <h1 class="text-lg font-bold font-serif">Notification Manager</h1>
        <p class="text-[10px] text-amber-600 font-semibold tracking-wide">PUSH · PWA · TEMPLATES</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <?php include __DIR__ . '/includes/admin-theme-toggle.php'; ?>
        <a href="<?= htmlspecialchars($home) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-xl text-sm border border-gray-200">Dashboard</a>
        <a href="<?= htmlspecialchars($home) ?>?logout" class="bg-red-50 text-red-600 font-semibold py-2 px-4 rounded-xl text-sm border border-red-100">Log Out</a>
    </div>
</header>
<?php else: ?>
<div class="bg-amber-50 border-b border-amber-100 px-4 py-2 text-xs text-amber-800 font-semibold flex items-center justify-between gap-2">
    <span>Notifications inside Control Center · <?= htmlspecialchars($_SESSION['username'] ?? 'user') ?></span>
    <?php include __DIR__ . '/includes/admin-theme-toggle.php'; ?>
</div>
<?php endif; ?>

<main class="admin-main w-full mx-auto p-4 sm:p-6 max-w-6xl">
    <?php if ($message): ?>
        <div class="admin-alert mb-4 p-4 rounded-xl border <?= $message_type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (empty($vapid['publicKey']) || strlen((string)$vapid['publicKey']) < 80): ?>
        <div class="admin-alert mb-4 p-4 rounded-xl border bg-rose-50 border-rose-200 text-rose-900 text-sm">
            <p class="font-bold mb-1">Push keys are missing on this server</p>
            <p>That is why “Allow notifications” fails. While logged in here, open:</p>
            <p class="mt-2"><a class="underline font-semibold" href="api/setup-vapid.php" target="_blank" rel="noopener">api/setup-vapid.php</a></p>
            <p class="mt-2 text-xs">Then come back and click <strong>Enable push on this device</strong>.</p>
        </div>
    <?php else: ?>
        <div class="admin-alert mb-4 p-4 rounded-xl border bg-emerald-50 border-emerald-200 text-emerald-900 text-xs">
            Push keys ready (public key length <?= (int)strlen((string)$vapid['publicKey']) ?>). You can enable notifications on this device below.
        </div>
    <?php endif; ?>

    <?php
    $totalSubs = (int)$subCounts['customer'] + (int)$subCounts['admin'] + (int)$subCounts['superadmin'];
    ?>
    <?php if ($totalSubs === 0): ?>
        <div class="admin-alert mb-4 p-4 rounded-xl border bg-amber-50 border-amber-200 text-amber-950 text-sm">
            <p class="font-bold mb-1">Why OK and FAIL both show 0</p>
            <p>No phones have allowed notifications yet. When you send a push, there is nobody to deliver it to — so the log records <strong>0 OK / 0 FAIL</strong>.</p>
            <ol class="list-decimal ml-5 mt-2 space-y-1 text-xs">
                <li>Open the public website (or this admin page) on a phone/computer.</li>
                <li>When the popup asks <strong>Allow notifications?</strong>, tap <strong>Allow</strong> (also allow the browser permission).</li>
                <li>Watch <em>Customer devices</em> below become at least 1.</li>
                <li>Then send a notification again — it can arrive even if the website/app is closed.</li>
            </ol>
            <p class="text-xs mt-2">Cron is only for <em>daily automatic</em> reminders. It is not required for the Send button above.</p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="admin-stat bg-white p-4 rounded-xl border border-gray-100"><p class="stat-label text-xs text-gray-400 uppercase font-semibold">Customer devices</p><p class="stat-value text-2xl font-bold"><?= (int)$subCounts['customer'] ?></p></div>
        <div class="admin-stat bg-white p-4 rounded-xl border border-gray-100"><p class="stat-label text-xs text-gray-400 uppercase font-semibold">PWA installs</p><p class="stat-value text-2xl font-bold"><?= (int)$subCounts['pwa'] ?></p></div>
        <div class="admin-stat bg-white p-4 rounded-xl border border-gray-100"><p class="stat-label text-xs text-gray-400 uppercase font-semibold">Admin devices</p><p class="stat-value text-2xl font-bold"><?= (int)$subCounts['admin'] ?></p></div>
        <div class="admin-stat bg-white p-4 rounded-xl border border-gray-100"><p class="stat-label text-xs text-gray-400 uppercase font-semibold">Superadmin devices</p><p class="stat-value text-2xl font-bold"><?= (int)$subCounts['superadmin'] ?></p></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <section class="admin-card bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
            <h2 class="text-base font-bold font-serif mb-3"><i class="fas fa-bell text-amber-500 mr-1"></i> Send custom notification</h2>
            <form method="post" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Title</label>
                    <input type="text" name="title" maxlength="160" required class="w-full p-2.5 border border-gray-200 rounded-lg text-sm" placeholder="Today's gold rate is live">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Message</label>
                    <textarea name="body" rows="2" maxlength="500" required class="w-full p-2.5 border border-gray-200 rounded-lg text-sm" placeholder="Tap to open ChandraKala Jewellers…"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Image URL (Optional banner)</label>
                    <input type="url" name="image_url" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm" placeholder="https://example.com/banner.jpg">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Audience</label>
                        <select name="audience" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                            <option value="customer">Customers / PWA users</option>
                            <option value="admin">Admins</option>
                            <option value="superadmin">Super admins</option>
                            <option value="all">Everyone</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Open page</label>
                        <input type="text" name="url_path" value="gallery" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm" placeholder="gallery">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 border-t border-gray-100 pt-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Delivery Schedule</label>
                        <select name="schedule_type" id="schedule_type" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm" onchange="toggleScheduleFields()">
                            <option value="now">Send Immediately</option>
                            <option value="scheduled">One-Time Scheduled</option>
                            <option value="recurring">Recurring Daily (Constant)</option>
                        </select>
                    </div>
                    <div id="scheduled_fields" class="hidden">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Scheduled Date & Time</label>
                        <input type="datetime-local" name="scheduled_at" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                    </div>
                    <div id="recurring_fields" class="hidden">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Daily Send Time</label>
                        <input type="time" name="constant_time" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                    </div>
                </div>
                <button type="submit" name="send_custom" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 rounded-lg text-sm mt-2">Send / Schedule notification</button>
            </form>
            <button type="button" id="enablePushBtn" class="mt-3 w-full border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-700 font-semibold py-2.5 rounded-lg text-sm">Enable push on this device</button>
        </section>

        <section class="admin-card bg-white p-5 rounded-xl border border-gray-100 shadow-sm">
            <h2 class="text-base font-bold font-serif mb-3"><i class="fas fa-lightbulb text-amber-500 mr-1"></i> Default templates</h2>
            <div class="space-y-3 max-h-[420px] overflow-y-auto">
                <?php foreach ($templates as $t): ?>
                    <form method="post" class="border border-gray-100 rounded-xl p-3 bg-gray-50/50">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="template_slug" value="<?= htmlspecialchars($t['slug']) ?>">
                        <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($t['title']) ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($t['body']) ?></p>
                        <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-wide"><?= htmlspecialchars($t['audience']) ?> · <?= htmlspecialchars($t['url_path']) ?></p>
                        <button type="submit" name="send_template" class="mt-2 text-xs font-bold text-amber-700 bg-amber-50 border border-amber-100 px-3 py-1.5 rounded-lg">Send now</button>
                    </form>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <section class="admin-card bg-white p-5 rounded-xl border border-gray-100 shadow-sm mb-8">
        <h2 class="text-base font-bold font-serif mb-3">Recent send log</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-xs uppercase text-gray-400">
                    <tr>
                        <th class="text-left py-2">When</th>
                        <th class="text-left py-2">Title</th>
                        <th class="text-left py-2">Audience</th>
                        <th class="text-right py-2">OK</th>
                        <th class="text-right py-2">Fail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (!$logs): ?>
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">No notifications sent yet.</td></tr>
                    <?php else: foreach ($logs as $log): ?>
                        <tr>
                            <td class="py-2.5 text-gray-500 whitespace-nowrap"><?= htmlspecialchars(date('d M H:i', strtotime($log['created_at']))) ?></td>
                            <td class="py-2.5 font-semibold"><?= htmlspecialchars($log['title']) ?></td>
                            <td class="py-2.5"><?= htmlspecialchars($log['audience']) ?></td>
                            <td class="py-2.5 text-right text-emerald-600 font-bold"><?= (int)$log['success_count'] ?></td>
                            <td class="py-2.5 text-right text-rose-600 font-bold"><?= (int)$log['fail_count'] ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-4">
            <strong>0 OK / 0 FAIL</strong> = no subscribed devices for that audience.<br>
            Daily auto reminders (optional on InfinityFree): open
            <code>cron/daily-push.php?key=…</code> once per day in a browser (secret in <code>config/cron-secret.php</code>).
        </p>
    </section>
</main>
<script src="js/pwa.js" defer></script>
<script>
document.getElementById('enablePushBtn')?.addEventListener('click', function () {
    var role = <?= json_encode($_SESSION['role'] ?? 'admin') ?>;
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Requesting permission…';
    if (window.CJPwa && window.CJPwa.subscribe) {
        window.CJPwa.subscribe(role).then(function (r) {
            btn.disabled = false;
            btn.textContent = 'Enable push on this device';
            if (r && r.ok) {
                alert('Push enabled on this device. Refresh the page — device count should increase. You can receive alerts even when the site is closed.');
                location.reload();
            } else {
                alert((r && r.detail) ? r.detail : 'Could not enable push. Try api/setup-vapid.php then Allow again.');
            }
        });
    } else {
        btn.disabled = false;
        btn.textContent = 'Enable push on this device';
        alert('Service worker not ready yet. Refresh and try again.');
    }
});

function toggleScheduleFields() {
    var type = document.getElementById('schedule_type').value;
    var sched = document.getElementById('scheduled_fields');
    var recur = document.getElementById('recurring_fields');
    if (sched) sched.classList.toggle('hidden', type !== 'scheduled');
    if (recur) recur.classList.toggle('hidden', type !== 'recurring');
}
</script>
</body>
</html>
