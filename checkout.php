<?php
require 'config/db.php'; // Database connection
if (function_exists('cj_is_local_host') && cj_is_local_host()) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL);
ini_set('log_errors', '1');
require_once 'includes/pricing.php';
require_once 'includes/payments.php';
require_once 'includes/customers.php';
$razorpayEnabled = cj_razorpay_configured();
$cjGst = cj_gst_breakdown(0);
$cjCustomer = cj_customer_session();

// Check if the database connection was successful
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Fetch latest rates for price calculation (if needed for any on-the-fly calculation, though cart items should have final prices)
$rate_query = $conn->query("SELECT * FROM rates ORDER BY updated_at DESC LIMIT 1");
$rate = $rate_query->fetch_assoc() ?? [];
$gold_rate = $rate['gold_rate'] ?? 0;
$silver_rate = $rate['silver_rate'] ?? 0;
$silver_925_rate = $rate['silver_925_rate'] ?? 0;
$making_charges = $rate['making_charges_percent'] ?? 0;

// Metal Name Map for Display
function get_metal_name($metal) {
    $map = [
        'gold' => 'Gold',
        'silver' => 'Silver',
        'silver_925' => '925 Silver'
    ];
    return $map[$metal] ?? 'Unknown';
}

require_once 'includes/seo.php';
$seo = cj_merge_seo([
    'title' => 'Checkout | ChandraKala Jewellers',
    'description' => 'Enter your details, choose payment, and complete your jewellery order with ChandraKala Jewellers, Khedbrahma.',
    'canonical' => cj_abs_url('checkout'),
    'robots' => 'noindex, nofollow',
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'includes/seo-head.php'; ?>
    <link rel="stylesheet" href="css/tailwind.min.css?v=9">
    <link rel="stylesheet" href="css/style.css?v=9">
    <link rel="stylesheet" href="css/admin-theme.css?v=9">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include 'includes/theme-boot.php'; ?>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-gray-50 text-gray-800">
    <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }
        .shake {
            animation: shake 0.2s ease-in-out 0s 2;
        }
        .input-error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
        }
        .btn-green {
            background-color: #10b981;
            color: white;
            padding: 0.85rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }
        .btn-green:hover:not(:disabled) {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
        }
        .btn-green:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
            box-shadow: none;
        }
    </style>

    <?php $activePage = 'cart'; include 'includes/header.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8 flex-grow flex flex-col lg:flex-row gap-8">
        <!-- Order Summary Section -->
        <div class="lg:w-2/3 bg-white rounded-xl shadow-sm p-6 sm:p-8 order-2 lg:order-1 border border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900 mb-6 font-serif border-b border-gray-100 pb-4">Order Summary</h2>
            
            <div id="loading-summary" class="text-center text-gray-500 py-8">
                <svg class="animate-spin h-8 w-8 mx-auto text-amber-500 mb-2" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading order summary...
            </div>

            <div id="empty-summary" class="text-center py-12 hidden">
                <p class="text-lg text-gray-500 mb-4">Your cart is empty.</p>
                <a href="gallery" class="inline-block bg-amber-500 hover:bg-amber-600 text-white font-semibold px-6 py-2.5 rounded-xl transition-all duration-200">Shop Items</a>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-100 hidden" id="summary-table-wrapper">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Weight</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Price</th>
                        </tr>
                    </thead>
                    <tbody id="cart-summary-body" class="bg-white divide-y divide-gray-100">
                        <!-- Summary items inserted dynamically -->
                    </tbody>
                </table>
                <div class="flex justify-between items-center p-6 bg-gray-50/50 border-t border-gray-100 rounded-b-lg">
                    <div>
                        <p id="gst-line" class="text-xs text-gray-500 mb-1 hidden"></p>
                        <span class="text-base font-medium text-gray-600">Grand Total Amount</span>
                    </div>
                    <span id="total-price" class="text-2xl font-extrabold text-amber-600">₹ 0</span>
                </div>
            </div>
        </div>
        
        <!-- Form Section -->
        <div class="lg:w-1/3 bg-white rounded-xl shadow-sm p-6 sm:p-8 order-1 lg:order-2 border border-gray-100 h-fit">
            <h2 class="text-xl font-bold text-gray-900 mb-6 font-serif border-b border-gray-100 pb-4">Details, address &amp; payment</h2>
            
            <form id="checkout-form" method="POST" action="place_order.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-2">Full Name</label>
                    <input type="text" id="name" name="name" placeholder="Full Name" class="p-3.5 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all duration-200" required value="<?= htmlspecialchars($cjCustomer['name'] ?? '') ?>">
                    <p class="text-xs text-red-500 mt-1 hidden" id="error-name">Name is required.</p>
                </div>
                
                <div class="mb-4">
                    <label for="address" class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-2">Shipping Address</label>
                    <textarea id="address" name="address" placeholder="Complete Delivery Address" rows="3" class="p-3.5 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all duration-200" required></textarea>
                    <p class="text-xs text-red-500 mt-1 hidden" id="error-address">Address is required.</p>
                </div>

                <div class="mb-4">
                    <label for="pincode" class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-2">PIN code <span class="normal-case tracking-normal text-gray-400">(optional)</span></label>
                    <input type="text" id="pincode" name="pincode" inputmode="numeric" maxlength="6" placeholder="e.g. 383255" class="p-3.5 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all duration-200">
                    <p class="text-xs text-red-500 mt-1 hidden" id="error-pincode">Enter a 6-digit PIN code, or leave blank.</p>
                </div>
                
                <div class="mb-6">
                    <label for="phone" class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-2">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="e.g. 9876543210" class="p-3.5 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all duration-200" required value="<?= htmlspecialchars($cjCustomer['phone'] ?? '') ?>">
                    <p class="text-xs text-red-500 mt-1 hidden" id="error-phone">Valid phone number (10-15 digits) is required.</p>
                </div>

                <div class="mb-6">
                    <label for="email" class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-2">Email <span class="normal-case tracking-normal text-gray-400">(optional<?= cj_feature('feat_email_invoices') ? ', for invoice' : '' ?>)</span></label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" class="p-3.5 border border-gray-200 rounded-xl w-full focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 focus:outline-none transition-all duration-200" value="<?= htmlspecialchars($cjCustomer['email'] ?? '') ?>">
                </div>

                <fieldset class="mb-6">
                    <legend class="block text-gray-700 text-xs font-semibold uppercase tracking-wider mb-2">Payment</legend>
                    <?php if ($razorpayEnabled): ?>
                    <label class="flex items-start gap-3 p-3.5 border border-amber-200 bg-amber-50/50 rounded-xl mb-2 cursor-pointer">
                        <input type="radio" name="payment_method" value="razorpay" class="mt-1" checked>
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Pay now (UPI / card)</span>
                            <span class="block text-xs text-gray-500">Secure Razorpay checkout. Pieces stay reserved until you pay.</span>
                        </span>
                    </label>
                    <?php endif; ?>
                    <label class="flex items-start gap-3 p-3.5 border border-gray-200 rounded-xl cursor-pointer">
                        <input type="radio" name="payment_method" value="whatsapp" class="mt-1" <?= $razorpayEnabled ? '' : 'checked' ?>>
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Reserve &amp; pay later</span>
                            <span class="block text-xs text-gray-500">Hold the piece for 24 hours. Confirm on WhatsApp or pay at the shop.</span>
                        </span>
                    </label>
                </fieldset>
                
                <button type="submit" class="btn-green w-full"><?= $razorpayEnabled ? 'Place order &amp; continue' : 'Reserve order' ?></button>
            </form>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <!-- Custom Alert Modal Structure -->
    <div id="customAlertModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[101] hidden">
        <div class="bg-white p-6 rounded-xl shadow-xl text-center max-w-sm mx-4 border border-gray-100 animate-slide-up">
            <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center mx-auto mb-4 text-amber-500">
                <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <p id="customAlertMessage" class="text-base font-semibold text-gray-800 mb-5"></p>
            <div class="alert-buttons flex justify-center gap-4">
                <button onclick="closeCustomAlert()" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-6 py-2.5 rounded-lg transition-colors duration-200 text-sm shadow-sm">OK</button>
            </div>
        </div>
    </div>

