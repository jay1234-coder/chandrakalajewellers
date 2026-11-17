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

// Fetch latest standard rates from the 'rates' table
$rate_result = $conn->query("SELECT * FROM rates ORDER BY updated_at DESC LIMIT 1");
$rate = $rate_result->fetch_assoc();
$standard_rates = [
    'gold_rate' => $rate['gold_rate'] ?? 0,
    'silver_rate' => $rate['silver_rate'] ?? 0,
    'silver_925_rate' => $rate['silver_925_rate'] ?? 0,
    'copper_rate' => $rate['copper_rate'] ?? 0,
    'making_charges' => $rate['making_charges_percent'] ?? 0,
    'apply_making_to_silver' => $rate['apply_making_to_silver'] ?? 0
];

// Fetch latest manual rates from the 'manual_rates' table
$manual_rate_result = $conn->query("SELECT * FROM manual_rates ORDER BY updated_at DESC LIMIT 1");
$manual_rate = $manual_rate_result->fetch_assoc();
$manual_rates = [
    'gold_rate' => $manual_rate['gold_rate'] ?? 0,
    'silver_rate' => $manual_rate['silver_rate'] ?? 0,
    'silver_925_rate' => $manual_rate['silver_925_rate'] ?? 0,
    'copper_rate' => $manual_rate['copper_rate'] ?? 0,
    'making_charges' => $manual_rate['manual_making_charges_percent'] ?? 0,
    'apply_making_to_silver' => $manual_rate['manual_apply_making_to_silver'] ?? 0
];

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
