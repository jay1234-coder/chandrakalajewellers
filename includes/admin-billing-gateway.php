<?php
/**
 * ChandraKala Jewellers — In-Store Billing & POS Studio (Native Admin Integration)
 * 
 * 100% Native, Iframe-Free Point-of-Sale, GST Counter Billing, Labour Invoicing,
 * Customer Khata Ledger & Daily Audit Reports directly integrated into the Store Admin Shell.
 */

// Ensure database session does not crash on restrictive ONLY_FULL_GROUP_BY
if (isset($conn) && $conn instanceof mysqli) {
    @mysqli_query($conn, "SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
}

// ─── 1. Initialize State & Tab Routing ──────────────────────────────────────────
$activeTab = strtolower(trim((string)($_GET['tab'] ?? 'sale')));
$allowedTabs = ['sale', 'history', 'labour', 'customers', 'reports', 'qr'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'sale';
}

$tabUrl = static function (string $tab, array $extra = []): string {
    $p = ['view' => 'billing', 'tab' => $tab];
    foreach ($extra as $k => $v) {
        if ($v !== null && $v !== '') {
            $p[$k] = (string)$v;
        }
    }
    return '/admin/index.php?' . http_build_query($p);
};

// ─── 2. Fetch Live Rates for Instant Counter Calculations ──────────────────────
$liveGold24 = 7350.00;
$liveSilver  = 90.00;
$ratesRes = $conn->query("SELECT gold_rate, silver_rate, silver_925_rate FROM rates ORDER BY id DESC LIMIT 1");
if ($ratesRes && $rRow = $ratesRes->fetch_assoc()) {
    if (floatval($rRow['gold_rate']) > 0) $liveGold24 = floatval($rRow['gold_rate']);
    if (floatval($rRow['silver_rate']) > 0) $liveSilver = floatval($rRow['silver_rate']);
}
$liveGold22 = round($liveGold24 * (22 / 24), 2);
$liveGold18 = round($liveGold24 * (18 / 24), 2);

// ─── 3. Top KPI Metrics (Today's Business Pulse) ───────────────────────────────
$todayDate = date('Y-m-d');
$todayStats = [
    'bills_count'  => 0,
    'total_sales'  => 0.00,
    'gold_grams'   => 0.000,
    'silver_grams' => 0.000,
    'khata_due'    => 0.00
];

$todayQ = $conn->query("
    SELECT 
        COUNT(*) as cnt, 
        COALESCE(SUM(net_amount), 0) as sales
    FROM bills 
    WHERE bill_date = '$todayDate' AND status != 'CANCELLED'
");
if ($todayQ && $tRow = $todayQ->fetch_assoc()) {
    $todayStats['bills_count'] = (int)$tRow['cnt'];
    $todayStats['total_sales'] = floatval($tRow['sales']);
}

$wtQ = $conn->query("
    SELECT 
        COALESCE(SUM(CASE WHEN bi.item_metal_type LIKE '%Gold%' THEN bi.quantity ELSE 0 END), 0) as gold_wt,
        COALESCE(SUM(CASE WHEN bi.item_metal_type LIKE '%Silver%' THEN bi.quantity ELSE 0 END), 0) as silver_wt
    FROM bill_items bi
    JOIN bills b ON b.id = bi.bill_id
    WHERE b.bill_date = '$todayDate' AND b.status != 'CANCELLED'
");
if ($wtQ && $wRow = $wtQ->fetch_assoc()) {
    $todayStats['gold_grams'] = floatval($wRow['gold_wt']);
    $todayStats['silver_grams'] = floatval($wRow['silver_wt']);
}

$dueQ = $conn->query("
    SELECT COALESCE(SUM(balance_amount), 0) as due 
    FROM bills 
    WHERE balance_amount > 0 AND status != 'CANCELLED'
");
if ($dueQ && $dRow = $dueQ->fetch_assoc()) {
    $todayStats['khata_due'] = floatval($dRow['due']);
}

// ─── 4. Edit Mode Detection (for Sale Bill) ───────────────────────────────────
$editMode = false;
$editBill = null;
$editItems = [];
if ($activeTab === 'sale' && isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $editId = intval($_GET['edit']);
    $eRes = $conn->query("SELECT * FROM bills WHERE id = $editId AND status != 'CANCELLED' LIMIT 1");
    if ($eRes && $eRes->num_rows > 0) {
        $editMode = true;
        $editBill = $eRes->fetch_assoc();
        $itRes = $conn->query("SELECT * FROM bill_items WHERE bill_id = $editId ORDER BY id ASC");
        while ($it = $itRes->fetch_assoc()) {
            $editItems[] = $it;
        }
    }
}

// Next Bill Number
if (!$editMode) {
    $year = date('Y');
    $qMax = $conn->query("SELECT id FROM bills ORDER BY id DESC LIMIT 1");
    $nextNum = ($qMax && $qMax->num_rows > 0) ? ((int)$qMax->fetch_assoc()['id'] + 1) : 1;
    $defaultBillNo = $year . '-' . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
} else {
    $defaultBillNo = $editBill['bill_no'];
}

// Toast Notification
$toast = $_SESSION['billing_toast'] ?? null;
unset($_SESSION['billing_toast']);
$autoPrintId = $_GET['print_id'] ?? ($toast['print_id'] ?? null);
$autoPrintLabourId = $_GET['print_labour_id'] ?? ($toast['print_labour_id'] ?? null);
?>

<div class="cj-native-pos space-y-6">

    <!-- Toast Notification Banner -->
    <?php if ($toast): ?>
        <div id="billingToastAlert" class="flex items-center justify-between p-4 rounded-xl border <?= ($toast['type'] === 'error') ? 'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-300' : 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300' ?> shadow-sm transition-all duration-300">
            <div class="flex items-center gap-3">
                <i class="fas <?= ($toast['type'] === 'error') ? 'fa-exclamation-circle text-rose-500' : 'fa-check-circle text-emerald-500' ?> text-lg"></i>
                <span class="text-sm font-semibold"><?= htmlspecialchars($toast['msg']) ?></span>
            </div>
            <button type="button" onclick="document.getElementById('billingToastAlert').remove()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-sm">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- Auto-Print Trigger Script -->
    <?php if ($autoPrintId): ?>
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                window.open('/billing-software/billing/print_bill.php?id=<?= (int)$autoPrintId ?>', '_blank');
            });
        </script>
    <?php elseif ($autoPrintLabourId): ?>
        <script>
            window.addEventListener('DOMContentLoaded', function () {
                window.open('/billing-software/billing/labour_print_bill.php?id=<?= (int)$autoPrintLabourId ?>', '_blank');
            });
        </script>
    <?php endif; ?>

    <!-- ─── TOP KPI ROW: LIVE COUNTER AUDIT PULSE ─── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- Card 1: Today's Bills -->
        <div class="bg-white dark:bg-gray-800 p-4 sm:p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Today's Invoices</p>
                <h3 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-gray-100 m-0"><?= number_format($todayStats['bills_count']) ?></h3>
                <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold"><i class="fas fa-receipt mr-1"></i> Counter Invoices</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl">
                <i class="fas fa-file-invoice"></i>
            </div>
        </div>

        <!-- Card 2: Today's Turnover -->
        <div class="bg-white dark:bg-gray-800 p-4 sm:p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Today's Sales</p>
                <h3 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-gray-100 m-0">₹<?= number_format($todayStats['total_sales'], 2) ?></h3>
                <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold"><i class="fas fa-chart-line mr-1"></i> Net Turnover</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl">
                <i class="fas fa-rupee-sign"></i>
            </div>
        </div>

        <!-- Card 3: Metal Weight Sold -->
        <div class="bg-white dark:bg-gray-800 p-4 sm:p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Metals Sold Today</p>
                <div class="flex items-baseline gap-2">
                    <span class="text-base sm:text-lg font-bold text-amber-600 dark:text-amber-400">G: <?= number_format($todayStats['gold_grams'], 3) ?>g</span>
                    <span class="text-xs text-gray-400">|</span>
                    <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">S: <?= number_format($todayStats['silver_grams'], 2) ?>g</span>
                </div>
                <span class="text-[11px] text-amber-600 dark:text-amber-400 font-medium">Gold &amp; Silver Grams</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 flex items-center justify-center text-xl">
                <i class="fas fa-balance-scale"></i>
            </div>
        </div>

        <!-- Card 4: Customer Khata Outstanding -->
        <div class="bg-white dark:bg-gray-800 p-4 sm:p-5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Khata Pending Due</p>
                <h3 class="text-xl sm:text-2xl font-black text-rose-600 dark:text-rose-400 m-0">₹<?= number_format($todayStats['khata_due'], 2) ?></h3>
                <span class="text-[11px] text-rose-500 font-medium"><i class="fas fa-book mr-1"></i> Customer Credit</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
        </div>
    </div>

    <!-- ─── SUB-NAVIGATION PILL TABS ─── -->
    <div class="bg-white dark:bg-gray-800 p-2 sm:p-2.5 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm">
        <nav class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto scrollbar-none py-0.5">
            <a href="<?= $tabUrl('sale') ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold whitespace-nowrap transition-all <?= ($activeTab === 'sale') ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' ?>">
                <i class="fas fa-file-invoice-dollar text-[13px]"></i>
                <span><?= $editMode ? 'Edit Sale Bill' : 'New Sale Bill (GST)' ?></span>
            </a>

            <a href="<?= $tabUrl('history') ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold whitespace-nowrap transition-all <?= ($activeTab === 'history') ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' ?>">
                <i class="fas fa-history text-[13px]"></i>
                <span>Bill History &amp; Audit</span>
            </a>

            <a href="<?= $tabUrl('labour') ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold whitespace-nowrap transition-all <?= ($activeTab === 'labour') ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' ?>">
                <i class="fas fa-tools text-[13px]"></i>
                <span>Labour &amp; Repair Bill</span>
            </a>

            <a href="<?= $tabUrl('customers') ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold whitespace-nowrap transition-all <?= ($activeTab === 'customers') ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' ?>">
                <i class="fas fa-users-cog text-[13px]"></i>
                <span>Customer Khata (CRM)</span>
            </a>

            <a href="<?= $tabUrl('reports') ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold whitespace-nowrap transition-all <?= ($activeTab === 'reports') ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' ?>">
                <i class="fas fa-chart-pie text-[13px]"></i>
                <span>Daily Sales &amp; GST</span>
            </a>

            <a href="<?= $tabUrl('qr') ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold whitespace-nowrap transition-all <?= ($activeTab === 'qr') ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700/50' ?>">
                <i class="fas fa-qrcode text-[13px]"></i>
                <span>Payment QR Stands</span>
            </a>
        </nav>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <!-- TAB 1: CREATE NEW SALE BILL (GST)                                          -->
    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'sale'): ?>
    <form method="POST" action="/admin/index.php?view=billing" id="posSaleForm" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="save_sale_bill" value="1">
        <?php if ($editMode): ?>
            <input type="hidden" name="edit_bill_id" value="<?= (int)$editBill['id'] ?>">
        <?php endif; ?>

        <!-- 1. Invoice & Customer Meta Header -->
        <div class="bg-white dark:bg-gray-800 p-5 sm:p-6 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700/60 gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-400 flex items-center justify-center font-bold text-sm">
                        <i class="fas fa-cash-register"></i>
                    </span>
                    <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100 font-serif m-0">
                        <?= $editMode ? 'Edit Tax Invoice #' . htmlspecialchars($editBill['bill_no']) : 'Create Tax Invoice (GST Sales)' ?>
                    </h3>
                </div>

                <!-- Live Store Rates Reference Strip -->
                <div class="flex items-center gap-2 sm:gap-3 flex-wrap text-xs bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-800/60 px-3 py-1.5 rounded-xl font-medium text-amber-950 dark:text-amber-200">
                    <span class="flex items-center gap-1 font-bold text-amber-800 dark:text-amber-300">
                        <i class="fas fa-bolt text-amber-500"></i> Counter Rates:
                    </span>
                    <span>24K: <strong>₹<?= number_format($liveGold24) ?>/g</strong></span>
                    <span class="text-amber-300">|</span>
                    <span>22K: <strong>₹<?= number_format($liveGold22) ?>/g</strong></span>
                    <span class="text-amber-300">|</span>
                    <span>Silver: <strong>₹<?= number_format($liveSilver) ?>/g</strong></span>
                </div>
            </div>

            <!-- Header Grid: Bill Details -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Invoice Number</label>
                    <input type="text" name="bill_no" value="<?= htmlspecialchars($defaultBillNo) ?>" required class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Invoice Date</label>
                    <input type="date" name="bill_date" value="<?= htmlspecialchars($editBill['bill_date'] ?? date('Y-m-d')) ?>" required class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Delivery / Handover</label>
                    <select name="delivery_option" class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="Hand Delivery" <?= (($editBill['delivery_option'] ?? '') === 'Hand Delivery') ? 'selected' : '' ?>>Hand Delivery (In-Counter)</option>
                        <option value="Store Pickup" <?= (($editBill['delivery_option'] ?? '') === 'Store Pickup') ? 'selected' : '' ?>>Store Pickup (Scheduled)</option>
                        <option value="Courier Delivery" <?= (($editBill['delivery_option'] ?? '') === 'Courier Delivery') ? 'selected' : '' ?>>Insured Courier / Parcel</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">State &amp; POS Code</label>
                    <input type="text" name="state_code" value="<?= htmlspecialchars($editBill['state_code'] ?? '24 - Gujarat') ?>" class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>

            <!-- Customer Search & CRM Sync Card -->
            <div class="pt-3 border-t border-gray-100 dark:border-gray-700/60">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 relative">
                    <!-- Customer Name with Autocomplete Search -->
                    <div class="relative">
                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">
                            Customer Name <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="text" id="custSearchInput" name="customer_name" value="<?= htmlspecialchars($editBill['customer_name'] ?? '') ?>" placeholder="Search or type name..." required autocomplete="off" class="w-full px-3.5 py-2 pl-9 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                        </div>
                        <!-- Autocomplete Suggestions Dropdown -->
                        <div id="custSuggestions" class="hidden absolute z-30 left-0 right-0 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl max-h-56 overflow-y-auto"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Mobile (10 Digits)</label>
                        <input type="tel" id="custMobile" name="mobile" value="<?= htmlspecialchars($editBill['mobile'] ?? '') ?>" maxlength="10" placeholder="9876543210" class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Address / Town</label>
                        <input type="text" id="custAddress" name="address" value="<?= htmlspecialchars($editBill['address'] ?? 'Khedbrahma') ?>" placeholder="e.g. Khedbrahma, Vadali" class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">GSTIN / PAN</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" id="custGst" name="gst_no" value="<?= htmlspecialchars($editBill['gst_no'] ?? '') ?>" placeholder="GSTIN (Opt)" class="w-full px-2.5 py-2 rounded-xl text-xs font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:outline-none uppercase">
                            <input type="text" id="custPan" name="pan_no" value="<?= htmlspecialchars($editBill['pan_no'] ?? '') ?>" placeholder="PAN No" class="w-full px-2.5 py-2 rounded-xl text-xs font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:outline-none uppercase">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden Global Rates for JS Calculation -->
            <input type="hidden" id="goldRateGlobal" name="gold_rate_global" value="<?= $liveGold24 ?>">
            <input type="hidden" id="silverRateGlobal" name="silver_rate_global" value="<?= $liveSilver ?>">
        </div>

        <!-- 2. Dynamic Jewellery Item Rows Grid -->
        <div class="bg-white dark:bg-gray-800 p-5 sm:p-6 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700/60">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-400 flex items-center justify-center font-bold text-xs">
                        <i class="fas fa-ring"></i>
                    </span>
                    <h4 class="text-sm sm:text-base font-bold text-gray-900 dark:text-gray-100 m-0">
                        Line Items (Ornaments &amp; Bullion)
                    </h4>
                </div>
                <button type="button" id="btnAddItemRow" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-500 text-white hover:bg-amber-600 shadow-sm transition-all cursor-pointer">
                    <i class="fas fa-plus"></i> Add Item Row
                </button>
            </div>

            <!-- Items Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse" id="posItemsTable">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2.5 px-3 w-8">#</th>
                            <th class="py-2.5 px-3 min-w-[180px]">Item Description</th>
                            <th class="py-2.5 px-3 min-w-[120px]">Metal / Purity</th>
                            <th class="py-2.5 px-3 w-20">HSN</th>
                            <th class="py-2.5 px-3 w-24">Weight (g)</th>
                            <th class="py-2.5 px-3 w-28">Rate/g (₹)</th>
                            <th class="py-2.5 px-3 w-20">Making %</th>
                            <th class="py-2.5 px-3 w-24 text-right">Making (₹)</th>
                            <th class="py-2.5 px-3 w-32 text-right">Item Total (₹)</th>
                            <th class="py-2.5 px-3 w-10 text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="posItemsBody" class="divide-y divide-gray-100 dark:divide-gray-700/50">
                        <!-- Initial rows populated dynamically or from edit mode -->
                        <?php if ($editMode && !empty($editItems)): ?>
                            <?php foreach ($editItems as $idx => $it): ?>
                                <tr class="pos-item-row" data-row-idx="<?= $idx ?>">
                                    <td class="py-2 px-3 font-semibold text-gray-400 row-num"><?= $idx + 1 ?></td>
                                    <td class="py-2 px-3">
                                        <input type="text" name="description[]" value="<?= htmlspecialchars($it['description']) ?>" required placeholder="e.g. Gold Mangalsutra 22K" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-semibold text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-amber-500">
                                    </td>
                                    <td class="py-2 px-3">
                                        <select name="item_metal_type[]" class="item-metal-select w-full px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-semibold text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-amber-500">
                                            <option value="Gold 22K" data-purity="91.6" data-hsn="7113" <?= ($it['item_metal_type'] === 'Gold 22K') ? 'selected' : '' ?>>Gold 22K (916)</option>
                                            <option value="Gold 24K" data-purity="99.9" data-hsn="7113" <?= ($it['item_metal_type'] === 'Gold 24K') ? 'selected' : '' ?>>Gold 24K (999)</option>
                                            <option value="Gold 18K" data-purity="75.0" data-hsn="7113" <?= ($it['item_metal_type'] === 'Gold 18K') ? 'selected' : '' ?>>Gold 18K (750)</option>
                                            <option value="Silver 925" data-purity="92.5" data-hsn="7114" <?= ($it['item_metal_type'] === 'Silver 925') ? 'selected' : '' ?>>Silver 92.5</option>
                                            <option value="Silver" data-purity="70.0" data-hsn="7114" <?= ($it['item_metal_type'] === 'Silver') ? 'selected' : '' ?>>Silver Regular</option>
                                        </select>
                                        <input type="hidden" name="purity_val[]" class="item-purity-hidden" value="91.6">
                                    </td>
                                    <td class="py-2 px-3">
                                        <input type="text" name="hsn_code[]" value="<?= htmlspecialchars($it['hsn_code'] ?? '7113') ?>" class="item-hsn w-full px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-xs text-center font-mono">
                                    </td>
                                    <td class="py-2 px-3">
                                        <input type="number" step="0.001" min="0.001" name="quantity[]" value="<?= floatval($it['quantity']) ?>" required placeholder="0.000" class="item-qty w-full px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-bold text-gray-900 dark:text-gray-100 text-right focus:ring-1 focus:ring-amber-500">
                                    </td>
                                    <td class="py-2 px-3">
                                        <input type="number" step="0.01" name="rate[]" value="<?= floatval($it['rate']) ?>" required class="item-rate w-full px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-semibold text-right focus:ring-1 focus:ring-amber-500">
                                    </td>
                                    <td class="py-2 px-3">
                                        <input type="number" step="0.1" min="0" name="making[]" value="<?= floatval($it['making_percent']) ?>" placeholder="12" class="item-making w-full px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs text-right font-semibold focus:ring-1 focus:ring-amber-500">
                                    </td>
                                    <td class="py-2 px-3 text-right font-medium text-gray-700 dark:text-gray-300 item-making-flat">
                                        ₹<?= number_format(floatval($it['making_amount']), 2) ?>
                                    </td>
                                    <td class="py-2 px-3 text-right font-bold text-gray-900 dark:text-gray-100 item-total-disp">
                                        ₹<?= number_format(floatval($it['total_amount']), 2) ?>
                                    </td>
                                    <td class="py-2 px-3 text-center">
                                        <button type="button" class="btn-remove-row text-rose-500 hover:text-rose-700 p-1"><i class="fas fa-trash-alt"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Default Fresh Row -->
                            <tr class="pos-item-row" data-row-idx="0">
                                <td class="py-2 px-3 font-semibold text-gray-400 row-num">1</td>
                                <td class="py-2 px-3">
                                    <input type="text" name="description[]" required placeholder="e.g. Gold Mangalsutra 22K" class="w-full px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-semibold text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-amber-500">
                                </td>
                                <td class="py-2 px-3">
                                    <select name="item_metal_type[]" class="item-metal-select w-full px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-semibold text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-amber-500">
                                        <option value="Gold 22K" data-purity="91.6" data-hsn="7113">Gold 22K (916)</option>
                                        <option value="Gold 24K" data-purity="99.9" data-hsn="7113">Gold 24K (999)</option>
                                        <option value="Gold 18K" data-purity="75.0" data-hsn="7113">Gold 18K (750)</option>
                                        <option value="Silver 925" data-purity="92.5" data-hsn="7114">Silver 92.5</option>
                                        <option value="Silver" data-purity="70.0" data-hsn="7114">Silver Regular</option>
                                    </select>
                                    <input type="hidden" name="purity_val[]" class="item-purity-hidden" value="91.6">
                                </td>
                                <td class="py-2 px-3">
                                    <input type="text" name="hsn_code[]" value="7113" class="item-hsn w-full px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-xs text-center font-mono">
                                </td>
                                <td class="py-2 px-3">
                                    <input type="number" step="0.001" min="0.001" name="quantity[]" value="" required placeholder="0.000" class="item-qty w-full px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-bold text-gray-900 dark:text-gray-100 text-right focus:ring-1 focus:ring-amber-500">
                                </td>
                                <td class="py-2 px-3">
                                    <input type="number" step="0.01" name="rate[]" value="<?= $liveGold22 ?>" required class="item-rate w-full px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-semibold text-right focus:ring-1 focus:ring-amber-500">
                                </td>
                                <td class="py-2 px-3">
                                    <input type="number" step="0.1" min="0" name="making[]" value="12" placeholder="12" class="item-making w-full px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs text-right font-semibold focus:ring-1 focus:ring-amber-500">
                                </td>
                                <td class="py-2 px-3 text-right font-medium text-gray-700 dark:text-gray-300 item-making-flat">
                                    ₹0.00
                                </td>
                                <td class="py-2 px-3 text-right font-bold text-gray-900 dark:text-gray-100 item-total-disp">
                                    ₹0.00
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <button type="button" class="btn-remove-row text-rose-500 hover:text-rose-700 p-1"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Bottom Two-Column Split: Payment Breakdown & Calculations Summary -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left: Payment Modes & Settlement -->
            <div class="bg-white dark:bg-gray-800 p-5 sm:p-6 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-gray-100 dark:border-gray-700/60">
                    <span class="w-7 h-7 rounded-lg bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
                        <i class="fas fa-wallet"></i>
                    </span>
                    <h4 class="text-sm sm:text-base font-bold text-gray-900 dark:text-gray-100 m-0">
                        Payment Settlement &amp; Exchange
                    </h4>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">
                            <i class="fas fa-money-bill-wave text-emerald-600 mr-1"></i> Cash Received
                        </label>
                        <input type="number" step="0.01" id="cashReceived" name="cash_received" value="<?= floatval($editBill['cash_received'] ?? 0) ?>" class="w-full px-3.5 py-2 rounded-xl text-sm font-bold text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">
                            <i class="fas fa-qrcode text-blue-600 mr-1"></i> Bank / Digital UPI
                        </label>
                        <input type="number" step="0.01" id="bankReceived" name="bank_received" value="<?= floatval($editBill['bank_received'] ?? 0) ?>" class="w-full px-3.5 py-2 rounded-xl text-sm font-bold text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">
                            <i class="fas fa-coins text-amber-600 mr-1"></i> Old Gold/Silver Exchange
                        </label>
                        <input type="number" step="0.01" id="oldGoldAmount" name="old_gold_amount" value="<?= floatval($editBill['old_gold_amount'] ?? 0) ?>" class="w-full px-3.5 py-2 rounded-xl text-sm font-bold text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-rose-600 uppercase mb-1">
                            <i class="fas fa-clock text-rose-600 mr-1"></i> Remaining Khata Balance
                        </label>
                        <input type="number" step="0.01" id="balanceAmount" name="balance_amount" value="<?= floatval($editBill['balance_amount'] ?? 0) ?>" readonly class="w-full px-3.5 py-2 rounded-xl text-sm font-black text-rose-600 bg-rose-50/50 dark:bg-rose-950/30 border border-rose-300 dark:border-rose-800 focus:outline-none">
                    </div>
                </div>

                <!-- Quick Payment Shortcuts -->
                <div class="flex items-center gap-2 pt-2 text-xs">
                    <span class="text-gray-400 font-semibold">1-Click Match:</span>
                    <button type="button" id="btnMatchCash" class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 font-semibold hover:bg-gray-200">All Cash</button>
                    <button type="button" id="btnMatchUpi" class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 font-semibold hover:bg-gray-200">All UPI</button>
                    <button type="button" id="btnClearPayments" class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 font-semibold hover:bg-gray-200">Reset</button>
                </div>
            </div>

            <!-- Right: Tax & Calculation Summary -->
            <div class="bg-white dark:bg-gray-800 p-5 sm:p-6 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-3">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700/60">
                    <h4 class="text-sm sm:text-base font-bold text-gray-900 dark:text-gray-100 m-0">
                        Invoice Total &amp; GST Tax
                    </h4>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 font-bold">
                        GST: 3% (1.5% CGST + 1.5% SGST)
                    </span>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between items-center text-gray-600 dark:text-gray-300">
                        <span>Items Subtotal:</span>
                        <span class="font-bold text-gray-900 dark:text-gray-100" id="dispSubtotal">₹0.00</span>
                        <input type="hidden" id="subtotalVal" name="subtotal" value="<?= floatval($editBill['subtotal'] ?? 0) ?>">
                    </div>

                    <div class="flex justify-between items-center text-gray-600 dark:text-gray-300">
                        <span>Discount / Roundoff (₹):</span>
                        <input type="number" step="0.01" id="discountVal" name="discount" value="<?= floatval($editBill['discount'] ?? 0) ?>" class="w-28 px-2 py-1 text-right rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-semibold focus:ring-1 focus:ring-amber-500">
                    </div>

                    <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                        <span>CGST (1.5%):</span>
                        <span class="font-semibold" id="dispCgst">₹0.00</span>
                        <input type="hidden" id="cgstVal" name="cgst" value="<?= floatval($editBill['cgst'] ?? 0) ?>">
                    </div>

                    <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                        <span>SGST (1.5%):</span>
                        <span class="font-semibold" id="dispSgst">₹0.00</span>
                        <input type="hidden" id="sgstVal" name="sgst" value="<?= floatval($editBill['sgst'] ?? 0) ?>">
                    </div>

                    <div class="pt-3 border-t-2 border-gray-200 dark:border-gray-700 flex justify-between items-baseline">
                        <span class="text-base font-bold text-gray-900 dark:text-gray-100">Net Invoice Amount:</span>
                        <span class="text-2xl font-black text-amber-600 dark:text-amber-400" id="dispNetAmount">₹0.00</span>
                        <input type="hidden" id="netAmountVal" name="net_amount" value="<?= floatval($editBill['net_amount'] ?? 0) ?>">
                    </div>
                </div>

                <!-- Form Action Buttons -->
                <div class="pt-4 flex items-center justify-end gap-3 flex-wrap">
                    <a href="<?= $tabUrl('sale') ?>" class="px-4 py-2.5 rounded-xl text-xs font-bold border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 transition-all">
                        Reset Form
                    </a>
                    <button type="submit" name="save_bill" class="px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-gray-900 dark:bg-gray-700 text-white hover:bg-black transition-all cursor-pointer">
                        <i class="fas fa-save mr-1.5"></i> Save Bill
                    </button>
                    <button type="submit" name="save_and_print" value="1" class="px-6 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-amber-500 hover:bg-amber-600 text-white shadow-md transition-all cursor-pointer">
                        <i class="fas fa-print mr-1.5"></i> Save &amp; Print (A4/A5)
                    </button>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <!-- TAB 2: BILL HISTORY & REPRINT SEARCH AUDIT                                 -->
    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'history'): ?>
    <?php
    $billsRes = $conn->query("
        SELECT b.*, 
               (SELECT COUNT(*) FROM bill_items bi WHERE bi.bill_id = b.id) AS item_count 
        FROM bills b 
        ORDER BY b.bill_date DESC, b.id DESC
    ");
    ?>
    <div class="bg-white dark:bg-gray-800 p-5 sm:p-6 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700/60 gap-3">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100 font-serif m-0">
                    Transaction Audit Ledger
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-0">Browse past customer invoices, reprint A4 / A5 receipts, or share WhatsApp bills.</p>
            </div>

            <!-- Instant Search Input -->
            <div class="relative w-full sm:w-72">
                <input type="text" id="historySearch" placeholder="Search bill #, customer, phone..." onkeyup="filterHistoryTable()" class="w-full px-3.5 py-2 pl-9 rounded-xl text-xs sm:text-sm font-medium border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-xs"></i>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse" id="historyTable">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">
                        <th class="py-3 px-3">Bill #</th>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3">Customer Details</th>
                        <th class="py-3 px-3 text-center">Type</th>
                        <th class="py-3 px-3 text-center">Items</th>
                        <th class="py-3 px-3 text-right">Net Amount</th>
                        <th class="py-3 px-3 text-right">Settlement</th>
                        <th class="py-3 px-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    <?php if ($billsRes && $billsRes->num_rows > 0): ?>
                        <?php while ($b = $billsRes->fetch_assoc()): 
                            $isLabour = ($b['bill_type'] === 'LABOUR');
                            $printUrl = $isLabour ? "/billing-software/billing/labour_print_bill.php?id=" . $b['id'] : "/billing-software/billing/print_bill.php?id=" . $b['id'];
                            $editUrl = $isLabour ? $tabUrl('labour', ['edit' => $b['id']]) : $tabUrl('sale', ['edit' => $b['id']]);
                        ?>
                        <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-700/30 transition-colors history-row">
                            <td class="py-3 px-3 font-bold text-gray-900 dark:text-gray-100 col-billno">
                                <?= htmlspecialchars($b['bill_no']) ?>
                            </td>
                            <td class="py-3 px-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                <?= date('d M Y', strtotime($b['bill_date'])) ?>
                            </td>
                            <td class="py-3 px-3 col-customer">
                                <strong class="text-gray-900 dark:text-gray-100 block"><?= htmlspecialchars($b['customer_name']) ?></strong>
                                <span class="text-gray-500 dark:text-gray-400 text-[11px]"><?= htmlspecialchars($b['mobile'] ?: 'No Phone') ?></span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider <?= $isLabour ? 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' ?>">
                                    <?= htmlspecialchars($b['bill_type']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center font-bold text-gray-600 dark:text-gray-300">
                                <?= (int)$b['item_count'] ?>
                            </td>
                            <td class="py-3 px-3 text-right font-black text-gray-900 dark:text-gray-100">
                                ₹<?= number_format(floatval($b['net_amount']), 2) ?>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <?php if (floatval($b['balance_amount']) > 0): ?>
                                    <span class="inline-block px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 font-bold text-[11px]">
                                        Due: ₹<?= number_format(floatval($b['balance_amount']), 2) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-block px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 font-bold text-[11px]">
                                        <i class="fas fa-check mr-0.5"></i> Paid
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="<?= htmlspecialchars($printUrl) ?>" target="_blank" title="Print Invoice (A4 / A5)" class="w-7 h-7 rounded-lg bg-sky-50 hover:bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300 flex items-center justify-center transition-all">
                                        <i class="fas fa-print text-xs"></i>
                                    </a>
                                    <a href="<?= htmlspecialchars($editUrl) ?>" title="Edit Invoice" class="w-7 h-7 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 flex items-center justify-center transition-all">
                                        <i class="fas fa-pencil-alt text-xs"></i>
                                    </a>
                                    <button type="button" onclick="shareWhatsApp('<?= htmlspecialchars($b['customer_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($b['mobile'], ENT_QUOTES) ?>', '<?= htmlspecialchars($b['bill_no'], ENT_QUOTES) ?>', '<?= number_format(floatval($b['net_amount']), 2) ?>', '<?= number_format(floatval($b['balance_amount']), 2) ?>')" title="Share on WhatsApp" class="w-7 h-7 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 flex items-center justify-center transition-all cursor-pointer">
                                        <i class="fab fa-whatsapp text-xs"></i>
                                    </button>
                                    <form method="POST" action="/admin/index.php?view=billing" class="inline" onsubmit="return confirm('Purge invoice #<?= htmlspecialchars($b['bill_no']) ?> permanently?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <input type="hidden" name="delete_bill" value="1">
                                        <input type="hidden" name="delete_id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" title="Purge Record" class="w-7 h-7 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400 flex items-center justify-center transition-all cursor-pointer">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-10 text-gray-400">
                                <i class="fas fa-folder-open text-3xl mb-2 block text-gray-300"></i>
                                No invoices recorded yet. Start billing via the "New Sale Bill" tab!
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <!-- TAB 3: LABOUR & KARIGAR BILL                                               -->
    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'labour'): ?>
    <?php
    $labourYear = date('Y');
    $qLMax = $conn->query("SELECT id FROM bills WHERE bill_type = 'LABOUR' ORDER BY id DESC LIMIT 1");
    $nextLNum = ($qLMax && $qLMax->num_rows > 0) ? ((int)$qLMax->fetch_assoc()['id'] + 1) : 1;
    $defaultLNo = 'L-' . $labourYear . '-' . str_pad((string)$nextLNum, 3, '0', STR_PAD_LEFT);
    ?>
    <form method="POST" action="/admin/index.php?view=billing" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="save_labour_bill" value="1">

        <div class="bg-white dark:bg-gray-800 p-5 sm:p-6 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-4">
            <div class="flex items-center gap-2.5 pb-3 border-b border-gray-100 dark:border-gray-700/60">
                <span class="w-8 h-8 rounded-lg bg-purple-500/15 text-purple-700 dark:text-purple-400 flex items-center justify-center font-bold text-sm">
                    <i class="fas fa-tools"></i>
                </span>
                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100 font-serif m-0">
                    Artisan &amp; Labour Repair Voucher
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Labour Voucher #</label>
                    <input type="text" name="bill_no" value="<?= htmlspecialchars($defaultLNo) ?>" required class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 text-gray-900 dark:text-gray-100">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Voucher Date</label>
                    <input type="date" name="bill_date" value="<?= date('Y-m-d') ?>" required class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Customer Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="customer_name" required placeholder="Customer Name" class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Customer Mobile</label>
                    <input type="tel" name="mobile" placeholder="10 Digits" maxlength="10" class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
            </div>

            <!-- Labour Line Items & Work Description -->
            <div class="pt-3 border-t border-gray-100 dark:border-gray-700/60 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Repair / Custom Work Description</label>
                    <input type="text" name="description[]" required placeholder="e.g. Mangalsutra soldering, Kada resizing, Polish &amp; Meena work" class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Labour Charges (₹)</label>
                    <input type="number" step="0.01" id="labourSubtotal" name="subtotal" value="0.00" onkeyup="calcLabourTotal()" required class="w-full px-3.5 py-2 rounded-xl text-sm font-black text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700">
                    <input type="hidden" name="rate[]" id="labourRate" value="0.00">
                    <input type="hidden" name="quantity[]" value="1">
                    <input type="hidden" name="item_total[]" id="labourItemTotal" value="0.00">
                </div>
            </div>

            <!-- Deposit Weight & Settlements -->
            <div class="pt-3 border-t border-gray-100 dark:border-gray-700/60 grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Old Gold Deposited (g)</label>
                    <input type="number" step="0.001" name="gold_credit" value="0.000" placeholder="0.000g" class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">Cash Received (₹)</label>
                    <input type="number" step="0.01" id="labourCash" name="cash_received" value="0.00" onkeyup="calcLabourTotal()" class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-300 uppercase mb-1">UPI / Bank Received (₹)</label>
                    <input type="number" step="0.01" id="labourBank" name="bank_received" value="0.00" onkeyup="calcLabourTotal()" class="w-full px-3.5 py-2 rounded-xl text-sm font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>

                <div>
                    <label class="block text-xs font-bold text-rose-600 uppercase mb-1">Balance Due (₹)</label>
                    <input type="number" step="0.01" id="labourBalance" name="balance_amount" value="0.00" readonly class="w-full px-3.5 py-2 rounded-xl text-sm font-black text-rose-600 bg-rose-50/50 dark:bg-rose-950/30 border border-rose-300 dark:border-rose-800">
                    <input type="hidden" name="discount" value="0">
                    <input type="hidden" name="cgst" value="0">
                    <input type="hidden" name="sgst" value="0">
                    <input type="hidden" id="labourNet" name="net_amount" value="0.00">
                </div>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3">
                <button type="submit" name="save_labour_bill" class="px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-gray-900 text-white hover:bg-black transition-all cursor-pointer">
                    <i class="fas fa-save mr-1.5"></i> Save Labour Voucher
                </button>
                <button type="submit" name="save_and_print" value="1" class="px-6 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-purple-600 hover:bg-purple-700 text-white shadow-md transition-all cursor-pointer">
                    <i class="fas fa-print mr-1.5"></i> Save &amp; Print Slip
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <!-- TAB 4: CUSTOMER KHATA CRM                                                  -->
    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'customers'): ?>
    <?php
    $crmRes = $conn->query("
        SELECT 
            b.customer_name, 
            b.mobile, 
            MAX(b.address) as address, 
            COUNT(b.id) as total_bills, 
            COALESCE(SUM(b.net_amount), 0) as total_spent, 
            COALESCE(SUM(b.balance_amount), 0) as total_due 
        FROM bills b 
        WHERE b.customer_name != '' AND b.status != 'CANCELLED'
        GROUP BY b.customer_name, b.mobile 
        ORDER BY total_due DESC, total_spent DESC
    ");
    ?>
    <div class="bg-white dark:bg-gray-800 p-5 sm:p-6 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700/60 gap-3">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100 font-serif m-0">
                    Customer Khata Ledger (CRM)
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-0">Track lifetime customer jewellery purchases, credit ledgers &amp; outstanding balances.</p>
            </div>

            <div class="relative w-full sm:w-72">
                <input type="text" id="crmSearch" placeholder="Filter customer name or phone..." onkeyup="filterCrmTable()" class="w-full px-3.5 py-2 pl-9 rounded-xl text-xs sm:text-sm font-medium border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-xs"></i>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse" id="crmTable">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">
                        <th class="py-3 px-3">Customer Profile</th>
                        <th class="py-3 px-3">Town / Address</th>
                        <th class="py-3 px-3 text-center">Invoices</th>
                        <th class="py-3 px-3 text-right">Lifetime Business</th>
                        <th class="py-3 px-3 text-right">Khata Pending Due</th>
                        <th class="py-3 px-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    <?php if ($crmRes && $crmRes->num_rows > 0): ?>
                        <?php while ($c = $crmRes->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-700/30 transition-colors crm-row">
                            <td class="py-3 px-3 crm-name">
                                <strong class="text-gray-900 dark:text-gray-100 block"><?= htmlspecialchars($c['customer_name']) ?></strong>
                                <span class="text-gray-500 text-[11px]"><?= htmlspecialchars($c['mobile'] ?: 'No Phone Recorded') ?></span>
                            </td>
                            <td class="py-3 px-3 text-gray-500 dark:text-gray-400 crm-addr">
                                <?= htmlspecialchars($c['address'] ?: 'Khedbrahma') ?>
                            </td>
                            <td class="py-3 px-3 text-center font-bold text-gray-700 dark:text-gray-300">
                                <?= (int)$c['total_bills'] ?> Bills
                            </td>
                            <td class="py-3 px-3 text-right font-black text-gray-900 dark:text-gray-100">
                                ₹<?= number_format(floatval($c['total_spent']), 2) ?>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <?php if (floatval($c['total_due']) > 0): ?>
                                    <span class="inline-block px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 font-black text-xs">
                                        ₹<?= number_format(floatval($c['total_due']), 2) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-emerald-600 dark:text-emerald-400 font-bold text-xs">
                                        <i class="fas fa-check-circle"></i> Clear
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <button type="button" onclick="openCustomerLedger('<?= htmlspecialchars($c['customer_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['mobile'], ENT_QUOTES) ?>')" class="px-3 py-1.5 rounded-lg bg-amber-500 text-white font-bold text-xs hover:bg-amber-600 transition-all cursor-pointer">
                                    <i class="fas fa-book-open mr-1"></i> View Ledger
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-10 text-gray-400">No customer records in database yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <!-- TAB 5: DAILY TURNOVER & GST REPORTS                                        -->
    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'reports'): ?>
    <?php
    $filterDate = $_GET['report_date'] ?? date('Y-m-d');
    $dailyQ = $conn->query("
        SELECT 
            b.*, 
            (SELECT COUNT(*) FROM bill_items bi WHERE bi.bill_id = b.id) AS item_cnt,
            (SELECT COALESCE(SUM(bi.quantity), 0) FROM bill_items bi WHERE bi.bill_id = b.id AND bi.item_metal_type LIKE '%Gold%') AS gold_weight,
            (SELECT COALESCE(SUM(bi.quantity), 0) FROM bill_items bi WHERE bi.bill_id = b.id AND bi.item_metal_type LIKE '%Silver%') AS silver_weight
        FROM bills b
        WHERE b.bill_date = '$filterDate' AND b.status != 'CANCELLED'
        ORDER BY b.id ASC
    ");

    $sumSales = 0; $sumCash = 0; $sumBank = 0; $sumCgst = 0; $sumSgst = 0; $sumGoldWt = 0; $sumSilverWt = 0;
    $dailyRows = [];
    if ($dailyQ) {
        while ($dr = $dailyQ->fetch_assoc()) {
            $sumSales += floatval($dr['net_amount']);
            $sumCash += floatval($dr['cash_received']);
            $sumBank += floatval($dr['bank_received']);
            $sumCgst += floatval($dr['cgst']);
            $sumSgst += floatval($dr['sgst']);
            $sumGoldWt += floatval($dr['gold_weight']);
            $sumSilverWt += floatval($dr['silver_weight']);
            $dailyRows[] = $dr;
        }
    }
    ?>
    <div class="bg-white dark:bg-gray-800 p-5 sm:p-6 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700/60 gap-3">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100 font-serif m-0">
                    Daily Sales &amp; GST Output Tax Register
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-0">Daily turnover audits, cash vs. UPI collection, and 1-click GST export.</p>
            </div>

            <!-- Date Selector & Excel Export -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <form method="GET" action="/admin/index.php" class="flex items-center gap-2">
                    <input type="hidden" name="view" value="billing">
                    <input type="hidden" name="tab" value="reports">
                    <input type="date" name="report_date" value="<?= htmlspecialchars($filterDate) ?>" onchange="this.form.submit()" class="px-3 py-1.5 rounded-xl text-xs font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </form>
                <a href="/billing-software/reports/export_daily_report_excel.php?filter_type=daily&report_date=<?= urlencode($filterDate) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition-all">
                    <i class="fas fa-file-excel"></i> Export Excel
                </a>
            </div>
        </div>

        <!-- Metric Summary Chips -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-gray-50 dark:bg-gray-700/40 p-4 rounded-xl border border-gray-100 dark:border-gray-700/60">
            <div>
                <span class="text-xs text-gray-500 block uppercase font-bold">Total Sales</span>
                <strong class="text-lg font-black text-gray-900 dark:text-gray-100">₹<?= number_format($sumSales, 2) ?></strong>
            </div>
            <div>
                <span class="text-xs text-gray-500 block uppercase font-bold">Cash Collection</span>
                <strong class="text-lg font-black text-emerald-600">₹<?= number_format($sumCash, 2) ?></strong>
            </div>
            <div>
                <span class="text-xs text-gray-500 block uppercase font-bold">UPI / Digital</span>
                <strong class="text-lg font-black text-blue-600">₹<?= number_format($sumBank, 2) ?></strong>
            </div>
            <div>
                <span class="text-xs text-gray-500 block uppercase font-bold">GST Tax (3%)</span>
                <strong class="text-lg font-black text-amber-600">₹<?= number_format($sumCgst + $sumSgst, 2) ?></strong>
            </div>
        </div>

        <!-- Register Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">
                        <th class="py-3 px-3">Vch #</th>
                        <th class="py-3 px-3">Customer</th>
                        <th class="py-3 px-3">Type</th>
                        <th class="py-3 px-3 text-right">Taxable</th>
                        <th class="py-3 px-3 text-right">CGST</th>
                        <th class="py-3 px-3 text-right">SGST</th>
                        <th class="py-3 px-3 text-right">Invoice Total</th>
                        <th class="py-3 px-3 text-center">Receipt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    <?php if (!empty($dailyRows)): ?>
                        <?php foreach ($dailyRows as $dr): 
                            $isL = ($dr['bill_type'] === 'LABOUR');
                            $printUrl = $isL ? "/billing-software/billing/labour_print_bill.php?id=" . $dr['id'] : "/billing-software/billing/print_bill.php?id=" . $dr['id'];
                        ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-2.5 px-3 font-bold"><?= htmlspecialchars($dr['bill_no']) ?></td>
                            <td class="py-2.5 px-3"><?= htmlspecialchars($dr['customer_name']) ?></td>
                            <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-700"><?= htmlspecialchars($dr['bill_type']) ?></span></td>
                            <td class="py-2.5 px-3 text-right">₹<?= number_format(floatval($dr['subtotal']), 2) ?></td>
                            <td class="py-2.5 px-3 text-right">₹<?= number_format(floatval($dr['cgst']), 2) ?></td>
                            <td class="py-2.5 px-3 text-right">₹<?= number_format(floatval($dr['sgst']), 2) ?></td>
                            <td class="py-2.5 px-3 text-right font-black">₹<?= number_format(floatval($dr['net_amount']), 2) ?></td>
                            <td class="py-2.5 px-3 text-center">
                                <a href="<?= htmlspecialchars($printUrl) ?>" target="_blank" class="text-sky-600 hover:text-sky-800 font-bold"><i class="fas fa-print"></i> Print</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-10 text-gray-400">No transactions recorded on <?= date('d M Y', strtotime($filterDate)) ?>.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <!-- TAB 6: DUAL PAYMENT QR STANDS STUDIO                                       -->
    <!-- ══════════════════════════════════════════════════════════════════════════ -->
    <?php if ($activeTab === 'qr'): ?>
    <div class="bg-white dark:bg-gray-800 p-5 sm:p-6 rounded-2xl border border-gray-100 dark:border-gray-700/60 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700/60 gap-3">
            <div>
                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100 font-serif m-0">
                    Dual Merchant UPI Payment QR Stand Studio
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-0">High-resolution print-ready payment displays for your showroom sales counter.</p>
            </div>
            <a href="/billing-software/print_qr.php" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold bg-amber-500 hover:bg-amber-600 text-white shadow-md transition-all">
                <i class="fas fa-print"></i> Print Combined Dual A4 Stand
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Stand 1: ChandraKala Jewellers -->
            <div class="bg-gray-50 dark:bg-gray-700/40 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 flex flex-col items-center text-center space-y-4">
                <div class="w-full flex items-center justify-between pb-2 border-b border-gray-200 dark:border-gray-600">
                    <span class="text-xs font-bold text-amber-700 dark:text-amber-400 uppercase">Stand 1: Primary Counter</span>
                    <a href="/billing-software/generate_qr.php" target="_blank" class="text-xs font-bold text-amber-600 hover:underline">
                        <i class="fas fa-external-link-alt"></i> Fullscreen
                    </a>
                </div>
                <div class="p-3 bg-white rounded-xl shadow-inner border border-gray-200 max-w-xs">
                    <img src="/billing-software/generate_qr.php" alt="Chandrakala Jewellers QR" class="w-full h-auto rounded-lg">
                </div>
                <div>
                    <h4 class="text-sm font-black text-gray-900 dark:text-gray-100 m-0">CHANDRAKALA JEWELLERS</h4>
                    <span class="text-xs text-gray-500 font-mono">UPI: boism-9427080359@boi</span>
                </div>
                <a href="/billing-software/generate_qr.php" download="Chandrakala_QR_Stand.png" class="px-4 py-2 rounded-xl bg-gray-900 text-white text-xs font-bold hover:bg-black transition-all">
                    <i class="fas fa-download mr-1"></i> Download Stand Image
                </a>
            </div>

            <!-- Stand 2: Hasmukh Hiralal Soni -->
            <div class="bg-gray-50 dark:bg-gray-700/40 p-5 rounded-2xl border border-gray-200 dark:border-gray-700 flex flex-col items-center text-center space-y-4">
                <div class="w-full flex items-center justify-between pb-2 border-b border-gray-200 dark:border-gray-600">
                    <span class="text-xs font-bold text-purple-700 dark:text-purple-400 uppercase">Stand 2: Proprietor Counter</span>
                    <a href="/billing-software/generate_qr_1.php" target="_blank" class="text-xs font-bold text-purple-600 hover:underline">
                        <i class="fas fa-external-link-alt"></i> Fullscreen
                    </a>
                </div>
                <div class="p-3 bg-white rounded-xl shadow-inner border border-gray-200 max-w-xs">
                    <img src="/billing-software/generate_qr_1.php" alt="Hasmukh Hiralal Soni QR" class="w-full h-auto rounded-lg">
                </div>
                <div>
                    <h4 class="text-sm font-black text-gray-900 dark:text-gray-100 m-0">HASMUKH HIRALAL SONI</h4>
                    <span class="text-xs text-gray-500 font-mono">UPI: boism-9427080359@boi</span>
                </div>
                <a href="/billing-software/generate_qr_1.php" download="Hasmukh_Hiralal_QR_Stand.png" class="px-4 py-2 rounded-xl bg-gray-900 text-white text-xs font-bold hover:bg-black transition-all">
                    <i class="fas fa-download mr-1"></i> Download Stand Image
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- MODAL: CUSTOMER EXTENDED KHATA LEDGER DETAIL                               -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div id="customerLedgerModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-gray-100 dark:border-gray-700/60 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 m-0" id="ledgerModalTitle">Customer Khata Ledger</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 mb-0" id="ledgerModalSubtitle"></p>
            </div>
            <button type="button" onclick="closeCustomerLedger()" class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-500 hover:text-gray-900 flex items-center justify-center cursor-pointer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4 sm:p-5 overflow-y-auto flex-1" id="ledgerModalContent">
            <div class="text-center py-8 text-gray-400">Loading ledger records…</div>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-end">
            <button type="button" onclick="closeCustomerLedger()" class="px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-xs font-bold cursor-pointer">
                Close
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!-- REAL-TIME JAVASCRIPT CALCULATOR & AUTOCOMPLETE LOGIC                       -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<script>
(function initPOS() {
    'use strict';

    // ─── 1. Autocomplete Search for Customers ─────────────────────────────────
    var custInput = document.getElementById('custSearchInput');
    var custBox   = document.getElementById('custSuggestions');
    var mobileIn  = document.getElementById('custMobile');
    var addrIn    = document.getElementById('custAddress');
    var gstIn     = document.getElementById('custGst');
    var panIn     = document.getElementById('custPan');

    if (custInput && custBox) {
        var searchDebounce = null;
        custInput.addEventListener('input', function () {
            clearTimeout(searchDebounce);
            var q = this.value.trim();
            if (q.length < 2) {
                custBox.classList.add('hidden');
                custBox.innerHTML = '';
                return;
            }
            searchDebounce = setTimeout(function () {
                fetch('/admin/index.php?billing_ajax=customer_search&q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (list) {
                        if (!list || list.length === 0) {
                            custBox.classList.add('hidden');
                            return;
                        }
                        var html = '';
                        list.forEach(function (c) {
                            html += '<div class="p-2.5 hover:bg-amber-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700/50 cursor-pointer text-xs" data-cust=\'' + JSON.stringify(c).replace(/'/g, '&#39;') + '\'>';
                            html += '<strong class="text-gray-900 dark:text-gray-100 block">' + c.name + '</strong>';
                            html += '<span class="text-gray-500 text-[11px]">' + (c.mobile || 'No Mobile') + (c.address ? ' • ' + c.address : '') + '</span>';
                            html += '</div>';
                        });
                        custBox.innerHTML = html;
                        custBox.classList.remove('hidden');

                        custBox.querySelectorAll('[data-cust]').forEach(function (el) {
                            el.addEventListener('click', function () {
                                var data = JSON.parse(this.getAttribute('data-cust'));
                                custInput.value = data.name;
                                if (mobileIn && data.mobile) mobileIn.value = data.mobile;
                                if (addrIn && data.address)   addrIn.value = data.address;
                                if (gstIn && data.gst_no)     gstIn.value = data.gst_no;
                                if (panIn && data.pan_no)     panIn.value = data.pan_no;
                                custBox.classList.add('hidden');
                            });
                        });
                    })
                    .catch(function () { custBox.classList.add('hidden'); });
            }, 250);
        });

        document.addEventListener('click', function (e) {
            if (!custInput.contains(e.target) && !custBox.contains(e.target)) {
                custBox.classList.add('hidden');
            }
        });
    }

    // ─── 2. Real-Time Table Calculations Engine ───────────────────────────────
    var tableBody = document.getElementById('posItemsBody');
    var addBtn    = document.getElementById('btnAddItemRow');

    function calculatePosTotals() {
        if (!tableBody) return;
        var rows = tableBody.querySelectorAll('.pos-item-row');
        var subtotal = 0;

        var goldRateGlobal = parseFloat(document.getElementById('goldRateGlobal')?.value || '7350');
        var silverRateGlobal = parseFloat(document.getElementById('silverRateGlobal')?.value || '90');

        rows.forEach(function (row, index) {
            var numCell = row.querySelector('.row-num');
            if (numCell) numCell.textContent = index + 1;

            var metalSel = row.querySelector('.item-metal-select');
            var purityIn = row.querySelector('.item-purity-hidden');
            var hsnIn    = row.querySelector('.item-hsn');
            var qtyIn    = row.querySelector('.item-qty');
            var rateIn   = row.querySelector('.item-rate');
            var makeIn   = row.querySelector('.item-making');
            var makeDisp = row.querySelector('.item-making-flat');
            var totDisp  = row.querySelector('.item-total-disp');

            if (!qtyIn || !rateIn || !makeIn) return;

            var qty = parseFloat(qtyIn.value || '0');
            var rate = parseFloat(rateIn.value || '0');
            var makePct = parseFloat(makeIn.value || '0');
            var isSilver = metalSel && metalSel.value.toLowerCase().indexOf('silver') > -1;

            // Flat making charge per gram
            var baseRate = isSilver ? silverRateGlobal : goldRateGlobal;
            var makingFlat = baseRate * (makePct / 100);

            // Formula: (Rate + Making per gram) * Quantity
            var rowTotal = (rate + makingFlat) * qty;

            if (makeDisp) makeDisp.textContent = '₹' + makingFlat.toFixed(2);
            if (totDisp) totDisp.textContent = '₹' + rowTotal.toFixed(2);

            subtotal += rowTotal;
        });

        // Compute Subtotal, Taxes & Net
        var discount = parseFloat(document.getElementById('discountVal')?.value || '0');
        var taxable = Math.max(0, subtotal - discount);
        var cgst = taxable * 0.015; // 1.5% CGST
        var sgst = taxable * 0.015; // 1.5% SGST
        var net = taxable + cgst + sgst;

        // Update Summary Elements
        if (document.getElementById('dispSubtotal')) document.getElementById('dispSubtotal').textContent = '₹' + subtotal.toFixed(2);
        if (document.getElementById('subtotalVal')) document.getElementById('subtotalVal').value = subtotal.toFixed(2);

        if (document.getElementById('dispCgst')) document.getElementById('dispCgst').textContent = '₹' + cgst.toFixed(2);
        if (document.getElementById('cgstVal')) document.getElementById('cgstVal').value = cgst.toFixed(2);

        if (document.getElementById('dispSgst')) document.getElementById('dispSgst').textContent = '₹' + sgst.toFixed(2);
        if (document.getElementById('sgstVal')) document.getElementById('sgstVal').value = sgst.toFixed(2);

        if (document.getElementById('dispNetAmount')) document.getElementById('dispNetAmount').textContent = '₹' + net.toFixed(2);
        if (document.getElementById('netAmountVal')) document.getElementById('netAmountVal').value = net.toFixed(2);

        // Update Balance
        var cash = parseFloat(document.getElementById('cashReceived')?.value || '0');
        var bank = parseFloat(document.getElementById('bankReceived')?.value || '0');
        var oldGold = parseFloat(document.getElementById('oldGoldAmount')?.value || '0');
        var balance = Math.max(0, net - (cash + bank + oldGold));

        if (document.getElementById('balanceAmount')) document.getElementById('balanceAmount').value = balance.toFixed(2);
    }

    // Attach row events
    function bindRowEvents(row) {
        var inputs = row.querySelectorAll('input, select');
        inputs.forEach(function (el) {
            el.addEventListener('input', calculatePosTotals);
            el.addEventListener('change', calculatePosTotals);
        });

        // Metal type changed -> update default rate, HSN & purity
        var metalSel = row.querySelector('.item-metal-select');
        var rateIn   = row.querySelector('.item-rate');
        var hsnIn    = row.querySelector('.item-hsn');
        var purityIn = row.querySelector('.item-purity-hidden');

        if (metalSel && rateIn) {
            metalSel.addEventListener('change', function () {
                var opt = this.options[this.selectedIndex];
                var purity = parseFloat(opt.getAttribute('data-purity') || '91.6');
                var hsn = opt.getAttribute('data-hsn') || '7113';
                if (hsnIn) hsnIn.value = hsn;
                if (purityIn) purityIn.value = purity;

                var goldRateGlobal = parseFloat(document.getElementById('goldRateGlobal')?.value || '7350');
                var silverRateGlobal = parseFloat(document.getElementById('silverRateGlobal')?.value || '90');

                if (opt.value.indexOf('24K') > -1) {
                    rateIn.value = goldRateGlobal.toFixed(2);
                } else if (opt.value.indexOf('22K') > -1) {
                    rateIn.value = (goldRateGlobal * (22 / 24)).toFixed(2);
                } else if (opt.value.indexOf('18K') > -1) {
                    rateIn.value = (goldRateGlobal * (18 / 24)).toFixed(2);
                } else if (opt.value.indexOf('925') > -1) {
                    rateIn.value = (silverRateGlobal * 1.05).toFixed(2);
                } else if (opt.value.indexOf('Silver') > -1) {
                    rateIn.value = silverRateGlobal.toFixed(2);
                }
                calculatePosTotals();
            });
        }

        // Remove row button
        var removeBtn = row.querySelector('.btn-remove-row');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                if (tableBody.querySelectorAll('.pos-item-row').length > 1) {
                    row.remove();
                    calculatePosTotals();
                } else {
                    alert('At least one line item is required on the invoice.');
                }
            });
        }
    }

    if (tableBody) {
        tableBody.querySelectorAll('.pos-item-row').forEach(bindRowEvents);

        // Add Row Button
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var goldRateGlobal = parseFloat(document.getElementById('goldRateGlobal')?.value || '7350');
                var def22k = (goldRateGlobal * (22 / 24)).toFixed(2);
                var rowIdx = tableBody.querySelectorAll('.pos-item-row').length;

                var tr = document.createElement('tr');
                tr.className = 'pos-item-row';
                tr.setAttribute('data-row-idx', rowIdx);
                tr.innerHTML = 
                    '<td class="py-2 px-3 font-semibold text-gray-400 row-num">' + (rowIdx + 1) + '</td>' +
                    '<td class="py-2 px-3"><input type="text" name="description[]" required placeholder="Item description..." class="w-full px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-semibold text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-amber-500"></td>' +
                    '<td class="py-2 px-3">' +
                        '<select name="item_metal_type[]" class="item-metal-select w-full px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-semibold text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-amber-500">' +
                            '<option value="Gold 22K" data-purity="91.6" data-hsn="7113">Gold 22K (916)</option>' +
                            '<option value="Gold 24K" data-purity="99.9" data-hsn="7113">Gold 24K (999)</option>' +
                            '<option value="Gold 18K" data-purity="75.0" data-hsn="7113">Gold 18K (750)</option>' +
                            '<option value="Silver 925" data-purity="92.5" data-hsn="7114">Silver 92.5</option>' +
                            '<option value="Silver" data-purity="70.0" data-hsn="7114">Silver Regular</option>' +
                        '</select>' +
                        '<input type="hidden" name="purity_val[]" class="item-purity-hidden" value="91.6">' +
                    '</td>' +
                    '<td class="py-2 px-3"><input type="text" name="hsn_code[]" value="7113" class="item-hsn w-full px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-xs text-center font-mono"></td>' +
                    '<td class="py-2 px-3"><input type="number" step="0.001" min="0.001" name="quantity[]" value="" required placeholder="0.000" class="item-qty w-full px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-bold text-gray-900 dark:text-gray-100 text-right focus:ring-1 focus:ring-amber-500"></td>' +
                    '<td class="py-2 px-3"><input type="number" step="0.01" name="rate[]" value="' + def22k + '" required class="item-rate w-full px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs font-semibold text-right focus:ring-1 focus:ring-amber-500"></td>' +
                    '<td class="py-2 px-3"><input type="number" step="0.1" min="0" name="making[]" value="12" placeholder="12" class="item-making w-full px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs text-right font-semibold focus:ring-1 focus:ring-amber-500"></td>' +
                    '<td class="py-2 px-3 text-right font-medium text-gray-700 dark:text-gray-300 item-making-flat">₹0.00</td>' +
                    '<td class="py-2 px-3 text-right font-bold text-gray-900 dark:text-gray-100 item-total-disp">₹0.00</td>' +
                    '<td class="py-2 px-3 text-center"><button type="button" class="btn-remove-row text-rose-500 hover:text-rose-700 p-1"><i class="fas fa-trash-alt"></i></button></td>';

                tableBody.appendChild(tr);
                bindRowEvents(tr);
                calculatePosTotals();
            });
        }

        // Attach listeners for payment inputs & discount
        ['discountVal', 'cashReceived', 'bankReceived', 'oldGoldAmount'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', calculatePosTotals);
            }
        });

        // 1-Click Match Buttons
        var matchCash = document.getElementById('btnMatchCash');
        var matchUpi  = document.getElementById('btnMatchUpi');
        var clearPay  = document.getElementById('btnClearPayments');

        if (matchCash) {
            matchCash.addEventListener('click', function () {
                var net = parseFloat(document.getElementById('netAmountVal')?.value || '0');
                if (document.getElementById('cashReceived')) document.getElementById('cashReceived').value = net.toFixed(2);
                if (document.getElementById('bankReceived')) document.getElementById('bankReceived').value = '0.00';
                calculatePosTotals();
            });
        }
        if (matchUpi) {
            matchUpi.addEventListener('click', function () {
                var net = parseFloat(document.getElementById('netAmountVal')?.value || '0');
                if (document.getElementById('bankReceived')) document.getElementById('bankReceived').value = net.toFixed(2);
                if (document.getElementById('cashReceived')) document.getElementById('cashReceived').value = '0.00';
                calculatePosTotals();
            });
        }
        if (clearPay) {
            clearPay.addEventListener('click', function () {
                if (document.getElementById('cashReceived')) document.getElementById('cashReceived').value = '0.00';
                if (document.getElementById('bankReceived')) document.getElementById('bankReceived').value = '0.00';
                if (document.getElementById('oldGoldAmount')) document.getElementById('oldGoldAmount').value = '0.00';
                calculatePosTotals();
            });
        }

        // Initial Calculation Run
        calculatePosTotals();
    }
})();

