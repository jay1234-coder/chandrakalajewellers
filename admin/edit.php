<?php
require_once dirname(__DIR__) . '/config/db.php'; // Database connection
if (function_exists('cj_is_local_host') && cj_is_local_host()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL);
ini_set('log_errors', '1');
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/pricing.php';
require_once dirname(__DIR__) . '/includes/img-helper.php';

cj_auth_bootstrap($conn);
cj_require_role(['admin', 'superadmin'], 'admin');


$product = null;
$message = '';
$message_type = ''; // 'success' or 'error'

// Fetch product details if ID is provided
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM jewellery_items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        $message = "Product not found.";
        $message_type = 'error';
    }
} else if (isset($_POST['update_item'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = "Security CSRF validation failed. Action aborted.";
        $message_type = 'error';
    } else {
        // Handle Update
        $id = intval($_POST['id']);
        $name = $conn->real_escape_string($_POST['name']);
        $desc = $conn->real_escape_string($_POST['description']);
        $weight = floatval($_POST['weight']);
        $metal = $conn->real_escape_string($_POST['metal_type']);
        $manual_price = ($_POST['manual_price'] !== '') ? floatval($_POST['manual_price']) : null;
        $is_fav = isset($_POST['is_fav']) ? 1 : 0;
        $old_image = $conn->real_escape_string($_POST['old_image']);
        $image = $old_image; // Default to old image

        // Handle new image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);

            if (!in_array($file_extension, $allowed_extensions) || strpos($mime_type, 'image/') !== 0) {
                $message = "Error: Invalid image file type uploaded.";
                $message_type = 'error';
            } else {
                // Ensure 'uploads' directory exists and is writable if on local disk
                if (!is_dir('../uploads')) {
                    @mkdir('../uploads', 0777, true);
                }
                $new_image_name = uniqid('item_edit_', true) . '.' . $file_extension;
                require_once __DIR__ . '/../includes/cloudinary.php';
                $uploaded = false;
                $cldErr = '';
                if (function_exists('cj_cloudinary_is_enabled') && cj_cloudinary_is_enabled()) {
                    $cldRes = cj_cloudinary_upload($_FILES['image']['tmp_name'], $new_image_name, 'chandrakala/uploads');
                    if (!empty($cldRes['success'])) {
                        $uploaded = true;
                    } else {
                        $cldErr = $cldRes['error'] ?? 'Cloudinary upload failed.';
                    }
                }
                if (is_writable('../uploads') && @move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $new_image_name)) {
                    $uploaded = true;
                }

                if ($uploaded) {
                    $image = $new_image_name;
                    // Delete old image if it's different from the new one and exists
                    if ($old_image && $old_image != $new_image_name && file_exists("../uploads/" . $old_image)) {
                        @unlink("../uploads/" . $old_image);
                    }
                } else {
                    $message = "Failed to upload new image" . ($cldErr ? ": $cldErr" : ".");
                    $message_type = 'error';
                }
            }
        }

        // Check Hot Favourite Limit
        // Only check if trying to set as favorite and it wasn't already
        $current_is_fav_query = $conn->query("SELECT is_favourite FROM jewellery_items WHERE id = $id")->fetch_assoc();
        $current_is_fav = $current_is_fav_query['is_favourite'];

        if ($is_fav == 1 && $current_is_fav == 0) { // If trying to make it favorite and it wasn't
            $fav_count_query = $conn->query("SELECT COUNT(*) as total FROM jewellery_items WHERE is_favourite = 1 AND id != $id");
            $fav_count = $fav_count_query->fetch_assoc()['total'];
            if ($fav_count >= 3) {
                $is_fav = 0; // Don't mark as favorite if limit reached
                $message = "Hot Favourite limit (3 items) reached. This item will not be marked as favourite.";
                $message_type = 'error';
            }
        }

        // Only save changes if no errors have occurred so far
        if ($message_type !== 'error') {
            $manual_price_sql = is_null($manual_price) ? "NULL" : $manual_price;

            $stmt = $conn->prepare("UPDATE jewellery_items SET name = ?, image = ?, description = ?, weight = ?, metal_type = ?, manual_price = ?, is_favourite = ? WHERE id = ?");
            $stmt->bind_param("sssdssii", $name, $image, $desc, $weight, $metal, $manual_price_sql, $is_fav, $id);

            if ($stmt->execute()) {
                $message = "Product updated successfully!";
                $message_type = 'success';
                // Re-fetch product to show updated details
                $stmt = $conn->prepare("SELECT * FROM jewellery_items WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
            } else {
                $message = "Error updating product: " . $stmt->error;
                $message_type = 'error';
            }
        }
    }
} else {
    $message = "No product ID provided.";
    $message_type = 'error';
}

