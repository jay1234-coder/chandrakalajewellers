<?php
require_once dirname(__DIR__) . '/config/db.php'; // Database connection (includes session start, CSRF & security headers)
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/activity-log.php';
require_once dirname(__DIR__) . '/includes/rates-schema.php';
require_once dirname(__DIR__) . '/includes/pricing.php';
require_once dirname(__DIR__) . '/includes/orders.php';
require_once dirname(__DIR__) . '/includes/catalog.php';
require_once dirname(__DIR__) . '/includes/img-helper.php';

if (function_exists('cj_is_local_host') && cj_is_local_host()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL);
ini_set('log_errors', '1');

cj_auth_bootstrap($conn);
cj_admin_noindex_headers();

// Secret code gate check (retained for backward compatibility with bookmark links)
if (isset($_GET['secret'])) {
    $_SESSION['admin_gate_unlocked'] = true;
}

$cj_admin_embed = (!empty($_GET['embed']) || !empty($_POST['embed'])
    || (!empty($_SERVER['HTTP_REFERER']) && strpos((string)$_SERVER['HTTP_REFERER'], 'embed=1') !== false));
$cj_admin_home = $cj_admin_embed ? '/admin/index.php?embed=1' : '/admin/index.php';

// Logout first — always clear remember cookie/token even if PHP session already expired.
if (isset($_GET['logout'])) {
    if (!empty($_SESSION['loggedin'])) {
        cj_log_activity($conn, 'logout', 'Signed out');
    }
    cj_invalidate_remember_tokens($conn, 0);
    if (function_exists('cj_clear_auth_cookie')) {
        cj_clear_auth_cookie();
    }
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: /secret');
    exit();
}

// Restore session from signed cookie or remember-me token when PHP session expired.
if (empty($_SESSION['loggedin'])) {
    if (function_exists('cj_restore_auth_from_cookie')) {
        cj_restore_auth_from_cookie();
    }
}
if (empty($_SESSION['loggedin'])) {
    cj_try_remember_login($conn, ['admin', 'superadmin']);
}

// Redirect to secret login gate if not authenticated
if (empty($_SESSION['loggedin'])) {
    header('Location: /secret');
    exit();
}

// Store admin panel: logged-in superadmin may use embed only; otherwise send them to Super Admin.
// Never interrupt POST handlers (rate updates, item edits, etc.) — forms may omit embed if Referer is stripped.
if (
    !empty($_SESSION['loggedin'])
    && ($_SESSION['role'] ?? '') === 'superadmin'
    && !$cj_admin_embed
    && $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header('Location: /admin/superadmin.php?tab=store');
    exit;
}

// --- NEW LOGIC FOR AJAX IMAGE UPLOAD ---
// This endpoint is hit by JavaScript to upload images asynchronously.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['action']) && $_GET['action'] == 'upload_images') {
    // Check if the user is logged in before allowing uploads
    $uploadRole = (string) ($_SESSION['role'] ?? '');
    if (empty($_SESSION['role']) && !empty($_SESSION['loggedin'])) {
        $uploadRole = 'admin';
    }
    if (empty($_SESSION['loggedin']) || !in_array($uploadRole, ['admin', 'superadmin'], true)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
        exit;
    }
    
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $uploaded_images = [];
    $errors = [];
    
    // Check if files were uploaded and process them
    if (isset($_FILES['images'])) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        foreach ($_FILES['images']['name'] as $key => $name) {
            if ($_FILES['images']['error'][$key] == UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['images']['tmp_name'][$key];
                $file_extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                
                // Verify MIME type safely
                $mime_type = '';
                if (function_exists('finfo_open')) {
                    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo !== false) {
                        $mime_type = @finfo_file($finfo, $tmp_name);
                        @finfo_close($finfo);
                    }
                }
                if (empty($mime_type) && function_exists('mime_content_type')) {
                    $mime_type = @mime_content_type($tmp_name);
                }
                if (empty($mime_type)) {
                    $mime_map = [
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'webp' => 'image/webp',
                        'gif' => 'image/gif'
                    ];
                    $mime_type = $mime_map[$file_extension] ?? '';
                }

                if (!in_array($file_extension, $allowed_extensions) || empty($mime_type) || strpos($mime_type, 'image/') !== 0) {
                    $errors[] = "Upload error for file $name: Invalid image format (detected: " . ($mime_type ?: 'unknown') . ").";
                    continue;
                }

                $unique_filename = uniqid('item_', true) . '.' . $file_extension;
                $target_file = $uploadDir . $unique_filename;
                
                require_once __DIR__ . '/../includes/cloudinary.php';
                $uploaded = false;
                $cldErr = '';
                if (function_exists('cj_cloudinary_is_enabled') && cj_cloudinary_is_enabled()) {
                    $cldRes = cj_cloudinary_upload($tmp_name, $unique_filename, 'chandrakala/uploads');
                    if (!empty($cldRes['success'])) {
                        $uploaded = true;
                    } else {
                        $cldErr = $cldRes['error'] ?? 'Cloudinary upload failed.';
                    }
                }
                if (is_writable($uploadDir) && @move_uploaded_file($tmp_name, $target_file)) {
                    $uploaded = true;
                }
                
                if ($uploaded) {
                    $uploaded_images[] = [
                        'filename' => $unique_filename,
                        'original_name' => $name
                    ];
                } else {
                    $errors[] = "Failed to upload file $name" . ($cldErr ? ": $cldErr" : ".");
                }
            } else if ($_FILES['images']['error'][$key] != UPLOAD_ERR_NO_FILE) {
                $errors[] = "Upload error for file $name: " . $_FILES['images']['error'][$key];
            }
        }
    }
    
    header('Content-Type: application/json');
    if (empty($errors) && count($uploaded_images) > 0) {
        echo json_encode(['success' => true, 'message' => count($uploaded_images) . ' images uploaded successfully!', 'files' => $uploaded_images]);
    } else {
        $msg = 'Some files failed to upload.';
        if (count($uploaded_images) === 0) {
            $msg = 'No images were uploaded. Please check the file format and ensure its size does not exceed the server limits.';
        }
        echo json_encode(['success' => false, 'message' => $msg, 'errors' => $errors]);
    }
    exit;
}
// --- END NEW LOGIC ---

cj_ensure_rates_schema($conn);

// Handle Rate Update before any page rendering / fragile queries
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_rates']) || (($_POST['rate_action'] ?? '') === 'update_rates'))) {
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        $_SESSION['error_message'] = 'Security check failed. Please refresh the page and try updating rates again.';
        header('Location: ' . $cj_admin_home . '#ratesSection');
        exit();
    }

    $gold = floatval($_POST['gold_rate'] ?? 0);
    $silver = floatval($_POST['silver_rate'] ?? 0);
    $silver925 = floatval($_POST['silver_925_rate'] ?? 0);
    $copper = floatval($_POST['copper_rate'] ?? 0);
    $making = floatval($_POST['making_charges'] ?? 0);
    $apply_making_to_silver = isset($_POST['apply_to_silver']) ? 1 : 0;

    // Save display toggles
    cj_setting_set($conn, 'show_gold_rate', isset($_POST['show_gold_rate']) ? '1' : '0');
    cj_setting_set($conn, 'show_silver_rate', isset($_POST['show_silver_rate']) ? '1' : '0');
    cj_setting_set($conn, 'show_silver_925_rate', isset($_POST['show_silver_925_rate']) ? '1' : '0');
    cj_setting_set($conn, 'show_copper_rate', isset($_POST['show_copper_rate']) ? '1' : '0');
    if (isset($_POST['gemini_api_key'])) {
        cj_setting_set($conn, 'gemini_api_key', trim((string)$_POST['gemini_api_key']));
    }

    $conn->begin_transaction();
    try {
        if (!$conn->query('DELETE FROM rates')) {
            throw new RuntimeException('Could not clear rates: ' . $conn->error);
        }
        $stmt = $conn->prepare('INSERT INTO rates (gold_rate, silver_rate, silver_925_rate, copper_rate, making_charges_percent, apply_making_to_silver, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        if (!$stmt) {
            throw new RuntimeException('Could not prepare rates insert: ' . $conn->error);
        }
        $stmt->bind_param('dddddi', $gold, $silver, $silver925, $copper, $making, $apply_making_to_silver);
        if (!$stmt->execute()) {
            throw new RuntimeException('Could not save rates: ' . $stmt->error);
        }
        $stmt->close();
        $conn->commit();
        cj_refresh_all_cached_prices($conn);
        if (function_exists('cj_invalidate_rates_cache')) {
            cj_invalidate_rates_cache();
        }
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: ' . $cj_admin_home . '#ratesSection');
        exit();
    }

    $_SESSION['success_message'] = 'Standard rates updated successfully!';
    require_once dirname(__DIR__) . '/includes/push.php';
    require_once dirname(__DIR__) . '/includes/indexnow-helper.php';
    cj_notify_event($conn, 'rates_updated', $_SESSION['username'] ?? 'admin', null, false);
    cj_log_activity($conn, 'update_rates', "Gold {$gold}, Silver {$silver}, 925 {$silver925}, Copper {$copper}, making {$making}%");
    cj_auto_ping_indexnow();
    header('Location: ' . $cj_admin_home . '#ratesSection');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_manual_rates']) || (($_POST['rate_action'] ?? '') === 'update_manual_rates'))) {
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        $_SESSION['error_message'] = 'Security check failed. Please refresh the page and try updating rates again.';
        header('Location: ' . $cj_admin_home . '#ratesSection');
        exit();
    }

    $manual_gold = floatval($_POST['manual_gold_rate'] ?? 0);
    $manual_silver = floatval($_POST['manual_silver_rate'] ?? 0);
    $manual_silver925 = floatval($_POST['manual_silver_925_rate'] ?? 0);
    $manual_copper = floatval($_POST['manual_copper_rate'] ?? 0);
    $manual_making = floatval($_POST['manual_making_charges'] ?? 0);
    $manual_apply_making_to_silver = isset($_POST['manual_apply_to_silver']) ? 1 : 0;

    $conn->begin_transaction();
    try {
        if (!$conn->query('DELETE FROM manual_rates')) {
            throw new RuntimeException('Could not clear manual rates: ' . $conn->error);
        }
        $stmt = $conn->prepare('INSERT INTO manual_rates (gold_rate, silver_rate, silver_925_rate, copper_rate, making_charges_percent, manual_making_charges_percent, manual_apply_making_to_silver, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        if (!$stmt) {
            throw new RuntimeException('Could not prepare manual rates insert: ' . $conn->error);
        }
        $stmt->bind_param('ddddddi', $manual_gold, $manual_silver, $manual_silver925, $manual_copper, $manual_making, $manual_making, $manual_apply_making_to_silver);
        if (!$stmt->execute()) {
            throw new RuntimeException('Could not save manual rates: ' . $stmt->error);
        }
        $stmt->close();
        $conn->commit();
        cj_refresh_all_cached_prices($conn);
        if (function_exists('cj_invalidate_rates_cache')) {
            cj_invalidate_rates_cache();
        }
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: ' . $cj_admin_home . '#ratesSection');
        exit();
    }

    $_SESSION['success_message'] = 'Manual rates updated successfully!';
    require_once dirname(__DIR__) . '/includes/push.php';
    cj_notify_event($conn, 'rates_updated', $_SESSION['username'] ?? 'admin', 'Manual override rates were updated. Check the shop for latest prices.', false);
    cj_log_activity($conn, 'update_manual_rates', "Gold {$manual_gold}, Silver {$manual_silver}, 925 {$manual_silver925}, Copper {$manual_copper}");
    header('Location: ' . $cj_admin_home . '#ratesSection');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_rate_formula']) || (($_POST['rate_action'] ?? '') === 'update_rate_formula'))) {
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        $_SESSION['error_message'] = 'Security check failed. Please refresh the page and try saving the rate formula again.';
        header('Location: ' . $cj_admin_home . '#rateFormulaSection');
        exit();
    }

    $decoded = json_decode((string) ($_POST['rate_formula_json'] ?? ''), true);
    $formula = cj_normalize_rate_formula(is_array($decoded) ? $decoded : []);

    foreach (cj_rate_formula_metals() as $metalKey) {
        $ops = $_POST['formula_op'][$metalKey] ?? null;
        $vals = $_POST['formula_value'][$metalKey] ?? null;
        if (!is_array($ops) || !is_array($vals)) {
            continue;
        }
        $formula[$metalKey] = [];
        foreach ($ops as $i => $op) {
            $op = (string) $op;
            if (!in_array($op, ['+', '-', '*', '/'], true)) {
                continue;
            }
            $formula[$metalKey][] = [
                'op' => $op,
                'value' => floatval($vals[$i] ?? 0),
            ];
        }
    }

    $formula = cj_normalize_rate_formula($formula);
    cj_setting_set($conn, 'rate_formula_json', json_encode($formula) ?: '{}');
    cj_setting_set($conn, 'price_cache_stamp', '');
    cj_refresh_all_cached_prices($conn);
    cj_setting_set($conn, 'price_cache_stamp', md5(cj_setting('rate_formula_json', '')));

    $_SESSION['success_message'] = 'Rate counting formula saved. Shop prices now use the counted rate.';
    cj_log_activity($conn, 'update_rate_formula', cj_rate_formula_label($formula['gold'] ?? []) . ' (gold)');
    header('Location: ' . $cj_admin_home . '#rateFormulaSection');
    exit();
}

// Ensure sold-out column exists (add → mark sold out pipeline)
$col_check = $conn->query("SHOW COLUMNS FROM jewellery_items LIKE 'is_sold_out'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE jewellery_items ADD COLUMN is_sold_out TINYINT(1) NOT NULL DEFAULT 0 AFTER is_favourite");
}

