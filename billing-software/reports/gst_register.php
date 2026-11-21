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

$p_start = '';
$p_end = '';

if ($filter_type === 'custom') {
    $p_start = $start_date;
    $p_end = $end_date;
    $scope_title = "1-Oct-2025 to 31-Oct-2025"; // Matches excel placeholder style format
    $scope_display = date('d-M-Y', strtotime($start_date)) . " to " . date('d-M-Y', strtotime($end_date));
} elseif ($filter_type === 'monthly') {
    $p_start = $target_month . "-01";
    $p_end = date('Y-m-t', strtotime($p_start));
    $scope_display = date('1-M-Y', strtotime($p_start)) . " to " . date('t-M-Y', strtotime($p_start));
} elseif ($filter_type === 'yearly') {
    $next_year = intval($target_year) + 1;
    $p_start = "{$target_year}-04-01";
    $p_end = "{$next_year}-03-31";
    $scope_display = "01-Apr-{$target_year} to 31-Mar-{$next_year}";
} else {
    $filter_type = 'daily';
    $p_start = $selected_date;
    $p_end = $selected_date;
    $scope_display = date('d-M-Y', strtotime($selected_date));
}

$sql_cond = "WHERE b.bill_date BETWEEN ? AND ? AND COALESCE(UPPER(b.status),'') <> 'CANCELLED'";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sales Ledger - Chandrakala Jewellers</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body { background: #f5f6fa; margin: 0; font-family: 'Segoe UI', Arial, sans-serif; color: #333; }
    .container { display: flex; }
    .main { flex: 1; padding: 20px; }
    .extended-filter-box { background: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .tab-row { display: flex; gap: 10px; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
    .tab-btn { padding: 8px 16px; background: #e2e8f0; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; color: #475569; text-decoration:none; font-size:13px; }
    .tab-btn.active { background: #111827; color: white; }
    
    /* Excel Style Ledger Typography & Border Colors */
    .ledger-container { background: white; padding: 20px; border: 1px solid #c2c2c2; border-radius: 4px; overflow-x: auto; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
    .ledger-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 5px; }
    .ledger-title { font-size: 16px; font-weight: bold; color: #000; }
    .ledger-date-range { font-size: 14px; font-weight: bold; color: #333; }
    
    table.excel-ledger { width: 100%; border-collapse: collapse; font-size: 13px; font-family: 'Arial', sans-serif; }
    table.excel-ledger th { border: 1px solid #a0a0a0; background-color: #f2f2f2; padding: 6px 8px; font-weight: normal; color: #444; text-align: left; }
    table.excel-ledger td { border: 1px solid #d0d0d0; padding: 6px 8px; color: #111; vertical-align: middle; }
    table.excel-ledger tr:nth-child(even) { background-color: #fafafa; }
    table.excel-ledger tr:hover { background-color: #f1f5f9; }
    
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: bold; }
    
    /* Grand Total Row Styling */
    table.excel-ledger tr.total-row td { border-top: 1px solid #000; border-bottom: 2px double #000; font-weight: bold; background-color: #f8fafc; }

    @media print {
        .sidebar, .extended-filter-box, .tab-row, button { display: none !important; }
        .main { padding: 0; }
        body { background: white; }
        .ledger-container { border: none; padding: 0; }
    }
</style>
</head>
<body>

<div class="container">
    <?php $active_page = 'reports'; include '../includes/sidebar.php'; ?>

    <main class="main">
        <div class="extended-filter-box">
            <div class="tab-row">
                <a href="gst_register.php?filter_type=daily" class="tab-btn <?= $filter_type === 'daily' ? 'active' : '' ?>">Daily Statement</a>
                <a href="gst_register.php?filter_type=custom" class="tab-btn <?= $filter_type === 'custom' ? 'active' : '' ?>">Custom Range</a>
                <a href="gst_register.php?filter_type=monthly" class="tab-btn <?= $filter_type === 'monthly' ? 'active' : '' ?>">Monthly Ledger</a>
                <a href="gst_register.php?filter_type=yearly" class="tab-btn <?= $filter_type === 'yearly' ? 'active' : '' ?>">Financial Year (FY)</a>
            </div>

            <form method="GET" action="gst_register.php">
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
                </div>
            </form>
        </div>

        <div class="ledger-container">
            <div class="ledger-header">
                <div class="ledger-title">Ledger: Cgst@1.50% & Sgst@1.50% (Sales Register)</div>
                <div class="ledger-date-range"><?= htmlspecialchars($scope_display); ?></div>
            </div>

            <table class="excel-ledger">
                <thead>
                    <tr>
                        <th style="width: 10%;">Date</th>
                        <th style="width: 25%;">Particulars</th>
                        <th style="width: 8%;" class="text-center">Vch No.</th>
                        <th style="width: 12%;">GST No.</th> <th style="width: 9%;" class="text-center">Hsncode</th>
                        <th style="width: 11%;" class="text-right">Gross Total</th>
                        <th style="width: 8%;" class="text-right">Cgst@1.50%</th>
                        <th style="width: 8%;" class="text-right">Sgst@1.50%</th>
                        <th style="width: 9%;" class="text-right">Sales (Taxable)</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // Querying specific item breaks out of bill totals to grab distinct HSN parameters per item row if required. 
                // We join 'bill_items' to gather Hsncode accurately matching your reference sample spreadsheet rows.
                $log_query = "SELECT 
                                b.bill_date, 
                                b.customer_name, 
                                b.bill_type,
                                b.bill_no, 
                                COALESCE(b.gst_no, '') as gst_no, 
                                bi.hsn_code,
                                bi.item_total as gross_total,
                                bi.cgst_amount,
                                bi.sgst_amount,
                                (bi.item_total - bi.cgst_amount - bi.sgst_amount) as taxable_sales
                              FROM bills b 
                              LEFT JOIN bill_items bi ON b.id = bi.bill_id
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
                            
                            // Visual Presentation formatting rules matching Excel sheet
                            $date_formatted = date('j/n/Y', strtotime($row['bill_date'])); 
                            $particulars = (!empty($row['customer_name'])) ? $row['customer_name'] : 'Retail sale';
                            
                            $gross  = floatval($row['gross_total']);
                            $cgst   = floatval($row['cgst_amount']);
                            $sgst   = floatval($row['sgst_amount']);
                            $sales  = floatval($row['taxable_sales']);
                            
                            // Accumulators for Grand Total footer
                            $t_gross += $gross;
                            $t_cgst  += $cgst;
                            $t_sgst  += $sgst;
                            $t_sales += $sales;

                            echo "<tr>
                                <td>".$date_formatted."</td>
                                <td>".htmlspecialchars($particulars)."</td>
                                <td class='text-center'>".htmlspecialchars($row['bill_no'])."</td>
                                <td>".htmlspecialchars($row['gst_no'])."</td>
                                <td class='text-center'>".htmlspecialchars($row['hsn_code'] ?? '711311')."</td>
                                <td class='text-right'>".number_format($gross, 2, '.', '')."</td>
                                <td class='text-right'>".number_format($cgst, 2, '.', '')."</td>
                                <td class='text-right'>".number_format($sgst, 2, '.', '')."</td>
                                <td class='text-right'>".number_format($sales, 2, '.', '')."</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9' style='text-align:center; padding:30px; color:#94a3b8;'>No operational records match the query constraints inside this filter date range.</td></tr>";
                    }
                }
                
                // Render the Bottom Double-Underlined Grand Totals row if rows are present
                if ($row_count > 0) {
                    echo "<tr class='total-row'>
                            <td colspan='5' class='text-right'>Grand Total:</td>
                            <td class='text-right'>".number_format($t_gross, 2, '.', '')."</td>
                            <td class='text-right'>".number_format($t_cgst, 2, '.', '')."</td>
                            <td class='text-right'>".number_format($t_sgst, 2, '.', '')."</td>
                            <td class='text-right'>".number_format($t_sales, 2, '.', '')."</td>
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