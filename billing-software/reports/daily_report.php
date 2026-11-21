<?php
include '../includes/db.php';
date_default_timezone_set('Asia/Kolkata');

$filter_type   = $_GET['filter_type'] ?? 'daily';
$selected_date = $_GET['report_date'] ?? date('Y-m-d');
$start_date    = $_GET['start_date']  ?? date('Y-m-d');
$end_date      = $_GET['end_date']    ?? date('Y-m-d');
$target_month  = $_GET['target_month'] ?? date('Y-m');
$target_year   = $_GET['target_year']  ?? date('Y');

// Strict Date Validation
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
function validate_month($date) {
    return validate_date($date . '-01', 'Y-m-d');
}

if (!validate_date($selected_date)) { $selected_date = date('Y-m-d'); }
if (!validate_date($start_date))    { $start_date = date('Y-m-d'); }
if (!validate_date($end_date))      { $end_date = date('Y-m-d'); }
if (!validate_month($target_month))  { $target_month = date('Y-m'); }
if (!preg_match('/^\d{4}$/', $target_year)) { $target_year = date('Y'); }

// Dynamic Date Range Logic for UI Screen and Print Output Header
$p_start = '';
$p_end = '';
$print_date_label = '';

if ($filter_type === 'custom') {
    $p_start = $start_date;
    $p_end = $end_date;
    $scope_title = "Statement Summary Range: " . date('d/m/Y', strtotime($start_date)) . " to " . date('d/m/Y', strtotime($end_date));
    $print_date_label = "Date Range: " . date('d/m/Y', strtotime($start_date)) . " to " . date('d/m/Y', strtotime($end_date));
} elseif ($filter_type === 'monthly') {
    $p_start = $target_month . "-01";
    $p_end = date('Y-m-t', strtotime($p_start));
    $scope_title = "Statement Summary for Month: " . date('F Y', strtotime($p_start));
    $print_date_label = "Date Range: " . date('01/m/Y', strtotime($p_start)) . " to " . date('t/m/Y', strtotime($p_start));
} elseif ($filter_type === 'yearly') {
    $next_year = intval($target_year) + 1;
    $p_start = "{$target_year}-04-01";
    $p_end = "{$next_year}-03-31";
    $scope_title = "Financial Year Statement representation: FY " . $target_year . "-" . substr($next_year, 2);
    $print_date_label = "Date Range: 01/04/{$target_year} to 31/03/{$next_year}";
} else {
    $filter_type = 'daily';
    $p_start = $selected_date;
    $p_end = $selected_date;
    $scope_title = "Statement Summary for Date: " . date('d/m/Y', strtotime($selected_date));
    $print_date_label = "Statement Date: " . date('d/m/Y', strtotime($selected_date));
}

// Cancelled Logic Check
$sql_cond = "WHERE b.bill_date BETWEEN ? AND ? AND COALESCE(UPPER(b.status),'') <> 'CANCELLED'";

// Summary Query
$summary_query = "SELECT 
                    COUNT(*) as total_bills,
                    SUM(b.subtotal) as gross_subtotal,
                    SUM(b.discount) as total_discount,
                    SUM(b.cgst) as total_cgst,
                    SUM(b.sgst) as total_sgst,
                    SUM(b.net_amount) as total_net,
                    SUM(b.cash_received) as total_cash,
                    SUM(b.bank_received) as total_bank,
                    SUM(b.old_gold_amount) as total_old_gold,
                    SUM(b.balance_amount) as total_balance,
                    SUM(CASE WHEN b.bill_type = 'SALE' THEN b.net_amount ELSE 0 END) as sale_revenue,
                    SUM(CASE WHEN b.bill_type = 'LABOUR' THEN b.net_amount ELSE 0 END) as labour_revenue,
                    SUM(CASE WHEN b.bill_type = 'LABOUR' THEN b.gold_credit ELSE 0 END) as total_gold_credit
                  FROM bills b
                  $sql_cond";

