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

// Check if the database connection was successful
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Fetch latest rates for price calculation if needed (though cart items should already have final prices)
// For robustness, let's keep it, but it might not be strictly necessary if get_cart_items.php handles all pricing.
$rate = cj_get_standard_rates($conn);
$gold_rate = $rate['gold_rate'] ?? 0;
$silver_rate = $rate['silver_rate'] ?? 0;
$silver_925_rate = $rate['silver_925_rate'] ?? 0;
$making_charges = $rate['making_charges'] ?? 0;

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
    'title' => 'Shopping Cart | ChandraKala Jewellers',
    'description' => 'Review your selected gold and silver jewellery before checkout at ChandraKala Jewellers, Khedbrahma.',
    'canonical' => cj_abs_url('cart'),
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
        /* Custom styles for buttons to ensure consistent look */
        .btn-primary {
            background-color: #f59e0b; /* yellow-500 */
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem; /* rounded-md */
            font-weight: 600; /* font-semibold */
            transition: background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-primary:hover {
            background-color: #d97706; /* yellow-600 */
            transform: translateY(-1px);
        }
        .btn-red {
            background-color: #ef4444; /* red-500 */
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem; /* rounded-md */
            font-weight: 600; /* font-semibold */
            transition: background-color 0.2s ease-in-out;
        }
        .btn-red:hover {
            background-color: #dc2626; /* red-600 */
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-gray-50 text-gray-800">

    <?php $activePage = 'cart'; include 'includes/header.php'; ?>

    <main class="container mx-auto p-4 sm:p-6 lg:p-8 flex-grow">
        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- Items Box -->
            <div class="flex-grow lg:w-2/3 bg-white rounded-xl shadow-sm p-6 sm:p-8 border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 font-serif border-b border-gray-100 pb-4">Your Shopping Cart</h2>

                <div id="loading-message" class="text-center text-gray-500 py-8">
                    <svg class="animate-spin h-8 w-8 mx-auto text-amber-500 mb-2" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Loading items...
                </div>

                <div id="empty-cart" class="text-center py-12 hidden">
                    <div class="text-6xl mb-4">🛒</div>
                    <p class="text-lg text-gray-500 mb-6">Your shopping cart is currently empty.</p>
                    <a href="gallery" class="inline-block bg-amber-500 hover:bg-amber-600 text-white font-semibold px-6 py-2.5 rounded-full transition-all duration-200">Start Shopping</a>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-100 hidden" id="cart-table-wrapper">
                    <table id="cart-table" class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Product</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Weight</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Metal</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Price</th>
                                <th scope="col" class="px-6 py-3 class=text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody id="cart-body" class="bg-white divide-y divide-gray-100">
                            <!-- Items inserted dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Summary Box -->
            <div id="summary-box" class="lg:w-1/3 bg-white rounded-xl shadow-sm p-6 border border-gray-100 h-fit hidden">
                <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100 font-serif">Order Summary</h3>
                <div class="flex justify-between mb-3 text-sm text-gray-600">
                    <span>Subtotal</span>
                    <span id="cart-subtotal" class="font-medium text-gray-900">₹ 0</span>
                </div>
                <div class="flex justify-between mb-3 text-sm text-gray-600">
                    <span>Delivery Charges</span>
                    <span class="text-green-600 font-semibold uppercase text-xs tracking-wider">Free Delivery</span>
                </div>
                <hr class="my-4 border-gray-100">
                <div class="flex justify-between mb-6 text-lg font-bold text-gray-900">
                    <span>Grand Total</span>
                    <span id="cart-total" class="text-amber-600">₹ 0</span>
                </div>
                <button class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-3.5 px-6 rounded-xl transition-all duration-200 shadow-sm hover:shadow text-center tracking-wide" onclick="proceedToCheckout()">
                    Proceed to Checkout
                </button>
                <button class="w-full mt-3 bg-gray-50 hover:bg-gray-100 text-gray-700 font-semibold py-2.5 px-6 rounded-xl transition-colors duration-200 text-center text-sm border border-gray-200/60" onclick="window.location.href='gallery'">
                    &larr; Continue Shopping
                </button>
            </div>

        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <!-- Custom Alert Modal Structure -->
    <div id="customAlertModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[101] hidden transition-opacity duration-300">
        <div class="bg-white p-6 rounded-xl shadow-xl text-center max-w-sm mx-4 border border-gray-100 animate-slide-up">
            <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center mx-auto mb-4 text-amber-500">
                <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <p id="customAlertMessage" class="text-base font-semibold text-gray-800 mb-5"></p>
            <div class="alert-buttons flex justify-center gap-4">
                <!-- Buttons dynamically loaded -->
            </div>
        </div>
    </div>

<script>
    // Custom Alert Modal Functions
    function showCustomAlert(message, isConfirm = false, onConfirm = null) {
        let alertModal = document.getElementById('customAlertModal');
        let alertMessage = document.getElementById('customAlertMessage');
        let alertButtons = alertModal.querySelector('.alert-buttons');

        alertMessage.innerText = message;
        alertButtons.innerHTML = ''; // Clear previous buttons

        if (isConfirm) {
            const confirmBtn = document.createElement('button');
            confirmBtn.className = 'bg-amber-500 hover:bg-amber-600 text-white font-bold px-6 py-2.5 rounded-lg transition-colors duration-200 text-sm shadow-sm';
            confirmBtn.innerText = 'Yes';
            confirmBtn.onclick = () => {
                closeCustomAlert();
                if (onConfirm) onConfirm();
            };
            alertButtons.appendChild(confirmBtn);

            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-6 py-2.5 rounded-lg transition-colors duration-200 text-sm border border-gray-200';
            cancelBtn.innerText = 'No';
            cancelBtn.onclick = closeCustomAlert;
            alertButtons.appendChild(cancelBtn);
        } else {
            const okBtn = document.createElement('button');
            okBtn.className = 'bg-amber-500 hover:bg-amber-600 text-white font-bold px-6 py-2.5 rounded-lg transition-colors duration-200 text-sm shadow-sm';
            okBtn.innerText = 'OK';
            okBtn.onclick = closeCustomAlert;
            alertButtons.appendChild(okBtn);
        }

        alertModal.classList.remove('hidden');
    }

    function closeCustomAlert() {
        document.getElementById('customAlertModal').classList.add('hidden');
    }

    // Function to load cart items
    async function loadCartItems() {
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        const cartTableWrapper = document.getElementById('cart-table-wrapper');
        const emptyCartMessage = document.getElementById('empty-cart');
        const loadingMessage = document.getElementById('loading-message');
        const summaryBox = document.getElementById('summary-box');
        const tbody = document.getElementById('cart-body');
        
        tbody.innerHTML = ''; // Clear
        loadingMessage.classList.remove('hidden');

        if (cart.length === 0) {
            cartTableWrapper.classList.add('hidden');
            summaryBox.classList.add('hidden');
            emptyCartMessage.classList.remove('hidden');
            loadingMessage.classList.add('hidden');
            return;
        }

        try {
            const response = await fetch('get_cart_items.php?ids=' + cart.join(','));
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();

            if (data.length === 0) {
                cartTableWrapper.classList.add('hidden');
                summaryBox.classList.add('hidden');
                emptyCartMessage.classList.remove('hidden');
            } else {
                cartTableWrapper.classList.remove('hidden');
                summaryBox.classList.remove('hidden');
                emptyCartMessage.classList.add('hidden');
                
                let subtotal = 0;
                let hasSoldOut = false;
                data.forEach(item => {
                    const soldOut = Number(item.is_sold_out) === 1;
                    if (soldOut) hasSoldOut = true;
                    else subtotal += parseFloat(item.final_price);
                    const tr = document.createElement('tr');
                    tr.className = "hover:bg-gray-50/50 transition-colors duration-150" + (soldOut ? " opacity-70" : "");
                    tr.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 flex items-center">
                            <img src="${item.thumb || ('uploads/' + item.image)}" alt="${item.name}" class="w-12 h-12 object-cover rounded-lg border border-gray-100 shadow-sm mr-4" loading="lazy" decoding="async" width="48" height="48">
                            <span class="font-semibold text-gray-800">${item.name}${soldOut ? ' <span class="text-rose-600 text-xs font-bold ml-1">Sold Out</span>' : ''}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${parseFloat(item.weight).toFixed(2)} g</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-800">
                                ${item.metal_type.replace('_', ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">${soldOut ? '—' : '₹ ' + Math.round(parseFloat(item.final_price)).toLocaleString('en-IN')}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button class="bg-red-50 hover:bg-red-100 text-red-600 font-semibold px-4 py-1.5 rounded-lg transition-colors duration-150 text-xs border border-red-100" onclick="removeFromCart(${item.id})">Remove</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
                
                document.getElementById('cart-subtotal').innerText = '₹ ' + Math.round(subtotal).toLocaleString('en-IN');
                document.getElementById('cart-total').innerText = '₹ ' + Math.round(subtotal).toLocaleString('en-IN');
                const checkoutBtn = document.querySelector('a[href="checkout"], a[href*="checkout"]');
                if (checkoutBtn) {
                    if (hasSoldOut || subtotal <= 0) {
                        checkoutBtn.classList.add('pointer-events-none', 'opacity-50');
                        checkoutBtn.setAttribute('aria-disabled', 'true');
                        if (hasSoldOut) checkoutBtn.title = 'Remove sold-out items before checkout';
                    } else {
                        checkoutBtn.classList.remove('pointer-events-none', 'opacity-50');
                        checkoutBtn.removeAttribute('aria-disabled');
                        checkoutBtn.removeAttribute('title');
                    }
                }
            }
        } catch (error) {
            console.error('Error fetching cart items:', error);
            showCustomAlert('Failed to load cart items. Please try again.');
            cartTableWrapper.classList.add('hidden');
            summaryBox.classList.add('hidden');
            emptyCartMessage.classList.remove('hidden');
            emptyCartMessage.innerHTML = '<p class="text-red-500">Failed to load cart items. Please refresh the page.</p>';
        } finally {
            loadingMessage.classList.add('hidden');
        }
    }

    function removeFromCart(id) {
        showCustomAlert('Are you sure you want to remove this item from your cart?', true, () => {
            let cart = JSON.parse(localStorage.getItem('cart')) || [];
            cart = cart.filter(item => Number(item) !== Number(id));
            localStorage.setItem('cart', JSON.stringify(cart));
            if (typeof updateCartCount === 'function') updateCartCount();
            loadCartItems();
        });
    }

    function proceedToCheckout() {
        const cart = JSON.parse(localStorage.getItem('cart')) || [];
        if (cart.length === 0) {
            showCustomAlert('Your cart is empty!');
        } else {
            window.location.href = 'checkout';
        }
    }

    window.onload = loadCartItems;
</script>
</body>
</html>