// Fetch current rates for calculations from both tables
$rate_query_current = $conn->query("SELECT * FROM rates ORDER BY updated_at DESC LIMIT 1");
$current_rates = ($rate_query_current instanceof mysqli_result) ? ($rate_query_current->fetch_assoc() ?: []) : [];
$rate = $current_rates;

// Fetch manual rates
$manual_rate_query = $conn->query("SELECT * FROM manual_rates ORDER BY updated_at DESC LIMIT 1");
$manual_rates = ($manual_rate_query instanceof mysqli_result) ? ($manual_rate_query->fetch_assoc() ?: []) : [];


// Handle Item Add (for jewellery_items table)
if (isset($_POST['add_item'])) {
    // Validate CSRF token
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        $_SESSION['error_message'] = "Security CSRF validation failed. Action aborted.";
        header('Location: ' . $cj_admin_home);
        exit();
    }
    try {
        $name = $conn->real_escape_string($_POST['name']);
        $desc = $conn->real_escape_string($_POST['description']);
        $weight = floatval($_POST['weight']);
        $metal = $conn->real_escape_string($_POST['metal_type']);
        $is_fav = isset($_POST['is_fav']) ? 1 : 0;
        $is_sold_out = isset($_POST['is_sold_out']) ? 1 : 0;
        // --- EDITED: Get image from hidden field instead of FILES ---
        $image = $conn->real_escape_string($_POST['image_filename'] ?? '');
        // --- END EDITED ---

        // --- NEW LOGIC ---
        $use_manual_rates = isset($_POST['use_manual_rates']) ? 1 : 0;
        $specs = cj_item_specs_from_post();
        
        // Calculate the price based on the checkbox
        $rates_to_use = $use_manual_rates ? $manual_rates : $current_rates;
        $manual_price = calculate_item_price_from_rates($weight, $metal, $rates_to_use);
        // --- END NEW LOGIC ---

        $fav_count_query = $conn->query("SELECT COUNT(*) as total FROM jewellery_items WHERE is_favourite = 1");
        if (!$fav_count_query) {
            throw new RuntimeException("Database error counting favourites: " . $conn->error);
        }
        $fav_count = $fav_count_query->fetch_assoc()['total'];
        if ($is_fav && $fav_count >= 3) {
            $is_fav = 0;
        }

        $tags = $conn->real_escape_string(trim((string)($_POST['tags'] ?? '')));

        // --- UPDATED INSERT STATEMENT ---
        $sql = "INSERT INTO jewellery_items (name, image, description, weight, metal_type, manual_price, is_favourite, is_sold_out, use_manual_rates, purity, stone, category, sku, dimensions, tags)
                      VALUES ('$name', '$image', '$desc', $weight, '$metal', $manual_price, $is_fav, $is_sold_out, $use_manual_rates, '" . $conn->real_escape_string($specs['purity']) . "', '" . $conn->real_escape_string($specs['stone']) . "', '" . $conn->real_escape_string($specs['category']) . "', '" . $conn->real_escape_string($specs['sku']) . "', '" . $conn->real_escape_string($specs['dimensions']) . "', '$tags')";
        
        if (!$conn->query($sql)) {
            throw new RuntimeException("Database insertion failed: " . $conn->error . " (Query: " . $sql . ")");
        }
        $newId = (int) $conn->insert_id;
        cj_refresh_item_cached_price($conn, $newId);
        cj_save_extra_image_uploads($conn, $newId);

        $_SESSION['success_message'] = "Jewellery item added successfully!";
        require_once dirname(__DIR__) . '/includes/push.php';
        require_once dirname(__DIR__) . '/includes/indexnow-helper.php';
        cj_notify_event($conn, 'new_product', $_SESSION['username'] ?? 'admin', 'New piece added: ' . $name);
        cj_log_activity($conn, 'add_item', $name . ' (' . $metal . ', ' . $weight . 'g)');
        cj_auto_ping_indexnow(['https://chandrakalajewellers.in/product?id=' . $newId]);
    } catch (Throwable $e) {
        $_SESSION['error_message'] = "Could not add item: " . $e->getMessage();
    }
    header('Location: ' . $cj_admin_home);
    exit();
}

// Handle Item Update (for jewellery_items table)
if (isset($_POST['update_item'])) {
    // Validate CSRF token
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        $_SESSION['error_message'] = "Security CSRF validation failed. Action aborted.";
        header('Location: ' . $cj_admin_home);
        exit();
    }
    try {
        $id = intval($_POST['item_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $desc = $conn->real_escape_string($_POST['description']);
        $weight = floatval($_POST['weight']);
        $metal = $conn->real_escape_string($_POST['metal_type']);
        $is_fav = isset($_POST['is_fav']) ? 1 : 0;
        $is_sold_out = isset($_POST['is_sold_out']) ? 1 : 0;
        $current_image = $conn->real_escape_string($_POST['current_image']);
        $image_to_save = $current_image;

        // --- NEW LOGIC ---
        $use_manual_rates = isset($_POST['edit_use_manual_rates']) ? 1 : 0;
        $specs = cj_item_specs_from_post();
        
        // Calculate the price based on the checkbox
        $rates_to_use = $use_manual_rates ? $manual_rates : $current_rates;
        $manual_price = calculate_item_price_from_rates($weight, $metal, $rates_to_use);
        // --- END NEW LOGIC ---
        
        // --- EDITED: Check for new image filename from hidden field ---
        if (isset($_POST['edit_image_filename']) && !empty($_POST['edit_image_filename'])) {
            $image_to_save = $conn->real_escape_string($_POST['edit_image_filename']);
        }
        // --- END EDITED ---

        $fav_count_query = $conn->query("SELECT COUNT(*) as total FROM jewellery_items WHERE is_favourite = 1 AND id != $id");
        if (!$fav_count_query) {
            throw new RuntimeException("Database error counting favourites: " . $conn->error);
        }
        $fav_count = $fav_count_query->fetch_assoc()['total'];
        if ($is_fav && $fav_count >= 3) {
            $is_fav = 0;
        }

        $tags = $conn->real_escape_string(trim((string)($_POST['tags'] ?? '')));

        // --- UPDATED UPDATE QUERY ---
        $update_query = "UPDATE jewellery_items SET 
                        name = '$name', 
                        image = '$image_to_save', 
                        description = '$desc', 
                        weight = $weight, 
                        metal_type = '$metal', 
                        manual_price = $manual_price, 
                        is_favourite = $is_fav,
                        is_sold_out = $is_sold_out,
                        use_manual_rates = $use_manual_rates,
                        purity = '" . $conn->real_escape_string($specs['purity']) . "',
                        stone = '" . $conn->real_escape_string($specs['stone']) . "',
                        category = '" . $conn->real_escape_string($specs['category']) . "',
                        sku = '" . $conn->real_escape_string($specs['sku']) . "',
                        dimensions = '" . $conn->real_escape_string($specs['dimensions']) . "',
                        tags = '$tags'
                        WHERE id = $id";
        if (!$conn->query($update_query)) {
            throw new RuntimeException("Database update failed: " . $conn->error . " (Query: " . $update_query . ")");
        }
        cj_save_extra_image_uploads($conn, $id);
        if (!empty($_POST['delete_extra_ids']) && is_array($_POST['delete_extra_ids'])) {
            foreach ($_POST['delete_extra_ids'] as $eid) {
                cj_delete_extra_image($conn, (int) $eid, $id);
            }
        }
        cj_refresh_item_cached_price($conn, $id);
        $_SESSION['success_message'] = "Jewellery item updated successfully!";
        require_once dirname(__DIR__) . '/includes/push.php';
        cj_notify_event($conn, 'product_updated', $_SESSION['username'] ?? 'admin', 'Updated: ' . $name);
        cj_log_activity($conn, 'update_item', "#{$id} " . $name);
    } catch (Throwable $e) {
        $_SESSION['error_message'] = "Could not update item: " . $e->getMessage();
    }
    header('Location: ' . $cj_admin_home);
    exit();
}


// Handle Delete (for jewellery_items table)
if (isset($_GET['delete'])) {
    // Validate CSRF token
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf((string)($_GET['csrf_token'] ?? ''))) {
        $_SESSION['error_message'] = "Security CSRF validation failed. Action aborted.";
        header('Location: ' . $cj_admin_home);
        exit();
    }
    try {
        $id = intval($_GET['delete']);
        $image_query_res = $conn->query("SELECT image FROM jewellery_items WHERE id = $id");
        if (!$image_query_res) {
            throw new RuntimeException("Database error finding item image: " . $conn->error);
        }
        $image_query = $image_query_res->fetch_assoc();
        if ($image_query && file_exists("../uploads/" . $image_query['image'])) {
            unlink("../uploads/" . $image_query['image']);
        }
        if (!$conn->query("DELETE FROM jewellery_items WHERE id = $id")) {
            throw new RuntimeException("Database deletion failed: " . $conn->error);
        }
        $_SESSION['success_message'] = "Jewellery item deleted successfully!";
        cj_log_activity($conn, 'delete_item', 'Deleted jewellery item #' . $id);
    } catch (Throwable $e) {
        $_SESSION['error_message'] = "Could not delete item: " . $e->getMessage();
    }
    header('Location: ' . $cj_admin_home);
    exit();
}

// Handle Toggle Favourite (for jewellery_items table)
if (isset($_GET['toggle_fav'])) {
    // Validate CSRF token
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf((string)($_GET['csrf_token'] ?? ''))) {
        $_SESSION['error_message'] = "Security CSRF validation failed. Action aborted.";
        header('Location: ' . $cj_admin_home);
        exit();
    }
    try {
        $id = intval($_GET['toggle_fav']);
        $current = intval($_GET['current']);
        if ($current == 0) {
            $fav_count_query = $conn->query("SELECT COUNT(*) as total FROM jewellery_items WHERE is_favourite = 1");
            if (!$fav_count_query) {
                throw new RuntimeException("Database error counting favourites: " . $conn->error);
            }
            $fav_count = $fav_count_query->fetch_assoc()['total'];
            if ($fav_count < 3) {
                if (!$conn->query("UPDATE jewellery_items SET is_favourite = 1 WHERE id = $id")) {
                    throw new RuntimeException("Database error setting favourite: " . $conn->error);
                }
                $_SESSION['success_message'] = "Item marked as Hot Favourite!";
            } else {
                $_SESSION['error_message'] = "Hot Favourite limit (3 items) reached. Cannot add more.";
            }
        } else {
            if (!$conn->query("UPDATE jewellery_items SET is_favourite = 0 WHERE id = $id")) {
                throw new RuntimeException("Database error removing favourite: " . $conn->error);
            }
            $_SESSION['success_message'] = "Item removed from Hot Favourites.";
        }
        cj_log_activity($conn, 'toggle_favourite', 'Item #' . $id . ' → ' . ($current == 0 ? 'favourite' : 'normal'));
    } catch (Throwable $e) {
        $_SESSION['error_message'] = "Could not toggle favourite: " . $e->getMessage();
    }
    header('Location: ' . $cj_admin_home);
    exit();
}

// Handle Toggle Sold Out
if (isset($_GET['toggle_sold'])) {
    $redirectUrl = $cj_admin_home;
    if (isset($_GET['page'])) {
        $redirectUrl .= (str_contains($redirectUrl, '?') ? '&' : '?') . 'page=' . intval($_GET['page']);
    }
    
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf((string)($_GET['csrf_token'] ?? ''))) {
        $_SESSION['error_message'] = "Security CSRF validation failed. Action aborted.";
        header('Location: ' . $redirectUrl);
        exit();
    }
    try {
        $id = intval($_GET['toggle_sold']);
        $current = intval($_GET['current']);
        $new_val = $current ? 0 : 1;
        if (!$conn->query("UPDATE jewellery_items SET is_sold_out = $new_val WHERE id = $id")) {
            throw new RuntimeException("Database error updating sold out status: " . $conn->error);
        }
        $_SESSION['success_message'] = $new_val
            ? "Item marked as Sold Out on the website."
            : "Item marked Available again on the website.";
        cj_log_activity($conn, 'toggle_sold_out', 'Item #' . $id . ' → ' . ($new_val ? 'sold out' : 'available'));
    } catch (Throwable $e) {
        $_SESSION['error_message'] = "Could not toggle sold out status: " . $e->getMessage();
    }
    header('Location: ' . $redirectUrl);
    exit();
}

