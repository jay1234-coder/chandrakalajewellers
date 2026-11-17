<?php
require 'config/db.php';
require_once 'includes/pricing.php';
require_once 'includes/img-helper.php';

error_reporting(E_ALL);
if (function_exists('cj_is_local_host') && cj_is_local_host()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
ini_set('log_errors', '1');

// Get product ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("Invalid product ID.");
}

// Fetch latest standard rates from the 'rates' table
$rate_result = $conn->query("SELECT * FROM rates ORDER BY updated_at DESC LIMIT 1");
$rate = $rate_result->fetch_assoc();
$standard_rates = [
    'gold_rate' => $rate['gold_rate'] ?? 0,
    'silver_rate' => $rate['silver_rate'] ?? 0,
    'silver_925_rate' => $rate['silver_925_rate'] ?? 0,
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
    'making_charges' => $manual_rate['manual_making_charges_percent'] ?? 0,
    'apply_making_to_silver' => $manual_rate['manual_apply_making_to_silver'] ?? 0
];

// Fetch product
$productQuery = $conn->query("SELECT * FROM jewellery_items WHERE id = $id");
if (!$productQuery || $productQuery->num_rows === 0) {
    die("Product not found.");
}
$product = $productQuery->fetch_assoc();

if (!empty($product['is_sold_out'])) {
    header('Location: product?id=' . $id . '&sold=1');
    exit;
}

// Determine which rate set to use for calculation
$rates_to_use = $product['use_manual_rates'] ? $manual_rates : $standard_rates;

// Calculate the final price
$final_price = calculate_price($product['metal_type'], $product['weight'], $rates_to_use);

// PHP Variables for WhatsApp Message
// ==============================================================================
// These variables will be used to construct the WhatsApp message dynamically.
$productName = htmlspecialchars($product['name']);
// Round the final price to the nearest integer
$productPrice = number_format(round($final_price), 0);
$productType = htmlspecialchars($product['metal_type']);
$productImageURL = cj_img_url($product['image'], 1200, 'uploads');
$whatsappNumber = function_exists('cj_shop_whatsapp_digits') ? cj_shop_whatsapp_digits() : '919427080359';

// Create message (rawurlencode for JS safety)
$message = rawurlencode("Hello, I would like to buy the following item:\n\n*Product:* {$productName}\n*Product Type:* {$productType}\n*Price:* ₹{$productPrice}\n*Image:* {$productImageURL}\n\n*Please confirm availability and how to proceed with the purchase.*");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include 'includes/gtm-head.php'; ?>
    <title>Buy Now - <?= $productName ?> - ChandraKala Jewellers</title>
    <link rel="stylesheet" href="css/tailwind.min.css?v=9">
    <link rel="stylesheet" href="css/style.css?v=9">
    <link rel="stylesheet" href="css/admin-theme.css?v=9">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include 'includes/theme-boot.php'; ?>
    <style>
        #buyNowModal {
            transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.25s;
            visibility: hidden;
            opacity: 0;
        }
        #buyNowModal.show {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

<?php $activePage = 'shop'; include 'includes/header.php'; ?>

<main class="flex-grow flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full border border-gray-100/80 text-center">
        <div class="relative overflow-hidden rounded-xl border border-gray-100 bg-gray-50/50 mb-6">
            <img src="<?= htmlspecialchars(cj_img_url($product['image'], 600)) ?>" alt="<?= $productName ?>" class="max-h-[300px] mx-auto object-contain p-4 hover:scale-[1.03] transition-transform duration-200" decoding="async" width="400" height="400">
        </div>
        
        <h1 class="text-2xl font-bold text-gray-900 mb-2 font-serif"><?= $productName ?></h1>
        <p class="text-lg font-semibold text-gray-500 mb-4"><?= ucfirst(str_replace('_', ' ', $product['metal_type'])) ?> &bull; <?= htmlspecialchars($product['weight']) ?> g</p>
        <p class="text-2xl font-extrabold text-amber-600 mb-6">₹ <?= $productPrice ?></p>
        
        <div class="border-t border-gray-100 pt-4 mb-6">
            <p class="text-xs text-gray-400 uppercase tracking-wider mb-2 font-semibold">Description</p>
            <p class="text-gray-600 text-sm leading-relaxed text-left"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        </div>
        
        <button id="buyNowButton" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-3.5 px-6 rounded-xl text-sm transition-all duration-200 shadow-sm flex items-center justify-center gap-2">
            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.458L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436 0 9.86-4.413 9.864-9.83.002-2.623-1.01-5.09-2.855-6.94S14.14 1.002 11.522 1c-5.44 0-9.866 4.417-9.87 9.836-.002 1.802.49 3.55 1.422 5.11L2.002 22l6.147-1.616zM17.486 14.4c-.3-.15-1.774-.875-2.048-.975s-.475-.15-.675.15-.775.975-.95 1.175-.35.225-.65.075c-.3-.15-1.267-.467-2.413-1.488-.892-.796-1.493-1.78-1.668-2.08s-.019-.462.131-.611c.135-.135.3-.35.45-.525s.2-.3.3-.5.05-.375-.025-.525-.675-1.625-.925-2.225c-.244-.589-.491-.51-.675-.52l-.575-.01c-.2 0-.525.075-.8 1.01S7 9.7 7 9.825c0 .125.125.25.125.375s2.215 3.393 5.362 4.757c.75.325 1.333.52 1.787.664.752.24 1.437.206 1.978.125.602-.09 1.774-.725 2.024-1.425s.25-1.3.175-1.425-.275-.225-.575-.375z"/>
            </svg>
            Proceed to Checkout
        </button>
        
        <div class="mt-4">
            <a href="gallery" class="text-xs text-gray-500 hover:text-amber-500 font-semibold transition-colors duration-150">&larr; Back to Shop Catalogue</a>
        </div>
    </div>
</main>

    <!-- Processing Modal -->
    <div id="buyNowModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-2xl shadow-xl p-8 max-w-sm w-full text-center border border-gray-100/80 transform scale-95 transition-transform duration-300">
            <svg class="mx-auto h-12 w-12 text-emerald-500 animate-spin mb-4" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <h2 class="text-xl font-bold text-gray-900 font-serif mb-1">Redirecting to WhatsApp</h2>
            <p class="text-xs text-gray-500">Preparing your order request...</p>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const whatsappMessage = "<?= $message ?>";
        const whatsappURL = "https://wa.me/<?= $whatsappNumber ?>?text=" + whatsappMessage;

        document.getElementById('buyNowButton').addEventListener('click', () => {
            try {
                localStorage.setItem('cart', JSON.stringify([<?= (int) $id ?>]));
            } catch (e) {}
            window.location.href = 'checkout';
        });
    });
    </script>
</body>
</html>
