<?php
/**
 * Self-Developed Image OCR + Visual AI Analysis Endpoint.
 * Accepts an uploaded image file, base64 data URI, or filename/URL (+ optional client OCR text)
 * and returns automated fancy SEO name, 150-char description, selectable type, metal, purity, stone, weight & tags.
 */
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/ai-vision.php';
require_once dirname(__DIR__, 2) . '/includes/img-helper.php';

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
    echo json_encode(['ok' => false, 'error' => 'Admin authentication required.']);
    exit;
}

if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token validation failed. Please reload the page.']);
    exit;
}

$clientOcrText = trim((string)($_POST['ocr_text'] ?? ''));
$filenameHint = trim((string)($_POST['filename_hint'] ?? ''));
$imageSource = '';

if (!empty($_FILES['image']['tmp_name']) && (int)$_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $imageSource = (string)$_FILES['image']['tmp_name'];
    if ($filenameHint === '') {
        $filenameHint = (string)($_FILES['image']['name'] ?? '');
    }
} elseif (!empty($_POST['image_data'])) {
    $imageSource = (string)$_POST['image_data'];
} elseif (!empty($_POST['image_filename'])) {
    $fn = basename((string)$_POST['image_filename']);
    $folder = ((string)($_POST['folder'] ?? 'uploads')) === 'catalogue' ? 'catalogue' : 'uploads';
    $localPath = dirname(__DIR__, 2) . '/' . $folder . '/' . $fn;
    if (is_file($localPath)) {
        $imageSource = $localPath;
    } else {
        $imageSource = cj_img_url($fn, 480, $folder);
    }
    if ($filenameHint === '') {
        $filenameHint = $fn;
    }
}

if ($imageSource === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please select or upload an image first to run OCR scan.']);
    exit;
}

$result = cj_analyze_jewellery_image($conn, $imageSource, $clientOcrText, $filenameHint, (int)($_POST['item_id'] ?? 0));
echo json_encode($result, JSON_UNESCAPED_UNICODE);