// Fetch Rates for calculation
$rate_query = $conn->query("SELECT * FROM rates ORDER BY updated_at DESC LIMIT 1");
$rate = $rate_query->fetch_assoc() ?? []; // Initialize as empty array if no rates found
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include dirname(__DIR__) . '/includes/gtm-head.php'; ?>
    <title>Edit Product - ChandraKala Jewellers</title>
    <link rel="stylesheet" href="/css/tailwind.min.css?v=9">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/admin.css">
    <?php include dirname(__DIR__) . '/includes/admin-head-theme.php'; ?>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
    <script src="/js/pricing.js"></script>
    <script>
        function calculatePrice() {
            const metal = document.getElementById('metal_type').value;
            const weight = parseFloat(document.getElementById('weight').value) || 0;
            const rates = {
                gold_rate: parseFloat(<?= json_encode((float) ($rate['gold_rate'] ?? 0)) ?>),
                silver_rate: parseFloat(<?= json_encode((float) ($rate['silver_rate'] ?? 0)) ?>),
                silver_925_rate: parseFloat(<?= json_encode((float) ($rate['silver_925_rate'] ?? 0)) ?>),
                copper_rate: parseFloat(<?= json_encode((float) ($rate['copper_rate'] ?? 0)) ?>),
                making_charges: parseFloat(<?= json_encode((float) ($rate['making_charges_percent'] ?? 0)) ?>),
                apply_making_to_silver: <?= !empty($rate['apply_making_to_silver']) ? 'true' : 'false' ?>,
                rate_formula: <?= json_encode(cj_rate_formula_map(), JSON_UNESCAPED_SLASHES) ?>
            };
            const price = cjCalculatePrice(metal, weight, rates);
            document.getElementById('calculated_price').innerHTML = "Calculated Price: ₹ " + Math.round(price).toLocaleString('en-IN');
        }

        window.onload = calculatePrice;
    </script>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">
