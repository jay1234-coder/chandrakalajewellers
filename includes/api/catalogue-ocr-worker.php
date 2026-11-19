<?php
/**
 * Background Auto-OCR Engine Worker for Catalogue Designs (`products` table).
 * Scans unnamed/pending catalogue photos in small fast batches, extracts OCR & visual cues,
 * and saves SEO-rich fancy names, descriptions, categories & tags automatically.
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
    echo json_encode(['ok' => false, 'error' => 'Admin login required.']);
    exit;
}

cj_ensure_catalogue_ocr_schema($conn);

// Helper to get counts
$getStats = static function (mysqli $db): array {
    $total = 0;
    $done = 0;
    $q = @$db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN ocr_status = 1 THEN 1 ELSE 0 END) AS done FROM products");
    if ($q && ($r = $q->fetch_assoc())) {
        $total = (int)($r['total'] ?? 0);
        $done = (int)($r['done'] ?? 0);
    }
    return [
        'total' => $total,
        'processed' => $done,
        'pending' => max(0, $total - $done),
    ];
};

if ($_SERVER['REQUEST_METHOD'] === 'GET' || isset($_GET['status'])) {
    echo json_encode(array_merge(['ok' => true], $getStats($conn)), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

// Optional: reset all numeric-named catalogue entries so they can be re-scanned
if (!empty($_POST['reset_all'])) {
    $conn->query("UPDATE products SET ocr_status = 0");
}

$batchSize = max(1, min(8, (int)($_POST['batch'] ?? 4)));
$singleId = (int)($_POST['product_id'] ?? 0);

$rows = [];
if ($singleId > 0) {
    $st = $conn->prepare("SELECT id, name, description, image_filename, ocr_text FROM products WHERE id = ? LIMIT 1");
    if ($st) {
        $st->bind_param('i', $singleId);
        $st->execute();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $st->close();
    }
} else {
    $res = $conn->query("SELECT id, name, description, image_filename, ocr_text FROM products WHERE ocr_status = 0 ORDER BY id DESC LIMIT {$batchSize}");
    if ($res) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
    }
}

$updated = [];
$updStmt = $conn->prepare("UPDATE products SET name = ?, description = ?, category = ?, tags = ?, ocr_text = ?, ocr_status = 1 WHERE id = ?");

foreach ($rows as $row) {
    $id = (int)$row['id'];
    $fn = basename((string)$row['image_filename']);
    $local = dirname(__DIR__, 2) . '/catalogue/' . $fn;
    // Prefer local file if available (super fast), else fast 320px Cloudinary thumbnail
    $imgSrc = is_file($local) ? $local : cj_img_url($fn, 320, 'catalogue');

    // Preserve human name if present
    $origName = trim((string)$row['name']);
    $existingCategory = trim((string)($row['category'] ?? ''));
    $hint = $origName;

    $analysis = cj_analyze_jewellery_image($conn, $imgSrc, (string)($row['ocr_text'] ?? ''), $hint, $id);

    // Only update name if original name was totally empty
    $newName = ($origName !== '') ? $origName : ($analysis['name'] ?? ('Custom Jewellery Design #' . $id));
    $newDesc = (string)($row['description'] ?? $analysis['description'] ?? 'Exquisite handcrafted jewellery design from ChandraKala Jewellers, Khedbrahma.');
    // Only update category if not already set by admin
    $newCat = ($existingCategory !== '' && strtolower($existingCategory) !== 'all') ? $existingCategory : (string)($analysis['category'] ?? 'Catalogue Design');
    $newTags = (string)($analysis['tags'] ?? '');
    $newOcr = (string)($analysis['ocr_text'] ?? '');

    if ($updStmt) {
        $updStmt->bind_param('sssssi', $newName, $newDesc, $newCat, $newTags, $newOcr, $id);
        $updStmt->execute();
    }

    $updated[] = [
        'id' => $id,
        'name' => $newName,
        'category' => $newCat,
        'description' => $newDesc,
        'engine' => $analysis['engine'] ?? 'Visual + OCR',
    ];
}

if ($updStmt) {
    $updStmt->close();
}

echo json_encode(array_merge([
    'ok' => true,
    'updated_count' => count($updated),
    'updated' => $updated,
], $getStats($conn)), JSON_UNESCAPED_UNICODE);
