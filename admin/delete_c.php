<?php
// delete_c.php

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/img-helper.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

cj_auth_bootstrap($conn);
cj_require_role(['admin', 'superadmin'], 'admin');

$available_categories = function_exists('cj_default_product_types')
    ? cj_default_product_types()
    : ['Necklace Set', 'Bridal Set', 'Kundan Set', 'Mangalsutra', 'Tanmaniya', 'Pendant Butti Set', 'Earrings / Butti', 'Gold Chain', 'Dokiya', 'Bangles / Patala', 'Ring', 'Kada', 'Bracelet', 'Hathpan', 'Payal / Anklet', 'Kanchain', 'Nose Pin', 'Coin / Bar', 'Catalogue Design'];

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
            require_once dirname(__DIR__) . '/includes/indexnow-helper.php';
            if (function_exists('cj_trigger_auto_indexing')) {
                cj_trigger_auto_indexing($conn, [
                    'https://chandrakalajewellers.in/Catalogue'
                ], 'catalogue_delete');
            }
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

        <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-5 sm:mb-6 border-b border-gray-200 pb-4">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-gray-900 font-serif">Catalogue Image Stock</h2>
                <p class="text-xs text-gray-500 mt-0.5"><?php echo count($products_to_display); ?> design<?php echo count($products_to_display) === 1 ? '' : 's'; ?> · rename, recategorize, or delete</p>
            </div>
            <?php if (!empty($products_to_display)): ?>
                <div class="catalogue-header-actions flex flex-wrap gap-2 items-center">
                    <a href="/admin/index.php?view=add-item" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold py-2 px-3.5 rounded-xl text-xs sm:text-sm shadow-sm transition-colors">
                        <i class="fas fa-plus mr-1"></i> Upload More Photos
                    </a>
                    <a href="?delete_all=1&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>"
                       onclick="return confirm('Are you sure you want to delete ALL catalogue photos and database entries? This action is permanent and cannot be undone!');"
                       class="bg-red-50 hover:bg-red-100 text-red-600 font-semibold py-2 px-3.5 rounded-xl transition-all duration-150 text-xs sm:text-sm border border-red-100 shadow-sm active:scale-[0.98]">
                        Delete All
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($products_to_display)): ?>
        <!-- Search & Filter Controls -->
        <div class="bg-white p-3 sm:p-4 rounded-xl border border-gray-100 shadow-sm mb-6 flex flex-col sm:flex-row gap-3 items-center justify-between">
            <div class="relative w-full sm:w-72">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                <input type="text" id="catSearchInput" placeholder="Search designs..." class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-lg text-xs sm:text-sm focus:border-amber-500 focus:outline-none">
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <label for="catCategoryFilter" class="text-xs font-semibold text-gray-500 whitespace-nowrap">Category:</label>
                <select id="catCategoryFilter" class="w-full sm:w-48 py-2 px-2.5 border border-gray-200 rounded-lg text-xs sm:text-sm focus:border-amber-500 focus:outline-none bg-white">
                    <option value="">All Categories</option>
                    <?php foreach ($available_categories as $catOpt): ?>
                        <option value="<?= htmlspecialchars($catOpt) ?>"><?= htmlspecialchars($catOpt) ?></option>
                    <?php endforeach; ?>
                </select>
                <span id="catFilteredCount" class="text-xs text-gray-400 whitespace-nowrap"><?= count($products_to_display) ?> items</span>
            </div>
        </div>
        <?php endif; ?>

        <div id="delCatOcrStatus" class="hidden mb-5 p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 font-medium"></div>

        <?php if (empty($products_to_display)): ?>
            <div class="admin-card bg-white rounded-xl border border-gray-100 shadow-sm p-8 sm:p-12 text-center text-gray-500 font-medium">
                <i class="fas fa-image text-4xl text-gray-200 mb-3"></i>
                <p>No images found in the catalogue to manage.</p>
                <a href="/admin/index.php?view=add-item" class="inline-block mt-4 text-xs font-semibold text-amber-600 hover:underline">Upload photos in Add Jewellery &amp; Catalogue &rarr;</a>
            </div>
        <?php else: ?>
            <div class="catalogue-grid-admin" id="catCardsGrid">
                <?php foreach ($products_to_display as $i => $product): ?>
                    <?php
                    $prodCat = trim((string)($product['category'] ?? ''));
                    $prodName = trim((string)$product['name']);
                    ?>
                    <div class="catalogue-card-admin bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col transition-all duration-150" 
                         data-cat-card-id="<?php echo (int)$product['id']; ?>" 
                         data-cat-name="<?= htmlspecialchars(strtolower($prodName)) ?>"
                         data-cat-category="<?= htmlspecialchars($prodCat) ?>"
                         style="animation-delay: <?php echo min($i * 0.03, 0.45); ?>s">
                        
                        <div class="relative">
                            <img
                                src="<?php echo htmlspecialchars(cj_img_url($product['image_filename'], 400, 'catalogue')); ?>"
                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                                class="product-image-thumbnail"
                                loading="lazy"
                                onerror="this.onerror=null;this.src='https://placehold.co/400x180/E0E0E0/888888?text=Image+Not+Found';"
                            >
                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-black/60 text-white backdrop-blur-sm">
                                #<?= (int)$product['id'] ?>
                            </span>
                        </div>

                        <div class="p-3 sm:p-4 flex flex-col justify-between flex-grow gap-2.5">
                            <!-- View State -->
                            <div class="cat-card-view flex flex-col gap-1.5 text-center">
                                <?php if (!empty($prodCat)): ?>
                                    <span class="self-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200" data-cat-pill><?php echo htmlspecialchars($prodCat); ?></span>
                                <?php else: ?>
                                    <span class="self-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-600 border border-gray-200" data-cat-pill>Catalogue Design</span>
                                <?php endif; ?>
                                <p class="text-gray-800 font-semibold text-xs sm:text-sm line-clamp-2 leading-snug" data-cat-title><?php echo htmlspecialchars($prodName); ?></p>
                                <?php if (!empty($product['created_at']) && function_exists('cj_format_ist')): ?>
                                    <p class="text-[10px] text-gray-400"><?php echo htmlspecialchars(cj_format_ist($product['created_at'], 'd M Y, h:i A')); ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Edit State (Hidden by default) -->
                            <div class="cat-card-edit hidden flex flex-col gap-2 bg-amber-50/40 p-2.5 rounded-xl border border-amber-200">
                                <div>
                                    <label class="block text-[10px] font-bold text-amber-900 uppercase mb-0.5">Title / Name</label>
                                    <input type="text" class="cat-edit-name-input w-full p-1.5 border border-amber-300 rounded text-xs bg-white text-gray-800 focus:outline-none focus:border-amber-600 font-medium" value="<?= htmlspecialchars($prodName) ?>">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-amber-900 uppercase mb-0.5">Category</label>
                                    <select class="cat-edit-category-select w-full p-1.5 border border-amber-300 rounded text-xs bg-white text-gray-800 focus:outline-none focus:border-amber-600">
                                        <?php foreach ($available_categories as $cOpt): ?>
                                            <option value="<?= htmlspecialchars($cOpt) ?>" <?= strcasecmp($cOpt, $prodCat) === 0 ? 'selected' : '' ?>><?= htmlspecialchars($cOpt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="flex gap-1.5 pt-1">
                                    <button type="button" onclick="saveCatalogueCard(<?= (int)$product['id'] ?>)" class="flex-1 bg-amber-600 hover:bg-amber-700 text-white font-bold py-1.5 px-2 rounded text-xs transition-colors shadow-sm">
                                        <i class="fas fa-check mr-1"></i> Save
                                    </button>
                                    <button type="button" onclick="cancelCatalogueCard(<?= (int)$product['id'] ?>)" class="bg-white hover:bg-gray-100 text-gray-700 border border-gray-200 font-medium py-1.5 px-2 rounded text-xs transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2 pt-1 border-t border-gray-100 cat-card-actions">
                                <button type="button" onclick="editCatalogueCard(<?= (int)$product['id'] ?>)" class="flex-1 bg-amber-50 hover:bg-amber-100 text-amber-800 font-semibold py-2 px-2.5 rounded-xl text-center text-xs transition-colors border border-amber-200 flex items-center justify-center gap-1 active:scale-[0.97]">
                                    <i class="fas fa-pen text-[10px]"></i> Rename
                                </button>
                                <a href="?delete_id=<?php echo (int)$product['id']; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>"
                                   onclick="return confirm('Are you sure you want to delete &quot;<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>&quot;? This action cannot be undone.');"
                                   class="bg-red-50 hover:bg-red-100 text-red-600 font-semibold py-2 px-3 rounded-xl text-center text-xs transition-colors border border-red-100 active:scale-[0.97]"
                                   title="Delete Design">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php include dirname(__DIR__) . '/includes/admin-shell-end.php'; ?>

    <script>
    (function () {
        var csrf = <?php echo json_encode((string)($_SESSION['csrf_token'] ?? '')); ?>;
        
        // Search & Filter
        var searchInput = document.getElementById('catSearchInput');
        var catFilter = document.getElementById('catCategoryFilter');
        var countBadge = document.getElementById('catFilteredCount');
        
        function filterCards() {
            var q = (searchInput ? searchInput.value.toLowerCase().trim() : '');
            var cat = (catFilter ? catFilter.value : '');
            var cards = document.querySelectorAll('.catalogue-card-admin');
            var visible = 0;
            
            cards.forEach(function (card) {
                var name = card.getAttribute('data-cat-name') || '';
                var itemCat = card.getAttribute('data-cat-category') || '';
                var id = card.getAttribute('data-cat-card-id') || '';
                
                var matchesQ = !q || name.indexOf(q) !== -1 || id.indexOf(q) !== -1 || itemCat.toLowerCase().indexOf(q) !== -1;
                var matchesCat = !cat || itemCat.toLowerCase() === cat.toLowerCase();
                
                if (matchesQ && matchesCat) {
                    card.style.display = '';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            if (countBadge) countBadge.textContent = visible + ' items';
        }
        
        if (searchInput) searchInput.addEventListener('input', filterCards);
        if (catFilter) catFilter.addEventListener('change', filterCards);

        // Edit Card
        window.editCatalogueCard = function (id) {
            var card = document.querySelector('[data-cat-card-id="' + id + '"]');
            if (!card) return;
            var view = card.querySelector('.cat-card-view');
            var edit = card.querySelector('.cat-card-edit');
            var actions = card.querySelector('.cat-card-actions');
            if (view) view.classList.add('hidden');
            if (actions) actions.classList.add('hidden');
            if (edit) edit.classList.remove('hidden');
            var inp = edit.querySelector('.cat-edit-name-input');
            if (inp) inp.focus();
        };

        window.cancelCatalogueCard = function (id) {
            var card = document.querySelector('[data-cat-card-id="' + id + '"]');
            if (!card) return;
            var view = card.querySelector('.cat-card-view');
            var edit = card.querySelector('.cat-card-edit');
            var actions = card.querySelector('.cat-card-actions');
            if (edit) edit.classList.add('hidden');
            if (view) view.classList.remove('hidden');
            if (actions) actions.classList.remove('hidden');
        };

        window.saveCatalogueCard = async function (id) {
            var card = document.querySelector('[data-cat-card-id="' + id + '"]');
            if (!card) return;
            var edit = card.querySelector('.cat-card-edit');
            var nameInp = edit.querySelector('.cat-edit-name-input');
            var catSel = edit.querySelector('.cat-edit-category-select');
            
            var newName = (nameInp ? nameInp.value.trim() : '');
            var newCat = (catSel ? catSel.value.trim() : '');
            if (!newName) {
                alert('Please enter a design title/name.');
                return;
            }

            var saveBtn = edit.querySelector('button[type="button"]');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';
            }

            try {
                var fd = new FormData();
                fd.append('id', id);
                fd.append('name', newName);
                fd.append('category', newCat);
                fd.append('csrf_token', csrf);

                var res = await fetch('/api/catalogue-item-update.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                });
                var data = await res.json();
                if (!data || !data.ok) {
                    alert('Error: ' + ((data && data.error) || 'Failed to update item'));
                    return;
                }

                // Update DOM
                var titleEl = card.querySelector('[data-cat-title]');
                var pillEl = card.querySelector('[data-cat-pill]');
                if (titleEl) titleEl.textContent = newName;
                if (pillEl) {
                    pillEl.textContent = newCat || 'Catalogue Design';
                    pillEl.className = 'self-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold ' + (newCat ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-gray-100 text-gray-600 border border-gray-200');
                }
                card.setAttribute('data-cat-name', newName.toLowerCase());
                card.setAttribute('data-cat-category', newCat);

                // Show success feedback
                card.classList.add('ring-2', 'ring-emerald-500');
                setTimeout(function () {
                    card.classList.remove('ring-2', 'ring-emerald-500');
                }, 1800);

                window.cancelCatalogueCard(id);
            } catch (err) {
                alert('Network error while saving: ' + err.message);
            } finally {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-check mr-1"></i> Save';
                }
            }
        };
    })();
    </script>
</body>
</html>