// --- Handle Catalogue Bulk Upload (for products table) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_catalogue_images'])) {
    // Validate CSRF token
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        die("CSRF validation failed. Action aborted.");
    }
    $uploadDir = './catalogue/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $uploadedCount = 0;
    $errors = [];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (isset($_FILES['catalogue_images'])) {
        foreach ($_FILES['catalogue_images']['name'] as $key => $name) {
            if ($_FILES['catalogue_images']['error'][$key] == UPLOAD_ERR_OK) {
                $tmp_name = $_FILES['catalogue_images']['tmp_name'][$key];
                $file_extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                // Verify MIME type safely
                $mime_type = '';
                if (function_exists('finfo_open')) {
                    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo !== false) {
                        $mime_type = @finfo_file($finfo, $tmp_name);
                        @finfo_close($finfo);
                    }
                }
                if (empty($mime_type) && function_exists('mime_content_type')) {
                    $mime_type = @mime_content_type($tmp_name);
                }
                if (empty($mime_type)) {
                    $mime_map = [
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'webp' => 'image/webp',
                        'gif' => 'image/gif'
                    ];
                    $mime_type = $mime_map[$file_extension] ?? '';
                }

                if (!in_array($file_extension, $allowed_extensions) || empty($mime_type) || strpos($mime_type, 'image/') !== 0) {
                    $errors[] = "Error: File '$name' is not a valid image format (detected: " . ($mime_type ?: 'unknown') . ").";
                    continue;
                }

                $unique_filename = uniqid('jewellery_', true) . '.' . $file_extension;
                $target_file = $uploadDir . $unique_filename;

                require_once __DIR__ . '/../includes/cloudinary.php';
                $uploaded = false;
                $cldErr = '';
                if (function_exists('cj_cloudinary_is_enabled') && cj_cloudinary_is_enabled()) {
                    $cldRes = cj_cloudinary_upload($tmp_name, $unique_filename, 'chandrakala/catalogue');
                    if (!empty($cldRes['success'])) {
                        $uploaded = true;
                    } else {
                        $cldErr = $cldRes['error'] ?? 'Cloudinary upload failed.';
                    }
                }
                if (is_writable($uploadDir) && @move_uploaded_file($tmp_name, $target_file)) {
                    $uploaded = true;
                }

                if ($uploaded) {
                    $product_name = pathinfo($name, PATHINFO_FILENAME);
                    $product_name = str_replace(['_', '-'], ' ', $product_name);
                    $product_name = ucwords($product_name);

                    $stmt = $conn->prepare("INSERT INTO products (name, description, image_filename) VALUES (?, ?, ?)");
                    $description = "Exquisite jewellery piece.";
                    $stmt->bind_param("sss", $product_name, $description, $unique_filename);
                    if ($stmt->execute()) {
                        $uploadedCount++;
                    } else {
                        $errors[] = "Failed to insert '$name' into database: " . $stmt->error;
                        if (file_exists($target_file)) {
                            @unlink($target_file);
                        }
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Failed to upload file '$name'" . ($cldErr ? ": $cldErr" : ".");
                }
            } else if ($_FILES['catalogue_images']['error'][$key] != UPLOAD_ERR_NO_FILE) {
                $error_code = $_FILES['catalogue_images']['error'][$key];
                $error_message = "Unknown upload error.";
                switch ($error_code) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_message = "File '$name' is too large.";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_message = "File '$name' was only partially uploaded.";
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error_message = "Missing a temporary folder for '$name'.";
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_message = "Failed to write file '$name' to disk.";
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $error_message = "A PHP extension stopped the upload of '$name'.";
                        break;
                }
                $errors[] = $error_message;
            }
        }
    }

    if ($uploadedCount > 0) {
        $_SESSION['success_message'] = "$uploadedCount images uploaded successfully to catalogue.";
        cj_log_activity($conn, 'upload_catalogue', $uploadedCount . ' image(s) uploaded');
    }
    if (!empty($errors)) {
        $_SESSION['error_message'] = "Some errors occurred during catalogue upload: " . implode("<br>", $errors);
    }
    header('Location: ' . $cj_admin_home);
    exit();
}


// Pagination setup
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(10, min(200, (int)$_GET['limit'])) : 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$total_query = $conn->query("SELECT COUNT(*) as count FROM jewellery_items");
$total_items = $total_query->fetch_assoc()['count'] ?? 0;
$total_pages = max(1, ceil($total_items / $limit));

// Fetch Rates & Products (from jewellery_items for the list below)
$rate_query = $conn->query("SELECT * FROM rates ORDER BY updated_at DESC LIMIT 1");
$rate = $rate_query->fetch_assoc() ?? [];

$products_result = $conn->query("SELECT * FROM jewellery_items ORDER BY id DESC LIMIT $limit OFFSET $offset");
$product_rows = [];
if ($products_result) {
    while ($r = $products_result->fetch_assoc()) {
        $product_rows[] = $r;
    }
}

// Fetch manual rates for display
$manual_rate_query_display = $conn->query("SELECT * FROM manual_rates ORDER BY updated_at DESC LIMIT 1");
$manual_rate = $manual_rate_query_display->fetch_assoc() ?? [];

$rate_formula_map = cj_rate_formula_map();
$rate_with_formula = $rate;
$rate_with_formula['rate_formula'] = $rate_formula_map;
$manual_with_formula = $manual_rate;
$manual_with_formula['rate_formula'] = $rate_formula_map;
$counted_rate_metrics = [];
$counted_metal_labels = [
    'gold' => ['Gold', 'gold_rate', 'per 1g'],
    'silver' => ['Silver', 'silver_rate', 'per 1g'],
    'silver_925' => ['925 Silver', 'silver_925_rate', 'per 1g'],
    'copper' => ['Copper', 'copper_rate', 'per 1kg'],
];
foreach ($counted_metal_labels as $metalKey => $meta) {
    $market = (float) ($rate[$meta[1]] ?? 0);
    $counted = cj_effective_metal_rate($metalKey, $rate_with_formula);
    $counted_rate_metrics[$metalKey] = [
        'label' => $meta[0],
        'unit' => $meta[2],
        'field' => $meta[1],
        'market' => $market,
        'counted' => $counted,
        'formula' => cj_rate_formula_label($rate_formula_map[$metalKey] ?? []),
        'steps' => $rate_formula_map[$metalKey] ?? [],
    ];
}

// Favorites count
$fav_query = $conn->query("SELECT COUNT(*) as count FROM jewellery_items WHERE is_favourite = 1");
$fav_count = $fav_query->fetch_assoc()['count'] ?? 0;

$sold_out_count = 0;
$available_count = (int)$total_items;
$sold_q = $conn->query("SELECT SUM(CASE WHEN is_sold_out = 1 THEN 1 ELSE 0 END) AS sold_out FROM jewellery_items");
if ($sold_q && ($sold_row = $sold_q->fetch_assoc())) {
    $sold_out_count = (int)($sold_row['sold_out'] ?? 0);
    $available_count = max(0, (int)$total_items - $sold_out_count);
}
$catalogue_count = 0;
$cat_q = $conn->query("SELECT COUNT(*) AS c FROM products");
if ($cat_q && ($cat_row = $cat_q->fetch_assoc())) {
    $catalogue_count = (int)($cat_row['c'] ?? 0);
}
$metal_counts_admin = ['gold' => 0, 'silver' => 0, 'silver_925' => 0];
$metal_q = $conn->query("SELECT metal_type, COUNT(*) AS c FROM jewellery_items GROUP BY metal_type");
if ($metal_q) {
    while ($mr = $metal_q->fetch_assoc()) {
        $metal_counts_admin[$mr['metal_type']] = (int)$mr['c'];
    }
}
?>

