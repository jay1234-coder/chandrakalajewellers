<?php
include '../includes/db.php';

// ─── 1. AJAX ENDPOINT: Fetch Extended Customer Invoice Ledger Detail Card ─────
if (isset($_GET['action']) && $_GET['action'] === 'get_customer_ledger') {
    $name = mysqli_real_escape_string($conn, $_GET['customer_name'] ?? '');
    $mobile = mysqli_real_escape_string($conn, $_GET['mobile'] ?? '');
    
    $where_clause = "WHERE customer_name = '$name'";
    if (!empty($mobile) && $mobile !== 'N/A') {
        $where_clause .= " AND mobile = '$mobile'";
    }

    $sql_items = "SELECT id, bill_no, bill_date, bill_type, net_amount, balance_amount 
                  FROM bills 
                  $where_clause 
                  ORDER BY bill_date DESC";
                  
    $res = mysqli_query($conn, $sql_items);
    $html = '<table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                        <th style="padding:10px; text-align:left;">Bill No</th>
                        <th style="padding:10px; text-align:left;">Date</th>
                        <th style="padding:10px; text-align:left;">Type</th>
                        <th style="padding:10px; text-align:right;">Net Total</th>
                        <th style="padding:10px; text-align:right;">Pending</th>
                        <th style="padding:10px; text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>';
                
    if (mysqli_num_rows($res) > 0) {
        while ($b = mysqli_fetch_assoc($res)) {
            $print_url = ($b['bill_type'] === 'LABOUR') ? '../billing/labour_print_bill.php?id=' . $b['id'] : '../billing/print_bill.php?id=' . $b['id'];
            $badge_type = ($b['bill_type'] === 'SALE') ? '<span class="badge-clear" style="padding:2px 6px; font-size:10px;">SALE</span>' : '<span class="badge-alert" style="padding:2px 6px; font-size:10px; background:#ede9fe; color:#5b21b6;">LABOUR</span>';
            
            $html .= '<tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:10px;"><strong>' . htmlspecialchars($b['bill_no']) . '</strong></td>
                <td style="padding:10px;">' . date('d/m/Y', strtotime($b['bill_date'])) . '</td>
                <td style="padding:10px;">' . $badge_type . '</td>
                <td style="padding:10px; text-align:right; font-weight:600;">₹' . number_format($b['net_amount'], 2) . '</td>
                <td style="padding:10px; text-align:right; font-weight:700; color:' . ($b['balance_amount'] > 0 ? '#ef4444' : '#10b981') . ';">₹' . number_format($b['balance_amount'], 2) . '</td>
                <td style="padding:10px; text-align:center;">
                    <a href="' . $print_url . '" target="_blank" style="color:#2563eb; text-decoration:none;"><i class="fas fa-print"></i> View</a>
                </td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="6" style="padding:20px; text-align:center; color:#64748b;">No individual invoices linked.</td></tr>';
    }
    $html .= '</tbody></table>';
    echo $html;
    exit;
}

// ─── 2. AJAX ENDPOINT: Live Filtering CRM Core Sub-Ledger Array Search ────────
if (isset($_GET['action']) && $_GET['action'] === 'live_search_crm') {
    $q = mysqli_real_escape_string($conn, $_GET['search_term'] ?? '');
    
    $where = "";
    if (!empty($q)) {
        $where = "WHERE customer_name LIKE '%$q%' OR mobile LIKE '%$q%' OR address LIKE '%$q%'";
    }

    $sql = "SELECT 
                customer_name, 
                mobile, 
                MAX(address) as customer_address, 
                COUNT(id) as total_bills, 
                SUM(net_amount) as total_spent, 
                SUM(balance_amount) as total_due 
            FROM bills 
            $where
            GROUP BY mobile, customer_name 
            ORDER BY total_spent DESC";
            
    $query = mysqli_query($conn, $sql);
    $output = '';

    if (mysqli_num_rows($query) > 0) {
        while($row = mysqli_fetch_assoc($query)) {
            $has_dues = floatval($row['total_due']) > 0;
            $mob_val = !empty($row['mobile']) ? $row['mobile'] : 'N/A';
            $addr_val = !empty($row['customer_address']) ? $row['customer_address'] : 'N/A';
            
            $output .= '<tr>
                <td>
                    <div style="font-weight:700; color:#1e293b; font-size:14px;">'.htmlspecialchars($row['customer_name']).'</div>
                    <small style="color:#64748b; font-weight:500;"><i class="fas fa-map-marker-alt"></i> '.htmlspecialchars($addr_val).'</small>
                </td>
                <td><span style="font-family:monospace; font-weight:600; font-size:13px;">'.htmlspecialchars($mob_val).'</span></td>
                <td style="text-align: center;"><span style="background:#f1f5f9; padding:4px 10px; border-radius:6px; font-weight:600; font-size:12px; color:#475569;">'.$row['total_bills'].' Bills</span></td>
                <td style="text-align: right; font-weight:700; color:#0f172a; font-size:14px;">₹ '.number_format($row['total_spent'], 2).'</td>
                <td style="text-align: right; font-weight:700; font-size:14px; color: '.($has_dues ? '#dc2626' : '#16a34a').';">₹ '.number_format($row['total_due'], 2).'</td>
                <td style="text-align: center;">
                    '.($has_dues ? '<span class="badge-alert">Has Outstanding</span>' : '<span class="badge-clear">Fully Settled</span>').'
                </td>
                <td>
                    <div style="display:flex; gap:6px; justify-content:center;">
                        <button class="btn-crm btn-view-profile" onclick="openCustomerProfile(\''.htmlspecialchars($row['customer_name'], ENT_QUOTES).'\', \''.htmlspecialchars($mob_val, ENT_QUOTES).'\', \''.number_format($row['total_spent'],2).'\', \''.number_format($row['total_due'],2).'\')" title="View Purchasing History"><i class="fas fa-eye"></i> Profile</button>
                        '.($mob_val !== 'N/A' ? '<button class="btn-crm btn-wa-crm" onclick="sendCrmWhatsAppReminder(\''.htmlspecialchars($row['customer_name'], ENT_QUOTES).'\', \''.$mob_val.'\', '.floatval($row['total_due']).')" title="Send Account Statement"><i class="fab fa-whatsapp"></i> Notify</button>' : '').'
                    </div>
                </td>
            </tr>';
        }
    } else {
        $output = '<tr><td colspan="7" style="text-align:center; padding:30px; color:#64748b;"><i class="fas fa-folder-open" style="font-size:24px; display:block; margin-bottom:8px; color:#cbd5e1;"></i> No matching client profile portfolios found.</td></tr>';
    }
    echo $output;
    exit;
}

// Get global metrics context for the top banner indicators
$totals_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT mobile, customer_name) as unique_clients, SUM(net_amount) as aggregate_sales, SUM(balance_amount) as aggregate_receivables FROM bills"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CRM Customer Dashboard - Chandrakala Jewellers</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body { background: #f8fafc; margin: 0; font-family: 'Segoe UI', -apple-system, sans-serif; color: #1e293b; }
    .container { display: flex; }
    .main { flex: 1; padding: 25px; min-width: 0; }
    
    .crm-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin: 20px 0; }
    .crm-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 16px; border: 1px solid #e2e8f0; }
    .crm-card-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    
    .table-box { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-top: 15px; overflow: hidden; }
    .table-header { padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    .table-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; }
    
    table { width: 100%; border-collapse: collapse; }
    table th { background: #f8fafc; text-align: left; padding: 14px 16px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
    table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; vertical-align: middle; }
    table tr:hover td { background: #f8fafc; }
    
    .badge-alert { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
    .badge-clear { background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; }
    
    .btn-crm { padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; background: #fff; }
    .btn-view-profile { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .btn-view-profile:hover { background: #dbeafe; }
    .btn-wa-crm { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
    .btn-wa-crm:hover { background: #bbf7d0; }

    /* Modal Sheet Styling Structure */
    .crm-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
    .crm-modal-box { background: white; width: 680px; max-width: 90%; max-height: 85vh; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column; }
    .crm-modal-header { padding: 18px 24px; background: #0f172a; color: white; display: flex; justify-content: space-between; align-items: center; }
    .crm-modal-body { padding: 24px; overflow-y: auto; flex: 1; }
</style>
</head>
<body>

<div class="crm-modal-overlay" id="profileModalSheet">
    <div class="crm-modal-box">
        <div class="crm-modal-header">
            <h3 style="margin:0; font-size:16px;"><i class="fas fa-id-card"></i> Customer Account Summary</h3>
            <button onclick="closeCustomerProfile()" style="background:none; border:none; color:white; font-size:18px; cursor:pointer;">✕</button>
        </div>
        <div class="crm-modal-body">
            <div style="background:#f8fafc; padding:16px; border-radius:8px; border:1px solid #e2e8f0; display:flex; justify-content:space-between; margin-bottom:20px;">
                <div>
                    <div id="modalClientName" style="font-size:18px; font-weight:700; color:#0f172a;">-</div>
                    <div id="modalClientPhone" style="font-size:13px; color:#64748b; font-weight:500; margin-top:2px;">-</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">Gross Purchases / Outstanding</div>
                    <div style="font-size:16px; font-weight:700; color:#0f172a; margin-top:2px;"><span id="modalGrossSales">₹0.00</span> / <span id="modalGrossDues" style="color:#dc2626;">₹0.00</span></div>
                </div>
            </div>
            
            <h4 style="margin:0 0 10px 0; font-size:13px; text-transform:uppercase; color:#64748b; letter-spacing:0.5px;">Linked Financial Invoice History</h4>
            <div id="modalLedgerTargetTable">
                </div>
        </div>
        <div style="background:#f8fafc; padding:12px 24px; text-align:right; border-top:1px solid #e2e8f0;">
            <button onclick="closeCustomerProfile()" style="padding:6px 16px; background:#64748b; color:white; border:none; border-radius:6px; font-weight:600; cursor:pointer;">Close Portfolio</button>
        </div>
    </div>
</div>

<div class="container">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1 style="margin:0; font-size:22px; font-weight:700; color:#0f172a;">Customer Relations Management Ledger</h1>
        </div>

        <div class="crm-summary-grid">
            <div class="crm-card">
                <div class="crm-card-icon" style="background:#eff6ff; color:#2563eb;"><i class="fas fa-users"></i></div>
                <div>
                    <div style="font-size:12px; color:#64748b; font-weight:600; text-transform:uppercase;">Active Portfolios</div>
                    <div style="font-size:20px; font-weight:700; margin-top:2px;"><?= intval($totals_res['unique_clients']); ?> Customers</div>
                </div>
            </div>
            <div class="crm-card">
                <div class="crm-card-icon" style="background:#ecfdf5; color:#10b981;"><i class="fas fa-chart-line"></i></div>
                <div>
                    <div style="font-size:12px; color:#64748b; font-weight:600; text-transform:uppercase;">Total Sales</div>
                    <div style="font-size:20px; font-weight:700; margin-top:2px;">₹<?= number_format($totals_res['aggregate_sales'], 2); ?></div>
                </div>
            </div>
            <div class="crm-card">
                <div class="crm-card-icon" style="background:#fef2f2; color:#ef4444;"><i class="fas fa-hand-holding-usd"></i></div>
                <div>
                    <div style="font-size:12px; color:#64748b; font-weight:600; text-transform:uppercase;">Amount to be get received</div>
                    <div style="font-size:20px; font-weight:700; margin-top:2px; color:#dc2626;">₹<?= number_format($totals_res['aggregate_receivables'], 2); ?></div>
                </div>
            </div>
        </div>

        <div class="table-box">
            <div class="table-header">
                <h3>Active Client Relations Portfolios</h3>
                <div style="position:relative;">
                    <i class="fas fa-search" style="position:absolute; left:12px; top:12px; color:#94a3b8;"></i>
                    <input type="text" id="crmLiveSearch" placeholder="Filter profiles by name, phone or town..." style="width:300px; padding:10px 10px 10px 34px; border:1px solid #cbd5e1; border-radius:8px; outline:none; font-size:13px;">
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Customer / Core Address</th>
                        <th>Mobile Contact</th>
                        <th style="text-align: center;">Frequency</th>
                        <th style="text-align: right;">Gross Purchases</th>
                        <th style="text-align: right;">Total Receivables</th>
                        <th style="text-align: center;">Credit Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="crmDashboardBody">
                    </tbody>
            </table>
        </div>
    </main>
</div>

<script>
const crmSearchInp = document.getElementById('crmLiveSearch');

function syncCrmLedgerGrid(term = '') {
    fetch(`customers.php?action=live_search_crm&search_term=${encodeURIComponent(term)}`)
        .then(response => response.text())
        .then(htmlContent => {
            document.getElementById('crmDashboardBody').innerHTML = htmlContent;
        });
}

function openCustomerProfile(name, mobile, grossSales, grossDues) {
    document.getElementById('modalClientName').textContent = name;
    document.getElementById('modalClientPhone').textContent = mobile === 'N/A' ? 'No phone identifier registered' : 'Mobile Contact: ' + mobile;
    document.getElementById('modalGrossSales').textContent = '₹' + grossSales;
    document.getElementById('modalGrossDues').textContent = '₹' + grossDues;
    
    document.getElementById('modalLedgerTargetTable').innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;"><i class="fas fa-spinner fa-spin"></i> Retrieving portfolio ledger...</div>';
    document.getElementById('profileModalSheet').style.display = 'flex';
    
    fetch(`customers.php?action=get_customer_ledger&customer_name=${encodeURIComponent(name)}&mobile=${encodeURIComponent(mobile)}`)
        .then(r => r.text())
        .then(htmlTable => {
            document.getElementById('modalLedgerTargetTable').innerHTML = htmlTable;
        });
}

function closeCustomerProfile() {
    document.getElementById('profileModalSheet').style.display = 'none';
}

function sendCrmWhatsAppReminder(name, mobile, dues) {
    let msg = '';
    if (dues > 0) {
        msg = `Dear ${name}, this is an account update statement notification from Chandrakala Jewellers. Your current pending outstanding ledger statement balance is ₹${dues.toFixed(2)}. Please drop by or clear at your convenience. Thank you!`;
    } else {
        msg = `Hello ${name}, thank you for maintaining a clear account balance status with Chandrakala Jewellers. We value your continuous trust and relation with us!`;
    }
    
    let sanitizedPhone = mobile.replace(/[^0-9]/g, '');
    if (sanitizedPhone.length === 10) sanitizedPhone = '91' + sanitizedPhone;
    
    window.open(`https://api.whatsapp.com/send?phone=${sanitizedPhone}&text=${encodeURIComponent(msg)}`, '_blank');
}

crmSearchInp.addEventListener('input', (e) => syncCrmLedgerGrid(e.target.value));
window.addEventListener('DOMContentLoaded', () => syncCrmLedgerGrid(''));
</script>
</body>
</html>