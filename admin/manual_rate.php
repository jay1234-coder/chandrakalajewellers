<?php
require dirname(__DIR__) . '/config/db.php'; // Database connection + security bootstrap
require_once dirname(__DIR__) . '/includes/auth.php';

cj_auth_bootstrap($conn);
cj_require_role(['admin', 'superadmin'], 'admin');

// Handle Manual Rate Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_manual_rates'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF validation failed. Action aborted.");
    }
    $success = false;
    foreach ($_POST['manual_prices'] as $id => $price) {
        $id = intval($id);
        $price = trim($price);

        // Sanitize and handle setting price to NULL if the field is empty
        if ($price === '') {
            $update_query = "UPDATE jewellery_items SET manual_price = NULL WHERE id = $id";
        } else {
            $price_float = floatval($price);
            $update_query = "UPDATE jewellery_items SET manual_price = $price_float WHERE id = $id";
        }
        
        if ($conn->query($update_query) === TRUE) {
            $success = true;
        } else {
            // Log the error but don't stop execution
            error_log("Error updating manual price for item ID $id: " . $conn->error);
        }
    }
    
    if ($success) {
        $_SESSION['success_message'] = "Manual rates updated successfully!";
    } else {
        $_SESSION['error_message'] = "No manual rates were updated.";
    }
    header("Location: manual_rate.php");
    exit();
}

// Fetch products that have a manual price set
$products_with_manual_rates_query = $conn->query("SELECT id, name, manual_price FROM jewellery_items WHERE manual_price IS NOT NULL ORDER BY name ASC");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/gtm-head.php'; ?>
    <title>Update Manual Rates - ChandraKala Jewellers</title>
    <link rel="stylesheet" href="/css/tailwind.min.css?v=9">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/admin.css">
    <?php include dirname(__DIR__) . '/includes/admin-head-theme.php'; ?>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">
<?php include __DIR__ . '/includes/gtm-body.php'; ?>

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

    <main class="flex-grow container mx-auto px-4 py-8 md:py-12 max-w-4xl">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">

            <h2 class="text-2xl font-bold text-gray-900 mb-6 font-serif border-b border-gray-100 pb-4">Update Product Manual Rates</h2>

            <?php
            if (isset($_SESSION['success_message'])) {
                echo '<div class="mb-6 p-4 rounded-xl border flex items-center gap-2 text-sm bg-emerald-50 border-emerald-200 text-emerald-800" role="alert">
                        <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="font-medium">' . $_SESSION['success_message'] . '</span>
                      </div>';
                unset($_SESSION['success_message']);
            }
            if (isset($_SESSION['error_message'])) {
                echo '<div class="mb-6 p-4 rounded-xl border flex items-center gap-2 text-sm bg-red-50 border-red-200 text-red-800" role="alert">
                        <svg class="h-5 w-5 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span class="font-medium">' . $_SESSION['error_message'] . '</span>
                      </div>';
                unset($_SESSION['error_message']);
            }
            ?>

            <form method="POST" action="manual_rate.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <?php if ($products_with_manual_rates_query && $products_with_manual_rates_query->num_rows > 0): ?>
                    <div class="overflow-x-auto rounded-xl border border-gray-100 mb-6">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50/50 font-semibold text-gray-500 uppercase tracking-wider text-xs">
                                <tr>
                                    <th class="px-6 py-3.5 text-left">Product Name</th>
                                    <th class="px-6 py-3.5 text-left">Current Manual Price</th>
                                    <th class="px-6 py-3.5 text-right">Update Price</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <?php while ($item = $products_with_manual_rates_query->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors duration-150">
                                        <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap font-bold text-amber-600">₹ <?= number_format($item['manual_price'], 2) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <input type="number" step="0.01" name="manual_prices[<?= $item['id'] ?>]" value="<?= htmlspecialchars($item['manual_price']) ?>" class="p-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none w-48 text-right inline-block">
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="flex justify-end border-t border-gray-100 pt-6">
                        <button type="submit" name="update_manual_rates" class="bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-200 shadow-sm text-sm">
                            Save All Manual Rates
                        </button>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>No products are currently set to use manual override prices.</p>
                        <a href="/admin/index.php" class="inline-block mt-4 text-xs font-semibold text-amber-600 hover:underline">Edit items in dashboard to set manual overrides &rarr;</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </main>

    <footer class="bg-gray-900 text-gray-400 py-6 border-t border-gray-800 text-center text-xs">
        <p>&copy; <?php echo date('Y'); ?> ChandraKala Jewellers. All rights reserved.</p>
    </footer>

</body>
</html>