<?php
$just_logged_in = !empty($_SESSION['just_logged_in']);
if ($just_logged_in) {
    unset($_SESSION['just_logged_in']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <?php include dirname(__DIR__) . '/includes/gtm-head.php'; ?>
    <title>Store Admin — ChandraKala Jewellers</title>
    <link rel="stylesheet" href="/css/tailwind.min.css?v=9">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/css/admin.css">
    <?php include dirname(__DIR__) . '/includes/admin-head-theme.php'; ?>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--admin-bg, #faf9f6);
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            max-width: 90%;
            width: 550px;
            animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .progress-bar-container {
            width: 100%;
            background-color: #f3f4f6;
            border-radius: 9999px;
            height: 1.25rem;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        .progress-bar {
            background: linear-gradient(135deg, #fbbf24, #d97706);
            height: 100%;
            text-align: center;
            line-height: 1.25rem;
            color: white;
            font-weight: bold;
            font-size: 11px;
            transition: width 0.3s ease;
        }
        .progress-message {
            margin-top: 0.5rem;
            font-size: 0.825rem;
            color: #6b7280;
        }
    </style>
</head>
<body class="admin-body bg-gray-50 text-gray-800 min-h-screen">
<?php include dirname(__DIR__) . '/includes/gtm-body.php'; ?>

    <?php if ($just_logged_in): ?>
    <div class="admin-success-overlay" id="loginSuccessOverlay" role="status" aria-live="polite">
        <div class="admin-success-card">
            <div class="check"><i class="fas fa-check"></i></div>
            <h2 class="text-lg font-bold text-gray-900 font-serif mb-1">Welcome back</h2>
            <p class="text-sm text-gray-500">Access granted. Loading your dashboard…</p>
        </div>
    </div>
    <script>
        setTimeout(function () {
            var el = document.getElementById('loginSuccessOverlay');
            if (el) {
                el.style.opacity = '0';
                el.style.transition = 'opacity 0.35s ease';
                setTimeout(function () { el.remove(); }, 360);
            }
        }, 1400);
    </script>
    <?php endif; ?>

    <!-- Header Section -->
    <?php if (empty($cj_admin_embed)): ?>
    <header class="admin-header bg-white shadow-sm border-b border-gray-100 py-4 px-4 sm:px-6 md:px-12 flex justify-between items-center sticky top-0 z-40">
        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
            <img src="/images/logo.png" alt="Jewellery Store Logo" class="h-10 w-auto shrink-0">
            <div class="min-w-0">
                <h1 class="text-base sm:text-xl font-bold text-gray-900 font-serif leading-tight truncate">ChandraKala Jewellers</h1>
                <p class="text-[10px] sm:text-xs text-amber-600 font-medium tracking-wide">ADMIN PORTAL</p>
            </div>
        </div>
        <div class="flex items-center gap-2 sm:gap-4 shrink-0">
            <?php include dirname(__DIR__) . '/includes/admin-theme-toggle.php'; ?>
            <a href="/admin/orders.php" class="hidden sm:inline-flex text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-100 px-3 py-2 rounded-xl">Orders</a>
            <a href="/admin/customers.php" class="hidden sm:inline-flex text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-100 px-3 py-2 rounded-xl">Customers</a>
            <a href="/admin/notifications.php" class="hidden sm:inline-flex text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-100 px-3 py-2 rounded-xl">Notifications</a>
            <span class="text-sm font-semibold text-gray-500 hidden md:inline">Welcome, <?= htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin') ?></span>
            <a href="/admin/index.php?logout" class="logout-btn bg-red-50 hover:bg-red-100 text-red-600 font-semibold py-2 px-4 sm:px-5 rounded-xl transition-colors duration-150 text-sm border border-red-100">Log Out</a>
        </div>
    </header>
    <?php else: ?>
    <div class="bg-amber-50 border-b border-amber-100 px-4 py-2 text-xs text-amber-800 font-semibold sticky top-0 z-40 flex items-center justify-between gap-2">
        <span>Store Admin inside Control Center · <?= htmlspecialchars($_SESSION['display_name'] ?? 'Admin') ?>
        (<?= htmlspecialchars($_SESSION['role'] ?? 'admin') ?>)</span>
        <?php include dirname(__DIR__) . '/includes/admin-theme-toggle.php'; ?>
    </div>
    <?php endif; ?>

    <main class="admin-main w-full mx-auto p-4 sm:p-6 lg:p-8 max-w-7xl">
        
        <!-- Alerts Display -->
        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="admin-alert bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl shadow-sm mb-6 flex items-center gap-2" role="alert">
                    <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="font-medium">' . htmlspecialchars($_SESSION['success_message']) . '</span>
                  </div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="admin-alert bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl shadow-sm mb-6 flex items-center gap-2" role="alert">
                    <svg class="h-5 w-5 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span class="font-medium">' . htmlspecialchars($_SESSION['error_message']) . '</span>
                  </div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <nav class="admin-mobile-jump" aria-label="Quick sections">
            <a href="/admin/orders.php">Orders</a>
            <a href="/admin/customers.php">Customers</a>
            <a href="#ratesSection">Rates</a>
            <a href="#rateFormulaSection">Count rates</a>
            <a href="#addItemSection">Add item</a>
            <a href="#stockList">Stock</a>
            <a href="#followUsSection">Follow Us</a>
            <a href="/admin/notifications.php">Notify</a>
            <a href="#visitorAnalytics">Visitors</a>
            <a href="/admin/delete_c.php">Catalogue</a>
        </nav>

        <?php
        require_once dirname(__DIR__) . '/includes/pwa-schema.php';
        cj_ensure_pwa_schema($conn);
        if (empty($_SESSION['role'])) {
            $_SESSION['role'] = 'admin';
        }
        $inbox = [];
        $ir = $conn->query("SELECT * FROM internal_messages WHERE to_role IN ('admin','all') ORDER BY id DESC LIMIT 8");
        if ($ir) {
            while ($row = $ir->fetch_assoc()) {
                $inbox[] = $row;
            }
        }
        if ($inbox) {
            $conn->query("UPDATE internal_messages SET is_read = 1, read_at = NOW() WHERE to_role IN ('admin','all') AND is_read = 0");
        }
        ?>
        <?php if ($inbox): ?>
        <section class="admin-card bg-white p-4 sm:p-5 rounded-xl border border-amber-100 shadow-sm mb-6">
            <h2 class="text-sm font-bold text-gray-900 mb-3"><i class="fas fa-envelope text-amber-500 mr-1"></i> Messages from Super Admin</h2>
            <div class="space-y-2">
                <?php foreach ($inbox as $m): ?>
                    <article class="border border-gray-100 rounded-xl p-3 bg-gray-50/70">
                        <div class="flex justify-between gap-2 text-[11px] text-gray-400">
                            <span><?= htmlspecialchars($m['from_role']) ?></span>
                            <span><?= htmlspecialchars(cj_format_ist($m['created_at'] ?? '')) ?></span>
                        </div>
                        <p class="font-semibold text-sm mt-1"><?= htmlspecialchars($m['subject']) ?></p>
                        <p class="text-xs text-gray-600 mt-1 whitespace-pre-wrap"><?= htmlspecialchars($m['body']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- KPI Dashboard (near rates) -->
        <div class="admin-kpi-grid grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
            <a href="#stockList" class="admin-stat admin-kpi bg-white p-5 rounded-xl border border-gray-100 shadow-sm block hover:border-emerald-200 hover:shadow-md transition-all duration-200 group">
                <div class="flex items-center gap-4">
                    <div class="icon-box w-12 h-12 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0 group-hover:scale-105 transition-transform"><i class="fas fa-check-circle"></i></div>
                    <div class="min-w-0">
                        <p class="stat-label text-xs font-semibold text-gray-400 uppercase tracking-wider">Available Stock</p>
                        <p class="stat-value text-2xl font-bold text-gray-900"><?= (int)$available_count ?></p>
                    </div>
                </div>
                <p class="stat-purpose mt-3 text-[11px] leading-snug text-gray-500">Pieces customers can still buy. Use this before updating rates so you price live inventory only.</p>
            </a>
            <a href="#stockList" class="admin-stat admin-kpi bg-white p-5 rounded-xl border border-gray-100 shadow-sm block hover:border-rose-200 hover:shadow-md transition-all duration-200 group">
                <div class="flex items-center gap-4">
                    <div class="icon-box w-12 h-12 rounded-lg bg-rose-50 text-rose-500 flex items-center justify-center text-xl shrink-0 group-hover:scale-105 transition-transform"><i class="fas fa-ban"></i></div>
                    <div class="min-w-0">
                        <p class="stat-label text-xs font-semibold text-gray-400 uppercase tracking-wider">Sold Out</p>
                        <p class="stat-value text-2xl font-bold text-gray-900"><?= (int)$sold_out_count ?></p>
                    </div>
                </div>
                <p class="stat-purpose mt-3 text-[11px] leading-snug text-gray-500">Marked sold on the website. Shows demand — restock or mark Available again when a piece returns.</p>
            </a>
            <a href="#rateFormulaSection" class="admin-stat admin-kpi bg-white p-5 rounded-xl border border-gray-100 shadow-sm hover:border-amber-200 hover:shadow-md transition-all duration-200 block">
                <div class="flex items-center gap-4">
                    <div class="icon-box w-12 h-12 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xl shrink-0"><i class="fas fa-coins"></i></div>
                    <div class="min-w-0">
                        <p class="stat-label text-xs font-semibold text-gray-400 uppercase tracking-wider">Counted Rates</p>
                        <p class="stat-value text-sm sm:text-base font-bold text-gray-900 leading-snug">
                            Au ₹<?= number_format($counted_rate_metrics['gold']['counted'] ?? 0, 0) ?> ·
                            Ag ₹<?= number_format($counted_rate_metrics['silver']['counted'] ?? 0, 0) ?> ·
                            Cu ₹<?= number_format($counted_rate_metrics['copper']['counted'] ?? 0, 0) ?>
                        </p>
                    </div>
                </div>
                <p class="stat-purpose mt-3 text-[11px] leading-snug text-gray-500">Counted gram rates after the formula below (not the full board rate). These drive shop prices.</p>
            </a>
            <a href="/admin/delete_c.php" class="admin-stat admin-kpi bg-white p-5 rounded-xl border border-gray-100 shadow-sm block hover:border-sky-200 hover:shadow-md transition-all duration-200 group">
                <div class="flex items-center gap-4">
                    <div class="icon-box w-12 h-12 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center text-xl shrink-0 group-hover:scale-105 transition-transform"><i class="fas fa-images"></i></div>
                    <div class="min-w-0">
                        <p class="stat-label text-xs font-semibold text-gray-400 uppercase tracking-wider">Catalogue Designs</p>
                        <p class="stat-value text-2xl font-bold text-gray-900"><?= (int)$catalogue_count ?></p>
                    </div>
                </div>
                <p class="stat-purpose mt-3 text-[11px] leading-snug text-gray-500">Custom design photos customers order on WhatsApp. More designs = more ready-to-order references.</p>
            </a>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-400 mb-4 -mt-4 sm:-mt-6">
            <p>
                Mix: <?= (int)$metal_counts_admin['gold'] ?> gold · <?= (int)$metal_counts_admin['silver'] ?> silver · <?= (int)$metal_counts_admin['silver_925'] ?> 925 silver · <?= (int)$fav_count ?>/3 homepage favourites
            </p>
            <p class="inline-flex items-center gap-1.5 font-medium text-amber-700 bg-amber-50/80 border border-amber-200/70 px-2.5 py-1 rounded-full">
                <i class="far fa-clock"></i>
                <span id="cjAdminIstClock"><?= htmlspecialchars(cj_format_ist(time())) ?></span>
            </p>
        </div>

        <!-- Rates Management Section -->
        <div id="ratesSection" class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-8 mb-6 sm:mb-8 scroll-mt-24">
            <!-- Standard rates card -->
            <div class="admin-card bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="mb-4 pb-2 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-bold text-gray-900 font-serif flex items-center gap-2"><i class="fas fa-chart-line text-amber-500"></i> Standard Auto Rates</h2>
                    <?php if (!empty($rate['updated_at'])): ?>
                        <span class="text-[11px] font-medium text-gray-500"><i class="far fa-clock text-amber-500 mr-1"></i>Updated: <?= htmlspecialchars(cj_format_ist($rate['updated_at'])) ?></span>
                    <?php endif; ?>
                </div>
                <form method="POST" action="<?= htmlspecialchars($cj_admin_home) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="rate_action" value="update_rates">
                    <?php if (!empty($cj_admin_embed)): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                    <div class="admin-form-grid grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div>
                            <label for="gold_rate" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Gold board rate (per 1g)</label>
                            <input type="number" step="0.01" id="gold_rate" name="gold_rate" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" value="<?= $rate['gold_rate'] ?? '' ?>" required>
                        </div>
                        <div>
                            <label for="silver_rate" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Silver (per 1g)</label>
                            <input type="number" step="0.01" id="silver_rate" name="silver_rate" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" value="<?= $rate['silver_rate'] ?? '' ?>" required>
                        </div>
                        <div>
                            <label for="silver_925_rate" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Silver 92.5 (per 1g)</label>
                            <input type="number" step="0.01" id="silver_925_rate" name="silver_925_rate" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" value="<?= $rate['silver_925_rate'] ?? '' ?>" required>
                        </div>
                        <div>
                            <label for="copper_rate" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Copper (per 1kg)</label>
                            <input type="number" step="0.01" id="copper_rate" name="copper_rate" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" value="<?= $rate['copper_rate'] ?? '' ?>" required>
                        </div>
                        <div class="col-span-1 sm:col-span-2">
                            <label for="making_charges" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Making Charges (%)</label>
                            <input type="number" step="0.01" id="making_charges" name="making_charges" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" value="<?= $rate['making_charges_percent'] ?? '' ?>" required>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center">
                        <input type="checkbox" id="apply_to_silver" name="apply_to_silver" class="h-4 w-4 text-amber-500 border-gray-200 rounded focus:ring-amber-500" <?= ($rate['apply_making_to_silver'] ?? 0) ? 'checked' : '' ?>>
                        <label for="apply_to_silver" class="ml-2 text-xs font-medium text-gray-600">Apply making charges to Silver as well</label>
                    </div>
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Homepage Rate Display Metrics</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label class="flex items-center text-xs font-medium text-gray-600 cursor-pointer">
                                <input type="checkbox" name="show_gold_rate" value="1" class="h-4 w-4 text-amber-500 border-gray-200 rounded focus:ring-amber-500" <?= cj_setting('show_gold_rate', '1', $conn) === '1' ? 'checked' : '' ?>>
                                <span class="ml-2">Show Gold Rate</span>
                            </label>
                            <label class="flex items-center text-xs font-medium text-gray-600 cursor-pointer">
                                <input type="checkbox" name="show_silver_rate" value="1" class="h-4 w-4 text-amber-500 border-gray-200 rounded focus:ring-amber-500" <?= cj_setting('show_silver_rate', '1', $conn) === '1' ? 'checked' : '' ?>>
                                <span class="ml-2">Show Silver Rate</span>
                            </label>
                            <label class="flex items-center text-xs font-medium text-gray-600 cursor-pointer">
                                <input type="checkbox" name="show_silver_925_rate" value="1" class="h-4 w-4 text-amber-500 border-gray-200 rounded focus:ring-amber-500" <?= cj_setting('show_silver_925_rate', '1', $conn) === '1' ? 'checked' : '' ?>>
                                <span class="ml-2">Show 925 Silver Rate</span>
                            </label>
                            <label class="flex items-center text-xs font-medium text-gray-600 cursor-pointer">
                                <input type="checkbox" name="show_copper_rate" value="1" class="h-4 w-4 text-amber-500 border-gray-200 rounded focus:ring-amber-500" <?= cj_setting('show_copper_rate', '1', $conn) === '1' ? 'checked' : '' ?>>
                                <span class="ml-2">Show Copper Rate</span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Google Gemini API Key (AI Integration)</h4>
                        <div class="flex flex-col gap-1">
                            <input type="password" name="gemini_api_key" value="<?= htmlspecialchars(cj_setting('gemini_api_key', '', $conn)) ?>" class="text-xs border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-amber-500 w-full" placeholder="Enter Gemini API Key from Google AI Studio...">
                            <p class="text-[10px] text-gray-400">Leave empty to disable AI features. Get a free key from Google AI Studio.</p>
                        </div>
                    </div>
                    <button type="submit" name="update_rates" class="mt-6 w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-4 rounded-lg text-sm transition-colors duration-150 shadow-sm">Update Standard Rates</button>
                </form>
            </div>

            <!-- Manual rates card -->
            <div class="admin-card bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="mb-4 pb-2 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-bold text-gray-900 font-serif flex items-center gap-2"><i class="fas fa-sliders-h text-amber-500"></i> Manual Override Rates</h2>
                    <?php if (!empty($manual_rate['updated_at'])): ?>
                        <span class="text-[11px] font-medium text-gray-500"><i class="far fa-clock text-amber-500 mr-1"></i>Updated: <?= htmlspecialchars(cj_format_ist($manual_rate['updated_at'])) ?></span>
                    <?php endif; ?>
                </div>
                <form method="POST" action="<?= htmlspecialchars($cj_admin_home) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="rate_action" value="update_manual_rates">
                    <?php if (!empty($cj_admin_embed)): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                    <div class="admin-form-grid grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div>
                            <label for="manual_gold_rate" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Gold (per 1g)</label>
                            <input type="number" step="0.01" id="manual_gold_rate" name="manual_gold_rate" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" value="<?= $manual_rate['gold_rate'] ?? '' ?>" required>
                        </div>
                        <div>
                            <label for="manual_silver_rate" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Silver (per 1g)</label>
                            <input type="number" step="0.01" id="manual_silver_rate" name="manual_silver_rate" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" value="<?= $manual_rate['silver_rate'] ?? '' ?>" required>
                        </div>
                        <div>
                            <label for="manual_silver_925_rate" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Silver 92.5 (per 1g)</label>
                            <input type="number" step="0.01" id="manual_silver_925_rate" name="manual_silver_925_rate" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" value="<?= $manual_rate['silver_925_rate'] ?? '' ?>" required>
                        </div>
                        <div>
                            <label for="manual_copper_rate" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Copper (per 1kg)</label>
                            <input type="number" step="0.01" id="manual_copper_rate" name="manual_copper_rate" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" value="<?= $manual_rate['copper_rate'] ?? '' ?>" required>
                        </div>
                        <div class="col-span-1 sm:col-span-2">
                            <label for="manual_making_charges" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Making Charges (%)</label>
                            <input type="number" step="0.01" id="manual_making_charges" name="manual_making_charges" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" value="<?= $manual_rate['manual_making_charges_percent'] ?? '' ?>" required>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center">
                        <input type="checkbox" id="manual_apply_to_silver" name="manual_apply_to_silver" class="h-4 w-4 text-amber-500 border-gray-200 rounded focus:ring-amber-500" <?= ($manual_rate['manual_apply_making_to_silver'] ?? 0) ? 'checked' : '' ?>>
                        <label for="manual_apply_to_silver" class="ml-2 text-xs font-medium text-gray-600">Apply making charges to Silver as well</label>
                    </div>
                    <button type="submit" name="update_manual_rates" class="mt-6 w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-4 rounded-lg text-sm transition-colors duration-150 shadow-sm">Update Manual Rates</button>
                </form>
            </div>
        </div>

        <div id="rateFormulaSection" class="admin-card bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-6 sm:mb-8 scroll-mt-24">
            <h2 class="text-lg font-bold text-gray-900 mb-1 font-serif pb-2 border-b border-gray-100 flex items-center gap-2">
                <i class="fas fa-calculator text-amber-500"></i> Dynamic Rate Counting
            </h2>
            <p class="text-xs text-gray-500 mt-2 mb-4">Shop prices use the <strong>counted</strong> rate, not the full board rate. Pick a metal, then add +, −, ×, ÷ steps. Gold defaults to × 0.9166 ÷ 1.03 (22K after 3% GST).</p>

            <div class="overflow-x-auto mb-6">
                <table class="w-full text-sm min-w-[520px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider text-gray-400 border-b border-gray-100">
                            <th class="text-left font-semibold py-2 pr-3">Metal</th>
                            <th class="text-right font-semibold py-2 px-3">Board rate</th>
                            <th class="text-left font-semibold py-2 px-3">Formula</th>
                            <th class="text-right font-semibold py-2 pl-3">Counted rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($counted_rate_metrics as $metricRow): ?>
                        <tr class="border-b border-gray-50">
                            <td class="py-2.5 pr-3 font-semibold text-gray-800"><?= htmlspecialchars($metricRow['label']) ?></td>
                            <td class="py-2.5 px-3 text-right tabular-nums text-gray-600">₹<?= number_format($metricRow['market'], 2) ?> <span class="text-[10px] text-gray-400"><?= htmlspecialchars($metricRow['unit']) ?></span></td>
                            <td class="py-2.5 px-3 text-gray-500 font-mono text-xs"><?= htmlspecialchars($metricRow['formula']) ?></td>
                            <td class="py-2.5 pl-3 text-right tabular-nums font-bold text-amber-700">₹<?= number_format($metricRow['counted'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form method="POST" action="<?= htmlspecialchars($cj_admin_home) ?>" id="rateFormulaForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="rate_action" value="update_rate_formula">
                <input type="hidden" name="rate_formula_json" id="rate_formula_json" value="<?= htmlspecialchars(json_encode($rate_formula_map), ENT_QUOTES) ?>">
                <?php if (!empty($cj_admin_embed)): ?><input type="hidden" name="embed" value="1"><?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <label for="formula_metal" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Select rate</label>
                        <select id="formula_metal" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none">
                            <option value="gold">Gold</option>
                            <option value="silver">Silver</option>
                            <option value="silver_925">925 Silver</option>
                            <option value="copper">Copper</option>
                        </select>

                        <div class="mt-4 rounded-xl border border-amber-100 bg-amber-50/60 p-4">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-700 mb-1">Market rate (fixed from above)</p>
                            <p class="text-2xl font-bold text-gray-900 tabular-nums" id="formula_fixed_rate">₹ 0.00</p>
                            <p class="text-[11px] text-gray-500 mt-1" id="formula_fixed_meta">per 1g</p>
                        </div>

                        <div class="mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-xs font-semibold text-gray-500 uppercase">Operators</label>
                                <button type="button" id="formula_add_step" class="text-[11px] font-bold text-amber-700 hover:text-amber-800">+ Add step</button>
                            </div>
                            <div id="formula_steps" class="space-y-2"></div>
                            <p class="text-[11px] text-gray-400 mt-2">Each step runs in order on the fixed rate. Example: 15100 × 0.9166 ÷ 1.03</p>
                        </div>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-end gap-3 mb-3">
                            <div class="flex-1 min-w-[120px]">
                                <label for="formula_example_weight" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Example weight</label>
                                <input type="number" step="0.01" min="0" id="formula_example_weight" value="10" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none">
                            </div>
                            <p class="text-[11px] text-gray-400 pb-2" id="formula_example_unit">grams</p>
                        </div>
                        <div id="formula_live" class="rounded-xl border border-gray-100 bg-gray-50 p-4 text-sm text-gray-700 min-h-[180px]"></div>
                    </div>
                </div>

                <button type="submit" name="update_rate_formula" class="mt-6 w-full sm:w-auto bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-6 rounded-lg text-sm transition-colors duration-150 shadow-sm">Save Rate Counting Formula</button>
            </form>
        </div>

        <!-- Middle split: Add item & catalogue upload -->
        <div id="addItemSection" class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-8 mb-6 sm:mb-8 scroll-mt-24">
            <!-- Add Jewellery Item -->
            <div class="admin-card bg-white p-6 rounded-xl shadow-sm border border-gray-100 lg:col-span-2">
                <h2 class="text-lg font-bold text-gray-900 mb-4 font-serif pb-2 border-b border-gray-100"><i class="fas fa-plus-circle text-amber-500 mr-1"></i> Add New Jewellery Item</h2>
                <form method="POST" action="<?= htmlspecialchars($cj_admin_home) ?>" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <?php if (!empty($cj_admin_embed)): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                    
                    <?php
                    $suggested_names = ["Gold Ring", "Silver Ring", "925 Silver Ring", "Ladies Ring", "Men's Ring", "Engagement Ring", "Couple Rings", "Pendant Set", "Earrings", "Gold Chain", "Silver Chain", "Kada Bangle", "Mangalsutra", "Payal Anklet", "Nose Pin"];
                    $name_q = $conn->query("SELECT DISTINCT name FROM jewellery_items ORDER BY name ASC LIMIT 100");
                    if ($name_q) {
                        while ($nr = $name_q->fetch_assoc()) {
                            $suggested_names[] = $nr['name'];
                        }
                    }
                    $suggested_names = array_values(array_unique(array_filter($suggested_names)));
                    ?>
                    <datalist id="name_suggestions">
                        <?php foreach ($suggested_names as $sname): ?>
                            <option value="<?= htmlspecialchars($sname, ENT_QUOTES) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>

                    <div class="admin-form-grid grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <div>
                            <label for="name" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Item Name</label>
                            <input type="text" id="name" name="name" list="name_suggestions" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" required>
                        </div>
                        <div>
                            <label for="metal_type" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Metal Type</label>
                            <select id="metal_type" name="metal_type" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" onchange="calculatePrice('add')" required>
                                <option value="gold">Gold</option>
                                <option value="silver">Silver</option>
                                <option value="silver_925">Silver 92.5</option>
                            </select>
                        </div>
                        <div>
                            <label for="weight" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Weight (g)</label>
                            <input type="number" step="0.01" id="weight" name="weight" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" oninput="calculatePrice('add')" required>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label for="description" class="block text-xs font-semibold text-gray-500 uppercase">Description</label>
                                <?php if (cj_setting('gemini_api_key', '') !== ''): ?>
                                    <button type="button" id="ai-generate-btn" class="text-[10px] text-amber-600 hover:text-amber-700 font-bold flex items-center gap-1 focus:outline-none" onclick="generateAIDescription(false)"><i class="fas fa-magic"></i> AI Generate</button>
                                <?php endif; ?>
                            </div>
                            <textarea id="description" name="description" rows="2" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" required></textarea>
                        </div>
                        <div>
                            <label for="tags" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Search Tags / Keywords (comma separated)</label>
                            <input type="text" id="tags" name="tags" placeholder="e.g. handmade, wedding gift, chandi" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none">
                        </div>
                        <div>
                            <label for="purity" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Purity (optional)</label>
                            <input type="text" id="purity" name="purity" placeholder="e.g. 22K / 916" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                        </div>
                        <div>
                            <label for="category" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Type (optional)</label>
                            <input type="text" id="category" name="category" placeholder="e.g. Necklace" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                        </div>
                        <div>
                            <label for="stone" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Stone (optional)</label>
                            <input type="text" id="stone" name="stone" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                        </div>
                        <div>
                            <label for="dimensions" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Size (optional)</label>
                            <input type="text" id="dimensions" name="dimensions" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                        </div>
                        <div>
                            <label for="sku" class="block text-xs font-semibold text-gray-500 uppercase mb-1">SKU (optional)</label>
                            <input type="text" id="sku" name="sku" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                        </div>
                    </div>

                    <div class="mt-4 admin-check-row grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="use_manual_rates" name="use_manual_rates" class="h-4 w-4 text-amber-500 border-gray-200 rounded focus:ring-amber-500" onchange="calculatePrice('add')">
                            <label for="use_manual_rates" class="ml-2 text-xs font-semibold text-gray-600">Use Manual Override Rates</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="is_fav" name="is_fav" class="h-4 w-4 text-amber-500 border-gray-200 rounded focus:ring-amber-500">
                            <label for="is_fav" class="ml-2 text-xs font-semibold text-gray-600">Mark as Hot Favourite (Max 3)</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="is_sold_out" name="is_sold_out" class="h-4 w-4 text-rose-500 border-gray-200 rounded focus:ring-rose-500">
                            <label for="is_sold_out" class="ml-2 text-xs font-semibold text-gray-600">Sold Out on website</label>
                        </div>
                    </div>

                    <!-- Image upload wrapper -->
                    <div class="mt-5 border border-dashed border-gray-200 rounded-xl p-4 bg-gray-50/50">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Item Image</label>
                        <input type="file" id="image_upload" name="image" accept="image/*" class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100" required>
                        <input type="hidden" name="image_filename" id="image_filename">
                        
                        <div class="flex gap-4 items-center mt-4">
                            <img id="image_preview" src="#" alt="Preview" class="w-20 h-20 object-cover rounded-xl border border-gray-200 hidden">
                            <div class="flex-grow">
                                <div class="progress-bar-container hidden" id="progress_wrapper">
                                    <div class="progress-bar" id="progress_bar">0%</div>
                                </div>
                                <div id="progress_message" class="text-xs text-gray-500 mt-1"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 border border-dashed border-gray-200 rounded-xl p-4 bg-gray-50/50">
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Extra photos (optional, up to 6)</label>
                        <input type="file" name="extra_images[]" accept="image/*" multiple class="text-sm text-gray-500">
                    </div>
                    <div class="mt-6 flex justify-between items-center p-4 bg-amber-50/50 border border-amber-100 rounded-xl">
                        <span class="text-xs font-bold text-amber-800 uppercase tracking-wider">Calculated Value (Realtime)</span>
                        <span id="calculated_price" class="text-lg font-bold text-amber-700">₹ 0</span>
                    </div>

                    <button type="submit" name="add_item" id="add_item_btn" class="mt-5 w-full bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-2.5 px-4 rounded-lg text-sm transition-colors duration-150 shadow-sm">
                        Add New Item to Stock
                    </button>
                </form>
            </div>

            <!-- Bulk Catalogue Card -->
            <div class="admin-card bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 mb-4 font-serif pb-2 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                        <span><i class="fas fa-images text-amber-500 mr-1"></i> Catalogue Bulk</span>
                        <a href="/admin/delete_c.php" class="text-xs text-red-500 hover:underline">Manage Catalogue &rarr;</a>
                    </h2>
                    
                    <form method="POST" action="<?= htmlspecialchars($cj_admin_home) ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <?php if (!empty($cj_admin_embed)): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                        <div class="border border-dashed border-gray-200 rounded-xl p-4 bg-gray-50/50 text-center">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-300 mb-2"></i>
                            <label for="catalogue_images" class="block text-xs font-semibold text-gray-500 uppercase mb-2">Upload Multiple Photos</label>
                            <input type="file" id="catalogue_images" name="catalogue_images[]" multiple accept="image/png, image/jpeg, image/jpg" class="text-xs text-gray-500 w-full file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                        </div>
                        <button type="submit" name="upload_catalogue_images" class="mt-6 w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-4 rounded-lg text-sm transition-colors duration-150 shadow-sm">
                            Upload Catalogue
                        </button>
                    </form>
                </div>
                
                <div class="bg-amber-50 border border-amber-100 rounded-xl p-3.5 mt-6 text-xs text-amber-800 leading-relaxed">
                    <strong>Tip:</strong> Uploading items in bulk automatically creates placeholder catalogue entries using filenames.
                </div>
            </div>
        </div>

        <!-- Jewellery Items Stock List -->
        <div id="stockList" class="admin-stock-section admin-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8 scroll-mt-24">
            <h2 class="text-lg font-bold text-gray-900 p-6 font-serif border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                <span><i class="fas fa-list text-amber-500 mr-1"></i> Jewellery Items Stock List</span>
                <span class="text-xs font-sans font-medium text-amber-800 bg-amber-50 border border-amber-200 px-3 py-1 rounded-full"><?= (int)$total_items ?> Total Products Uploaded</span>
            </h2>

            <?php
            $csrf_tok = urlencode($_SESSION['csrf_token']);
            $has_products = count($product_rows) > 0;
            $page_q = $page > 1 ? '&amp;page=' . (int)$page : '';
            ?>

            <!-- Mobile card list -->
            <div class="admin-stock-cards">
                <?php if ($has_products): ?>
                    <?php foreach ($product_rows as $idx => $row):
                        $current_price = calculate_item_price_from_rates($row['weight'], $row['metal_type'], $current_rates);
                        $manual_price_val = calculate_item_price_from_rates($row['weight'], $row['metal_type'], $manual_rates);
                        $display_price = $row['use_manual_rates'] ? $manual_price_val : $current_price;
                        $metal_label = ucfirst(str_replace('_', ' ', $row['metal_type']));
                        $is_sold = !empty($row['is_sold_out']);
                    ?>
                        <article class="stock-card<?= $is_sold ? ' is-sold-out' : '' ?>" style="animation-delay: <?= min($idx * 0.04, 0.4) ?>s">
                            <div class="stock-card-top">
                                <img src="<?= htmlspecialchars(cj_img_url($row['image'], 160, 'uploads')) ?>" alt="" loading="lazy" decoding="async" width="80" height="80">
                                <div class="stock-card-meta">
                                    <h3><?= htmlspecialchars($row['name']) ?></h3>
                                    <p><?= htmlspecialchars($row['weight']) ?> g · <?= htmlspecialchars($metal_label) ?></p>
                                    <p class="text-[11px] text-gray-400 mt-0.5"><i class="far fa-clock mr-1"></i>Uploaded: <?= htmlspecialchars(cj_format_ist($row['created_at'] ?? '')) ?></p>
                                    <div class="stock-card-price mt-1">₹<?= number_format(round($display_price), 0) ?></div>
                                    <div class="stock-card-row mt-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $row['use_manual_rates'] ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-500' ?>">
                                            Override: <?= $row['use_manual_rates'] ? 'Yes' : 'No' ?>
                                        </span>
                                        <?php if ($row['is_favourite']): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-rose-500"><i class="fas fa-heart"></i> Favourite</span>
                                        <?php endif; ?>
                                        <?php if ($is_sold): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-full">Sold Out</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="stock-card-actions stock-card-actions-4">
                                <a href="?toggle_fav=<?= (int)$row['id'] ?>&amp;current=<?= (int)$row['is_favourite'] ?>&amp;csrf_token=<?= $csrf_tok ?><?= $page_q ?>" class="btn-fav<?= $row['is_favourite'] ? ' is-fav' : '' ?>" aria-label="Toggle favourite"><i class="fas fa-heart"></i> Fav</a>
                                <a href="?toggle_sold=<?= (int)$row['id'] ?>&amp;current=<?= $is_sold ? 1 : 0 ?>&amp;csrf_token=<?= $csrf_tok ?><?= $page_q ?>" class="btn-sold<?= $is_sold ? ' is-sold' : '' ?>" aria-label="Toggle sold out"><i class="fas fa-ban"></i> <?= $is_sold ? 'Available' : 'Sold Out' ?></a>
                                <button type="button" class="btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)"><i class="fas fa-pen"></i> Edit</button>
                                <a href="?delete=<?= (int)$row['id'] ?>&amp;csrf_token=<?= $csrf_tok ?>" class="btn-del" onclick="return confirm('Are you sure you want to delete this item?');"><i class="fas fa-trash"></i> Delete</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-gray-500 font-medium py-8 px-4">No jewellery items found. Add items to see them here!</p>
                <?php endif; ?>
            </div>

            <!-- Desktop table -->
            <div class="admin-stock-table-wrap overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50/50 font-semibold text-gray-500 uppercase tracking-wider text-xs">
                        <tr>
                            <th scope="col" class="px-6 py-3.5 text-left">Image</th>
                            <th scope="col" class="px-6 py-3.5 text-left">Name</th>
                            <th scope="col" class="px-6 py-3.5 text-left">Weight</th>
                            <th scope="col" class="px-6 py-3.5 text-left">Metal</th>
                            <th scope="col" class="px-6 py-3.5 text-left">Calculated Price</th>
                            <th scope="col" class="px-6 py-3.5 text-left">Uploaded (IST)</th>
                            <th scope="col" class="px-6 py-3.5 text-center">Status</th>
                            <th scope="col" class="px-6 py-3.5 text-center">Favourite</th>
                            <th scope="col" class="px-6 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php if ($has_products): ?>
                            <?php foreach ($product_rows as $row):
                                $current_price = calculate_item_price_from_rates($row['weight'], $row['metal_type'], $current_rates);
                                $manual_price_val = calculate_item_price_from_rates($row['weight'], $row['metal_type'], $manual_rates);
                                $display_price = $row['use_manual_rates'] ? $manual_price_val : $current_price;
                                $is_sold = !empty($row['is_sold_out']);
                                $uploadedDateIst = !empty($row['created_at']) ? cj_format_ist($row['created_at'], 'd M Y', false) : '—';
                                $uploadedTimeIst = !empty($row['created_at']) ? cj_format_ist($row['created_at'], 'h:i A', true) : '';
                            ?>
                                <tr class="hover:bg-gray-50/50 transition-colors duration-150<?= $is_sold ? ' opacity-75' : '' ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <img class="h-10 w-10 rounded-lg object-cover border border-gray-100 shadow-sm" src="<?= htmlspecialchars(cj_img_url($row['image'], 80, 'uploads')) ?>" alt="Product photo" loading="lazy" decoding="async" width="40" height="40">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-semibold text-gray-900"><?= htmlspecialchars($row['name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500"><?= htmlspecialchars($row['weight']) ?> g</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-800 border border-amber-100">
                                            <?= ucfirst(str_replace('_', ' ', $row['metal_type'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-bold text-gray-900">₹<?= number_format(round($display_price), 0) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-xs font-semibold text-gray-800"><?= htmlspecialchars($uploadedDateIst) ?></div>
                                        <?php if ($uploadedTimeIst !== ''): ?>
                                            <div class="text-[11px] text-gray-400"><?= htmlspecialchars($uploadedTimeIst) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <a href="?toggle_sold=<?= (int)$row['id'] ?>&amp;current=<?= $is_sold ? 1 : 0 ?>&amp;csrf_token=<?= $csrf_tok ?><?= $page_q ?>" class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold transition-colors <?= $is_sold ? 'bg-rose-100 text-rose-700 hover:bg-rose-200' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>">
                                            <?= $is_sold ? 'Sold Out' : 'Available' ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <a href="?toggle_fav=<?= (int)$row['id'] ?>&amp;current=<?= (int)$row['is_favourite'] ?>&amp;csrf_token=<?= $csrf_tok ?><?= $page_q ?>" class="text-base <?= $row['is_favourite'] ? 'text-rose-500 hover:text-rose-600' : 'text-gray-200 hover:text-rose-400' ?> transition-colors duration-150">
                                            <i class="fas fa-heart"></i>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                                        <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)" class="text-amber-600 hover:text-amber-700 mr-4 font-semibold">Edit</button>
                                        <a href="?delete=<?= (int)$row['id'] ?>&amp;csrf_token=<?= $csrf_tok ?>" class="text-red-600 hover:text-red-700 font-semibold" onclick="return confirm('Are you sure you want to delete this item?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-6 py-8 text-center text-gray-500 font-medium">No jewellery items found. Add items to see them here!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Server-side Pagination Links -->
            <?php if ($total_pages > 1): ?>
                <div class="admin-pagination bg-gray-50/50 border-t border-gray-100 px-4 sm:px-6 py-4 flex items-center justify-between">
                    <div class="text-xs text-gray-500 font-medium text-center sm:text-left">
                        Showing page <?= $page ?> of <?= $total_pages ?>
                    </div>
                    <div class="pager-links flex gap-2 flex-wrap justify-center">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="px-3.5 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-xs font-semibold text-gray-600 transition-colors">&larr; Prev</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition-all <?= $i == $page ? 'bg-amber-500 border-amber-500 text-white shadow-sm' : 'border-gray-200 bg-white hover:bg-gray-50 text-gray-600' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="px-3.5 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-xs font-semibold text-gray-600 transition-colors">Next &rarr;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php
        if (!function_exists('cj_business_info')) {
            require_once dirname(__DIR__) . '/includes/seo.php';
        }
        $cj_fu = cj_business_info();
        $cj_fu_site = 'https://chandrakalajewellers.in';
        $cj_fu_ig = $cj_fu['instagram'] ?? 'https://www.instagram.com/chandra.kalajewellers?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw==';
        $cj_fu_review = $cj_fu['google_review'] ?? 'https://g.page/r/CWqeb0I3tG0hEAI/review';
        $cj_fu_listing = $cj_fu['google_listing'] ?? 'https://www.google.co.in/search?kgmid=/g/11q360mjpq&q=Chandrakala+jewellers';
        ?>
        <section id="followUsSection" class="admin-card bg-white rounded-xl shadow-sm border border-amber-100 overflow-hidden mb-8 scroll-mt-24">
            <div class="p-5 sm:p-6 border-b border-amber-100 bg-gradient-to-r from-amber-50/80 to-white">
                <h2 class="text-lg font-bold text-gray-900 font-serif flex items-center gap-2">
                    <i class="fas fa-qrcode text-amber-500"></i> Follow Us
                </h2>
                <p class="text-xs text-gray-500 mt-1">Google review link, Instagram, and printable QR codes for the shop floor.</p>
            </div>
            <div class="p-4 sm:p-6 space-y-5">
                <div class="rounded-xl border border-amber-100 bg-amber-50/40 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-amber-700 mb-1">Google · Leave a review</p>
                        <p class="text-sm font-semibold text-gray-900 break-all"><?= htmlspecialchars($cj_fu_review) ?></p>
                        <p class="text-xs text-gray-500 mt-1">Opens Google’s review form (iframe embeds are blocked by Google).</p>
                    </div>
                    <div class="flex flex-wrap gap-2 shrink-0">
                        <a href="<?= htmlspecialchars($cj_fu_review) ?>" target="_blank" rel="noopener" class="inline-flex items-center justify-center bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-4 rounded-lg text-sm transition-colors shadow-sm">
                            <i class="fas fa-star mr-2"></i> Leave a review
                        </a>
                        <a href="<?= htmlspecialchars($cj_fu_listing) ?>" target="_blank" rel="noopener" class="inline-flex items-center justify-center bg-white hover:bg-amber-50 text-amber-800 font-semibold py-2.5 px-4 rounded-lg text-sm border border-amber-200 transition-colors">
                            View listing
                        </a>
                        <a href="../follow-us" target="_blank" rel="noopener" class="inline-flex items-center justify-center bg-white hover:bg-gray-50 text-gray-700 font-semibold py-2.5 px-4 rounded-lg text-sm border border-gray-200 transition-colors">
                            Public page ↗
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <article class="rounded-xl border border-amber-100 bg-white p-4 text-center shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 font-serif mb-1">Website</h3>
                        <p class="text-[11px] text-gray-400 break-all mb-3"><?= htmlspecialchars($cj_fu_site) ?></p>
                        <div id="admin-qr-website" class="mx-auto mb-3 flex items-center justify-center min-h-[148px] w-fit max-w-full p-2 bg-white border border-gray-100 rounded-xl" role="img" aria-label="QR code for website"></div>
                        <a href="<?= htmlspecialchars($cj_fu_site) ?>" target="_blank" rel="noopener" class="text-xs font-semibold text-amber-700 hover:underline">Open website</a>
                    </article>
                    <article class="rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 font-serif mb-1">Instagram</h3>
                        <p class="text-[11px] text-gray-400 mb-3">@chandra.kalajewellers</p>
                        <div id="admin-qr-instagram" class="mx-auto mb-3 flex items-center justify-center min-h-[148px] w-fit max-w-full p-2 bg-white border border-gray-100 rounded-xl" role="img" aria-label="QR code for Instagram"></div>
                        <a href="<?= htmlspecialchars($cj_fu_ig) ?>" target="_blank" rel="noopener me" class="text-xs font-semibold text-amber-700 hover:underline">Open Instagram</a>
                    </article>
                    <article class="rounded-xl border border-amber-100 bg-amber-50/30 p-4 text-center shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 font-serif mb-1">Google review</h3>
                        <p class="text-[11px] text-gray-400 mb-3">g.page review short link</p>
                        <div id="admin-qr-google" class="mx-auto mb-3 flex items-center justify-center min-h-[148px] w-fit max-w-full p-2 bg-white border border-amber-100 rounded-xl" role="img" aria-label="QR code for Google review"></div>
                        <a href="<?= htmlspecialchars($cj_fu_review) ?>" target="_blank" rel="noopener" class="text-xs font-semibold text-amber-700 hover:underline">Leave a review</a>
                    </article>
                </div>
            </div>
        </section>

        <?php include dirname(__DIR__) . '/includes/visitor-analytics-admin.php'; ?>

    </main>

    <!-- Edit Item Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3 class="text-lg font-bold text-gray-900 mb-4 font-serif pb-2 border-b border-gray-100">Edit Jewellery Item</h3>
            <form id="editForm" method="POST" action="<?= htmlspecialchars($cj_admin_home) ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <?php if (!empty($cj_admin_embed)): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                <input type="hidden" name="item_id" id="edit_item_id">
                <input type="hidden" name="current_image" id="edit_current_image">
                <input type="hidden" name="edit_image_filename" id="edit_image_filename">
                
                <div class="admin-form-grid grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div class="col-span-1 sm:col-span-2">
                        <label for="edit_name" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Item Name</label>
                        <input type="text" id="edit_name" name="name" list="name_suggestions" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" required>
                    </div>
                    <div class="col-span-1 sm:col-span-2">
                        <div class="flex justify-between items-center mb-1">
                            <label for="edit_description" class="block text-xs font-semibold text-gray-500 uppercase">Description</label>
                            <?php if (cj_setting('gemini_api_key', '') !== ''): ?>
                                <button type="button" id="edit-ai-generate-btn" class="text-[10px] text-amber-600 hover:text-amber-700 font-bold flex items-center gap-1 focus:outline-none" onclick="generateAIDescription(true)"><i class="fas fa-magic"></i> AI Generate</button>
                            <?php endif; ?>
                        </div>
                        <textarea id="edit_description" name="description" rows="2" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" required></textarea>
                    </div>
                    <div class="col-span-1 sm:col-span-2">
                        <label for="edit_tags" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Search Tags / Keywords (comma separated)</label>
                        <input type="text" id="edit_tags" name="tags" placeholder="e.g. handmade, wedding gift, chandi" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="edit_purity" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Purity</label>
                        <input type="text" id="edit_purity" name="purity" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label for="edit_category" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Type</label>
                        <input type="text" id="edit_category" name="category" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label for="edit_stone" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Stone</label>
                        <input type="text" id="edit_stone" name="stone" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label for="edit_dimensions" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Size</label>
                        <input type="text" id="edit_dimensions" name="dimensions" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                    </div>
                    <div class="col-span-1 sm:col-span-2">
                        <label for="edit_sku" class="block text-xs font-semibold text-gray-500 uppercase mb-1">SKU</label>
                        <input type="text" id="edit_sku" name="sku" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm">
                    </div>
                    <div>
                        <label for="edit_weight" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Weight (g)</label>
                        <input type="number" step="0.01" id="edit_weight" name="weight" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" oninput="calculatePrice('edit')" required>
                    </div>
                    <div>
                        <label for="edit_metal_type" class="block text-xs font-semibold text-gray-500 uppercase mb-1">Metal Type</label>
                        <select id="edit_metal_type" name="metal_type" class="w-full p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none" onchange="calculatePrice('edit')" required>
                            <option value="gold">Gold</option>
                            <option value="silver">Silver</option>
                            <option value="silver_925">Silver 92.5</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 admin-check-row grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div class="flex items-center">
                        <input type="checkbox" id="edit_use_manual_rates" name="edit_use_manual_rates" class="h-4 w-4 text-amber-500 border-gray-200 rounded focus:ring-amber-500" onchange="calculatePrice('edit')">
                        <label for="edit_use_manual_rates" class="ml-2 text-xs font-semibold text-gray-600">Use Manual Rates</label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="edit_is_fav" name="is_fav" class="h-4 w-4 text-amber-500 border-gray-200 rounded focus:ring-amber-500">
                        <label for="edit_is_fav" class="ml-2 text-xs font-semibold text-gray-600">Favourite (Max 3)</label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="edit_is_sold_out" name="is_sold_out" class="h-4 w-4 text-rose-500 border-gray-200 rounded focus:ring-rose-500">
                        <label for="edit_is_sold_out" class="ml-2 text-xs font-semibold text-gray-600">Sold Out on website</label>
                    </div>
                </div>

                <div class="mt-4 border border-dashed border-gray-200 rounded-xl p-3 bg-gray-50/50">
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Change Image (Optional)</label>
                    <input type="file" id="edit_image_upload" name="image" accept="image/*" class="text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                    
                    <div class="flex gap-4 items-center mt-3">
                        <img id="edit_image_preview" src="#" alt="Preview" class="w-16 h-16 object-cover rounded-xl border border-gray-200 hidden">
                        <div class="flex-grow">
                            <div class="progress-bar-container hidden" id="edit_progress_wrapper">
                                <div class="progress-bar" id="edit_progress_bar">0%</div>
                            </div>
                            <div id="edit_progress_message" class="text-xs text-gray-500 mt-1"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 border border-dashed border-gray-200 rounded-xl p-3 bg-gray-50/50">
                    <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Add extra photos</label>
                    <input type="file" name="extra_images[]" accept="image/*" multiple class="text-xs text-gray-500">
                </div>
                    <span class="text-xs font-bold text-amber-800 uppercase tracking-wider">Calculated Value</span>
                    <span id="edit_calculated_price" class="text-base font-bold text-amber-700">₹ 0</span>
                </div>

                <div class="flex justify-end gap-3 mt-6 border-t border-gray-100 pt-4">
                    <button type="button" class="bg-gray-50 hover:bg-gray-100 text-gray-700 font-semibold py-2 px-5 rounded-lg text-xs border border-gray-200" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="update_item" id="update_item_btn" class="bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 px-5 rounded-lg text-xs transition-colors shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../js/pricing.js"></script>
    <script>
        const currentGoldRate = parseFloat(<?= json_encode((float) ($rate['gold_rate'] ?? 0)) ?>);
        const currentSilverRate = parseFloat(<?= json_encode((float) ($rate['silver_rate'] ?? 0)) ?>);
        const currentSilver925Rate = parseFloat(<?= json_encode((float) ($rate['silver_925_rate'] ?? 0)) ?>);
        const currentCopperRate = parseFloat(<?= json_encode((float) ($rate['copper_rate'] ?? 0)) ?>);
        const currentMakingCharges = parseFloat(<?= json_encode((float) ($rate['making_charges_percent'] ?? 0)) ?>);
        const currentApplyMakingToSilver = <?= isset($rate['apply_making_to_silver']) && $rate['apply_making_to_silver'] ? 'true' : 'false' ?>;

        const manualGoldRate = parseFloat(<?= json_encode((float) ($manual_rate['gold_rate'] ?? 0)) ?>);
        const manualSilverRate = parseFloat(<?= json_encode((float) ($manual_rate['silver_rate'] ?? 0)) ?>);
        const manualSilver925Rate = parseFloat(<?= json_encode((float) ($manual_rate['silver_925_rate'] ?? 0)) ?>);
        const manualCopperRate = parseFloat(<?= json_encode((float) ($manual_rate['copper_rate'] ?? 0)) ?>);
        const manualMakingCharges = parseFloat(<?= json_encode((float) ($manual_rate['manual_making_charges_percent'] ?? 0)) ?>);
        const manualApplyMakingToSilver = <?= isset($manual_rate['manual_apply_making_to_silver']) && $manual_rate['manual_apply_making_to_silver'] ? 'true' : 'false' ?>;

        window.CJ_RATE_FORMULA = <?= json_encode($rate_formula_map, JSON_UNESCAPED_SLASHES) ?>;

        function calculatePrice(formType) {
            let weight, metalType, useManualRates;
            let calculatedPriceElement;

            if (formType === 'add') {
                weight = parseFloat(document.getElementById('weight').value);
                metalType = document.getElementById('metal_type').value;
                useManualRates = document.getElementById('use_manual_rates').checked;
                calculatedPriceElement = document.getElementById('calculated_price');
            } else if (formType === 'edit') {
                weight = parseFloat(document.getElementById('edit_weight').value);
                metalType = document.getElementById('edit_metal_type').value;
                useManualRates = document.getElementById('edit_use_manual_rates').checked;
                calculatedPriceElement = document.getElementById('edit_calculated_price');
            }

            if (isNaN(weight)) {
                calculatedPriceElement.textContent = "₹ 0";
                return;
            }

            let rates_to_use = {};
            if (useManualRates) {
                rates_to_use = {
                    gold_rate: manualGoldRate,
                    silver_rate: manualSilverRate,
                    silver_925_rate: manualSilver925Rate,
                    copper_rate: manualCopperRate,
                    making_charges: manualMakingCharges,
                    apply_making_to_silver: manualApplyMakingToSilver,
                    rate_formula: window.CJ_RATE_FORMULA
                };
            } else {
                rates_to_use = {
                    gold_rate: currentGoldRate,
                    silver_rate: currentSilverRate,
                    silver_925_rate: currentSilver925Rate,
                    copper_rate: currentCopperRate,
                    making_charges: currentMakingCharges,
                    apply_making_to_silver: currentApplyMakingToSilver,
                    rate_formula: window.CJ_RATE_FORMULA
                };
            }

            const price = cjCalculatePrice(metalType, weight, rates_to_use);
            calculatedPriceElement.textContent = `₹ ${Math.round(price).toLocaleString('en-IN')}`;
        }

        (function initRateFormulaBuilder() {
            var metalSelect = document.getElementById('formula_metal');
            var stepsEl = document.getElementById('formula_steps');
            var liveEl = document.getElementById('formula_live');
            var jsonEl = document.getElementById('rate_formula_json');
            var form = document.getElementById('rateFormulaForm');
            if (!metalSelect || !stepsEl || !liveEl || !jsonEl) return;

            var FACTORS = [
                { value: '', label: 'Custom value' },
                { value: '0.9166', label: '0.9166 — 22K / 916' },
                { value: '0.75', label: '0.75 — 18K' },
                { value: '0.585', label: '0.585 — 14K' },
                { value: '0.925', label: '0.925 — 925 silver' },
                { value: '1.03', label: '1.03 — GST 3%' },
                { value: '1.05', label: '1.05' },
                { value: '10', label: '10' },
                { value: '100', label: '100' }
            ];
            var METAL_META = {
                gold: { label: 'Gold', field: 'gold_rate', unit: 'per 1g', exampleUnit: 'grams', defaultWeight: 10 },
                silver: { label: 'Silver', field: 'silver_rate', unit: 'per 1g', exampleUnit: 'grams', defaultWeight: 10 },
                silver_925: { label: '925 Silver', field: 'silver_925_rate', unit: 'per 1g', exampleUnit: 'grams', defaultWeight: 10 },
                copper: { label: 'Copper', field: 'copper_rate', unit: 'per 1kg', exampleUnit: 'kg', defaultWeight: 1 }
            };

            function cloneFormula(src) {
                try {
                    return JSON.parse(JSON.stringify(src || window.CJ_DEFAULT_RATE_FORMULA || {}));
                } catch (e) {
                    return { gold: [], silver: [], silver_925: [], copper: [] };
                }
            }

            var state = cloneFormula(window.CJ_RATE_FORMULA);
            ['gold', 'silver', 'silver_925', 'copper'].forEach(function (k) {
                if (!Array.isArray(state[k])) state[k] = [];
            });

            function liveBoardRate(metal) {
                var meta = METAL_META[metal] || METAL_META.gold;
                var input = document.getElementById(meta.field);
                if (input && input.value !== '') {
                    var n = parseFloat(input.value);
                    if (!isNaN(n)) return n;
                }
                if (metal === 'gold') return currentGoldRate;
                if (metal === 'silver') return currentSilverRate;
                if (metal === 'silver_925') return currentSilver925Rate;
                if (metal === 'copper') return currentCopperRate;
                return 0;
            }

            function liveMaking() {
                var input = document.getElementById('making_charges');
                if (input && input.value !== '') {
                    var n = parseFloat(input.value);
                    if (!isNaN(n)) return n;
                }
                return currentMakingCharges;
            }

            function liveApplySilver() {
                var box = document.getElementById('apply_to_silver');
                return box ? !!box.checked : currentApplyMakingToSilver;
            }

            function inr(n, digits) {
                digits = typeof digits === 'number' ? digits : 2;
                return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: digits, maximumFractionDigits: digits });
            }

            function opLabel(op) {
                if (op === '*') return '×';
                if (op === '/') return '÷';
                if (op === '-') return '−';
                return '+';
            }

            function factorMatch(value) {
                var s = String(value);
                for (var i = 0; i < FACTORS.length; i++) {
                    if (FACTORS[i].value !== '' && FACTORS[i].value === s) return FACTORS[i].value;
                }
                return '';
            }

            function currentMetal() {
                return metalSelect.value || 'gold';
            }

            function readVisibleSteps() {
                var rows = stepsEl.querySelectorAll('[data-formula-row]');
                var steps = [];
                rows.forEach(function (row) {
                    var op = row.querySelector('.formula-op');
                    var val = row.querySelector('.formula-value');
                    if (!op || !val) return;
                    steps.push({ op: op.value, value: parseFloat(val.value) || 0 });
                });
                state[currentMetal()] = steps;
                jsonEl.value = JSON.stringify(state);
                window.CJ_RATE_FORMULA = cloneFormula(state);
            }

            function addRow(step) {
                step = step || { op: '*', value: 1 };
                var row = document.createElement('div');
                row.setAttribute('data-formula-row', '1');
                row.className = 'flex flex-wrap items-center gap-2';

                var op = document.createElement('select');
                op.className = 'formula-op p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none';
                [['*', '× Multiply'], ['/', '÷ Divide'], ['+', '+ Add'], ['-', '− Subtract']].forEach(function (pair) {
                    var opt = document.createElement('option');
                    opt.value = pair[0];
                    opt.textContent = pair[1];
                    if (pair[0] === step.op) opt.selected = true;
                    op.appendChild(opt);
                });

                var factor = document.createElement('select');
                factor.className = 'formula-factor flex-1 min-w-[140px] p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none';
                var matched = factorMatch(step.value);
                FACTORS.forEach(function (f) {
                    var opt = document.createElement('option');
                    opt.value = f.value;
                    opt.textContent = f.label;
                    if (f.value === matched) opt.selected = true;
                    factor.appendChild(opt);
                });

                var val = document.createElement('input');
                val.type = 'number';
                val.step = 'any';
                val.className = 'formula-value w-28 p-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none';
                val.value = step.value;

                factor.addEventListener('change', function () {
                    if (factor.value !== '') val.value = factor.value;
                    renderLive();
                });

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'text-xs font-semibold text-rose-600 hover:text-rose-700 px-2 py-2';
                remove.textContent = 'Remove';
                remove.addEventListener('click', function () {
                    row.remove();
                    renderLive();
                });

                op.addEventListener('change', renderLive);
                val.addEventListener('input', function () {
                    factor.value = factorMatch(val.value);
                    renderLive();
                });

                row.appendChild(op);
                row.appendChild(factor);
                row.appendChild(val);
                row.appendChild(remove);
                stepsEl.appendChild(row);
            }

            function renderSteps() {
                stepsEl.innerHTML = '';
                var steps = state[currentMetal()] || [];
                if (!steps.length) {
                    var empty = document.createElement('p');
                    empty.className = 'text-xs text-gray-400';
                    empty.textContent = 'No extra counting — full board rate is used. Add a step to convert it.';
                    stepsEl.appendChild(empty);
                } else {
                    steps.forEach(addRow);
                }
            }

            function renderLive() {
                readVisibleSteps();
                var metal = currentMetal();
                var meta = METAL_META[metal] || METAL_META.gold;
                var board = liveBoardRate(metal);
                var steps = state[metal] || [];
                var making = liveMaking();
                var applySilver = liveApplySilver();
                var weightInput = document.getElementById('formula_example_weight');
                var weight = weightInput ? parseFloat(weightInput.value) : meta.defaultWeight;
                if (isNaN(weight) || weight < 0) weight = 0;

                var fixedEl = document.getElementById('formula_fixed_rate');
                var fixedMeta = document.getElementById('formula_fixed_meta');
                if (fixedEl) fixedEl.textContent = '₹ ' + inr(board);
                if (fixedMeta) fixedMeta.textContent = meta.label + ' · ' + meta.unit + ' from Standard Auto Rates';
                var unitEl = document.getElementById('formula_example_unit');
                if (unitEl) unitEl.textContent = meta.exampleUnit;

                var html = '';
                html += '<p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-2">Live example</p>';
                html += '<ol class="space-y-1.5 font-mono text-[13px] leading-relaxed">';
                html += '<li><span class="text-gray-400">Board</span> ₹' + inr(board) + '</li>';
                var running = board;
                if (!steps.length) {
                    html += '<li class="text-gray-500">No operators — counted rate stays the full board rate</li>';
                } else {
                    steps.forEach(function (step) {
                        var before = running;
                        running = window.cjApplyRateFormula(running, [step]);
                        html += '<li>' + inr(before) + ' ' + opLabel(step.op) + ' ' + inr(step.value, 4) + ' = <strong>₹' + inr(running) + '</strong></li>';
                    });
                }
                html += '</ol>';
                html += '<p class="mt-3 text-base font-bold text-amber-800">Counted rate: ₹' + inr(running) + ' ' + meta.unit + '</p>';

                var metalValue = running * weight;
                var applyMaking = metal === 'gold' || ((metal === 'silver' || metal === 'silver_925') && applySilver);
                var makingAmt = applyMaking ? metalValue * (making / 100) : 0;
                var total = metalValue + makingAmt;
                html += '<div class="mt-3 pt-3 border-t border-gray-200 text-xs text-gray-600 space-y-1">';
                html += '<p>' + inr(running) + ' × ' + inr(weight, 2) + ' ' + meta.exampleUnit + ' = ₹' + inr(metalValue) + '</p>';
                if (applyMaking) {
                    html += '<p>+ Making ' + inr(making, 2) + '% = ₹' + inr(makingAmt) + '</p>';
                } else {
                    html += '<p>Making not applied to this metal</p>';
                }
                html += '<p class="text-sm font-bold text-gray-900">Example jewellery: ₹' + inr(total, 0) + '</p>';
                html += '</div>';
                liveEl.innerHTML = html;
            }

            var lastMetal = currentMetal();

            metalSelect.addEventListener('change', function () {
                var rows = stepsEl.querySelectorAll('[data-formula-row]');
                var saved = [];
                rows.forEach(function (row) {
                    var op = row.querySelector('.formula-op');
                    var val = row.querySelector('.formula-value');
                    if (!op || !val) return;
                    saved.push({ op: op.value, value: parseFloat(val.value) || 0 });
                });
                state[lastMetal] = saved;
                lastMetal = currentMetal();
                var meta = METAL_META[lastMetal] || METAL_META.gold;
                var weightInput = document.getElementById('formula_example_weight');
                if (weightInput && (weightInput.value === '' || weightInput.value === '10' || weightInput.value === '1')) {
                    weightInput.value = String(meta.defaultWeight);
                }
                renderSteps();
                renderLive();
            });

            var addBtn = document.getElementById('formula_add_step');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    var emptyNote = stepsEl.querySelector('p');
                    if (emptyNote) emptyNote.remove();
                    addRow({ op: '*', value: 1 });
                    renderLive();
                });
            }

            var weightInput = document.getElementById('formula_example_weight');
            if (weightInput) weightInput.addEventListener('input', renderLive);

            ['gold_rate', 'silver_rate', 'silver_925_rate', 'copper_rate', 'making_charges'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('input', renderLive);
            });
            var applyBox = document.getElementById('apply_to_silver');
            if (applyBox) applyBox.addEventListener('change', renderLive);

            if (form) {
                form.addEventListener('submit', function () {
                    readVisibleSteps();
                });
            }

            renderSteps();
            renderLive();
        })();
        
        function compressAndUpload(file, callback) {
            if (!file.type.startsWith('image/')) {
                callback(file);
                return;
            }
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = (event) => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const MAX_WIDTH = 1200;
                    const MAX_HEIGHT = 1200;
                    let width = img.width;
                    let height = img.height;

                    if (width > height) {
                        if (width > MAX_WIDTH) {
                            height *= MAX_WIDTH / width;
                            width = MAX_WIDTH;
                        }
                    } else {
                        if (height > MAX_HEIGHT) {
                            width *= MAX_HEIGHT / height;
                            height = MAX_HEIGHT;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob((blob) => {
                        if (blob) {
                            const ext = file.name.split('.').pop();
                            const compressedFile = new File([blob], file.name.substring(0, file.name.lastIndexOf('.')) + '_compressed.' + ext, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });
                            callback(compressedFile);
                        } else {
                            callback(file);
                        }
                    }, 'image/jpeg', 0.85);
                };
                img.onerror = () => callback(file);
            };
            reader.onerror = () => callback(file);
        }

        function handleImageUpload(inputFileId, previewId, progressBarId, messageId, hiddenInputId, submitBtnId, progressWrapperId) {
            const inputFile = document.getElementById(inputFileId);
            if (!inputFile) return;
            inputFile.addEventListener('change', (event) => {
                const file = event.target.files[0];
                if (!file) return;

                const preview = document.getElementById(previewId);
                preview.src = URL.createObjectURL(file);
                preview.classList.remove('hidden');

                const progressBar = document.getElementById(progressBarId);
                const progressMessage = document.getElementById(messageId);
                const hiddenInput = document.getElementById(hiddenInputId);
                const submitBtn = document.getElementById(submitBtnId);
                const progressWrapper = document.getElementById(progressWrapperId);
                
                if (progressWrapper) progressWrapper.classList.remove('hidden');
                progressBar.style.width = '0%';
                progressBar.textContent = '0%';
                progressMessage.textContent = 'Compressing image...';
                
                if (submitBtn) {
                   submitBtn.disabled = true;
                   submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }

                compressAndUpload(file, (processedFile) => {
                    progressMessage.textContent = 'Uploading...';
                    const formData = new FormData();
                    formData.append('images[]', processedFile);

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'index.php?action=upload_images', true);

                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            progressBar.style.width = percent + '%';
                            progressBar.textContent = percent + '%';
                            if (percent === 100) {
                                progressMessage.textContent = 'Processing file...';
                            }
                        }
                    });

                    xhr.onload = () => {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.success && response.files && response.files.length > 0) {
                                    progressMessage.textContent = 'Image uploaded successfully!';
                                    hiddenInput.value = response.files[0].filename;
                                    if (submitBtn) {
                                        submitBtn.disabled = false;
                                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                                    }
                                } else {
                                    const errorMsg = response.message || (response.errors ? response.errors.join(', ') : 'Unknown upload error');
                                    progressMessage.textContent = 'Upload failed: ' + errorMsg;
                                    if (submitBtn) {
                                        submitBtn.disabled = false;
                                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                                    }
                                }
                            } catch(err) {
                                progressMessage.textContent = 'Response error parsing server output.';
                                if (submitBtn) {
                                    submitBtn.disabled = false;
                                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                                }
                            }
                        } else {
                            progressMessage.textContent = 'Connection error (HTTP ' + xhr.status + ').';
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                            }
                        }
                    };

                    xhr.onerror = () => {
                        progressMessage.textContent = 'Network connection failed.';
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        }
                    };

                    xhr.send(formData);
                });
            });
        }
        
        handleImageUpload('image_upload', 'image_preview', 'progress_bar', 'progress_message', 'image_filename', 'add_item_btn', 'progress_wrapper');
        handleImageUpload('edit_image_upload', 'edit_image_preview', 'edit_progress_bar', 'edit_progress_message', 'edit_image_filename', 'update_item_btn', 'edit_progress_wrapper');

        const editModal = document.getElementById('editModal');

        function openEditModal(item) {
            document.getElementById('edit_item_id').value = item.id;
            document.getElementById('edit_name').value = item.name;
            document.getElementById('edit_description').value = item.description;
            document.getElementById('edit_weight').value = item.weight;
            document.getElementById('edit_metal_type').value = item.metal_type;
            document.getElementById('edit_is_fav').checked = item.is_favourite == 1;
            document.getElementById('edit_is_sold_out').checked = item.is_sold_out == 1;
            document.getElementById('edit_current_image').value = item.image;
            document.getElementById('edit_image_preview').src = 'https://res.cloudinary.com/hihrgcxm/image/upload/f_auto,q_auto,w_400,c_limit/chandrakala/uploads/' + item.image;
            document.getElementById('edit_image_preview').classList.remove('hidden');
            document.getElementById('edit_use_manual_rates').checked = item.use_manual_rates == 1;
            document.getElementById('edit_purity').value = item.purity || '';
            document.getElementById('edit_category').value = item.category || '';
            document.getElementById('edit_stone').value = item.stone || '';
            document.getElementById('edit_dimensions').value = item.dimensions || '';
            document.getElementById('edit_sku').value = item.sku || '';
            document.getElementById('edit_tags').value = item.tags || '';

            calculatePrice('edit');
            editModal.style.display = 'flex';
        }

        function closeEditModal() {
            editModal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == editModal) {
                closeEditModal();
            }
        }

        async function generateAIDescription(isEdit) {
            var prefix = isEdit ? 'edit_' : '';
            var nameInput = document.getElementById(prefix + 'name');
            var metalInput = document.getElementById(prefix + 'metal_type');
            var weightInput = document.getElementById(prefix + 'weight');
            var descInput = document.getElementById(prefix + 'description');
            var tagsInput = document.getElementById(prefix + 'tags');
            var btn = document.getElementById(isEdit ? 'edit-ai-generate-btn' : 'ai-generate-btn');

            var nameVal = nameInput ? nameInput.value.trim() : '';
            var metalVal = metalInput ? metalInput.value : '';
            var weightVal = weightInput ? weightInput.value : '';

            if (!nameVal) {
                alert('Please enter an item name first.');
                return;
            }

            var apiKey = <?= json_encode(cj_setting('gemini_api_key', '')) ?>;
            if (!apiKey) {
                alert('Please configure your Gemini API Key in the settings first.');
                return;
            }

            var oldText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

            try {
                var promptText = "Generate a professional, short, and attractive description and search tags/keywords for a jewellery store item. Details: Name is '" + nameVal + "', Metal is '" + metalVal + "', Weight is '" + weightVal + "'. Return a JSON object with keys: 'description' (a beautiful 2-sentence description) and 'tags' (a comma-separated string of 10-15 highly relevant search keywords/synonyms/attributes like style, occasion, target group, type, e.g. hand-crafted, gift, wedding, chandi, sonar, etc.). Return only the raw JSON. Do not include markdown formatting, backticks, or any conversational text.";

                var res = await fetch('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' + apiKey, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        contents: [{
                            parts: [{ text: promptText }]
                        }]
                    })
                });

                if (!res.ok) throw new Error('API request failed: ' + res.statusText);
                var data = await res.json();
                
                var text = '';
                if (data && data.candidates && data.candidates[0] && data.candidates[0].content && data.candidates[0].content.parts && data.candidates[0].content.parts[0]) {
                    text = data.candidates[0].content.parts[0].text.trim();
                }

                // Remove markdown JSON code blocks if present
                text = text.replace(/^```json\s*/i, '').replace(/```\s*$/, '').trim();

                var parsed = JSON.parse(text);
                if (descInput && parsed.description) descInput.value = parsed.description;
                if (tagsInput && parsed.tags) tagsInput.value = parsed.tags;
            } catch (e) {
                alert('Error generating description: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = oldText;
            }
        }
        window.generateAIDescription = generateAIDescription;

        // Auto-dismiss alerts + subtle submit feedback
        document.querySelectorAll('.admin-alert').forEach(function (alert) {
            setTimeout(function () {
                alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-8px)';
                setTimeout(function () { alert.remove(); }, 420);
            }, 4500);
        });

        document.querySelectorAll('main form, #editForm').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('button[type="submit"]');
                if (!btn || btn.disabled) return;
                if (!btn.dataset.originalLabel) {
                    btn.dataset.originalLabel = btn.innerHTML;
                }
                setTimeout(function () {
                    btn.disabled = true;
                    btn.classList.add('opacity-80');
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving…';
                }, 0);
            });
        });

        (function initVisitorMap() {
            var el = document.getElementById('adminVisitorMap');
            var dataEl = document.getElementById('adminVisitorMapData');
            if (!el || !dataEl) return;
            var points = [];
            try { points = JSON.parse(dataEl.textContent || '[]'); } catch (e) { points = []; }

            var linkCss = document.createElement('link');
            linkCss.rel = 'stylesheet';
            linkCss.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(linkCss);

            var script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = function () {
                var map = L.map(el, { scrollWheelZoom: false }).setView([22.5, 72.5], 6);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 18,
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);

                if (!points.length) {
                    L.marker([24.0314, 73.0464]).addTo(map)
                        .bindPopup('ChandraKala Jewellers — Khedbrahma<br>Waiting for visitor locations…');
                    map.setView([24.0314, 73.0464], 11);
                } else {
                    var bounds = [];
                    points.forEach(function (p) {
                        var mins = Math.round((p.duration || 0) / 60);
                        var m = L.marker([p.lat, p.lng]).addTo(map);
                        m.bindPopup(
                            '<strong>' + (p.label || 'Visitor') + '</strong><br>' +
                            mins + ' min on site · ' + (p.pages || 0) + ' pages<br>' +
                            'Landed: ' + (p.landing || '—') + '<br>' +
                            'Last seen: ' + (p.when || '')
                        );
                        bounds.push([p.lat, p.lng]);
                    });
                    if (bounds.length === 1) map.setView(bounds[0], 12);
                    else map.fitBounds(bounds, { padding: [28, 28], maxZoom: 12 });
                }
                setTimeout(function () { map.invalidateSize(true); }, 200);
                setTimeout(function () { map.invalidateSize(true); }, 800);
            };
            document.body.appendChild(script);
        })();
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
    (function () {
        var siteUrl = <?= json_encode($cj_fu_site ?? 'https://chandrakalajewellers.in', JSON_UNESCAPED_SLASHES) ?>;
        var igUrl = <?= json_encode($cj_fu_ig ?? '', JSON_UNESCAPED_SLASHES) ?>;
        var reviewUrl = <?= json_encode($cj_fu_review ?? 'https://g.page/r/CWqeb0I3tG0hEAI/review', JSON_UNESCAPED_SLASHES) ?>;

        function paint(id, text, size) {
            var el = document.getElementById(id);
            if (!el || typeof QRCode === 'undefined') return;
            el.innerHTML = '';
            new QRCode(el, {
                text: text,
                width: size,
                height: size,
                colorDark: '#111827',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }

        function run() {
            paint('admin-qr-website', siteUrl, 148);
            paint('admin-qr-instagram', igUrl, 148);
            paint('admin-qr-google', reviewUrl, 148);
        }

        if (typeof QRCode !== 'undefined') {
            run();
        } else {
            setTimeout(run, 300);
        }
    })();
    </script>
    <script src="../js/pwa.js" defer></script>
    <script>
        setTimeout(function () {
            if (window.CJPwa) window.CJPwa.subscribe('admin');
        }, 3500);
        (function () {
            var el = document.getElementById('cjAdminIstClock');
            if (!el || typeof Intl === 'undefined' || !Intl.DateTimeFormat) return;
            var fmt = new Intl.DateTimeFormat('en-IN', {
                timeZone: 'Asia/Kolkata',
                day: '2-digit', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
            });
            function tick() {
                el.textContent = fmt.format(new Date()).replace(/\bam\b/i, 'AM').replace(/\bpm\b/i, 'PM') + ' IST';
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
