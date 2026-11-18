<?php
// delete_c.php

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/img-helper.php';

cj_auth_bootstrap($conn);
cj_require_role(['admin', 'superadmin'], 'admin');

// --- Image Folder Path ---
$imageFolderPath = '../catalogue/';

// --- Message Variable for Feedback ---
$message = '';
$message_type = ''; // 'success' or 'error'


// --- Handle Deletion Request ---
if (isset($_GET['delete_id'])) {
    // Validate CSRF token
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die("CSRF validation failed. Action aborted.");
    }
    $id_to_delete = intval($_GET['delete_id']); // Sanitize input

    if ($id_to_delete > 0) {
        // 1. Fetch the image filename before deleting the database record
        $sql_select_filename = "SELECT image_filename FROM products WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select_filename);
        $stmt_select->bind_param("i", $id_to_delete);
        $stmt_select->execute();
        $stmt_select->bind_result($image_filename_to_delete);
        $stmt_select->fetch();
        $stmt_select->close();

        // 2. Delete the record from the database
        $sql_delete_db = "DELETE FROM products WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete_db);
        $stmt_delete->bind_param("i", $id_to_delete);

        if ($stmt_delete->execute()) {
            // 3. If database deletion is successful, attempt to delete local file if present
            if ($image_filename_to_delete) {
                $file_path = $imageFolderPath . $image_filename_to_delete;
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            $message = "Product deleted successfully!";
            $message_type = 'success';
        } else {
            $message = "Error deleting product from database: " . $stmt_delete->error;
            $message_type = 'error';
        }
        $stmt_delete->close();
    } else {
        $message = "Invalid product ID provided.";
        $message_type = 'error';
    }
}

// --- Handle Delete All Request ---
if (isset($_GET['delete_all'])) {
    // Validate CSRF token
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die("CSRF validation failed. Action aborted.");
    }

    // 1. Fetch all product image filenames
    $sql_images = "SELECT image_filename FROM products";
    $result_images = $conn->query($sql_images);

    $deleted_files_count = 0;
    $failed_files_count = 0;

    if ($result_images && $result_images->num_rows > 0) {
        while ($row = $result_images->fetch_assoc()) {
            $image_filename = $row['image_filename'];
            if ($image_filename) {
                $file_path = $imageFolderPath . $image_filename;
                if (file_exists($file_path)) {
                    if (unlink($file_path)) {
                        $deleted_files_count++;
                    } else {
                        $failed_files_count++;
                    }
                }
            }
        }
    }

    // 2. Delete all records from database
    $sql_delete_all = "DELETE FROM products";
    if ($conn->query($sql_delete_all) === TRUE) {
        $message = "All products and catalogue photos deleted successfully! (Deleted $deleted_files_count files" . ($failed_files_count > 0 ? ", failed to delete $failed_files_count files" : "") . ")";
        $message_type = 'success';
    } else {
        $message = "Failed to delete products from database: " . $conn->error;
        $message_type = 'error';
    }
}

// --- Fetch all products to display (after potential deletion) ---
$hasCatCol = ($conn->query("SHOW COLUMNS FROM products LIKE 'category'")?->num_rows ?? 0) > 0;
$sql_fetch_all = $hasCatCol
    ? "SELECT id, name, image_filename, category, ocr_status, created_at FROM products ORDER BY id DESC"
    : "SELECT id, name, image_filename FROM products ORDER BY id DESC";
$result_fetch_all = $conn->query($sql_fetch_all);