// ─── 3. Client-Side Search Filters ─────────────────────────────────────────────
function filterHistoryTable() {
    var q = document.getElementById('historySearch')?.value.toLowerCase() || '';
    var rows = document.querySelectorAll('#historyTable tbody .history-row');
    rows.forEach(function (r) {
        var text = r.textContent.toLowerCase();
        r.style.display = (text.indexOf(q) > -1) ? '' : 'none';
    });
}

function filterCrmTable() {
    var q = document.getElementById('crmSearch')?.value.toLowerCase() || '';
    var rows = document.querySelectorAll('#crmTable tbody .crm-row');
    rows.forEach(function (r) {
        var text = r.textContent.toLowerCase();
        r.style.display = (text.indexOf(q) > -1) ? '' : 'none';
    });
}

// ─── 4. Labour Calculator ──────────────────────────────────────────────────────
function calcLabourTotal() {
    var sub = parseFloat(document.getElementById('labourSubtotal')?.value || '0');
    var cash = parseFloat(document.getElementById('labourCash')?.value || '0');
    var bank = parseFloat(document.getElementById('labourBank')?.value || '0');

    if (document.getElementById('labourRate')) document.getElementById('labourRate').value = sub.toFixed(2);
    if (document.getElementById('labourItemTotal')) document.getElementById('labourItemTotal').value = sub.toFixed(2);
    if (document.getElementById('labourNet')) document.getElementById('labourNet').value = sub.toFixed(2);

    var bal = Math.max(0, sub - (cash + bank));
    if (document.getElementById('labourBalance')) document.getElementById('labourBalance').value = bal.toFixed(2);
}

