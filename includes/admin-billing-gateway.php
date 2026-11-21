<?php
/**
 * ChandraKala Jewellers — In-Store Billing & POS Gateway
 * 
 * Provides direct access to the local jewellery-management POS & Billing software
 * while keeping databases, records, and codebases 100% separate and isolated.
 */

$isLocal = function_exists('cj_is_local_host') && cj_is_local_host();
$localBase = '/jewellery-management/';
$defaultFrameUrl = $localBase . 'dashboard.php';
?>

<style>
/* POS & Billing Gateway Luxury Theme */
.cj-pos-wrapper {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.cj-pos-card {
    background: var(--cj-sh-surface, #ffffff);
    border: 1px solid var(--cj-sh-border, #ece7da);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.cj-pos-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 16px;
}
@media (min-width: 640px) {
    .cj-pos-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (min-width: 1024px) {
    .cj-pos-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

.cj-pos-tile {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 20px;
    border-radius: 14px;
    background: var(--cj-sh-surface, #ffffff);
    border: 1px solid var(--cj-sh-border, #ece7da);
    text-decoration: none;
    color: inherit;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}
.cj-pos-tile:hover {
    transform: translateY(-3px);
    border-color: #d4af37;
    box-shadow: 0 10px 24px rgba(212, 175, 55, 0.12);
}
.cj-pos-tile::before {
    content: '';
    position: absolute;
    top: 0; left: 0; width: 4px; height: 100%;
    background: linear-gradient(180deg, #d4af37, #960018);
    opacity: 0;
    transition: opacity 0.2s ease;
}
.cj-pos-tile:hover::before {
    opacity: 1;
}

.cj-pos-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    margin-bottom: 14px;
    background: rgba(212, 175, 55, 0.12);
    color: #b45309;
}
html[data-theme="dark"] .cj-pos-icon-box {
    background: rgba(240, 215, 140, 0.14);
    color: #f0d78c;
}

/* Embedded Frame Architecture */
.cj-pos-frame-card {
    background: var(--cj-sh-surface, #ffffff);
    border: 1px solid var(--cj-sh-border, #ece7da);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}
.cj-pos-frame-toolbar {
    padding: 12px 18px;
    background: var(--cj-sh-surface-alt, #f8f7f3);
    border-bottom: 1px solid var(--cj-sh-border, #ece7da);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.cj-pos-frame-wrapper {
    position: relative;
    width: 100%;
    height: 820px;
    background: #f8fafc;
}
@media (max-width: 768px) {
    .cj-pos-frame-wrapper { height: 600px; }
}
.cj-pos-iframe {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
}

/* Offline Helper Alert */
.cj-pos-alert {
    padding: 14px 18px;
    border-radius: 12px;
    background: #fef3c7;
    border: 1px solid #fde68a;
    color: #92400e;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 10px;
}
html[data-theme="dark"] .cj-pos-alert {
    background: rgba(245, 158, 11, 0.12);
    border-color: rgba(245, 158, 11, 0.3);
    color: #fbbf24;
}
</style>

<div class="cj-pos-wrapper">

    <!-- 1. Header & Live Controller -->
    <div class="cj-pos-card">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-amber-500/15 text-amber-700 dark:text-amber-400">
                        <i class="fas fa-cash-register text-base"></i>
                    </span>
                    <h2 class="text-xl sm:text-2xl font-bold font-serif m-0 text-gray-900 dark:text-gray-100">
                        In-Store Billing &amp; POS Studio
                    </h2>
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span id="posServerBadge">Checking Local POS Server…</span>
                    </span>
                </div>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1 mb-0">
                    Autonomous offline billing engine. Generate GST sale bills, labour repair invoices, customer khata ledger &amp; metal reports.
                </p>
            </div>

            <!-- View Switcher & Actions -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <button type="button" id="btnPosEmbedded" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-amber-500 text-white shadow-sm hover:bg-amber-600 transition-all cursor-pointer">
                    <i class="fas fa-desktop text-[11px]"></i> Embedded Studio
                </button>
                <button type="button" id="btnPosLaunchpad" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200 transition-all cursor-pointer">
                    <i class="fas fa-th-large text-[11px]"></i> Launchpad Deck
                </button>
                <a href="<?= htmlspecialchars($defaultFrameUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30 text-amber-900 dark:text-amber-300 hover:bg-amber-100 transition-all">
                    <i class="fas fa-external-link-alt text-[10px]"></i> Open in New Window
                </a>
            </div>
        </div>

        <!-- System Architecture Isolation Notice -->
        <div class="mt-4 pt-3.5 border-t border-gray-100 dark:border-gray-800/60 flex items-center justify-between text-[11px] text-gray-500 flex-wrap gap-2">
            <span class="flex items-center gap-1.5">
                <i class="fas fa-shield-alt text-amber-600"></i>
                <strong>Independent Database:</strong> Customer invoices &amp; GST records are stored locally in <code>jewellery_management</code>.
            </span>
            <span class="flex items-center gap-1.5">
                <i class="fas fa-cloud text-blue-500"></i>
                <strong>Online Cloud Separation:</strong> Public website database &amp; online stock remain completely isolated and unaffected.
            </span>
        </div>
    </div>

    <!-- 2. Embedded POS Studio Frame (Default) -->
    <div id="posEmbeddedView" class="cj-pos-frame-card">
        <div class="cj-pos-frame-toolbar">
            <div class="flex items-center gap-2">
                <label for="posRouteSelect" class="text-xs font-bold text-gray-600 dark:text-gray-300 flex items-center gap-1.5">
                    <i class="fas fa-route text-amber-500"></i> Jump to Screen:
                </label>
                <select id="posRouteSelect" class="text-xs font-semibold py-1.5 px-3 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="/jewellery-management/dashboard.php">POS Dashboard (Overview &amp; Metrics)</option>
                    <option value="/jewellery-management/billing/sale_bill.php">Create New Sale Bill (GST)</option>
                    <option value="/jewellery-management/billing/bill_history.php">Bill History &amp; Reprint Search</option>
                    <option value="/jewellery-management/billing/labour_bill.php">Labour &amp; Repair Invoicing</option>
                    <option value="/jewellery-management/customers/customers.php">Customer Ledger &amp; Khata</option>
                    <option value="/jewellery-management/reports/daily_report.php">Daily Sales &amp; Metal Reports</option>
                    <option value="/jewellery-management/print_qr.php">Dual Payment QR Stand Studio</option>
                    <option value="/jewellery-management/settings.php">System &amp; Business Settings</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" id="btnReloadPos" class="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" title="Reload Frame">
                    <i class="fas fa-redo-alt text-[10px]"></i> Reload
                </button>
                <button type="button" id="btnFullscreenPos" class="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" title="Expand Fullscreen">
                    <i class="fas fa-expand text-[10px]"></i> Fullscreen
                </button>
            </div>
        </div>

        <div class="cj-pos-frame-wrapper" id="posFrameContainer">
            <iframe id="posIframe" src="<?= htmlspecialchars($defaultFrameUrl) ?>" class="cj-pos-iframe" title="Jewellery Management Billing Suite"></iframe>
        </div>
    </div>

    <!-- 3. Launchpad Deck Cards View (Toggled or Fallback) -->
    <div id="posLaunchpadView" class="cj-pos-grid hidden">
        <!-- Tile 1: Create Sale Bill -->
        <a href="/jewellery-management/billing/sale_bill.php" target="_blank" rel="noopener" class="cj-pos-tile">
            <div>
                <div class="cj-pos-icon-box">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <h3 class="text-base font-bold font-serif text-gray-900 dark:text-gray-100 m-0 mb-1.5">
                    New Sale Bill (GST)
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 m-0 leading-relaxed">
                    Fast counter billing: Gold/Silver items, purity, weight, waste/haldhar, making charges, CGST/SGST &amp; payment splits.
                </p>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs font-bold text-amber-600 dark:text-amber-400">
                <span>Open Billing Form</span>
                <i class="fas fa-arrow-right text-[10px]"></i>
            </div>
        </a>

        <!-- Tile 2: Bill History -->
        <a href="/jewellery-management/billing/bill_history.php" target="_blank" rel="noopener" class="cj-pos-tile">
            <div>
                <div class="cj-pos-icon-box">
                    <i class="fas fa-history"></i>
                </div>
                <h3 class="text-base font-bold font-serif text-gray-900 dark:text-gray-100 m-0 mb-1.5">
                    Bill History &amp; Reprint
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 m-0 leading-relaxed">
                    Search and lookup past customer invoices by Bill #, customer name or phone. Instant A4 and thermal slip reprint.
                </p>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs font-bold text-amber-600 dark:text-amber-400">
                <span>Browse Invoice Records</span>
                <i class="fas fa-arrow-right text-[10px]"></i>
            </div>
        </a>

        <!-- Tile 3: Labour & Repair Bill -->
        <a href="/jewellery-management/billing/labour_bill.php" target="_blank" rel="noopener" class="cj-pos-tile">
            <div>
                <div class="cj-pos-icon-box">
                    <i class="fas fa-tools"></i>
                </div>
                <h3 class="text-base font-bold font-serif text-gray-900 dark:text-gray-100 m-0 mb-1.5">
                    Labour &amp; Karigar Bill
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 m-0 leading-relaxed">
                    Specialized invoicing for custom handmade jewellery, polish, resizing, stone setting, repair, and artisan charges.
                </p>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs font-bold text-amber-600 dark:text-amber-400">
                <span>Create Labour Bill</span>
                <i class="fas fa-arrow-right text-[10px]"></i>
            </div>
        </a>

        <!-- Tile 4: Customers & Khata -->
        <a href="/jewellery-management/customers/customers.php" target="_blank" rel="noopener" class="cj-pos-tile">
            <div>
                <div class="cj-pos-icon-box">
                    <i class="fas fa-users-cog"></i>
                </div>
                <h3 class="text-base font-bold font-serif text-gray-900 dark:text-gray-100 m-0 mb-1.5">
                    Customer Ledger (Khata)
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 m-0 leading-relaxed">
                    Customer profile database, credit balance tracking, transaction ledger, city categorization, and contact book.
                </p>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs font-bold text-amber-600 dark:text-amber-400">
                <span>Manage Customers</span>
                <i class="fas fa-arrow-right text-[10px]"></i>
            </div>
        </a>

        <!-- Tile 5: Daily Reports & GST -->
        <a href="/jewellery-management/reports/daily_report.php" target="_blank" rel="noopener" class="cj-pos-tile">
            <div>
                <div class="cj-pos-icon-box">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <h3 class="text-base font-bold font-serif text-gray-900 dark:text-gray-100 m-0 mb-1.5">
                    Daily Sales &amp; Metal Reports
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 m-0 leading-relaxed">
                    Daily turnover audits: Gold &amp; Silver weight sold, cash vs. UPI collection, and 1-click GST register export to Excel.
                </p>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs font-bold text-amber-600 dark:text-amber-400">
                <span>View Daily Audits</span>
                <i class="fas fa-arrow-right text-[10px]"></i>
            </div>
        </a>

        <!-- Tile 6: QR Payment Stands -->
        <a href="/jewellery-management/print_qr.php" target="_blank" rel="noopener" class="cj-pos-tile">
            <div>
                <div class="cj-pos-icon-box">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h3 class="text-base font-bold font-serif text-gray-900 dark:text-gray-100 m-0 mb-1.5">
                    Payment QR Stand Studio
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 m-0 leading-relaxed">
                    High-resolution merchant payment QR codes for ChandraKala Jewellers &amp; Hasmukh Hiralal Soni counter stands.
                </p>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs font-bold text-amber-600 dark:text-amber-400">
                <span>Print QR Codes</span>
                <i class="fas fa-arrow-right text-[10px]"></i>
            </div>
        </a>
    </div>

</div>

<script>
(function initBillingGateway() {
    'use strict';

    var btnEmbedded = document.getElementById('btnPosEmbedded');
    var btnLaunchpad = document.getElementById('btnPosLaunchpad');
    var embeddedView = document.getElementById('posEmbeddedView');
    var launchpadView = document.getElementById('posLaunchpadView');
    var iframe = document.getElementById('posIframe');
    var routeSelect = document.getElementById('posRouteSelect');
    var btnReload = document.getElementById('btnReloadPos');
    var btnFullscreen = document.getElementById('btnFullscreenPos');
    var frameContainer = document.getElementById('posFrameContainer');
    var serverBadge = document.getElementById('posServerBadge');

    // 1. View Toggle Logic
    if (btnEmbedded && btnLaunchpad) {
        btnEmbedded.addEventListener('click', function () {
            embeddedView.classList.remove('hidden');
            launchpadView.classList.add('hidden');
            btnEmbedded.className = 'inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-amber-500 text-white shadow-sm hover:bg-amber-600 transition-all cursor-pointer';
            btnLaunchpad.className = 'inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200 transition-all cursor-pointer';
        });

        btnLaunchpad.addEventListener('click', function () {
            embeddedView.classList.add('hidden');
            launchpadView.classList.remove('hidden');
            btnLaunchpad.className = 'inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-amber-500 text-white shadow-sm hover:bg-amber-600 transition-all cursor-pointer';
            btnEmbedded.className = 'inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200 transition-all cursor-pointer';
        });
    }

    // 2. Screen Selector
    if (routeSelect && iframe) {
        routeSelect.addEventListener('change', function () {
            iframe.src = this.value;
        });
    }

    // 3. Reload button
    if (btnReload && iframe) {
        btnReload.addEventListener('click', function () {
            iframe.src = iframe.src;
        });
    }

    // 4. Fullscreen
    if (btnFullscreen && frameContainer) {
        btnFullscreen.addEventListener('click', function () {
            if (!document.fullscreenElement) {
                frameContainer.requestFullscreen().catch(function (err) {
                    window.open(iframe.src, '_blank');
                });
            } else {
                document.exitFullscreen();
            }
        });
    }

    // 5. Ping Local XAMPP Server to update status badge
    if (serverBadge) {
        fetch('/jewellery-management/dashboard.php', { method: 'HEAD', mode: 'no-cors' })
            .then(function () {
                serverBadge.textContent = 'Local POS Active';
            })
            .catch(function () {
                serverBadge.textContent = 'XAMPP Offline (Start Apache & MySQL)';
            });
    }
})();
</script>
