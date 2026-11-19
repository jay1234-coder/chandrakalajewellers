<?php
/**
 * Single-image Catalogue Upload API Endpoint
 * Handles asynchronous upload of individual catalogue photos with custom name, category, and progress tracking.
 */
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/activity-log.php';
require_once dirname(__DIR__, 2) . '/includes/img-helper.php';
require_once dirname(__DIR__, 2) . '/includes/cloudinary.php';
require_once dirname(__DIR__, 2) . '/includes/indexnow-helper.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (empty($_SESSION['loggedin']) && function_exists('cj_restore_auth_from_cookie')) {
    cj_restore_auth_from_cookie();
}
if (empty($_SESSION['loggedin'])) {
    cj_try_remember_login($conn, ['admin', 'superadmin']);
}

if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Admin session expired. Please re-login.']);
    exit;
}

if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF verification failed.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No image file uploaded.']);
    exit;
}

$file = $_FILES['image'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errMap = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server size limit.',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit.',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file received.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
    ];
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $errMap[$file['error']] ?? 'Upload error: ' . $file['error']]);
    exit;
}

$origName = $file['name'];
$tmpPath = $file['tmp_name'];
$fileExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

// Verify MIME
$mime = '';
if (function_exists('finfo_open')) {
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $mime = @finfo_file($finfo, $tmpPath);
        @finfo_close($finfo);
    }
}
if (empty($mime) && function_exists('mime_content_type')) {
    $mime = @mime_content_type($tmpPath);
}
if (!in_array($fileExt, $allowed, true) || (strpos((string)$mime, 'image/') !== 0 && !empty($mime))) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => "File '{$origName}' is not a supported image."]);
    exit;
}

// User-provided name and category
$cleanBase = pathinfo($origName, PATHINFO_FILENAME);
$cleanBase = ucwords(str_replace(['_', '-'], ' ', $cleanBase));
$productName = trim((string)($_POST['name'] ?? ''));
if ($productName === '') {
    $productName = $cleanBase ?: 'Custom Jewellery Design';
}

$category = trim((string)($_POST['category'] ?? ''));
if ($category === '' || strtolower($category) === 'all') {
    $category = 'Catalogue Design';
}

$description = trim((string)($_POST['description'] ?? ''));
if ($description === '') {
    $description = "Exquisite handcrafted {$productName} from ChandraKala Jewellers, Khedbrahma.";
}

$uploadDir = dirname(__DIR__, 2) . '/catalogue/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

$uniqueFilename = uniqid('jewellery_', true) . '.' . $fileExt;
$targetPath = $uploadDir . $uniqueFilename;

$uploaded = false;
$cldError = '';

if (function_exists('cj_cloudinary_is_enabled') && cj_cloudinary_is_enabled()) {
    $cldRes = cj_cloudinary_upload($tmpPath, $uniqueFilename, 'chandrakala/catalogue');
    if (!empty($cldRes['success'])) {
        $uploaded = true;
    } else {
        $cldError = $cldRes['error'] ?? 'Cloudinary upload failed.';
    }
}

if (is_writable($uploadDir) && @move_uploaded_file($tmpPath, $targetPath)) {
    $uploaded = true;
}

if (!$uploaded) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save image. ' . $cldError]);
    exit;
}

// Check if category column exists
$hasCat = ($conn->query("SHOW COLUMNS FROM products LIKE 'category'")?->num_rows ?? 0) > 0;
if ($hasCat) {
    $stmt = $conn->prepare("INSERT INTO products (name, description, image_filename, category, ocr_status) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param("ssss", $productName, $description, $uniqueFilename, $category);
} else {
    $stmt = $conn->prepare("INSERT INTO products (name, description, image_filename) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $productName, $description, $uniqueFilename);
}

if (!$stmt || !$stmt->execute()) {
    $err = $stmt ? $stmt->error : $conn->error;
    if (file_exists($targetPath)) @unlink($targetPath);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database insert error: ' . $err]);
    exit;
}

$insertId = $conn->insert_id;
$stmt->close();

// Log activity and auto-index
cj_log_activity($conn, 'upload_catalogue_single', "Uploaded design #{$insertId}: {$productName} ({$category})");

if (function_exists('cj_trigger_auto_indexing')) {
    cj_trigger_auto_indexing($conn, [
        'https://chandrakalajewellers.in/Catalogue',
        'https://chandrakalajewellers.in/Catalogue?id=' . $insertId
    ], 'catalogue_single_upload');
}

echo json_encode([
    'ok' => true,
    'id' => $insertId,
    'name' => $productName,
    'category' => $category,
    'image_filename' => $uniqueFilename,
    'thumbnail_url' => cj_img_url($uniqueFilename, 300, 'catalogue'),
], JSON_UNESCAPED_UNICODE);