$products_to_display = [];
$ocr_pending_count = 0;
if ($result_fetch_all && $result_fetch_all->num_rows > 0) {
    while ($row = $result_fetch_all->fetch_assoc()) {
        $products_to_display[] = $row;
        if ($hasCatCol && empty($row['ocr_status'])) {
            $ocr_pending_count++;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php include dirname(__DIR__) . '/includes/gtm-head.php'; ?>
    <title>Manage Catalogue Images - ChandraKala Jewellers</title>
    <link rel="stylesheet" href="/css/tailwind.min.css?v=9">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/css/admin.css">
    <?php include dirname(__DIR__) . '/includes/admin-head-theme.php'; ?>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .product-image-thumbnail {
            width: 100%;
            height: 140px;
            object-fit: cover;
            background-color: #f9fafb;
        }
        @media (min-width: 640px) {
            .product-image-thumbnail { height: 180px; }
        }
    </style>
</head>
<body class="admin-body min-h-screen bg-gray-50 text-gray-800">
<?php include dirname(__DIR__) . '/includes/gtm-body.php'; ?>

<?php
$cjShellPage     = 'catalogue';
$cjShellTitle    = 'Catalogue Photos';
$cjShellSubtitle = 'Manage custom catalogue designs & run AI OCR enrichment';
$cjShellEmbed    = !empty($_GET['embed']);
include dirname(__DIR__) . '/includes/admin-shell-start.php';
?>
        <?php if ($message): ?>
            <div class="admin-alert mb-4 sm:mb-6 p-3 sm:p-4 rounded-xl border flex items-start gap-2 text-sm
                <?php echo ($message_type === 'success') ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'; ?>">
                <?php if ($message_type === 'success'): ?>
                    <svg class="h-5 w-5 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?php else: ?>
                    <svg class="h-5 w-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <?php endif; ?>
                <span class="font-medium"><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-5 sm:mb-8 border-b border-gray-200 pb-4">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-gray-900 font-serif">Catalogue Image Stock</h2>
                <p class="text-xs text-gray-500 mt-0.5"><?php echo count($products_to_display); ?> image<?php echo count($products_to_display) === 1 ? '' : 's'; ?> · tap to manage</p>
            </div>
            <?php if (!empty($products_to_display)): ?>
                <div class="catalogue-header-actions flex flex-wrap gap-2">
                    <button type="button" id="delCatOcrBtn" onclick="runDeletePageOcr(false)" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2.5 px-4 rounded-xl text-xs sm:text-sm shadow-sm cursor-pointer">
                        <i class="fas fa-wand-magic-sparkles mr-1"></i> Auto-OCR Scan (<?= (int)$ocr_pending_count ?> Pending)
                    </button>
                    <a href="?delete_all=1&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>"
                       onclick="return confirm('Are you sure you want to delete ALL catalogue photos and database entries? This action is permanent and cannot be undone!');"
                       class="block w-full sm:w-auto text-center bg-red-50 hover:bg-red-100 text-red-600 font-semibold py-2.5 px-5 rounded-xl transition-all duration-150 text-sm border border-red-100 shadow-sm active:scale-[0.98]">
                        Delete All Photos
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div id="delCatOcrStatus" class="hidden mb-5 p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 font-medium"></div>

        <?php if (empty($products_to_display)): ?>
            <div class="admin-card bg-white rounded-xl border border-gray-100 shadow-sm p-8 sm:p-12 text-center text-gray-500 font-medium">
                <i class="fas fa-image text-4xl text-gray-200 mb-3"></i>
                <p>No images found in the catalogue to manage.</p>
                <a href="/admin/index.php?view=add-item" class="inline-block mt-4 text-xs font-semibold text-amber-600 hover:underline">Upload photos in Add Jewellery &amp; Catalogue &rarr;</a>
            </div>
        <?php else: ?>
            <div class="catalogue-grid-admin">
                <?php foreach ($products_to_display as $i => $product): ?>
                    <div class="catalogue-card-admin bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col" data-cat-card-id="<?php echo (int)$product['id']; ?>" style="animation-delay: <?php echo min($i * 0.03, 0.45); ?>s">
                        <img
                            src="<?php echo htmlspecialchars(cj_img_url($product['image_filename'], 400, 'catalogue')); ?>"
                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                            class="product-image-thumbnail"
                            loading="lazy"
                            onerror="this.onerror=null;this.src='https://placehold.co/400x180/E0E0E0/888888?text=Image+Not+Found';"
                        >
                        <div class="p-3 sm:p-4 flex flex-col justify-between flex-grow gap-2">
                            <?php if (!empty($product['category'])): ?>
                                <span class="self-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200" data-cat-pill><?php echo htmlspecialchars($product['category']); ?></span>
                            <?php endif; ?>
                            <p class="text-gray-800 font-semibold text-xs sm:text-sm text-center line-clamp-2 leading-snug" data-cat-title><?php echo htmlspecialchars($product['name']); ?></p>
                            <?php if (!empty($product['created_at']) && function_exists('cj_format_ist')): ?>
                                <p class="text-[10px] text-gray-400 text-center"><?php echo htmlspecialchars(cj_format_ist($product['created_at'], 'd M Y, h:i A')); ?></p>
                            <?php endif; ?>
                            <a href="?delete_id=<?php echo (int)$product['id']; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>"
                               onclick="return confirm('Are you sure you want to delete &quot;<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>&quot;? This action cannot be undone.');"
                               class="block w-full bg-red-50 hover:bg-red-100 text-red-600 font-semibold py-2.5 px-3 rounded-xl text-center text-xs transition-colors duration-150 border border-red-100 active:scale-[0.97]">
                                Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php include dirname(__DIR__) . '/includes/admin-shell-end.php'; ?>

    <script>
    (function () {
        var csrf = <?php echo json_encode((string)($_SESSION['csrf_token'] ?? '')); ?>;
        var running = false;
        window.runDeletePageOcr = async function (resetAll) {
            if (running) return;
            running = true;
            var btn = document.getElementById('delCatOcrBtn');
            var status = document.getElementById('delCatOcrStatus');
            if (btn) btn.disabled = true;
            if (status) { status.classList.remove('hidden'); status.textContent = 'Starting Background OCR Engine...'; }
            var first = true;
            try {
                while (true) {
                    var fd = new FormData();
                    fd.append('csrf_token', csrf);
                    fd.append('batch', '4');
                    if (resetAll && first) fd.append('reset_all', '1');
                    first = false;
                    var res = await fetch('/api/catalogue-ocr-worker.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                    var data = await res.json();
                    if (!data || !data.ok) {
                        if (status) status.textContent = 'OCR error: ' + ((data && data.error) || 'unknown');
                        break;
                    }
                    (data.updated || []).forEach(function (u) {
                        var card = document.querySelector('[data-cat-card-id="' + u.id + '"]');
                        if (!card) return;
                        var t = card.querySelector('[data-cat-title]');
                        if (t) t.textContent = u.name;
                    });
                    if (btn) btn.innerHTML = '<i class="fas fa-wand-magic-sparkles mr-1"></i> Auto-OCR Scan (' + (data.pending || 0) + ' Pending)';
                    if (status) status.textContent = data.pending > 0
                        ? ('Background OCR running: ' + data.processed + ' / ' + data.total + ' designs enriched...')
                        : ('✓ All ' + data.total + ' catalogue designs enriched with fancy SEO names & categories!');
                    if (!data.pending || data.updated_count === 0) break;
                    await new Promise(function (r) { setTimeout(r, 300); });
                }
            } catch (e) {
                if (status) status.textContent = 'OCR paused due to network error.';
            } finally {
                running = false;
                if (btn) btn.disabled = false;
            }
        };
        <?php if ($ocr_pending_count > 0): ?>
        setTimeout(function () { window.runDeletePageOcr(false); }, 1200);
        <?php endif; ?>
    })();
    </script>
</body>
</html>