$stmt1 = $conn->prepare($summary_query);
if (!$stmt1) { die("DB Error: " . $conn->error); }
$stmt1->bind_param("ss", $p_start, $p_end);
$stmt1->execute();
$summary_res = $stmt1->get_result();
$metrics = $summary_res->fetch_assoc();

$total_bills = $metrics['total_bills'] ?? 0;
$gross_subtotal = $metrics['gross_subtotal'] ?? 0;
$total_discount = $metrics['total_discount'] ?? 0;
$combined_gst = ($metrics['total_cgst'] ?? 0) + ($metrics['total_sgst'] ?? 0);
$total_net = $metrics['total_net'] ?? 0;
$total_cash = $metrics['total_cash'] ?? 0;
$total_bank = $metrics['total_bank'] ?? 0;
$total_old_gold = $metrics['total_old_gold'] ?? 0;
$total_balance = $metrics['total_balance'] ?? 0;
$sale_revenue = $metrics['sale_revenue'] ?? 0;
$labour_revenue = $metrics['labour_revenue'] ?? 0;
$total_gold_credit = $metrics['total_gold_credit'] ?? 0;

// Gold/Silver Classification
$metal_query = "SELECT 
                    SUM(CASE WHEN LOWER(bi.item_metal_type) = 'gold' THEN bi.quantity ELSE 0 END) as gold_weight,
                    SUM(CASE WHEN LOWER(bi.item_metal_type) = 'silver' THEN bi.quantity ELSE 0 END) as silver_weight
                 FROM bill_items bi
                 JOIN bills b ON bi.bill_id = b.id
                 $sql_cond";

$stmt2 = $conn->prepare($metal_query);
if (!$stmt2) { die("DB Error: " . $conn->error); }
$stmt2->bind_param("ss", $p_start, $p_end);
$stmt2->execute();
$metal_res = $stmt2->get_result();

$gold_weight_sold = 0;
$silver_weight_sold = 0;
if ($metal_res && $metal_metrics = $metal_res->fetch_assoc()) {
    $gold_weight_sold = $metal_metrics['gold_weight'] ?? 0;
    $silver_weight_sold = $metal_metrics['silver_weight'] ?? 0;
}

