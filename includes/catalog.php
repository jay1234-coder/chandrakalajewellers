<?php
/**
 * Extra product media, specs, reviews, related pieces.
 */
require_once __DIR__ . '/settings.php';

if (!function_exists('cj_ensure_catalog_schema')) {
    function cj_ensure_catalog_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        // Create jewellery_items table if it does not exist
        $conn->query("CREATE TABLE IF NOT EXISTS jewellery_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            image VARCHAR(255) NOT NULL DEFAULT '',
            description TEXT,
            weight DECIMAL(10,3) NOT NULL DEFAULT 0.000,
            metal_type VARCHAR(32) NOT NULL DEFAULT 'gold',
            manual_price DECIMAL(12,2) DEFAULT NULL,
            is_favourite TINYINT(1) NOT NULL DEFAULT 0,
            is_sold_out TINYINT(1) NOT NULL DEFAULT 0,
            use_manual_rates TINYINT(1) NOT NULL DEFAULT 0,
            purity VARCHAR(32) NOT NULL DEFAULT '',
            stone VARCHAR(80) NOT NULL DEFAULT '',
            category VARCHAR(80) NOT NULL DEFAULT '',
            sku VARCHAR(64) NOT NULL DEFAULT '',
            dimensions VARCHAR(80) NOT NULL DEFAULT '',
            tags TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $alters = [
            'purity' => "ADD COLUMN purity VARCHAR(32) NOT NULL DEFAULT '' AFTER description",
            'stone' => "ADD COLUMN stone VARCHAR(80) NOT NULL DEFAULT '' AFTER purity",
            'category' => "ADD COLUMN category VARCHAR(80) NOT NULL DEFAULT '' AFTER stone",
            'sku' => "ADD COLUMN sku VARCHAR(64) NOT NULL DEFAULT '' AFTER category",
            'dimensions' => "ADD COLUMN dimensions VARCHAR(80) NOT NULL DEFAULT '' AFTER sku",
            'tags' => "ADD COLUMN tags TEXT AFTER dimensions",
            'is_sold_out' => "ADD COLUMN is_sold_out TINYINT(1) NOT NULL DEFAULT 0 AFTER is_favourite",
            'use_manual_rates' => "ADD COLUMN use_manual_rates TINYINT(1) NOT NULL DEFAULT 0 AFTER is_sold_out",
        ];
        foreach ($alters as $column => $ddl) {
            $col = $conn->query("SHOW COLUMNS FROM jewellery_items LIKE '" . $conn->real_escape_string($column) . "'");
            if ($col && $col->num_rows === 0) {
                $conn->query("ALTER TABLE jewellery_items " . $ddl);
            }
        }

        $conn->query("CREATE TABLE IF NOT EXISTS jewellery_item_images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id INT UNSIGNED NOT NULL,
            filename VARCHAR(255) NOT NULL,
            sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS product_reviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id INT UNSIGNED NOT NULL,
            customer_name VARCHAR(80) NOT NULL,
            rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
            comment VARCHAR(800) NOT NULL DEFAULT '',
            is_approved TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_product_approved (product_id, is_approved),
            KEY idx_approved_created (is_approved, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS abandoned_carts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_key VARCHAR(64) NOT NULL,
            product_ids VARCHAR(500) NOT NULL DEFAULT '',
            item_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_session (session_key),
            KEY idx_last_seen (last_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('cj_product_extra_images')) {
    /** @return list<array<string,mixed>> */
    function cj_product_extra_images(mysqli $conn, int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }
        cj_ensure_catalog_schema($conn);
        $rows = [];
        $stmt = $conn->prepare('SELECT id, filename, sort_order FROM jewellery_item_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('cj_product_gallery_filenames')) {
    /** @return list<string> */
    function cj_product_gallery_filenames(array $product, array $extras): array
    {
        $files = [];
        $main = trim((string) ($product['image'] ?? ''));
        if ($main !== '') {
            $files[] = $main;
        }
        foreach ($extras as $ex) {
            $f = trim((string) ($ex['filename'] ?? ''));
            if ($f !== '' && !in_array($f, $files, true)) {
                $files[] = $f;
            }
        }
        return $files;
    }
}

if (!function_exists('cj_save_extra_image_uploads')) {
    function cj_save_extra_image_uploads(mysqli $conn, int $productId, string $field = 'extra_images'): int
    {
        if ($productId <= 0 || empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
            return 0;
        }
        cj_ensure_catalog_schema($conn);
        $dir = __DIR__ . '/../uploads/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $saved = 0;
        $max = min(count($_FILES[$field]['name']), 6);
        $sortStmt = $conn->prepare('SELECT COALESCE(MAX(sort_order), 0) AS m FROM jewellery_item_images WHERE product_id = ?');
        $next = 0;
        if ($sortStmt) {
            $sortStmt->bind_param('i', $productId);
            $sortStmt->execute();
            $next = (int) ($sortStmt->get_result()->fetch_assoc()['m'] ?? 0);
            $sortStmt->close();
        }
        $ins = $conn->prepare('INSERT INTO jewellery_item_images (product_id, filename, sort_order) VALUES (?, ?, ?)');
        if (!$ins) {
            return 0;
        }
        for ($i = 0; $i < $max; $i++) {
            if ((int) $_FILES[$field]['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            $ext = strtolower(pathinfo((string) $_FILES[$field]['name'][$i], PATHINFO_EXTENSION));
            $tmp = (string) $_FILES[$field]['tmp_name'][$i];
            if (!in_array($ext, $allowed, true) || !is_uploaded_file($tmp)) {
                continue;
            }
            $mime = '';
            if (function_exists('finfo_open')) {
                $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo !== false) {
                    $mime = @finfo_file($finfo, $tmp);
                    @finfo_close($finfo);
                }
            }
            if (empty($mime) && function_exists('mime_content_type')) {
                $mime = @mime_content_type($tmp);
            }
            if (empty($mime)) {
                $mime_map = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif'
                ];
                $mime = $mime_map[$ext] ?? '';
            }
            if (empty($mime) || strpos((string) $mime, 'image/') !== 0) {
                continue;
            }
            $name = uniqid('item_extra_', true) . '.' . $ext;
            if (!move_uploaded_file($tmp, $dir . $name)) {
                continue;
            }
            $next++;
            $ins->bind_param('isi', $productId, $name, $next);
            $ins->execute();
            $saved++;
        }
        $ins->close();
        return $saved;
    }
}

if (!function_exists('cj_delete_extra_image')) {
    function cj_delete_extra_image(mysqli $conn, int $imageId, int $productId): bool
    {
        if ($imageId <= 0 || $productId <= 0) {
            return false;
        }
        $stmt = $conn->prepare('SELECT filename FROM jewellery_item_images WHERE id = ? AND product_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $imageId, $productId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return false;
        }
        $del = $conn->prepare('DELETE FROM jewellery_item_images WHERE id = ? AND product_id = ?');
        $del->bind_param('ii', $imageId, $productId);
        $del->execute();
        $del->close();
        $path = __DIR__ . '/../uploads/' . basename((string) $row['filename']);
        if (is_file($path)) {
            @unlink($path);
        }
        return true;
    }
}

if (!function_exists('cj_related_products')) {
    /** @return list<array<string,mixed>> */
    function cj_related_products(mysqli $conn, array $product, int $limit = 4): array
    {
        $id = (int) ($product['id'] ?? 0);
        $metal = (string) ($product['metal_type'] ?? '');
        if ($id <= 0 || $metal === '') {
            return [];
        }
        $limit = max(1, min(8, $limit));
        $sql = 'SELECT id, name, image, weight, metal_type, cached_price, is_sold_out
                FROM jewellery_items
                WHERE metal_type = ? AND id <> ? AND is_sold_out = 0
                ORDER BY id DESC LIMIT ' . $limit;
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('si', $metal, $id);
        $stmt->execute();
        $rows = [];
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('cj_approved_reviews')) {
    /** @return list<array<string,mixed>> */
    function cj_approved_reviews(mysqli $conn, int $productId, int $limit = 20): array
    {
        cj_ensure_catalog_schema($conn);
        $rows = [];
        $stmt = $conn->prepare(
            'SELECT customer_name, rating, comment, created_at
             FROM product_reviews
             WHERE product_id = ? AND is_approved = 1
             ORDER BY id DESC LIMIT ' . max(1, min(50, $limit))
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('cj_review_stats')) {
    /** @return array{count:int,avg:float} */
    function cj_review_stats(mysqli $conn, int $productId): array
    {
        cj_ensure_catalog_schema($conn);
        $stmt = $conn->prepare(
            'SELECT COUNT(*) AS c, AVG(rating) AS a FROM product_reviews WHERE product_id = ? AND is_approved = 1'
        );
        if (!$stmt) {
            return ['count' => 0, 'avg' => 0.0];
        }
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return [
            'count' => (int) ($row['c'] ?? 0),
            'avg' => round((float) ($row['a'] ?? 0), 1),
        ];
    }
}

if (!function_exists('cj_submit_review')) {
    /** @return array{ok:bool,message:string} */
    function cj_submit_review(mysqli $conn, int $productId, string $name, int $rating, string $comment): array
    {
        cj_ensure_catalog_schema($conn);
        $name = mb_substr(trim($name), 0, 80);
        $comment = mb_substr(trim($comment), 0, 800);
        $rating = max(1, min(5, $rating));
        if ($productId <= 0 || $name === '' || $comment === '') {
            return ['ok' => false, 'message' => 'Please enter your name and a short review.'];
        }
        $approved = cj_setting('reviews_auto_approve', '0') === '1' ? 1 : 0;
        $stmt = $conn->prepare(
            'INSERT INTO product_reviews (product_id, customer_name, rating, comment, is_approved) VALUES (?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Could not save review.'];
        }
        $stmt->bind_param('isisi', $productId, $name, $rating, $comment, $approved);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'message' => 'Could not save review.'];
        }
        $msg = $approved
            ? 'Thank you — your review is live.'
            : 'Thank you — your review will appear after the shop approves it.';
        return ['ok' => true, 'message' => $msg];
    }
}

if (!function_exists('cj_item_specs_from_post')) {
    /** @return array{purity:string,stone:string,category:string,sku:string,dimensions:string} */
    function cj_item_specs_from_post(): array
    {
        return [
            'purity' => mb_substr(trim((string) ($_POST['purity'] ?? '')), 0, 32),
            'stone' => mb_substr(trim((string) ($_POST['stone'] ?? '')), 0, 80),
            'category' => mb_substr(trim((string) ($_POST['category'] ?? '')), 0, 80),
            'sku' => mb_substr(trim((string) ($_POST['sku'] ?? '')), 0, 64),
            'dimensions' => mb_substr(trim((string) ($_POST['dimensions'] ?? '')), 0, 80),
        ];
    }
}
