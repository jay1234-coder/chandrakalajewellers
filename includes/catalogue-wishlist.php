<?php
/**
 * Customer Catalogue Wishlist Helpers.
 * Manages saved custom catalogue design images in the database.
 */
declare(strict_types=1);

require_once __DIR__ . '/customers.php';

if (!function_exists('cj_ensure_catalogue_wishlist_schema')) {
    function cj_ensure_catalogue_wishlist_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $conn->query("CREATE TABLE IF NOT EXISTS customer_catalogue_wishlist (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            catalogue_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_customer_catalogue (customer_id, catalogue_id),
            KEY idx_cust_wishlist (customer_id),
            KEY idx_cat_wishlist (catalogue_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('cj_get_customer_catalogue_wishlist_ids')) {
    /**
     * @return int[]
     */
    function cj_get_customer_catalogue_wishlist_ids(mysqli $conn, int $customerId): array
    {
        if ($customerId <= 0) return [];
        cj_ensure_catalogue_wishlist_schema($conn);

        $stmt = $conn->prepare("SELECT catalogue_id FROM customer_catalogue_wishlist WHERE customer_id = ? ORDER BY id DESC");
        if (!$stmt) return [];
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['catalogue_id'];
        }
        $stmt->close();
        return $ids;
    }
}

if (!function_exists('cj_toggle_customer_catalogue_wishlist')) {
    /**
     * Toggles like state. Returns true if now liked, false if unliked.
     */
    function cj_toggle_customer_catalogue_wishlist(mysqli $conn, int $customerId, int $catalogueId): bool
    {
        if ($customerId <= 0 || $catalogueId <= 0) return false;
        cj_ensure_catalogue_wishlist_schema($conn);

        // Check if exists
        $chk = $conn->prepare("SELECT id FROM customer_catalogue_wishlist WHERE customer_id = ? AND catalogue_id = ?");
        if (!$chk) return false;
        $chk->bind_param('ii', $customerId, $catalogueId);
        $chk->execute();
        $res = $chk->get_result();
        $exists = $res && $res->num_rows > 0;
        $chk->close();

        if ($exists) {
            $del = $conn->prepare("DELETE FROM customer_catalogue_wishlist WHERE customer_id = ? AND catalogue_id = ?");
            if ($del) {
                $del->bind_param('ii', $customerId, $catalogueId);
                $del->execute();
                $del->close();
            }
            return false;
        } else {
            $ins = $conn->prepare("INSERT IGNORE INTO customer_catalogue_wishlist (customer_id, catalogue_id) VALUES (?, ?)");
            if ($ins) {
                $ins->bind_param('ii', $customerId, $catalogueId);
                $ins->execute();
                $ins->close();
            }
            return true;
        }
    }
}

if (!function_exists('cj_get_catalogue_wishlist_items')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function cj_get_catalogue_wishlist_items(mysqli $conn, int $customerId): array
    {
        if ($customerId <= 0) return [];
        cj_ensure_catalogue_wishlist_schema($conn);

        $sql = "SELECT p.id, p.image_filename, w.created_at as saved_at
                FROM customer_catalogue_wishlist w
                JOIN products p ON p.id = w.catalogue_id
                WHERE w.customer_id = ?
                ORDER BY w.id DESC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }
}

if (!function_exists('cj_get_catalogue_items_by_ids')) {
    /**
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    function cj_get_catalogue_items_by_ids(mysqli $conn, array $ids): array
    {
        $cleanIds = array_values(array_filter(array_map('intval', $ids), fn($n) => $n > 0));
        if (empty($cleanIds)) return [];

        $inList = implode(',', $cleanIds);
        $res = $conn->query("SELECT id, image_filename FROM products WHERE id IN ($inList)");
        if (!$res) return [];

        $items = [];
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
        return $items;
    }
}