$average_ticket_size = ($total_bills > 0) ? ($total_net / $total_bills) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Business Statements - Chandrakala Jewellers</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body { background: #f5f6fa; margin: 0; font-family: 'Segoe UI', sans-serif; }
    .container { display: flex; width: 100%; }
    .main { flex: 1; padding: 25px; min-width: 0; }
    .extended-filter-box { background: #ffffff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 25px; }
    .tab-row { display: flex; gap: 10px; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
    .tab-btn { padding: 8px 16px; background: #e2e8f0; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; color: #475569; text-decoration:none; font-size:13px; }
    .tab-btn.active { background: #111827; color: white; }
    
    .dashboard-section-header { font-size: 14px; font-weight: 700; color: #475569; margin: 20px 0 10px 0; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px; }
    .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 16px; }
    .report-card { background: white; padding: 18px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); border-left: 4px solid #111827; }
    .report-card h4 { margin: 0 0 6px 0; color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.3px; }
    .report-card h2 { margin: 0; color: #1e293b; font-size: 22px; font-weight: 700; }
    .report-card .sub-text { margin-top: 4px; font-size: 11px; color: #94a3b8; }
    
    .table-box-modern { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); width: 100%; overflow: hidden; border: 1px solid #e2e8f0; margin-top: 15px; }
    .print-metadata-header { display: none; }
    
    table.modern-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; table-layout: fixed; }
    
    table.modern-table th:nth-child(1), table.modern-table td:nth-child(1) { width: 11%; } 
    table.modern-table th:nth-child(2), table.modern-table td:nth-child(2) { width: 18%; } 
    table.modern-table th:nth-child(3), table.modern-table td:nth-child(3) { width: 11%; } 
    table.modern-table th:nth-child(4), table.modern-table td:nth-child(4) { width: 12%; } 
    table.modern-table th:nth-child(5), table.modern-table td:nth-child(5) { width: 10%; } 
    table.modern-table th:nth-child(6), table.modern-table td:nth-child(6) { width: 13%; } 
    table.modern-table th:nth-child(7), table.modern-table td:nth-child(7) { width: 11%; } 
    table.modern-table th:nth-child(8), table.modern-table td:nth-child(8) { width: 11%; } 
    table.modern-table th:nth-child(9), table.modern-table td:nth-child(9) { width: 13%; } 

    table.modern-table th { background: #f8fafc; padding: 14px 12px; font-weight: 600; font-size: 12px; color: #475569; text-transform: uppercase; border-bottom: 2px solid #edf2f7; letter-spacing: 0.5px; word-wrap: break-word; }
    table.modern-table td { padding: 14px 12px; border-top: 1px solid #edf2f7; color: #334155; word-wrap: break-word; vertical-align: middle; }
    table.modern-table tr:hover { background: #f8fafc; transition: background 0.2s ease-in-out; }
    
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    
    .badge-vch { background: #f1f5f9; color: #1e293b; padding: 4px 8px; border-radius: 6px; font-family: monospace; font-weight: 600; font-size: 12px; display: inline-block; white-space: nowrap; }
    .badge-hsn { background: #e0f2fe; color: #0369a1; padding: 3px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; }
    table.modern-table tr.grand-total-footer td { background: #f8fafc; border-top: 2px solid #cbd5e1; font-weight: 700; color: #0f172a; font-size: 13px; padding: 16px 12px; }

    @media print {
        @page { 
            size: A4 portrait; 
            margin: 10mm 8mm 10mm 8mm; 
        }
        html, body { background: #fff; font-size: 10px; width: 100%; color: #000; }
        
        /* Hide UI elements */
        .sidebar, .extended-filter-box, .tab-row, h2, h3, .dashboard-section-header, .report-grid, button, i { 
            display: none !important; 
        }
        
        .container { display: block; width: 100%; }
        .main { padding: 0; margin: 0; width: 100%; }
        
        /* Print Header[cite: 1] */
        .print-metadata-header { 
            display: flex !important; 
            justify-content: space-between; 
            align-items: flex-end; 
            width: 100%; 
            margin-bottom: 12px; 
            border-bottom: 2px solid #000; 
            padding-bottom: 5px; 
        }
        .print-meta-left { font-size: 12px; font-weight: bold; color: #000; font-family: Arial, sans-serif; text-transform: uppercase; }
        .print-meta-right { font-size: 11px; font-weight: bold; color: #000; font-family: Arial, sans-serif; }
        
        /* FIX: Consistent solid grid layout all over the table */
        .table-box-modern { 
            border: none; /* Let the table element handle outer borders cleanly */
            box-shadow: none; 
            border-radius: 0; 
            overflow: visible; 
            width: 100%; 
        }
        table.modern-table { 
            width: 100%; 
            table-layout: fixed; 
            page-break-inside: auto; 
            font-size: 9px; 
            border-collapse: collapse !important; /* Forces cells to share borders tightly */
            border: 1px solid #000000 !important; /* Uniform outer table border */
        }
        table.modern-table tr { page-break-inside: avoid; page-break-after: auto; }
        
        /* Recalculate Column Widths so 9 columns fit horizontally on A4 portrait */
        table.modern-table th:nth-child(1), table.modern-table td:nth-child(1) { width: 10%; } /* Date */
        table.modern-table th:nth-child(2), table.modern-table td:nth-child(2) { width: 16%; } /* Particulars */
        table.modern-table th:nth-child(3), table.modern-table td:nth-child(3) { width: 8%; }  /* Vch No. */
        table.modern-table th:nth-child(4), table.modern-table td:nth-child(4) { width: 12%; } /* Gst No. */
        table.modern-table th:nth-child(5), table.modern-table td:nth-child(5) { width: 8%; }  /* Hsncode */
        table.modern-table th:nth-child(6), table.modern-table td:nth-child(6) { width: 12%; } /* Gross Total */
        table.modern-table th:nth-child(7), table.modern-table td:nth-child(7) { width: 11%; } /* Cgst */
        table.modern-table th:nth-child(8), table.modern-table td:nth-child(8) { width: 11%; } /* Sgst */
        table.modern-table th:nth-child(9), table.modern-table td:nth-child(9) { width: 12%; } /* Sales */

        /* FIX: Apply explicit, uniform black solid borders to every single cell */
        table.modern-table th { 
            background: #f1f5f9 !important; 
            color: #000 !important; 
            padding: 6px 4px; 
            font-size: 9px; 
            border: 1px solid #000000 !important; 
            text-transform: uppercase; 
        }
        table.modern-table td { 
            padding: 6px 4px; 
            font-size: 9px; 
            border: 1px solid #000000 !important; /* Uniform solid dark gridline */
            color: #000 !important; 
            word-wrap: break-word; 
        }
        
        .badge-vch { background: transparent; padding: 0; font-size: 9px; font-weight: normal; }
        .badge-hsn { background: transparent; padding: 0; font-size: 9px; color: #000 !important; font-weight: normal; }
        
        /* FIX: Retain clean grid layout rules inside the grand total row */
        table.modern-table tr.grand-total-footer td { 
            border-top: 2px solid #000000 !important; 
            border-bottom: 4px double #000000 !important; 
            background: transparent !important; 
            color: #000 !important; 
            font-size: 10px; 
            font-weight: bold; 
        }
    }
</style>
</head>
<body>

<div class="container">
    <?php $active_page = 'reports'; include '../includes/sidebar.php'; ?>

    <main class="main">
        <h2><i class="fas fa-chart-pie" style="color:#2563eb; margin-right:6px;"></i> Chandrakala Jewellers Report board</h2>

        <div class="extended-filter-box">
            <div class="tab-row">
                <a href="daily_report.php?filter_type=daily" class="tab-btn <?= $filter_type === 'daily' ? 'active' : '' ?>">Daily Statement</a>
                <a href="daily_report.php?filter_type=custom" class="tab-btn <?= $filter_type === 'custom' ? 'active' : '' ?>">Custom Range</a>
                <a href="daily_report.php?filter_type=monthly" class="tab-btn <?= $filter_type === 'monthly' ? 'active' : '' ?>">Monthly Ledger</a>
                <a href="daily_report.php?filter_type=yearly" class="tab-btn <?= $filter_type === 'yearly' ? 'active' : '' ?>">Financial Year (FY)</a>
            </div>

            <form method="GET" action="daily_report.php">
                <input type="hidden" name="filter_type" value="<?= htmlspecialchars($filter_type); ?>">
                
                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <?php if ($filter_type === 'daily'): ?>
                        <label>Target Statement Date:</label>
                        <input type="date" name="report_date" value="<?= htmlspecialchars($selected_date); ?>" style="padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                    <?php endif; ?>

                    <?php if ($filter_type === 'custom'): ?>
                        <label>Start Date:</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date); ?>" style="padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                        <label>End Date:</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date); ?>" style="padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                    <?php endif; ?>

                    <?php if ($filter_type === 'monthly'): ?>
                        <label>Select Operating Month:</label>
                        <input type="month" name="target_month" value="<?= htmlspecialchars($target_month); ?>" style="padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                    <?php endif; ?>

                    <?php if ($filter_type === 'yearly'): ?>
                        <label>Select Financial Year:</label>
                        <select name="target_year" style="padding:8px; border-radius:6px; border:1px solid #cbd5e1;">
                            <?php for($y = date('Y'); $y >= 2022; $y--): ?>
                                <option value="<?= $y ?>" <?= strval($target_year) === strval($y) ? 'selected' : '' ?>>FY <?= $y ?> - <?= ($y+1) ?></option>
                            <?php endfor; ?>
                        </select>
                    <?php endif; ?>

                    <button type="submit" style="padding: 9px 20px; background:#111827; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer;">Generate Layout</button>
                    <button type="button" onclick="window.print()" style="padding: 9px 20px; background:#2563eb; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer; margin-left:auto;"><i class="fas fa-print"></i> Print Audit Copy</button>
                    <a href="export_daily_report_excel.php?<?= http_build_query($_GET) ?>" style="padding: 9px 20px; background:#16a34a; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; margin-left:12px;">
                        <i class="fas fa-file-excel" style="margin-right:6px;"></i> Export to Excel
                    </a>
                </div>
            </form>
        </div>

        <h3 style="color:#1e293b; margin-bottom:15px; font-weight:600; border-bottom:1px solid #cbd5e1; padding-bottom:8px; font-size:16px;"><i class="far fa-calendar-alt" style="color:#64748b; margin-right:5px;"></i> <?= htmlspecialchars($scope_title); ?></h3>

        <div class="dashboard-section-header"><i class="fas fa-weight-hanging" style="color:#d97706;"></i> Physical Inventory</div>
        <div class="report-grid">
            <div class="report-card" style="border-left-color:#d97706;">
                <h4>Gold Stock Released</h4>
                <h2><?= number_format($gold_weight_sold, 3); ?> Gms</h2>
                <div class="sub-text">Total outward jewelry mass sold</div>
            </div>
            <div class="report-card" style="border-left-color:#94a3b8;">
                <h4>Silver Stock Released</h4>
                <h2><?= number_format($silver_weight_sold, 3); ?> Gms</h2>
                <div class="sub-text">Total outward silver commodities</div>
            </div>
            <div class="report-card" style="border-left-color:#10b981;">
                <h4>Gold Metal Deposits</h4>
                <h2><?= number_format($total_gold_credit, 3); ?> Gms</h2>
                <div class="sub-text">Raw weight banking credit deposits</div>
            </div>
            <div class="report-card" style="border-left-color:#4f46e5;">
                <h4>Average Ticket Size</h4>
                <h2>₹ <?= number_format($average_ticket_size, 2); ?></h2>
                <div class="sub-text">Average transaction value mean</div>
            </div>
        </div>

        <div class="dashboard-section-header"><i class="fas fa-wallet" style="color:#2563eb;"></i>Breakdown</div>
        <div class="report-grid">
            <div class="report-card" style="border-left-color:#0284c7;">
                <h4>Retail Showroom Sales</h4>
                <h2>₹ <?= number_format($sale_revenue, 2); ?></h2>
            </div>
            <div class="report-card" style="border-left-color:#7c3aed;">
                <h4>Labour / Repair Income</h4>
                <h2>₹ <?= number_format($labour_revenue, 2); ?></h2>
            </div>
            <div class="report-card" style="border-left-color:#10b981;">
                <h4>Cash Liquidity Inflow</h4>
                <h2>₹ <?= number_format($total_cash, 2); ?></h2>
            </div>
            <div class="report-card" style="border-left-color:#2563eb;">
                <h4>Bank / UPI Clearing</h4>
                <h2>₹ <?= number_format($total_bank, 2); ?></h2>
            </div>
        </div>
        <div class="report-grid">
            <div class="report-card" style="border-left-color:#f59e0b;">
                <h4>Old Metal Exchange Val</h4>
                <h2>₹ <?= number_format($total_old_gold, 2); ?></h2>
            </div>
            <div class="report-card" style="border-left-color:#ef4444;">
                <h4>Outstanding Book Debt</h4>
                <h2>₹ <?= number_format($total_balance, 2); ?></h2>
            </div>
            <div class="report-card" style="border-left-color:#64748b;">
                <h4>GST Collected</h4>
                <h2>₹ <?= number_format($combined_gst, 2); ?></h2>
            </div>
            <div class="report-card" style="border-left-color:#111827; background:#fafafa;">
                <h4>Net Consolidated Turnover</h4>
                <h2 style="color:#2563eb;">₹ <?= number_format($total_net, 2); ?></h2>
            </div>
        </div>

        <div class="dashboard-section-header"><i class="fas fa-receipt" style="color:#64748b;"></i> Transaction Registry Audit Trails</div>
        
        <div class="table-box-modern">
            <div class="print-metadata-header">
    <div class="print-meta-left">Ledger: Chandrakala Jewellers</div>
    <div class="print-meta-right"><?= htmlspecialchars($print_date_label); ?></div>
</div>
            
            <table class="modern-table">
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
                        <th class="text-right">Sales</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Correlated subquery for dynamic HSN codes (immune to ONLY_FULL_GROUP_BY)
                $log_query = "SELECT 
                b.id, b.bill_no, b.bill_date, b.customer_name, b.mobile, b.bill_type, b.subtotal, b.cgst, b.sgst, b.net_amount, COALESCE(b.gst_no, '') as gst_no,
                (SELECT GROUP_CONCAT(DISTINCT bi.hsn_code SEPARATOR ', ') FROM bill_items bi WHERE bi.bill_id = b.id) as dynamic_hsn
              FROM bills b 
              $sql_cond 
              ORDER BY b.bill_date ASC, b.id ASC";
                
                $stmt3 = $conn->prepare($log_query);
                
                $t_gross = 0;
                $t_cgst  = 0;
                $t_sgst  = 0;
                $t_sales = 0;
                $row_count = 0;

                if ($stmt3) {
                    $stmt3->bind_param("ss", $p_start, $p_end);
                    $stmt3->execute();
                    $log_res = $stmt3->get_result();

                    if ($log_res && $log_res->num_rows > 0) {
                        while($row = $log_res->fetch_assoc()) {
                            $row_count++;
                            
                            $date_formatted = date('d/m/Y', strtotime($row['bill_date'])); 
                            $particulars = (!empty($row['customer_name'])) ? $row['customer_name'] : 'Retail sale';
                            
                            // Check if bill has dynamic codes; provide fallback if none are declared
                            $hsn_display = !empty($row['dynamic_hsn']) ? $row['dynamic_hsn'] : '7113';
                            
                            $gross  = floatval($row['net_amount']); 
                            $cgst   = floatval($row['cgst']);
                            $sgst   = floatval($row['sgst']);
                            $sales  = $gross - ($cgst + $sgst);
                            
                            $t_gross += $gross;
                            $t_cgst  += $cgst;
                            $t_sgst  += $sgst;
                            $t_sales += $sales;

                            echo "<tr>
                                <td>".$date_formatted."</td>
                                <td><strong>".htmlspecialchars($particulars)."</strong></td>
                                <td class='text-center'><span class='badge-vch'>".htmlspecialchars($row['bill_no'])."</span></td>
                                <td>".htmlspecialchars($row['gst_no'])."</td>
                                <td class='text-center'><span class='badge-hsn'>".htmlspecialchars($hsn_display)."</span></td>
                                <td class='text-right' style='font-weight:600;'>₹ ".number_format($gross, 2)."</td>
                                <td class='text-right' style='color:#475569;'>₹ ".number_format($cgst, 2)."</td>
                                <td class='text-right' style='color:#475569;'>₹ ".number_format($sgst, 2)."</td>
                                <td class='text-right' style='font-weight:600; color:#2563eb;'>₹ ".number_format($sales, 2)."</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9' style='text-align:center; padding:35px; color:#94a3b8; font-weight:500;'>No operations compiled inside this specific operational scope range filter.</td></tr>";
                    }
                }
                
                if ($row_count > 0) {
                    echo "<tr class='grand-total-footer'>
                            <td colspan='5' class='text-right'>Summary Grand Total:</td>
                            <td class='text-right'>₹ ".number_format($t_gross, 2)."</td>
                            <td class='text-right'>₹ ".number_format($t_cgst, 2)."</td>
                            <td class='text-right'>₹ ".number_format($t_sgst, 2)."</td>
                            <td class='text-right' style='color:#2563eb;'>₹ ".number_format($t_sales, 2)."</td>
                          </tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>