<script>
    window.CJ_GST = <?= json_encode([
        'enabled' => !empty($cjGst['enabled']),
        'rate' => (float) ($cjGst['rate'] ?? 0),
        'inclusive' => !empty($cjGst['inclusive']),
    ]) ?>;
    function showCustomAlert(message) {
        let alertModal = document.getElementById('customAlertModal');
        let alertMessage = document.getElementById('customAlertMessage');
        alertMessage.innerText = message;
        alertModal.classList.remove('hidden');
    }

    function closeCustomAlert() {
        document.getElementById('customAlertModal').classList.add('hidden');
    }

    // Function to load order summary
    async function loadOrderSummary() {
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        const cartSummaryBody = document.getElementById('cart-summary-body');
        const loadingSummary = document.getElementById('loading-summary');
        const emptySummary = document.getElementById('empty-summary');
        const summaryTableWrapper = document.getElementById('summary-table-wrapper');
        const totalPriceElement = document.getElementById('total-price');
        const checkoutForm = document.getElementById('checkout-form');
        const submitBtn = checkoutForm.querySelector('button[type="submit"]');

        cartSummaryBody.innerHTML = ''; 
        loadingSummary.classList.remove('hidden'); 
        submitBtn.disabled = true;

        if (cart.length === 0) {
            emptySummary.classList.remove('hidden');
            loadingSummary.classList.add('hidden');
            totalPriceElement.innerText = '₹ 0';
            return;
        }

        try {
            const response = await fetch('get_cart_items.php?ids=' + cart.join(','));
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();

            let total = 0;
            if (data.length === 0) {
                emptySummary.classList.remove('hidden');
            } else {
                emptySummary.classList.add('hidden');
                summaryTableWrapper.classList.remove('hidden');
                
                data.forEach(item => {
                    const finalPrice = Math.round(parseFloat(item.final_price));
                    total += finalPrice;

                    const tr = document.createElement('tr');
                    tr.className = "hover:bg-gray-50/50 transition-colors duration-150";
                    tr.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 flex items-center">
                            <img src="${item.thumb || ('uploads/' + item.image)}" alt="${item.name}" class="w-10 h-10 object-cover rounded-lg border border-gray-100 mr-3 shadow-sm" loading="lazy" decoding="async" width="40" height="40">
                            <span class="font-medium text-gray-800">${item.name}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${parseFloat(item.weight).toFixed(2)} g</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800">₹ ${finalPrice.toLocaleString('en-IN')}</td>
                    `;
                    cartSummaryBody.appendChild(tr);
                });
                submitBtn.disabled = false;
            }
            var gstCfg = window.CJ_GST || {};
            var payable = total;
            var gstLine = document.getElementById('gst-line');
            if (gstCfg.enabled && gstCfg.rate > 0) {
                if (gstCfg.inclusive) {
                    var gstInc = Math.round((total - (total * 100 / (100 + gstCfg.rate))) * 100) / 100;
                    if (gstLine) {
                        gstLine.classList.remove('hidden');
                        gstLine.textContent = 'Includes GST ' + gstCfg.rate + '% (₹' + gstInc.toLocaleString('en-IN') + ')';
                    }
                } else {
                    var gstEx = Math.round(total * gstCfg.rate) / 100;
                    payable = Math.round(total + gstEx);
                    if (gstLine) {
                        gstLine.classList.remove('hidden');
                        gstLine.textContent = 'Subtotal ₹' + total.toLocaleString('en-IN') + ' + GST ' + gstCfg.rate + '% ₹' + gstEx.toLocaleString('en-IN');
                    }
                }
            } else if (gstLine) {
                gstLine.classList.add('hidden');
            }
            totalPriceElement.innerText = `₹ ${payable.toLocaleString('en-IN')}`;

        } catch (error) {
            console.error('Error fetching cart items for summary:', error);
            showCustomAlert('Failed to load order summary. Please try again.');
            emptySummary.classList.remove('hidden');
            emptySummary.innerHTML = '<p class="text-red-500">Failed to load order summary. Please refresh the page.</p>';
        } finally {
            loadingSummary.classList.add('hidden');
        }
    }

    window.onload = loadOrderSummary;

    // --- FORM SUBMISSION AND WHATSAPP REDIRECTION ---
    document.getElementById('checkout-form').addEventListener('submit', async function(event) {
        event.preventDefault(); 

        const nameInput = document.getElementById('name');
        const addressInput = document.getElementById('address');
        const phoneInput = document.getElementById('phone');
        const pincodeInput = document.getElementById('pincode');
        
        let hasError = false;
        
        // Client-side validations
        if (!nameInput.value.trim()) {
            nameInput.classList.add('input-error', 'shake');
            document.getElementById('error-name').classList.remove('hidden');
            setTimeout(() => nameInput.classList.remove('shake'), 400);
            hasError = true;
        } else {
            nameInput.classList.remove('input-error');
            document.getElementById('error-name').classList.add('hidden');
        }

        if (!addressInput.value.trim()) {
            addressInput.classList.add('input-error', 'shake');
            document.getElementById('error-address').classList.remove('hidden');
            setTimeout(() => addressInput.classList.remove('shake'), 400);
            hasError = true;
        } else {
            addressInput.classList.remove('input-error');
            document.getElementById('error-address').classList.add('hidden');
        }

        const phonePattern = /^[0-9]{10,15}$/;
        if (!phoneInput.value.trim() || !phonePattern.test(phoneInput.value.trim())) {
            phoneInput.classList.add('input-error', 'shake');
            document.getElementById('error-phone').classList.remove('hidden');
            setTimeout(() => phoneInput.classList.remove('shake'), 400);
            hasError = true;
        } else {
            phoneInput.classList.remove('input-error');
            document.getElementById('error-phone').classList.add('hidden');
        }

        const pinVal = (pincodeInput.value || '').trim();
        if (pinVal !== '' && !/^[0-9]{6}$/.test(pinVal)) {
            pincodeInput.classList.add('input-error', 'shake');
            document.getElementById('error-pincode').classList.remove('hidden');
            setTimeout(() => pincodeInput.classList.remove('shake'), 400);
            hasError = true;
        } else {
            pincodeInput.classList.remove('input-error');
            document.getElementById('error-pincode').classList.add('hidden');
        }

        if (hasError) return;

        const checkoutButton = this.querySelector('button[type="submit"]');
        const originalButtonText = checkoutButton.innerText;
        
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        if (cart.length === 0) {
            showCustomAlert('Your cart is empty. Please add items before proceeding.');
            return;
        }

        checkoutButton.disabled = true;
        checkoutButton.innerText = 'Reserving your pieces…';

        try {
            const fd = new FormData(this);
            fd.set('ids', cart.join(','));
            const response = await fetch('place_order.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();
            if (!result || !result.ok) {
                throw new Error((result && result.message) || 'Could not reserve stock.');
            }
            localStorage.removeItem('cart');
            if (typeof updateCartCount === 'function') updateCartCount();
            const next = result.next === 'pay' ? 'pay' : 'success';
            window.location.href = next + '?ref=' + encodeURIComponent(result.order_no);
            return;
        } catch (error) {
            console.error('Error processing checkout:', error);
            showCustomAlert(error.message || 'An error occurred during checkout. Please try again.');
            checkoutButton.disabled = false;
            checkoutButton.innerText = originalButtonText;
        }
    });

</script>
</body>
</html>