<?php include dirname(__DIR__) . '/includes/gtm-body.php'; ?>

    <header class="bg-white shadow-sm border-b border-gray-100 py-4 px-6 md:px-12 flex justify-between items-center sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <img src="/images/logo.png" alt="Jewellery Store Logo" class="h-10 w-auto">
            <div>
                <h1 class="text-xl font-bold text-gray-900 font-serif leading-tight">ChandraKala Jewellers</h1>
                <p class="text-xs text-amber-600 font-medium tracking-wide">ADMIN PORTAL</p>
            </div>
        </div>
        <div>
            <a href="/admin/index.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-5 rounded-xl transition-colors duration-150 text-sm border border-gray-200">
                &larr; Back to Dashboard
            </a>
        </div>
    </header>

    <main class="flex-grow container mx-auto px-4 py-8 md:py-12 max-w-3xl">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">

            <h2 class="text-2xl font-bold text-gray-900 mb-6 font-serif border-b border-gray-100 pb-4">Edit Jewellery Item</h2>

            <?php if ($message): ?>
                <?php if ($message_type === 'success'): ?>
                    <div class="mb-6 p-4 rounded-xl border flex items-center gap-2 text-sm bg-emerald-50 border-emerald-200 text-emerald-800">
                        <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="font-medium"><?= htmlspecialchars($message) ?></span>
                    </div>
                <?php else: ?>
                    <div class="mb-6 p-4 rounded-xl border flex items-center gap-2 text-sm bg-red-50 border-red-200 text-red-800">
                        <svg class="h-5 w-5 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span class="font-medium"><?= htmlspecialchars($message) ?></span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($product): ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($product['id']) ?>">
                    <input type="hidden" name="old_image" value="<?= htmlspecialchars($product['image']) ?>">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="name" class="block text-xs font-semibold text-gray-500 uppercase mb-2">Item Name</label>
                            <input type="text" name="name" id="name" value="<?= htmlspecialchars($product['name']) ?>" class="p-3 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all duration-200" required>
                        </div>
                        <div>
                            <label for="weight" class="block text-xs font-semibold text-gray-500 uppercase mb-2">Weight (grams)</label>
                            <input type="number" name="weight" id="weight" value="<?= htmlspecialchars($product['weight']) ?>" class="p-3 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all duration-200" step="0.01" oninput="calculatePrice()" required>
                        </div>

                        <div>
                            <label for="metal_type" class="block text-xs font-semibold text-gray-500 uppercase mb-2">Metal Type</label>
                            <select name="metal_type" id="metal_type" class="p-3 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all duration-200" onchange="calculatePrice()" required>
                                <option value="gold" <?= ($product['metal_type'] == 'gold') ? 'selected' : '' ?>>Gold</option>
                                <option value="silver" <?= ($product['metal_type'] == 'silver') ? 'selected' : '' ?>>Silver</option>
                                <option value="silver_925" <?= ($product['metal_type'] == 'silver_925') ? 'selected' : '' ?>>925 Silver</option>
                            </select>
                        </div>

                        <div>
                            <label for="manual_price" class="block text-xs font-semibold text-gray-500 uppercase mb-2">Manual Override Price (optional)</label>
                            <input type="number" name="manual_price" id="manual_price" value="<?= htmlspecialchars($product['manual_price'] ?? '') ?>" class="p-3 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all duration-200" step="0.01">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="description" class="block text-xs font-semibold text-gray-500 uppercase mb-2">Description</label>
                        <textarea name="description" id="description" rows="3" class="p-3 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all duration-200" required><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>

                    <div class="mb-6 border border-dashed border-gray-200 rounded-xl p-4 bg-gray-50/50">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Item Image</label>
                        <?php if (!empty($product['image'])): ?>
                            <img src="<?= htmlspecialchars(cj_img_url($product['image'], 200, 'uploads')) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="w-24 h-24 object-cover rounded-xl border border-gray-100 mb-4 shadow-sm">
                        <?php endif; ?>
                        <input type="file" name="image" id="image" class="text-xs text-gray-500 w-full file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100" accept="image/*">
                    </div>
                    
                    <div class="mb-6 flex items-center">
                        <input type="checkbox" id="is_fav" name="is_fav" class="h-4 w-4 text-amber-500 border-gray-200 rounded focus:ring-amber-500" <?= ($product['is_favourite']) ? 'checked' : '' ?>>
                        <label for="is_fav" class="ml-2 text-xs font-semibold text-gray-600">Mark as Hot Favourite (Max 3)</label>
                    </div>

                    <div class="mb-6 flex justify-between items-center p-4 bg-amber-50/50 border border-amber-100 rounded-xl">
                        <span class="text-xs font-bold text-amber-800 uppercase tracking-wider">Calculated Value</span>
                        <span id="calculated_price" class="text-lg font-bold text-amber-700">₹ 0</span>
                    </div>

                    <div class="flex justify-between items-center border-t border-gray-100 pt-6">
                        <a href="/admin/index.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2.5 px-6 rounded-xl transition-colors duration-150 text-sm border border-gray-200">Cancel &amp; Go Back</a>
                        <button type="submit" name="update_item" class="bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-200 shadow-sm text-sm">Save Product Changes</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-8">
                    <p class="text-gray-500 mb-4">Please select a product from the list to edit.</p>
                    <a href="/admin/index.php" class="bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 px-5 rounded-lg text-xs transition-colors shadow-sm">Dashboard &rarr;</a>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <footer class="bg-gray-900 text-gray-400 py-6 border-t border-gray-800 text-center text-xs">
        <p>&copy; <?php echo date('Y'); ?> ChandraKala Jewellers. All rights reserved.</p>
    </footer>

</body>
</html>
