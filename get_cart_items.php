<?php
require 'config/db.php';
require_once 'includes/pricing.php';
require_once 'includes/orders.php';
require_once 'includes/img-helper.php';

cj_expire_reservations($conn);

$ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];

if (empty($ids)) {
    echo json_encode([]);
    exit;
}

// Prepare SQL safely
$idList = implode(',', array_map('intval', $ids));
$query = "SELECT * FROM jewellery_items WHERE id IN ($idList)";
$result = $conn->query($query);

$standard_rates = cj_get_standard_rates($conn);
$manual_rates = cj_get_manual_rates($conn);

$items = [];

while($row = $result->fetch_assoc()) {
    $rates_to_use = $row['use_manual_rates'] ? $manual_rates : $standard_rates;
    $final_price = calculate_price($row['metal_type'], $row['weight'], $rates_to_use);

    $items[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'image' => $row['image'],
        'thumb' => cj_img_url($row['image'], 160),
        'metal_type' => $row['metal_type'],
        'weight' => $row['weight'],
        'final_price' => $final_price,
        'is_sold_out' => (int)($row['is_sold_out'] ?? 0),
    ];
}

echo json_encode($items);
?>
