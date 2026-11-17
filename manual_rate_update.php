<?php
require 'config/db.php';
if (function_exists('cj_is_local_host') && cj_is_local_host()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL);
ini_set('log_errors', '1');
cj_admin_noindex_headers();

$correct_password_hash = '$2y$10$U2.hPB0SGxr9lqfvOYpYzeZ4ps3V3Rh7ZVvep6m4zh7usQIMQ.0Wm';

// Handle login attempt via POST request.
if (isset($_POST['password_submit'])) {
    $entered_password = $_POST['admin_password'] ?? '';
    if (password_verify($entered_password, $correct_password_hash)) {
        session_regenerate_id(true);
        $_SESSION['loggedin'] = true;
        $_SESSION['role'] = $_SESSION['role'] ?? 'admin';
        header('Location: manual_rate_update');
        exit();
    }
    $_SESSION['error_message'] = 'Incorrect password. Please try again.';
}

// Check if the user is not logged in.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $cj_login_page_title = 'Rates Portal — ChandraKala Jewellers';
    $cj_login_page_description = 'Private metal-rates portal for ChandraKala Jewellers, Khedbrahma. Not a customer, Google, bank, or email login.';
    include __DIR__ . '/includes/admin-login-meta.php';
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #faf9f6;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(212, 175, 55, 0.25);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen relative p-4 bg-gray-50/50">
    <div class="absolute inset-0 z-0 opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(#d4af37 1px, transparent 1px); background-size: 24px 24px;"></div>
    
    <div class="glass-card p-8 rounded-2xl shadow-xl w-full max-w-md z-10 transition-all duration-300 hover:shadow-2xl">
        <div class="text-center mb-6">
            <img src="images/logo.png" alt="ChandraKala Jewellers official logo" class="h-16 w-auto mx-auto mb-3 filter drop-shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900 font-serif leading-tight">ChandraKala Jewellers</h1>
            <span class="text-[10px] text-amber-600 font-semibold tracking-widest uppercase mt-1 inline-block">Rates Portal</span>
            <p class="text-xs text-gray-500 mt-3 leading-relaxed">Family store access only · Khedbrahma, Gujarat<br>This is <strong>not</strong> a customer login. Do not enter Google, bank, or email passwords here.</p>
        </div>

        <?php
        if (isset($_SESSION['error_message'])) {
            echo '<div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-xl mb-6 text-sm flex gap-2 items-start" role="alert">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <div>
                        <span class="font-bold">Login Failed: </span>' . htmlspecialchars((string)$_SESSION['error_message']) . '
                    </div>
                </div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <form method="POST" action="manual_rate_update" class="space-y-5" autocomplete="on">
            <div>
                <label for="admin_password" class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-2">Password</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </span>
                    <input type="password" id="admin_password" name="admin_password" placeholder="Your rates portal password" autocomplete="current-password" class="pl-11 pr-4 py-3.5 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all duration-200" required>
                </div>
            </div>
            
            <button type="submit" name="password_submit" class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-bold py-3.5 px-6 rounded-xl transition-all duration-200 shadow-sm hover:shadow text-sm tracking-wide">
                Sign in to rates portal
            </button>
        </form>
        <p class="text-[11px] text-center text-gray-400 mt-5">Prefer full store admin? <a href="admin" class="text-amber-700 hover:underline">Open store admin</a></p>
    </div>
</body>
</html>
<?php
    exit();
}

// --- Logout functionality ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: manual_rate_update");
    exit();
}

// Handle Rate Update via POST request.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_rates'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF validation failed. Action aborted.");
    }
    $gold = floatval($_POST['gold_rate']);
    $silver = floatval($_POST['silver_rate']);
    $silver925 = floatval($_POST['silver_925_rate']);
    $making = floatval($_POST['making_charges']);
    $apply_making_to_silver = isset($_POST['apply_to_silver']) ? 1 : 0;

    // Delete existing rates from the new manual_rates table.
    if (!$conn->query("DELETE FROM manual_rates")) {
        die("Error clearing manual_rates table: " . $conn->error);
    }

    // Insert the new rates into the manual_rates table.
    if (!$conn->query("INSERT INTO manual_rates (gold_rate, silver_rate, silver_925_rate, making_charges_percent, manual_making_charges_percent, manual_apply_making_to_silver, updated_at) 
                  VALUES ($gold, $silver, $silver925, $making, $making, $apply_making_to_silver, NOW())")) {
        die("Error inserting new manual rates: " . $conn->error);
    } 

    $_SESSION['success_message'] = "Rates updated successfully!";
    header("Location: manual_rate_update");
    exit();
}

