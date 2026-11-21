<?php
include '../includes/db.php';
date_default_timezone_set('Asia/Kolkata');

// ─── 1. Handle Delete Bill Request (Security Handled via POST) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_bill') {
    if (isset($_POST['delete_id']) && intval($_POST['delete_id']) > 0) {
        $delete_id = intval($_POST['delete_id']);
        
        mysqli_begin_transaction($conn);
        try {
            // Use prepared stmt for items delete
            $stmt_items = mysqli_prepare($conn, "DELETE FROM bill_items WHERE bill_id = ?");
            if (!$stmt_items) { throw new Exception(mysqli_error($conn)); }
            mysqli_stmt_bind_param($stmt_items, "i", $delete_id);
            if (!mysqli_stmt_execute($stmt_items)) { throw new Exception(mysqli_error($conn)); }
            mysqli_stmt_close($stmt_items);

            // Fix broken block here
            $stmt = mysqli_prepare($conn, "DELETE FROM bills WHERE id = ?");
            if (!$stmt) { throw new Exception(mysqli_error($conn)); }
            mysqli_stmt_bind_param($stmt, "i", $delete_id);
            if (!mysqli_stmt_execute($stmt)) { throw new Exception(mysqli_error($conn)); }
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            header("Location: bill_history.php?msg=Invoice+purged+successfully&msg_type=error");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            die("Failed securely purging accounting records: " . $e->getMessage());
        }
    }
}

