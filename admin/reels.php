<?php
/**
 * ChandraKala Jewellers — Admin Video Reels Manager & Impressions Analytics Portal
 * Features direct Browser-to-Cloudinary video upload (no 4.5MB Vercel limit),
 * IST upload date & time, Top-Viewed auto-ranking, Impressions/Views/Clicks/CTR analytics,
 * Comment moderation, and Reel Order Inquiries management.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/reels.php';

cj_auth_bootstrap($conn);
cj_ensure_reels_schema($conn);
cj_admin_noindex_headers();
cj_require_role(['admin', 'superadmin'], 'admin');

$currentRole = (string)($_SESSION['role'] ?? 'admin');
$tab = ($_GET['tab'] ?? 'reels') === 'orders' ? 'orders' : (($_GET['tab'] ?? 'reels') === 'comments' ? 'comments' : 'reels');
$rawRange = (string)($_GET['range'] ?? 'all');
$range = in_array($rawRange, ['all', 'today', '7d', '30d'], true) ? $rawRange : 'all';

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        $msg = 'Security CSRF validation failed. Please refresh and try again.';
        $msgType = 'error';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'toggle_reel') {
            $rid = (int)($_POST['reel_id'] ?? 0);
            if ($rid > 0) {
                $conn->query("UPDATE video_reels SET is_active = IF(is_active = 1, 0, 1) WHERE id = {$rid}");
                $msg = "Reel #{$rid} visibility updated.";
                $msgType = 'success';
            }
        } elseif ($action === 'update_note') {
            $rid = (int)($_POST['reel_id'] ?? 0);
            $note = mb_substr(trim((string)($_POST['admin_note'] ?? '')), 0, 200);
            if ($rid > 0) {
                $st = $conn->prepare("UPDATE video_reels SET admin_note = ? WHERE id = ?");
                if ($st) {
                    $st->bind_param('si', $note, $rid);
                    $st->execute();
                    $st->close();
                    $msg = "Internal note saved for Reel #{$rid}.";
                    $msgType = 'success';
                }
            }
        } elseif ($action === 'delete_reel') {
            $rid = (int)($_POST['reel_id'] ?? 0);
            if ($rid > 0) {
                $reel = cj_get_reel_by_id($conn, $rid);
                if ($reel && !empty($reel['public_id'])) {
                    @cj_cloudinary_destroy_video((string)$reel['public_id']);
                }
                $conn->query("DELETE FROM video_reel_events WHERE reel_id = {$rid}");
                $conn->query("DELETE FROM video_reel_likes WHERE reel_id = {$rid}");
                $conn->query("DELETE FROM video_reel_comments WHERE reel_id = {$rid}");
                $conn->query("DELETE FROM video_reel_orders WHERE reel_id = {$rid}");
                $conn->query("DELETE FROM video_reels WHERE id = {$rid}");
                $msg = "Reel #{$rid} permanently deleted.";
                $msgType = 'success';
            }
        } elseif ($action === 'delete_comment') {
            $cid = (int)($_POST['comment_id'] ?? 0);
            $rid = (int)($_POST['reel_id'] ?? 0);
            if ($cid > 0) {
                $conn->query("DELETE FROM video_reel_comments WHERE id = {$cid}");
                if ($rid > 0) {
                    $conn->query("UPDATE video_reels SET comments_count = (SELECT COUNT(*) FROM video_reel_comments WHERE reel_id = {$rid} AND is_approved = 1) WHERE id = {$rid}");
                }
                $msg = 'Comment deleted.';
                $msgType = 'success';
            }
        } elseif ($action === 'update_order_status') {
            $oid = (int)($_POST['order_id'] ?? 0);
            $rawStatus = (string)($_POST['status'] ?? '');
            $status = in_array($rawStatus, ['new', 'contacted', 'completed'], true) ? $rawStatus : 'new';
            if ($oid > 0) {
                $st = $conn->prepare("UPDATE video_reel_orders SET status = ? WHERE id = ?");
                if ($st) {
                    $st->bind_param('si', $status, $oid);
                    $st->execute();
                    $st->close();
                    $msg = "Order inquiry #{$oid} marked as " . ucfirst($status) . '.';
                    $msgType = 'success';
                }
            }
        }
    }
}

$analytics = cj_get_reel_analytics($conn, $range);
$reelsList = cj_get_ranked_reels($conn, 200, false, 'top');

$recentComments = [];
$cRes = $conn->query("SELECT c.*, r.poster_url FROM video_reel_comments c LEFT JOIN video_reels r ON r.id = c.reel_id ORDER BY c.id DESC LIMIT 100");
if ($cRes) {
    while ($cr = $cRes->fetch_assoc()) $recentComments[] = $cr;
}

$reelOrders = [];
$oRes = $conn->query("SELECT o.*, r.poster_url FROM video_reel_orders o LEFT JOIN video_reels r ON r.id = o.reel_id ORDER BY o.id DESC LIMIT 100");
if ($oRes) {
    while ($or = $oRes->fetch_assoc()) $reelOrders[] = $or;
}
$newOrdersCount = 0;
foreach ($reelOrders as $or) {
    if (($or['status'] ?? '') === 'new') $newOrdersCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Video Reels &amp; Impressions Analytics — ChandraKala Admin</title>
    <link rel="stylesheet" href="/css/tailwind.min.css?v=10">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/admin.css">
    <?php include dirname(__DIR__) . '/includes/admin-head-theme.php'; ?>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .reel-dropzone {
            border: 2px dashed rgba(212, 175, 55, 0.55);
            border-radius: 18px;
            padding: 28px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s ease;
            background: rgba(245, 158, 11, 0.04);
        }
        .reel-dropzone:hover, .reel-dropzone.is-dragover {
            border-color: #d97706;
            background: rgba(245, 158, 11, 0.1);
        }
        .reel-card-video {
            width: 100%;
            aspect-ratio: 9 / 14;
            object-fit: cover;
            border-radius: 14px;
            background: #0d1017;
            display: block;
        }
        html[data-theme="dark"] .reel-admin-card,
        html[data-theme="dark"] .reel-admin-panel {
            background: #161a22 !important;
            border-color: #2e3544 !important;
            color: #f3f4f6 !important;
        }
        html[data-theme="dark"] .reel-stat-chip {
            background: #10131a !important;
            border-color: #2a3242 !important;
            color: #e5e7eb !important;
        }
    </style>
</head>
<body class="admin-body bg-gray-50 text-gray-800 min-h-screen">

<header class="admin-header bg-white border-b border-gray-100 shadow-sm sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap justify-between items-center gap-3">
        <div class="flex items-center gap-3">
            <img src="/images/logo.png" alt="ChandraKala Jewellers" class="h-10 w-auto">
            <div>
                <h1 class="text-base sm:text-lg font-bold font-serif text-gray-900 leading-tight">ChandraKala Jewellers</h1>
                <p class="text-[11px] text-amber-600 font-semibold tracking-wider uppercase">Video Reels &amp; Impressions Studio</p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <?php include dirname(__DIR__) . '/includes/admin-theme-toggle.php'; ?>
            <a href="/reels" target="_blank" rel="noopener" class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-2 rounded-xl">Live Reels &nearr;</a>
            <a href="/admin/index.php" class="text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 px-3 py-2 rounded-xl">&larr; Store Admin</a>
            <a href="/admin/orders.php" class="text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-3 py-2 rounded-xl">Orders</a>
            <a href="/admin/customers.php" class="text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-3 py-2 rounded-xl">Customers</a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <?php if ($msg !== ''): ?>
        <div class="mb-5 p-4 rounded-xl border text-xs sm:text-sm font-semibold <?= $msgType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Top Controls: Tabs + Time Range Filter -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-2 bg-white reel-admin-panel border border-gray-200 p-1 rounded-xl shadow-sm">
            <a href="?tab=reels&amp;range=<?= urlencode($range) ?>" class="px-4 py-2 text-xs font-bold rounded-lg <?= $tab === 'reels' ? 'bg-amber-500 text-white' : 'text-gray-600' ?>">
                🎬 Reels &amp; Analytics (<?= count($reelsList) ?>)
            </a>
            <a href="?tab=orders&amp;range=<?= urlencode($range) ?>" class="px-4 py-2 text-xs font-bold rounded-lg <?= $tab === 'orders' ? 'bg-amber-500 text-white' : 'text-gray-600' ?>">
                🛍️ Order Inquiries (<?= count($reelOrders) ?><?= $newOrdersCount > 0 ? " · {$newOrdersCount} new" : '' ?>)
            </a>
            <a href="?tab=comments&amp;range=<?= urlencode($range) ?>" class="px-4 py-2 text-xs font-bold rounded-lg <?= $tab === 'comments' ? 'bg-amber-500 text-white' : 'text-gray-600' ?>">
                💬 Comments (<?= count($recentComments) ?>)
            </a>
        </div>

        <div class="flex items-center gap-1.5 bg-white reel-admin-panel border border-gray-200 p-1 rounded-xl shadow-sm">
            <?php foreach (['all' => 'All Time', 'today' => 'Today', '7d' => '7 Days', '30d' => '30 Days'] as $rk => $rl): ?>
                <a href="?tab=<?= urlencode($tab) ?>&amp;range=<?= $rk ?>" class="px-3 py-1.5 text-[11px] font-bold rounded-lg <?= $range === $rk ? 'bg-amber-500 text-white' : 'text-gray-600' ?>"><?= $rl ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Impressions & Engagement KPI Row -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="reel-admin-card bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
            <div class="text-[11px] font-bold text-gray-400 uppercase">Impressions</div>
            <div class="text-2xl font-bold font-serif text-gray-900 mt-1"><?= number_format((int)$analytics['impressions']) ?></div>
            <div class="text-[10px] text-gray-500 mt-0.5">Viewport ≥50% (1s+)</div>
        </div>
        <div class="reel-admin-card bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
            <div class="text-[11px] font-bold text-gray-400 uppercase">Reel Views</div>
            <div class="text-2xl font-bold font-serif text-gray-900 mt-1"><?= number_format((int)$analytics['views']) ?></div>
            <div class="text-[10px] text-gray-500 mt-0.5">Autoplay + Showcase</div>
        </div>
        <div class="reel-admin-card bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
            <div class="text-[11px] font-bold text-gray-400 uppercase">Clicks &amp; CTR</div>
            <div class="text-2xl font-bold font-serif text-amber-600 mt-1"><?= number_format((int)$analytics['clicks']) ?></div>
            <div class="text-[10px] text-gray-500 mt-0.5">CTR: <strong><?= htmlspecialchars((string)$analytics['ctr']) ?>%</strong></div>
        </div>
        <div class="reel-admin-card bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
            <div class="text-[11px] font-bold text-gray-400 uppercase">Likes</div>
            <div class="text-2xl font-bold font-serif text-gray-900 mt-1"><?= number_format((int)$analytics['likes']) ?></div>
            <div class="text-[10px] text-gray-500 mt-0.5">All-time hearts</div>
        </div>
        <div class="reel-admin-card bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
            <div class="text-[11px] font-bold text-gray-400 uppercase">Order / WA Leads</div>
            <div class="text-2xl font-bold font-serif text-emerald-600 mt-1"><?= number_format((int)$analytics['orders'] + (int)$analytics['whatsapp']) ?></div>
            <div class="text-[10px] text-gray-500 mt-0.5"><?= (int)$analytics['orders'] ?> orders · <?= (int)$analytics['whatsapp'] ?> WA</div>
        </div>
        <div class="reel-admin-card bg-white p-4 rounded-2xl border border-amber-200 shadow-sm">
            <div class="text-[11px] font-bold text-amber-600 uppercase">👑 Top Viewed</div>
            <?php if (!empty($analytics['top_reel'])): ?>
                <div class="text-xl font-bold font-serif text-gray-900 mt-1">Reel #<?= (int)$analytics['top_reel']['id'] ?></div>
                <div class="text-[10px] text-gray-500 mt-0.5"><?= number_format((int)$analytics['top_reel']['display_views']) ?> views</div>
            <?php else: ?>
                <div class="text-sm font-semibold text-gray-400 mt-2">No reels yet</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($tab === 'reels'): ?>
    <!-- Direct Cloudinary Video Upload Studio -->
    <section class="reel-admin-panel bg-white p-5 sm:p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
        <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
            <div>
                <h2 class="text-lg font-bold font-serif text-gray-900">Upload New Jewellery Reel Videos</h2>
                <p class="text-xs text-gray-500">Direct high-speed Cloudinary video upload (supports MP4, MOV, WebM up to 100 MB each). No title is shown on the storefront video wall — pure moving jewellery!</p>
            </div>
            <span class="text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-3 py-1 rounded-full">
                IST Time: <?= htmlspecialchars(cj_format_ist(null, 'd M Y, h:i A')) ?>
            </span>
        </div>

        <div class="mb-3">
            <label for="reelAdminNote" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Internal Admin Note (Optional — NEVER shown on website)</label>
            <input type="text" id="reelAdminNote" maxlength="200" placeholder="e.g. Bridal Kundan Haar shoot, 22K Mangalsutra reel..." class="w-full text-xs p-2.5 border border-gray-200 rounded-xl">
        </div>

        <div id="reelDropZone" class="reel-dropzone">
            <input type="file" id="reelFileInput" accept="video/mp4,video/quicktime,video/webm,video/*" multiple class="hidden">
            <div class="text-3xl mb-2">🎬</div>
            <div class="text-sm font-bold text-gray-800">Click or Drag &amp; Drop Video Reels Here</div>
            <div class="text-xs text-gray-500 mt-1">Select one or multiple vertical (9:16) or square jewellery videos · Auto-optimized for instant autoplay</div>
        </div>

        <div id="reelUploadQueue" class="mt-4 space-y-2"></div>
    </section>

    <!-- Uploaded Reels Grid with IST Timestamp & Impressions Breakdown -->
    <?php if (empty($reelsList)): ?>
        <div class="reel-admin-panel bg-white p-12 rounded-2xl border border-gray-100 text-center">
            <div class="text-4xl mb-2">✨</div>
            <h3 class="text-base font-bold text-gray-900">No Video Reels Uploaded Yet</h3>
            <p class="text-xs text-gray-500 mt-1">Upload your first jewellery reel above — it will automatically appear in the Cinematic Bento Masonry below Hot Favourites and on <strong>/reels</strong>.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            <?php foreach ($reelsList as $idx => $r):
                $rid = (int)$r['id'];
                $pr = ($range !== 'all' && isset($analytics['per_reel'][$rid])) ? $analytics['per_reel'][$rid] : null;
                $imp = $pr ? (int)$pr['impressions'] : (int)$r['impressions'];
                $vw = $pr ? (int)$pr['views'] : (int)$r['views'];
                $clk = $pr ? (int)$pr['clicks'] : (int)$r['clicks'];
                $wa = $pr ? (int)$pr['whatsapp'] : (int)$r['whatsapp_clicks'];
                $ord = $pr ? (int)$pr['orders'] : (int)$r['order_clicks'];
                $base = max($imp, $vw);
                $ctr = $base > 0 ? round(($clk / $base) * 100, 1) : 0.0;
                $ws = (int)($r['watch_sessions'] ?? 0);
                $avgWatchSec = $ws > 0 ? round(((int)($r['total_watch_ms'] ?? 0) / $ws) / 1000, 1) : 0.0;
                $istTime = cj_format_ist($r['created_at'] ?? '', 'd M Y, h:i A');
            ?>
            <div class="reel-admin-card bg-white rounded-2xl border <?= !empty($r['is_top_viewed']) ? 'border-amber-400' : 'border-gray-100' ?> shadow-sm overflow-hidden flex flex-col">
                <div class="relative p-3 pb-0">
                    <video class="reel-card-video"
                           src="<?= htmlspecialchars((string)$r['preview_url'], ENT_QUOTES, 'UTF-8') ?>"
                           <?php if (!empty($r['poster_thumb'])): ?>poster="<?= htmlspecialchars((string)$r['poster_thumb'], ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                           muted loop playsinline preload="metadata"
                           onmouseenter="this.play().catch(()=>{})" onmouseleave="this.pause()"></video>

                    <div class="absolute top-5 left-5 flex flex-wrap gap-1.5">
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-gray-900/80 text-white">#<?= $rid ?></span>
                        <?php if (!empty($r['is_top_viewed'])): ?>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-400 text-gray-950">👑 #1 Top Viewed</span>
                        <?php endif; ?>
                        <?php if (empty($r['is_active'])): ?>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-red-600 text-white">Hidden</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-3.5 flex-1 flex flex-col justify-between gap-3">
                    <div>
                        <div class="flex items-center justify-between gap-2 text-[11px] text-gray-500 mb-2">
                            <span title="Upload IST Date & Time">🕒 <?= htmlspecialchars($istTime) ?></span>
                            <span title="Average Watch Time per Swipe" class="font-semibold text-amber-600">⏱ <?= $avgWatchSec ?>s avg</span>
                            <a href="/showcase?v=<?= $rid ?>" target="_blank" rel="noopener" class="text-amber-600 font-bold hover:underline">Open &nearr;</a>
                        </div>

                        <!-- Live Metrics Grid -->
                        <div class="grid grid-cols-3 gap-1.5 text-center mb-2.5">
                            <div class="reel-stat-chip bg-gray-50 border border-gray-100 rounded-xl p-1.5">
                                <div class="text-[10px] text-gray-400">Impr.</div>
                                <div class="text-xs font-bold text-gray-900"><?= number_format($imp) ?></div>
                            </div>
                            <div class="reel-stat-chip bg-gray-50 border border-gray-100 rounded-xl p-1.5">
                                <div class="text-[10px] text-gray-400">Views</div>
                                <div class="text-xs font-bold text-gray-900"><?= number_format($vw) ?></div>
                            </div>
                            <div class="reel-stat-chip bg-gray-50 border border-gray-100 rounded-xl p-1.5">
                                <div class="text-[10px] text-gray-400">Clicks/CTR</div>
                                <div class="text-xs font-bold text-amber-600"><?= $clk ?> (<?= $ctr ?>%)</div>
                            </div>
                            <div class="reel-stat-chip bg-gray-50 border border-gray-100 rounded-xl p-1.5">
                                <div class="text-[10px] text-gray-400">❤️ Likes</div>
                                <div class="text-xs font-bold text-gray-900"><?= (int)$r['likes_count'] ?></div>
                            </div>
                            <div class="reel-stat-chip bg-gray-50 border border-gray-100 rounded-xl p-1.5">
                                <div class="text-[10px] text-gray-400">💬 Cmts</div>
                                <div class="text-xs font-bold text-gray-900"><?= (int)$r['comments_count'] ?></div>
                            </div>
                            <div class="reel-stat-chip bg-gray-50 border border-gray-100 rounded-xl p-1.5">
                                <div class="text-[10px] text-gray-400">🛍️ Leads</div>
                                <div class="text-xs font-bold text-emerald-600"><?= $ord + $wa ?></div>
                            </div>
                        </div>

                        <!-- Editable internal admin note -->
                        <form method="post" class="flex items-center gap-1.5">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="update_note">
                            <input type="hidden" name="reel_id" value="<?= $rid ?>">
                            <input type="text" name="admin_note" value="<?= htmlspecialchars((string)$r['admin_note']) ?>" placeholder="Internal note (admin only)" class="flex-1 text-[11px] px-2.5 py-1.5 border border-gray-200 rounded-lg">
                            <button type="submit" class="px-2.5 py-1.5 text-[11px] font-bold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">Save</button>
                        </form>
                    </div>

                    <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
                        <form method="post" class="flex-1">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="toggle_reel">
                            <input type="hidden" name="reel_id" value="<?= $rid ?>">
                            <button type="submit" class="w-full py-1.5 text-xs font-bold rounded-xl border <?= !empty($r['is_active']) ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-emerald-50 border-emerald-200 text-emerald-700' ?>">
                                <?= !empty($r['is_active']) ? 'Hide Reel' : 'Show Reel' ?>
                            </button>
                        </form>
                        <form method="post" onsubmit="return confirm('Delete Reel #<?= $rid ?> permanently from site and Cloudinary?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="delete_reel">
                            <input type="hidden" name="reel_id" value="<?= $rid ?>">
                            <button type="submit" class="px-3 py-1.5 text-xs font-bold rounded-xl bg-red-50 border border-red-200 text-red-600 hover:bg-red-100">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php elseif ($tab === 'orders'): ?>
    <!-- Order & Showroom Visit Inquiries From Reels -->
    <section class="reel-admin-panel bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
            <h2 class="text-base font-bold font-serif text-gray-900">Reel Order &amp; Showroom Visit Requests (<?= count($reelOrders) ?>)</h2>
        </div>
        <?php if (empty($reelOrders)): ?>
            <div class="p-10 text-center text-xs text-gray-500">No reel order inquiries received yet.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-xs">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-[11px]">
                        <tr>
                            <th class="px-4 py-3 text-left">Reel</th>
                            <th class="px-4 py-3 text-left">Customer</th>
                            <th class="px-4 py-3 text-left">Note &amp; Preferred Time</th>
                            <th class="px-4 py-3 text-left">Received (IST)</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($reelOrders as $o):
                            $wa = 'https://wa.me/91' . preg_replace('/\D+/', '', (string)$o['customer_phone']) . '?text=' . rawurlencode("Namaste {$o['customer_name']}! Thank you for enquiring about Reel #{$o['reel_id']} at ChandraKala Jewellers, Khedbrahma.");
                        ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="/showcase?v=<?= (int)$o['reel_id'] ?>" target="_blank" class="font-bold text-amber-600 hover:underline">Reel #<?= (int)$o['reel_id'] ?></a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="font-bold text-gray-900"><?= htmlspecialchars((string)$o['customer_name']) ?></div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <a href="tel:<?= htmlspecialchars((string)$o['customer_phone']) ?>" class="text-gray-600"><?= htmlspecialchars((string)$o['customer_phone']) ?></a>
                                    <a href="<?= htmlspecialchars($wa) ?>" target="_blank" class="text-emerald-600 font-bold hover:underline">WhatsApp &nearr;</a>
                                </div>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <?php if (!empty($o['preferred_time'])): ?>
                                    <div class="font-semibold text-amber-700">Time: <?= htmlspecialchars((string)$o['preferred_time']) ?></div>
                                <?php endif; ?>
                                <div class="text-gray-600"><?= htmlspecialchars((string)($o['message'] ?: '—')) ?></div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500"><?= htmlspecialchars(cj_format_ist($o['created_at'] ?? '', 'd M Y, h:i A')) ?></td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <form method="post" class="inline-flex items-center gap-1.5">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                    <select name="status" onchange="this.form.submit()" class="text-xs px-2 py-1 rounded-lg border border-gray-200">
                                        <option value="new" <?= ($o['status'] ?? '') === 'new' ? 'selected' : '' ?>>New</option>
                                        <option value="contacted" <?= ($o['status'] ?? '') === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                                        <option value="completed" <?= ($o['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php else: ?>
    <!-- Reel Comments & Reviews Moderation -->
    <section class="reel-admin-panel bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-base font-bold font-serif text-gray-900">Reel Comments &amp; Reviews (<?= count($recentComments) ?>)</h2>
        </div>
        <?php if (empty($recentComments)): ?>
            <div class="p-10 text-center text-xs text-gray-500">No comments posted on reels yet.</div>
        <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($recentComments as $cm): ?>
                <div class="p-4 flex flex-wrap justify-between items-start gap-3">
                    <div>
                        <div class="flex items-center gap-2 text-xs">
                            <a href="/showcase?v=<?= (int)$cm['reel_id'] ?>" target="_blank" class="font-bold text-amber-600">Reel #<?= (int)$cm['reel_id'] ?></a>
                            <span class="font-bold text-gray-900"><?= htmlspecialchars((string)$cm['customer_name']) ?></span>
                            <?php if (!empty($cm['customer_phone'])): ?><span class="text-gray-500">(<?= htmlspecialchars((string)$cm['customer_phone']) ?>)</span><?php endif; ?>
                            <?php if ((int)$cm['rating'] > 0): ?><span class="text-amber-500"><?= str_repeat('★', (int)$cm['rating']) ?></span><?php endif; ?>
                            <span class="text-gray-400">· <?= htmlspecialchars(cj_format_ist($cm['created_at'] ?? '', 'd M Y, h:i A')) ?></span>
                        </div>
                        <p class="text-xs text-gray-700 mt-1"><?= nl2br(htmlspecialchars((string)$cm['comment'])) ?></p>
                    </div>
                    <form method="post" onsubmit="return confirm('Delete this comment?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="delete_comment">
                        <input type="hidden" name="comment_id" value="<?= (int)$cm['id'] ?>">
                        <input type="hidden" name="reel_id" value="<?= (int)$cm['reel_id'] ?>">
                        <button type="submit" class="px-3 py-1 text-xs font-bold rounded-lg bg-red-50 text-red-600 border border-red-200">Delete</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>

<script>
(function () {
    const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    const dropZone = document.getElementById('reelDropZone');
    const fileInput = document.getElementById('reelFileInput');
    const queueEl = document.getElementById('reelUploadQueue');
    const noteInput = document.getElementById('reelAdminNote');
    if (!dropZone || !fileInput) return;

    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('is-dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('is-dragover'));
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('is-dragover');
        if (e.dataTransfer && e.dataTransfer.files.length) handleFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) handleFiles(fileInput.files);
        fileInput.value = '';
    });

    async function handleFiles(files) {
        let uploadedAny = false;
        for (const file of Array.from(files)) {
            if (!file.type.startsWith('video/')) {
                alert('Skipping "' + file.name + '" — please select video files (MP4, MOV, WebM).');
                continue;
            }
            const ok = await uploadSingleVideo(file);
            if (ok) uploadedAny = true;
        }
        if (uploadedAny) {
            setTimeout(() => window.location.reload(), 900);
        }
    }

    async function uploadSingleVideo(file) {
        const row = document.createElement('div');
        row.className = 'reel-stat-chip p-3 rounded-xl border border-gray-200 bg-gray-50';
        const mb = (file.size / (1024 * 1024)).toFixed(1);
        row.innerHTML = '<div class="flex justify-between items-center text-xs font-semibold mb-1.5"><span class="truncate pr-2"></span><span class="pct text-amber-600">Preparing...</span></div><div class="w-full h-2 rounded-full bg-gray-200 overflow-hidden"><div class="bar h-full bg-amber-500 transition-all" style="width:4%"></div></div>';
        row.querySelector('span').textContent = file.name + ' (' + mb + ' MB)';
        const pctEl = row.querySelector('.pct');
        const barEl = row.querySelector('.bar');
        queueEl.prepend(row);

        try {
            // 1. Get Cloudinary upload signature from PHP
            const sfd = new FormData();
            sfd.append('csrf_token', csrfToken);
            sfd.append('action', 'sign');
            const sRes = await fetch('/api/reel-upload-sign.php', { method: 'POST', body: sfd, credentials: 'same-origin' });
            const sig = await sRes.json();
            if (!sRes.ok || !sig.ok) throw new Error(sig.error || 'Could not authorize video upload.');

            // 2. Upload video directly to Cloudinary with live progress bar
            const cldResult = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', sig.upload_url, true);
                xhr.upload.onprogress = (ev) => {
                    if (ev.lengthComputable) {
                        const pct = Math.min(96, Math.round((ev.loaded / ev.total) * 100));
                        pctEl.textContent = pct + '% uploading...';
                        barEl.style.width = pct + '%';
                    }
                };
                xhr.onload = () => {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (xhr.status >= 200 && xhr.status < 300 && data.secure_url) resolve(data);
                        else reject(new Error(data?.error?.message || 'Cloudinary upload failed (' + xhr.status + ')'));
                    } catch (err) {
                        reject(new Error('Invalid Cloudinary response.'));
                    }
                };
                xhr.onerror = () => reject(new Error('Network error uploading video to Cloudinary.'));

                const ufd = new FormData();
                ufd.append('file', file);
                ufd.append('api_key', sig.api_key);
                ufd.append('timestamp', String(sig.timestamp));
                ufd.append('folder', sig.folder);
                ufd.append('public_id', sig.public_id);
                ufd.append('signature', sig.signature);
                xhr.send(ufd);
            });

            // 3. Save reel metadata + IST timestamp in TiDB
            pctEl.textContent = 'Saving...';
            barEl.style.width = '98%';
            const saveFd = new FormData();
            saveFd.append('csrf_token', csrfToken);
            saveFd.append('action', 'save');
            saveFd.append('public_id', cldResult.public_id || '');
            saveFd.append('video_url', cldResult.secure_url || '');
            saveFd.append('width', String(cldResult.width || 0));
            saveFd.append('height', String(cldResult.height || 0));
            saveFd.append('duration', String(cldResult.duration || 0));
            saveFd.append('admin_note', noteInput ? noteInput.value.trim() : '');

            const saveRes = await fetch('/api/reel-upload-sign.php', { method: 'POST', body: saveFd, credentials: 'same-origin' });
            const saved = await saveRes.json();
            if (!saveRes.ok || !saved.ok) throw new Error(saved.error || 'Could not save reel.');

            barEl.style.width = '100%';
            barEl.className = 'bar h-full bg-emerald-500';
            pctEl.className = 'pct text-emerald-600';
            pctEl.textContent = '✅ Live! Reel #' + saved.id + ' (' + (saved.uploaded_ist || 'IST') + ')';
            return true;
        } catch (err) {
            barEl.className = 'bar h-full bg-red-500';
            pctEl.className = 'pct text-red-600';
            pctEl.textContent = '❌ ' + (err.message || 'Upload failed');
            return false;
        }
    }
})();
</script>
</body>
</html>