// Fetch the most recent rates from the new manual_rates table to pre-populate the form.
$rate_query = $conn->query("SELECT * FROM manual_rates ORDER BY updated_at DESC LIMIT 1");
$rate = $rate_query->fetch_assoc() ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>Rates Portal — ChandraKala Jewellers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

    <header class="bg-white shadow-sm border-b border-gray-100 py-4 px-6 md:px-12 flex justify-between items-center sticky top-0 z-40">
        <div class="flex items-center gap-3">
            <img src="images/logo.png" alt="ChandraKala Jewellers official logo" class="h-10 w-auto">
            <div>
                <h1 class="text-xl font-bold text-gray-900 font-serif leading-tight">ChandraKala Jewellers</h1>
                <p class="text-xs text-amber-600 font-medium tracking-wide">RATES PORTAL</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="admin" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-5 rounded-xl transition-colors duration-150 text-sm border border-gray-200">
                &larr; Back to Dashboard
            </a>
            <a href="?logout=true" class="bg-red-50 hover:bg-red-100 text-red-600 font-semibold py-2 px-5 rounded-xl transition-colors duration-150 text-sm border border-red-100">
                Logout
            </a>
        </div>
    </header>

    <main class="flex-grow container mx-auto px-4 py-8 md:py-12 max-w-4xl">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6 font-serif border-b border-gray-100 pb-4">Set Today's Manual Metal Rates</h2>

            <?php
            if (isset($_SESSION['success_message'])) {
                echo '<div class="mb-6 p-4 rounded-xl border flex items-center gap-2 text-sm bg-emerald-50 border-emerald-200 text-emerald-800" role="alert">
                        <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="font-medium">' . $_SESSION['success_message'] . '</span>
                      </div>';
                unset($_SESSION['success_message']);
            }
            ?>
            
            <form method="POST" action="manual_rate_update" class="bg-gray-50/50 p-6 rounded-2xl border border-gray-100">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Gold Rate (10g)</label>
                        <input type="number" name="gold_rate" value="<?= $rate['gold_rate'] ?? '' ?>" class="p-3 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all text-sm" step="0.01" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Silver Rate (10g)</label>
                        <input type="number" name="silver_rate" value="<?= $rate['silver_rate'] ?? '' ?>" class="p-3 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all text-sm" step="0.01" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Silver 92.5 (10g)</label>
                        <input type="number" name="silver_925_rate" value="<?= $rate['silver_925_rate'] ?? '' ?>" class="p-3 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all text-sm" step="0.01" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Making Charges %</label>
                        <input type="number" name="making_charges" value="<?= $rate['manual_making_charges_percent'] ?? '' ?>" class="p-3 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all text-sm" step="0.01" required>
                    </div>
                </div>
                
                <div class="mt-4 flex items-center">
                    <input type="checkbox" id="apply_to_silver" name="apply_to_silver" class="h-4 w-4 text-amber-500 border-gray-200 rounded focus:ring-amber-500" <?= isset($rate['manual_apply_making_to_silver']) && $rate['manual_apply_making_to_silver'] ? 'checked' : '' ?>>
                    <label for="apply_to_silver" class="ml-2 text-xs font-medium text-gray-600">Apply Making Charges to Silver as well</label>
                </div>
                
                <button name="update_rates" class="mt-6 bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-200 shadow-sm text-sm">
                    Update Rates
                </button>
            </form>
        </div>
    </main>

    <footer class="bg-gray-900 text-gray-400 py-6 border-t border-gray-800 text-center text-xs">
        <p>&copy; <?php echo date('Y'); ?> ChandraKala Jewellers. All rights reserved.</p>
    </footer>

</body>
</html>