// ─── 2. AJAX Endpoint: Live Structural Ledger Search ──────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'live_search') {
    $q = $_GET['search_query'] ?? '';
    
    // Fix: Secure Live Search via Prepared Statement
    $search_term = "%$q%";
    $sql = "SELECT b.id, b.bill_no, b.bill_date, b.customer_name, b.mobile, b.bill_type, b.net_amount, b.balance_amount, b.status, COUNT(bi.id) as item_count 
            FROM bills b 
            LEFT JOIN bill_items bi ON b.id = bi.bill_id
            WHERE b.customer_name LIKE ? OR b.mobile LIKE ? OR b.bill_no LIKE ?
            GROUP BY b.id 
            ORDER BY b.bill_date ASC, b.id ASC"; // <-- Changed to ASC and removed LIMIT 50 to show all records chronologically
            
    $stmt_search = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt_search, "sss", $search_term, $search_term, $search_term);
    mysqli_stmt_execute($stmt_search);
    $res = mysqli_stmt_get_result($stmt_search);
    $output = '';
    
    if(mysqli_num_rows($res) > 0) {
        while($bill = mysqli_fetch_assoc($res)) {
            $is_cancelled = ($bill['status'] === 'CANCELLED');
            $edit_url = ($bill['bill_type'] === 'LABOUR') ? 'labour_bill.php?edit=' . $bill['id'] : 'sale_bill.php?edit=' . $bill['id'];
            $print_url = ($bill['bill_type'] === 'LABOUR') ? 'labour_print_bill.php?id=' . $bill['id'] : 'print_bill.php?id=' . $bill['id'];

            $output .= '<tr class="'.($is_cancelled ? 'cancelled-row' : '').'">
                <td><strong>'.htmlspecialchars($bill['bill_no']).'</strong></td>
                <td>'.date('d/m/Y', strtotime($bill['bill_date'])).'</td>
                <td>'.htmlspecialchars($bill['customer_name']).'</td>
                <td>'.htmlspecialchars($bill['mobile']).'</td>
                <td><span class="badge '.($bill['bill_type'] === 'SALE' ? 'badge-sale' : 'badge-labour').'">'.htmlspecialchars($bill['bill_type']).'</span></td>
                <td style="text-align:center;">'.intval($bill['item_count']).'</td>
                <td style="text-align:right; font-weight:700;">₹'.number_format($bill['net_amount'], 2).'</td>
                <td style="text-align:right;">'.($bill['balance_amount'] > 0 ? '<span class="balance-due">₹'.number_format($bill['balance_amount'], 2).'</span>' : '<span class="balance-clear">Paid</span>').'</td>
                <td>
                    <div class="actions-cell">
                        <a href="'.htmlspecialchars($print_url).'" class="btn-action btn-print" target="_blank"><i class="fas fa-print"></i></a>
                        <a href="'.htmlspecialchars($edit_url).'" class="btn-action btn-edit"><i class="fas fa-edit"></i></a>
                        <button onclick="triggerWhatsAppModal('.intval($bill['id']).', \''.htmlspecialchars($bill['customer_name'], ENT_QUOTES).'\', \''.htmlspecialchars($bill['mobile'], ENT_QUOTES).'\', \''.number_format($bill['balance_amount'], 2).'\', \''.htmlspecialchars($bill['bill_no'], ENT_QUOTES).'\')" class="btn-action" style="background:#dcfce7; color:#16a34a; border:1px solid #bbf7d0;"><i class="fab fa-whatsapp"></i></button>
                        <form method="POST" action="bill_history.php" style="display:inline;" onsubmit="return confirm(\'Purge invoice records permanently?\')">
                            <input type="hidden" name="action" value="delete_bill">
                            <input type="hidden" name="delete_id" value="'.intval($bill['id']).'">
                            <button type="submit" class="btn-action" style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; height:32px; width:32px; padding:0;"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>';
        }
    } else {
        $output = '<tr><td colspan="9" style="text-align:center; padding:30px; color:#9ca3af;">No record matches current parameter search entries.</td></tr>';
    }
    mysqli_stmt_close($stmt_search);
    echo $output;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ledger History - Chandrakala Jewellers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; margin: 0; }
        .container { display: flex; }
        .main { flex: 1; padding: 25px; min-width: 0; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .table-box { background: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #f8fafc; padding: 12px 14px; text-align: left; font-size: 12px; font-weight: 700; color: #374151; text-transform: uppercase; border-bottom: 2px solid #e5e7eb; }
        tbody td { padding: 12px 14px; border-bottom: 1px solid #f3f4f6; font-size: 13px; vertical-align: middle; }
        
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-sale { background: #dbeafe; color: #1e40af; }
        .badge-labour { background: #ede9fe; color: #5b21b6; }
        .balance-due { color: #dc2626; font-weight: 700; }
        .balance-clear { color: #16a34a; font-weight: 600; }
        
        .actions-cell { display: flex; gap: 4px; align-items: center; }
        .btn-action { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; font-size: 13px; text-decoration: none; cursor: pointer; box-sizing: border-box; }
        .btn-print { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .btn-edit { background: #fefce8; color: #92400e; border: 1px solid #fde68a; }

        .wa-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .wa-modal-card { background: white; padding: 24px; border-radius: 12px; width: 440px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .wa-btn { width: 100%; padding: 12px; border-radius: 8px; font-weight: bold; margin-top: 10px; cursor: pointer; text-align: left; display: flex; align-items: center; gap: 10px; border: 1px solid #e2e8f0; background: #fff; transition: background 0.2s; }
        .wa-btn:hover { background: #f8fafc; }
    </style>
</head>
<body>

<div class="wa-modal-overlay" id="whatsappModal">
    <div class="wa-modal-card">
        <h3 style="margin:0 0 4px 0;"><i class="fab fa-whatsapp" style="color:#16a34a;"></i> WhatsApp Communication Routing</h3>
        <p style="font-size:13px; color:#64748b; margin:0 0 16px 0;">Select dispatch option parameters for client transmission:</p>
        
        <button class="wa-btn" onclick="executeWhatsAppTransmission('invoice')">
            <div style="background:#dcfce7; color:#16a34a; width:36px; height:36px; border-radius:5px; display:flex; align-items:center; justify-content:center;"><i class="fas fa-file-invoice"></i></div>
            <div>
                <strong>Send Digital Soft-Copy Invoice</strong>
                <div style="font-size:11px; font-weight:normal; color:#64748b;">Dispatches structured link details configuration link.</div>
            </div>
        </button>

        <button class="wa-btn" onclick="executeWhatsAppTransmission('reminder')">
            <div style="background:#fee2e2; color:#dc2626; width:36px; height:36px; border-radius:5px; display:flex; align-items:center; justify-content:center;"><i class="fas fa-clock"></i></div>
            <div>
                <strong>Send Payment Outstanding Reminder</strong>
                <div style="font-size:11px; font-weight:normal; color:#64748b;">Dispatches pending valuation balance alert notifications.</div>
            </div>
        </button>
        
        <button onclick="closeWhatsAppModal()" style="margin-top:16px; width:100%; border:none; padding:8px; background:#f1f5f9; font-weight:600; color:#475569; border-radius:6px; cursor:pointer;">Close Window</button>
    </div>
</div>

<div class="container">
    <?php $active_page = 'bill_history'; include '../includes/sidebar.php'; ?>

    <main class="main">
        <div class="page-header">
            <h1><i class="fas fa-history" style="color:#2563eb; margin-right:8px;"></i>Bill History</h1>
            <input type="text" id="liveHistorySearch" placeholder="Live search by name, phone, or invoice code..." style="width:320px; padding:10px; border:1px solid #cbd5e1; border-radius:8px; outline:none;">
        </div>

        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th>Bill Invoice No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Mobile</th>
                        <th>Classification</th>
                        <th>Items</th>
                        <th style="text-align:right;">Net Amount</th>
                        <th style="text-align:right;">Balance Outstanding</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="historyRenderBody">
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
let contextActiveBill = {};

// Triggers the modal and populates contextual data variables
function triggerWhatsAppModal(id, name, phone, balance, billNo) {
    contextActiveBill = { id, name, phone, balance, billNo };
    document.getElementById('whatsappModal').style.display = 'flex';
}
function closeWhatsAppModal() {
    document.getElementById('whatsappModal').style.display = 'none';
}

function executeWhatsAppTransmission(mode) {
    let message = '';
    if (mode === 'invoice') {
        message = `Hello ${contextActiveBill.name}, thank you for your business with Chandrakala Jewellers. Your Invoice ${contextActiveBill.billNo} is prepared. View link: ${window.location.origin}/jewellery-management/billing/print_bill.php?id=${contextActiveBill.id}`;
    } else {
        message = `Dear ${contextActiveBill.name}, this is a friendly reminder regarding your outstanding balance of ₹${contextActiveBill.balance} on Invoice ${contextActiveBill.billNo} with Chandrakala Jewellers. Kindly clear at your earliest convenience.`;
    }
    
    let cleanPhone = contextActiveBill.phone.replace(/[^0-9]/g, '');
    if(cleanPhone.length === 10) cleanPhone = '91' + cleanPhone;
    
    window.open(`https://api.whatsapp.com/send?phone=${cleanPhone}&text=${encodeURIComponent(message)}`, '_blank');
    closeWhatsAppModal();
}

const searchInp = document.getElementById('liveHistorySearch');
function fetchFilteredHistory(term = '') {
    fetch(`bill_history.php?action=live_search&search_query=${encodeURIComponent(term)}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById('historyRenderBody').innerHTML = html;
        });
}

searchInp.addEventListener('input', (e) => fetchFilteredHistory(e.target.value));
window.addEventListener('DOMContentLoaded', () => fetchFilteredHistory(''));
</script>
</body>
</html>