// ─── 5. WhatsApp Message Composer ──────────────────────────────────────────────
function shareWhatsApp(custName, mobile, billNo, netAmt, balAmt) {
    if (!mobile || mobile.length < 10) {
        mobile = prompt("Enter customer's 10-digit mobile number for WhatsApp:", "");
        if (!mobile) return;
    }
    var cleanPhone = mobile.replace(/[^0-9]/g, '');
    if (cleanPhone.length === 10) cleanPhone = '91' + cleanPhone;

    var text = "Namaste *" + custName + "* ji,\n\n" +
               "Thank you for shopping at *CHANDRAKALA JEWELLERS*.\n" +
               "📄 Invoice No: *" + billNo + "*\n" +
               "💰 Net Amount: ₹" + netAmt + "\n";
    if (parseFloat(balAmt.replace(/,/g, '')) > 0) {
        text += "⚠️ Pending Khata Balance: *₹" + balAmt + "*\n" +
                "UPI Payment ID: *boism-9427080359@boi*\n";
    } else {
        text += "✅ Status: *Fully Paid*\n";
    }
    text += "\nWe value your trust and look forward to serving you again!\n_ChandraKala Jewellers, Khedbrahma_";

    window.open("https://wa.me/" + cleanPhone + "?text=" + encodeURIComponent(text), "_blank");
}

