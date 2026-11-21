<?php
include 'includes/db.php';

// Set timezone to ensure date matches your current region accurately
date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');

// 1. Total Lifetime Counts
$totalBills = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT COUNT(*) as total FROM bills")
)['total'];

$totalSales = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT SUM(net_amount) as total FROM bills")
)['total'];
$totalSales = $totalSales ?: 0;

// 2. Today's Dynamic Metrics
$todayBills = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT COUNT(*) as total FROM bills WHERE bill_date = '$today'")
)['total'];

$todaySales = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT SUM(net_amount) as total FROM bills WHERE bill_date = '$today'")
)['total'];
$todaySales = $todaySales ?: 0;

// 3. Next-Level Upgrades: Ticket Size Mean Dynamic Logic
$averageTicket = ($todayBills > 0) ? ($todaySales / $todayBills) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Jewellery Management - Chandrakala Jewellers</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    /* Premium Dashboard Next-Level Design System Archetype overrides */
    body { background-color: #f8fafc; color: #0f172a; font-family: 'Outfit', 'Segoe UI', system-ui, sans-serif; }
    .main { padding: 30px; }
    
    .dashboard-hero-title { font-size: 28px; font-weight: 800; color: #0f172a; margin: 0 0 5px 0; letter-spacing: 0.5px; }
    .dashboard-sub-desc { font-size: 14px; color: #64748b; margin: 0 0 10px 0; font-weight: 500; }
    
    /* Executive Card Layout Engine Extensions */
    .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .card { background: #ffffff; padding: 22px; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02), 0 2px 4px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
    .card:hover { transform: translateY(-4px); box-shadow: 0 12px 20px -8px rgba(0, 0, 0, 0.08); border-color: rgba(212, 175, 55, 0.3); }
    .card h3 { margin: 0 0 8px 0; color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
    .card h2 { margin: 0; font-size: 26px; font-weight: 700; color: #0f172a; }
    .card .icon-decorator { position: absolute; bottom: 15px; right: 15px; font-size: 24px; color: #cbd5e1; opacity: 0.6; }
    
    /* Interactive Quick Routing Control Box */
    .quick-actions-bar { background: #ffffff; border: 1px solid rgba(212, 175, 55, 0.18); padding: 20px; border-radius: 12px; display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 25px; align-items: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.01); }
    .action-bar-title { font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-right: 10px; }
    .btn-quick-nav { background: #ffffff; border: 1.5px solid #cbd5e1; color: #334155; font-weight: 600; padding: 10px 18px; font-size: 13px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease; }
    .btn-quick-nav:hover { background: #f8fafc; border-color: #d4af37; color: #0f172a; box-shadow: 0 4px 12px rgba(212, 175, 55, 0.1); }
    .btn-quick-nav.accent-red { background: #960018; color: #ffffff; border: none; }
    .btn-quick-nav.accent-red:hover { background: #730012; color: #ffffff; box-shadow: 0 4px 12px rgba(150, 0, 24, 0.2); }
    
    .hotkey-badge { background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 700; padding: 2px 6px; border-radius: 4px; border: 1px solid #cbd5e1; margin-left: 8px; }
    .btn-quick-nav:hover .hotkey-badge { border-color: #d4af37; color: #0f172a; }
    .btn-quick-nav.accent-red .hotkey-badge { background: rgba(255, 255, 255, 0.25); border-color: rgba(255, 255, 255, 0.4); color: #ffffff; }

    /* Next-Level Structural Audit Tables UI */
    .table-box { background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); overflow: hidden; margin-top: 20px; }
    .table-header { padding: 20px; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; }
    .table-header h3 { margin: 0; font-size: 15px; font-weight: 700; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; }
    
    table.modern-dashboard-grid { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; table-layout: fixed; }
    
    /* Fixed structural column alignments matching ledger blueprints with Action column support */
    table.modern-dashboard-grid th:nth-child(1), table.modern-dashboard-grid td:nth-child(1) { width: 10%; } /* Date */
    table.modern-dashboard-grid th:nth-child(2), table.modern-dashboard-grid td:nth-child(2) { width: 20%; } /* Particulars */
    table.modern-dashboard-grid th:nth-child(3), table.modern-dashboard-grid td:nth-child(3) { width: 10%; } /* Vch No */
    table.modern-dashboard-grid th:nth-child(4), table.modern-dashboard-grid td:nth-child(4) { width: 12%; } /* Gst No */
    table.modern-dashboard-grid th:nth-child(5), table.modern-dashboard-grid td:nth-child(5) { width: 8%; } /* Hsncode */
    table.modern-dashboard-grid th:nth-child(6), table.modern-dashboard-grid td:nth-child(6) { width: 12%; } /* Gross Total */
    table.modern-dashboard-grid th:nth-child(7), table.modern-dashboard-grid td:nth-child(7) { width: 9%; } /* Cgst */
    table.modern-dashboard-grid th:nth-child(8), table.modern-dashboard-grid td:nth-child(8) { width: 9%; } /* Sgst */
    table.modern-dashboard-grid th:nth-child(9), table.modern-dashboard-grid td:nth-child(9) { width: 10%; } /* Actions */
    
    table.modern-dashboard-grid th { background: #f8fafc; padding: 14px 12px; font-weight: 600; font-size: 12px; color: #475569; text-transform: uppercase; border-bottom: 1px solid #edf2f7; letter-spacing: 0.3px; }
    table.modern-dashboard-grid td { padding: 14px 12px; border-top: 1px solid #edf2f7; color: #334155; word-wrap: break-word; vertical-align: middle; }
    table.modern-dashboard-grid tr:hover { background: #f8fafc; }
    
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    
    .badge-vch-ui { background: #f1f5f9; color: #1e293b; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-weight: 600; font-size: 12px; display: inline-block; }
    .badge-hsn-ui { background: #e0f2fe; color: #0369a1; padding: 3px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; }
    
    .action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 6px; font-size: 13px; margin: 0 2px; transition: all 0.2s ease; text-decoration: none; box-sizing: border-box; }
    .action-btn.btn-print { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .action-btn.btn-print:hover { background: #0284c7; color: white; border-color: #0284c7; }
    .action-btn.btn-edit { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .action-btn.btn-edit:hover { background: #d97706; color: white; border-color: #d97706; }
</style>
</head>
<body>

<div class="container">

    <?php 
    $active_page = 'dashboard'; 
    include 'includes/sidebar.php'; 
    ?>

    <main class="main">

        <div class="dashboard-header-container" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; gap: 20px; flex-wrap: wrap;">
            <div class="header">
                <h1 class="dashboard-hero-title">CHANDRAKALA JEWELLERS</h1>
                <p class="dashboard-sub-desc">Owned and managed by Hasmukh Soni</p>
            </div>
            
            <!-- Live Metal Rates Widget -->
            <div class="metal-rates-widget" id="metalRatesWidget" style="background: #ffffff; border: 1.5px solid rgba(212, 175, 55, 0.3); padding: 12px 18px; border-radius: 12px; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 12px rgba(212, 175, 55, 0.05); margin-top: 5px;">
                <div class="rate-icon" style="color: #d4af37; font-size: 20px;"><i class="fas fa-coins"></i></div>
                <div style="display: flex; gap: 15px; font-size: 13px; font-weight: 600; color: #334155;">
                    <div style="border-right: 1px solid #e2e8f0; padding-right: 15px;">
                        <span style="color: #64748b; font-size: 10px; display: block; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px;">Gold 24K</span>
                        <span id="rateGold24" style="color: #0f172a; font-size: 14px; font-weight: 700;">₹ 7,350/g</span>
                    </div>
                    <div style="border-right: 1px solid #e2e8f0; padding-right: 15px;">
                        <span style="color: #64748b; font-size: 10px; display: block; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px;">Gold 22K</span>
                        <span id="rateGold22" style="color: #0f172a; font-size: 14px; font-weight: 700;">₹ 6,735/g</span>
                    </div>
                    <div>
                        <span style="color: #64748b; font-size: 10px; display: block; text-transform: uppercase; font-weight: 700; letter-spacing: 0.3px;">Silver</span>
                        <span id="rateSilver" style="color: #0f172a; font-size: 14px; font-weight: 700;">₹ 90/g</span>
                    </div>
                </div>
                <button onclick="editMetalRates()" style="background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s ease;">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
        </div>

        <div class="quick-actions-bar" style="border: 1.5px solid rgba(212, 175, 55, 0.18); background: linear-gradient(135deg, #ffffff 0%, #fffdf9 100%);">
            <div class="action-bar-title"><i class="fas fa-bolt" style="color: #d4af37;"></i> Terminal Quick Links:</div>
            <a href="billing/sale_bill.php" class="btn-quick-nav" style="border-color: rgba(212, 175, 55, 0.3);"><i class="fas fa-plus" style="color: #d4af37;"></i> Create New Invoice <span class="hotkey-badge">N</span></a>
            <a href="reports/daily_report.php" class="btn-quick-nav"><i class="fas fa-file-invoice-dollar" style="color: #64748b;"></i> View Sales Register Ledger <span class="hotkey-badge">S</span></a>
            <a href="print_qr.php" class="btn-quick-nav accent-red"><i class="fas fa-qrcode"></i> Reverse Side QR Print <span class="hotkey-badge">Q</span></a>
        </div>

        <div class="cards">
            <div class="card" style="border-left: 4px solid #64748b;">
                <h3>Total Cumulative Bills</h3>
                <h2><?php echo $totalBills; ?></h2>
                <div class="icon-decorator"><i class="fas fa-folder-open"></i></div>
            </div>
            <div class="card" style="border-left: 4px solid #2563eb;">
                <h3>Total Lifetime Revenue</h3>
                <h2>₹ <?php echo number_format($totalSales, 2); ?></h2>
                <div class="icon-decorator"><i class="fas fa-chart-line"></i></div>
            </div>
            <div class="card" style="border-left: 4px solid #059669;">
                <h3>Today's Invoice Vol</h3>
                <h2><?php echo $todayBills; ?></h2>
                <div class="icon-decorator"><i class="fas fa-receipt"></i></div>
            </div>
            <div class="card" style="border-left: 4px solid #d97706;">
                <h3>Today's Mean Value Size</h3>
                <h2>₹ <?php echo number_format($averageTicket, 2); ?></h2>
                <div class="icon-decorator"><i class="fas fa-calculator"></i></div>
            </div>
        </div>

        <div class="table-box">
            <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <h3>Recent Transactions Audit Trail</h3>
                <div class="search-wrapper" style="position: relative; display: flex; align-items: center;">
                    <i class="fas fa-search" style="position: absolute; left: 12px; color: #94a3b8; font-size: 13px;"></i>
                    <input type="text" id="billSearchInput" placeholder="Search transactions... [F]" onkeyup="filterTable()" style="padding: 8px 12px 8px 32px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; outline: none; width: 230px; transition: all 0.2s ease; font-weight: 500; font-family: inherit;">
                </div>
            </div>
            <table class="modern-dashboard-grid">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Particulars</th>
                        <th class="text-center">Vch No.</th>
                        <th>Gst No.</th>
                        <th class="text-center">Hsncode</th>
                        <th class="text-right">Gross Total</th>
                        <th class="text-right">Cgst@1.50%</th>
                        <th class="text-right">Sgst@1.50%</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Fixed query: sorted by bill_date and id in ASCENDING order; LIMIT removed to show full history
                $query = mysqli_query(
                    $conn,
                    "SELECT b.id, b.bill_date, b.customer_name, b.bill_no, b.cgst, b.sgst, b.net_amount, COALESCE(b.gst_no, '') as gst_no, b.bill_type,
                            GROUP_CONCAT(DISTINCT bi.hsn_code SEPARATOR ', ') as dynamic_hsn 
                     FROM bills b 
                     LEFT JOIN bill_items bi ON b.id = bi.bill_id 
                     GROUP BY b.id 
                     ORDER BY b.bill_date ASC, b.id ASC"
                );

                if (mysqli_num_rows($query) > 0) {
                    while($row = mysqli_fetch_assoc($query)) {
                        $date_formatted = date('d/m/Y', strtotime($row['bill_date'])); 
                        $particulars = (!empty($row['customer_name'])) ? $row['customer_name'] : 'Retail sale';
                        
                        $gross = floatval($row['net_amount']); 
                        $cgst  = floatval($row['cgst']);
                        $sgst  = floatval($row['sgst']);
                        
                        // Safe dynamic output mapping with default fallback indicator if item rows are absent
                        $hsn_display = !empty($row['dynamic_hsn']) ? $row['dynamic_hsn'] : '7113';
                        
                        // Dynamic action URL mapping based on sale/labour bill type
                        $is_labour = (isset($row['bill_type']) && $row['bill_type'] === 'LABOUR');
                        $print_url = $is_labour ? 'billing/labour_print_bill.php?id=' . $row['id'] : 'billing/print_bill.php?id=' . $row['id'];
                        $edit_url = $is_labour ? 'billing/labour_bill.php?edit=' . $row['id'] : 'billing/sale_bill.php?edit=' . $row['id'];
                ?>
                    <tr>
                        <td><?= $date_formatted; ?></td>
                        <td><strong><?= htmlspecialchars($particulars); ?></strong></td>
                        <td class="text-center"><span class="badge-vch-ui"><?= htmlspecialchars($row['bill_no']); ?></span></td>
                        <td><?= htmlspecialchars($row['gst_no']); ?></td>
                        <td class="text-center"><span class="badge-hsn-ui"><?= htmlspecialchars($hsn_display); ?></span></td>
                        <td class="text-right" style="font-weight: 600;">₹ <?= number_format($gross, 2); ?></td>
                        <td class="text-right" style="color: #475569;">₹ <?= number_format($cgst, 2); ?></td>
                        <td class="text-right" style="color: #475569;">₹ <?= number_format($sgst, 2); ?></td>
                        <td class="text-center">
                            <a href="<?= htmlspecialchars($print_url); ?>" class="action-btn btn-print" title="Print Invoice" target="_blank">
                                <i class="fas fa-print"></i>
                            </a>
                            <a href="<?= htmlspecialchars($edit_url); ?>" class="action-btn btn-edit" title="Edit Invoice">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                <?php 
                    }
                } else {
                    echo "<tr><td colspan='9' style='text-align:center; padding: 30px; color:#94a3b8; font-weight: 500;'>No operational ledger accounts logged yet.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>

    </main>

</div>

<script>
    // Live Metal Rates persistence system (loads on page load)
    function initMetalRates() {
        let gold24 = localStorage.getItem('rates_gold24') || '7,350';
        let gold22 = localStorage.getItem('rates_gold22') || '6,735';
        let silver = localStorage.getItem('rates_silver') || '90';

        document.getElementById('rateGold24').innerText = '₹ ' + gold24 + '/g';
        document.getElementById('rateGold22').innerText = '₹ ' + gold22 + '/g';
        document.getElementById('rateSilver').innerText = '₹ ' + silver + '/g';
    }

    function editMetalRates() {
        let currentGold24 = localStorage.getItem('rates_gold24') || '7,350';
        let currentGold22 = localStorage.getItem('rates_gold22') || '6,735';
        let currentSilver = localStorage.getItem('rates_silver') || '90';

        let newGold24 = prompt("Enter Gold 24K rate per gram (e.g. 7,350):", currentGold24);
        if (newGold24 === null) return;
        let newGold22 = prompt("Enter Gold 22K rate per gram (e.g. 6,735):", currentGold22);
        if (newGold22 === null) return;
        let newSilver = prompt("Enter Silver rate per gram (e.g. 90):", currentSilver);
        if (newSilver === null) return;

        localStorage.setItem('rates_gold24', newGold24);
        localStorage.setItem('rates_gold22', newGold22);
        localStorage.setItem('rates_silver', newSilver);

        initMetalRates();
    }

    // Keyboard billing shortcuts
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ignore shortcut if user is actively entering text/fields
            if (document.activeElement.tagName === 'INPUT' || 
                document.activeElement.tagName === 'TEXTAREA' || 
                document.activeElement.tagName === 'SELECT') {
                return;
            }
            
            const key = e.key.toLowerCase();
            if (key === 'n') {
                e.preventDefault();
                window.location.href = 'billing/sale_bill.php';
            } else if (key === 's') {
                e.preventDefault();
                window.location.href = 'reports/daily_report.php';
            } else if (key === 'q') {
                e.preventDefault();
                window.location.href = 'print_qr.php';
            } else if (key === 'f') {
                e.preventDefault();
                const searchEl = document.getElementById('billSearchInput');
                if (searchEl) {
                    searchEl.focus();
                    searchEl.select();
                }
            }
        });
    }

    // Instant client-side transaction table query filter
    function filterTable() {
        const input = document.getElementById('billSearchInput');
        const filter = input.value.toLowerCase();
        const table = document.querySelector('.modern-dashboard-grid');
        const rows = table.getElementsByTagName('tr');

        // Loop through all table rows, skip the header (index 0)
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const dateCell = row.cells[0];
            const nameCell = row.cells[1];
            const vchCell = row.cells[2];
            const gstCell = row.cells[3];
            const hsnCell = row.cells[4];
            const amountCell = row.cells[5];

            if (dateCell || nameCell || vchCell || gstCell) {
                const dateText = dateCell ? dateCell.innerText.toLowerCase() : '';
                const nameText = nameCell ? nameCell.innerText.toLowerCase() : '';
                const vchText = vchCell ? vchCell.innerText.toLowerCase() : '';
                const gstText = gstCell ? gstCell.innerText.toLowerCase() : '';
                const hsnText = hsnCell ? hsnCell.innerText.toLowerCase() : '';
                const amountText = amountCell ? amountCell.innerText.toLowerCase() : '';

                if (
                    dateText.indexOf(filter) > -1 ||
                    nameText.indexOf(filter) > -1 ||
                    vchText.indexOf(filter) > -1 ||
                    gstText.indexOf(filter) > -1 ||
                    hsnText.indexOf(filter) > -1 ||
                    amountText.indexOf(filter) > -1
                ) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        initMetalRates();
        initKeyboardShortcuts();
    });
</script>
</body>
</html>