// ─── 6. Customer Ledger Modal Loader ───────────────────────────────────────────
function openCustomerLedger(name, mobile) {
    var modal = document.getElementById('customerLedgerModal');
    var title = document.getElementById('ledgerModalTitle');
    var sub   = document.getElementById('ledgerModalSubtitle');
    var body  = document.getElementById('ledgerModalContent');
    if (!modal) return;

    title.textContent = name;
    sub.textContent = 'Mobile: ' + (mobile || 'N/A') + ' • Past Invoices & Ledger';
    body.innerHTML = '<div class="text-center py-8 text-gray-400"><i class="fas fa-spinner fa-spin mr-1"></i> Loading customer records…</div>';
    modal.classList.remove('hidden');

    fetch('/admin/index.php?billing_ajax=customer_ledger&customer_name=' + encodeURIComponent(name) + '&mobile=' + encodeURIComponent(mobile))
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success || !res.bills || res.bills.length === 0) {
                body.innerHTML = '<div class="text-center py-8 text-gray-400">No past transactions found for this customer.</div>';
                return;
            }
            var html = '<table class="w-full text-left text-xs border-collapse"><thead><tr class="bg-gray-50 border-b font-bold"><th class="p-2">Bill #</th><th class="p-2">Date</th><th class="p-2">Type</th><th class="p-2 text-right">Net (₹)</th><th class="p-2 text-right">Balance Due</th><th class="p-2 text-center">Receipt</th></tr></thead><tbody class="divide-y">';
            res.bills.forEach(function (b) {
                var isL = (b.bill_type === 'LABOUR');
                var printUrl = isL ? '/billing-software/billing/labour_print_bill.php?id=' + b.id : '/billing-software/billing/print_bill.php?id=' + b.id;
                var due = parseFloat(b.balance_amount || '0');
                html += '<tr>' +
                    '<td class="p-2 font-bold">' + b.bill_no + '</td>' +
                    '<td class="p-2">' + b.bill_date + '</td>' +
                    '<td class="p-2"><span class="px-2 py-0.5 rounded text-[10px] font-bold ' + (isL ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800') + '">' + b.bill_type + '</span></td>' +
                    '<td class="p-2 text-right font-black">₹' + parseFloat(b.net_amount).toFixed(2) + '</td>' +
                    '<td class="p-2 text-right font-bold ' + (due > 0 ? 'text-rose-600' : 'text-emerald-600') + '">' + (due > 0 ? '₹' + due.toFixed(2) : 'Paid') + '</td>' +
                    '<td class="p-2 text-center"><a href="' + printUrl + '" target="_blank" class="text-sky-600 hover:text-sky-800 font-bold"><i class="fas fa-print"></i></a></td>' +
                '</tr>';
            });
            html += '</tbody></table>';
            body.innerHTML = html;
        })
        .catch(function () {
            body.innerHTML = '<div class="text-center py-8 text-rose-500">Failed to load customer ledger.</div>';
        });
}

function closeCustomerLedger() {
    var modal = document.getElementById('customerLedgerModal');
    if (modal) modal.classList.add('hidden');
}
</script>
