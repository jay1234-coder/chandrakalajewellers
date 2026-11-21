<?php
include '../includes/db.php';

// ─── 1. Auto Labour Bill Number Generation (Format: L-YYYY-001) ─────────────
$year = date('Y');
$result = mysqli_query($conn, "SELECT id FROM bills WHERE bill_type = 'LABOUR' ORDER BY id DESC LIMIT 1");
if(mysqli_num_rows($result) > 0){
    $row = mysqli_fetch_assoc($result);
    $next = $row['id'] + 1;
} else {
    $next = 1;
}
$bill_no = 'L-' . $year . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);

// ─── 2. Handle Edit Mode ─────────────────────────────────────────────────────
$edit_mode = false;
$edit_bill = null;
$edit_items = [];
if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $edit_id = intval($_GET['edit']);
    $edit_res = mysqli_query($conn, "SELECT * FROM bills WHERE id = $edit_id AND bill_type = 'LABOUR' AND status != 'CANCELLED'");
    if (mysqli_num_rows($edit_res) > 0) {
        $edit_mode = true;
        $edit_bill = mysqli_fetch_assoc($edit_res);
        $items_res = mysqli_query($conn, "SELECT * FROM bill_items WHERE bill_id = $edit_id");
        while ($r = mysqli_fetch_assoc($items_res)) $edit_items[] = $r;
        $bill_no = $edit_bill['bill_no'];
    }
}

// ─── 3. Handle Form Submission (Save / Update) ───────────────────────────────
if(isset($_POST['save_labour_bill'])) {
    $is_update = isset($_POST['edit_bill_id']) && intval($_POST['edit_bill_id']) > 0;
    $edit_bill_id = $is_update ? intval($_POST['edit_bill_id']) : null;

    $bill_no_post = mysqli_real_escape_string($conn, $_POST['bill_no']);
    $bill_date = mysqli_real_escape_string($conn, $_POST['bill_date']);
    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $gst_no = mysqli_real_escape_string($conn, $_POST['gst_no']);
    $state_code = mysqli_real_escape_string($conn, $_POST['state_code']);
    $pan_no = mysqli_real_escape_string($conn, $_POST['pan_no']);
    
    $gold_rate = floatval($_POST['gold_rate'] ?? 0);
    $silver_rate = floatval($_POST['silver_rate'] ?? 0);
    $gold_credit = floatval($_POST['gold_credit'] ?? 0);
    
    $subtotal = floatval($_POST['subtotal']);
    $discount = floatval($_POST['discount']);
    $cgst = floatval($_POST['cgst']);
    $sgst = floatval($_POST['sgst']);
    $net_amount = floatval($_POST['net_amount']);
    
    $cash_received = floatval($_POST['cash_received']);
    $bank_received = floatval($_POST['bank_received']);
    $balance_amount = floatval($_POST['balance_amount']);

    // Server Side Logic Validations
    if (empty($customer_name)) { die("Error: Customer Name is required."); }
    if (!empty($mobile) && !preg_match('/^[0-9]{10}$/', $mobile)) { die("Error: Mobile number must be exactly 10 digits."); }
    if ($discount > $subtotal) { die("Error: Discount amount cannot exceed subtotal."); }
    if ($gold_credit < 0) { die("Error: Gold Credit deposit weight cannot be negative."); }
    
    mysqli_begin_transaction($conn);
    
    try {
        if ($is_update) {
            // Check for duplicate bill numbers when altering in edit mode
            $bill_check = mysqli_query($conn, "SELECT id FROM bills WHERE bill_no='$bill_no_post' AND id != $edit_bill_id");
            if(mysqli_num_rows($bill_check) > 0) { throw new Exception("Duplicate Bill Number detected for another transaction record."); }

            $bill_query = "UPDATE bills SET 
                bill_no='$bill_no_post', bill_date='$bill_date', customer_name='$customer_name', mobile='$mobile', 
                address='$address', gst_no='$gst_no', state_code='$state_code', pan_no='$pan_no', 
                gold_rate='$gold_rate', silver_rate='$silver_rate', gold_credit='$gold_credit', subtotal='$subtotal', 
                discount='$discount', cgst='$cgst', sgst='$sgst', net_amount='$net_amount', 
                cash_received='$cash_received', bank_received='$bank_received', balance_amount='$balance_amount'
                WHERE id=$edit_bill_id";
            if(!mysqli_query($conn, $bill_query)) { throw new Exception(mysqli_error($conn)); }
            if(!mysqli_query($conn, "DELETE FROM bill_items WHERE bill_id=$edit_bill_id")) { throw new Exception(mysqli_error($conn)); }
            $saved_bill_id = $edit_bill_id;
        } else {
            // Strict duplicate invoice isolation check during basic inserts
            $bill_check = mysqli_query($conn, "SELECT id FROM bills WHERE bill_no='$bill_no_post'");
            if(mysqli_num_rows($bill_check) > 0) { throw new Exception("Duplicate Labour Bill Number Detected."); }

            $bill_query = "INSERT INTO bills (
                bill_no, bill_date, customer_name, mobile, address, gst_no, state_code, pan_no, 
                gold_rate, silver_rate, gold_credit, subtotal, discount, cgst, sgst, net_amount, 
                cash_received, bank_received, balance_amount, bill_type, status
            ) VALUES (
                '$bill_no_post', '$bill_date', '$customer_name', '$mobile', '$address', '$gst_no', '$state_code', '$pan_no', 
                '$gold_rate', '$silver_rate', '$gold_credit', '$subtotal', '$discount', '$cgst', '$sgst', '$net_amount', 
                '$cash_received', '$bank_received', '$balance_amount', 'LABOUR', 'ACTIVE'
            )";
            if(!mysqli_query($conn, $bill_query)) { throw new Exception(mysqli_error($conn)); }
            $saved_bill_id = mysqli_insert_id($conn);
        }
        
        foreach($_POST['description'] as $key => $description) {
            $description = mysqli_real_escape_string($conn, $description);
            $sac_code = mysqli_real_escape_string($conn, $_POST['sac_code'][$key]);
            $item_metal = mysqli_real_escape_string($conn, $_POST['item_metal_type'][$key]);
            $qty = floatval($_POST['quantity'][$key]);
            $making_percent = floatval($_POST['making_percent'][$key]);
            
            $current_metal_rate = ($item_metal === 'silver') ? $silver_rate : $gold_rate;
            $total_amount = number_format(($current_metal_rate * $qty * ($making_percent / 100)), 2, '.', '');
            
            // Re-engineered save execution to populate the explicit item_metal_type column layout
            $insert_item = mysqli_query($conn, "INSERT INTO bill_items (
                bill_id, description, hsn_code, quantity, rate, making_percent, item_metal_type, making_amount, total_amount
            ) VALUES (
                '$saved_bill_id', '$description', '$sac_code', '$qty', '$current_metal_rate', '$making_percent', '$item_metal', '$item_metal', '$total_amount'
            )");

            if(!$insert_item) { throw new Exception(mysqli_error($conn)); }
        }
        
        mysqli_commit($conn);
        
        echo "<script>
            alert('" . ($is_update ? 'Labour Job Bill Updated Successfully!' : 'Labour Job Bill Saved Successfully!') . "');
            window.location.href = 'labour_print_bill.php?id=$saved_bill_id';
        </script>";
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        die("Error processing database operation: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $edit_mode ? 'Edit Labour Bill' : 'Create Labour Bill' ?> - Chandrakala Jewellers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; margin: 0; padding: 0; }
        .container { display: flex; }
        .main { flex: 1; padding: 25px; }
        .bill-box { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.05); }
        h1 { color: #333; margin-bottom: 25px; font-weight: 500; }
        .section-title { font-size: 16px; font-weight: 600; color: #444; border-bottom: 2px solid #eaeaea; padding-bottom: 8px; margin: 20px 0 15px 0; }
        .row { display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap; }
        .field { flex: 1; min-width: 200px; display: flex; flex-direction: column; }
        label { margin-bottom: 6px; font-size: 13px; font-weight: 600; color: #666; }
        input, select { padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; outline: none; transition: border 0.2s; }
        input:focus, select:focus { border-color: #3b82f6; }
        .badge-edit { background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; border: 1px solid #fcd34d; display: inline-block; margin-bottom: 15px;}
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; border: 1px solid #e0e0e0; text-align: left; }
        th { background: #f8fafc; font-weight: 600; color: #444; }
        .amount-cell { font-weight: bold; text-align: right; background: #fcfcfc; }
        .add-btn { background: #10b981; color: white; border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-weight: 600; margin-top: 15px; }
        .flex-summary { display: flex; justify-content: space-between; margin-top: 30px; gap: 40px; }
        .payment-settlement { flex: 1; max-width: 500px; }
        .financial-summary { width: 400px; }
        .summary-table { width: 100%; }
        .summary-table td { padding: 8px 12px; border: none; }
        .summary-table tr { border-bottom: 1px solid #f0f0f0; }
        .summary-table tr.grand-total { border-top: 2px solid #333; border-bottom: 2px solid #333; font-size: 16px; font-weight: bold; }
        .actions { margin-top: 30px; border-top: 1px solid #eaeaea; padding-top: 20px; display: flex; gap: 15px; justify-content: flex-end; }
        .btn-submit { background: #3b82f6; color: white; border: none; padding: 12px 30px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 15px; }
        table input, table select { width: 100%; box-sizing: border-box; border: 1px solid #e0e0e0; padding: 6px; border-radius: 4px; }
        .metal-ledger-box { background: #fafafa; border: 1px dashed #cbd5e1; padding: 15px; border-radius: 8px; margin-top: 15px; max-width: 450px; }
    </style>
</head>
<body>

<div class="container">
    <?php $active_page = 'labour_bill'; include '../includes/sidebar.php'; ?>

    <main class="main">
        <h1><?= $edit_mode ? 'Edit Labour / Job Work Bill' : 'New Labour / Job Work Bill' ?></h1>
        <?php if ($edit_mode): ?>
            <span class="badge-edit"><i class="fas fa-edit"></i> Editing Bill #<?= $edit_bill['bill_no'] ?></span>
        <?php endif; ?>
        
        <form method="POST" id="labourBillForm" onsubmit="return validateForm()">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="edit_bill_id" value="<?= $edit_bill['id'] ?>">
            <?php endif; ?>

            <div class="bill-box">
                <div class="row">
                    <div class="field">
                        <label>Labour Bill No</label>
                        <input type="text" name="bill_no" value="<?php echo htmlspecialchars($bill_no); ?>" style="font-weight:bold; border-color:#3b82f6;">
                    </div>
                    <div class="field">
                        <label>Date</label>
                        <input type="date" name="bill_date" value="<?php echo $edit_mode ? $edit_bill['bill_date'] : date('Y-m-d'); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="field">
                        <label>Today's Gold Rate (₹ / Gram)</label>
                        <input type="number" step="0.01" id="base_gold_rate" name="gold_rate" value="<?php echo $edit_mode ? $edit_bill['gold_rate'] : '10000.00'; ?>" class="calc-trigger">
                    </div>
                    <div class="field">
                        <label>Today's Silver Rate (₹ / Gram)</label>
                        <input type="number" step="0.01" id="base_silver_rate" name="silver_rate" value="<?php echo $edit_mode ? $edit_bill['silver_rate'] : '100.00'; ?>" class="calc-trigger">
                    </div>
                </div>

                <div class="section-title">Customer / Job-Worker Details</div>
                <div class="row">
                    <div class="field" style="flex: 2;">
                        <label>Customer Name</label>
                        <input type="text" name="customer_name" required placeholder="Full Name" value="<?php echo $edit_mode ? htmlspecialchars($edit_bill['customer_name']) : ''; ?>">
                    </div>
                    <div class="field">
                        <label>Mobile Number</label>
                        <input type="text" name="mobile" id="mobile" placeholder="10-digit mobile" value="<?php echo $edit_mode ? htmlspecialchars($edit_bill['mobile']) : ''; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="field" style="flex: 2;">
                        <label>Address</label>
                        <input type="text" name="address" placeholder="Billing Address" value="<?php echo $edit_mode ? htmlspecialchars($edit_bill['address']) : ''; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="field">
                        <label>GSTIN No</label>
                        <input type="text" name="gst_no" id="gst_no" placeholder="24XXXXXXXXXXXXX" value="<?php echo $edit_mode ? htmlspecialchars($edit_bill['gst_no']) : ''; ?>">
                    </div>
                    <div class="field">
                        <label>State Code</label>
                        <input type="text" name="state_code" value="<?php echo $edit_mode ? htmlspecialchars($edit_bill['state_code']) : '24'; ?>">
                    </div>
                    <div class="field">
                        <label>PAN No</label>
                        <input type="text" name="pan_no" placeholder="ABCDE1234F" value="<?php echo $edit_mode ? htmlspecialchars($edit_bill['pan_no']) : ''; ?>">
                    </div>
                </div>

                <div class="section-title">Description of Service / Repair Work</div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 5%;">Sr</th>
                            <th style="width: 35%;">Description of Job Work</th>
                            <th style="width: 15%;">Metal Type</th>
                            <th style="width: 12%;">HSNCODE</th>
                            <th style="width: 10%;">Qty/Wt (Gms)</th>
                            <th style="width: 10%;">Making %</th>
                            <th style="width: 13%;">Amount (Rs)</th>
                            <th style="width: 5%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <?php if ($edit_mode && !empty($edit_items)): ?>
                            <?php foreach ($edit_items as $idx => $ei): ?>
                            <?php 
                                $item_metal = !empty($ei['item_metal_type']) ? trim(strtolower($ei['item_metal_type'])) : trim(strtolower($ei['making_amount'])); 
                            ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><input type="text" name="description[]" required value="<?php echo htmlspecialchars($ei['description']); ?>"></td>
                                <td>
                                    <select name="item_metal_type[]" class="row-metal-type calc-trigger">
                                        <option value="gold" <?php echo $item_metal !== 'silver' ? 'selected' : ''; ?>>Gold</option>
                                        <option value="silver" <?php echo $item_metal === 'silver' ? 'selected' : ''; ?>>Silver</option>
                                    </select>
                                </td>
                                <td><input type="text" name="sac_code[]" value="<?php echo htmlspecialchars($ei['hsn_code']); ?>"></td>
                                <td><input type="number" step="0.001" name="quantity[]" class="qty calc-trigger" required value="<?php echo $ei['quantity']; ?>"></td>
                                <td><input type="number" step="0.01" name="making_percent[]" class="making-pct calc-trigger" required value="<?php echo $ei['making_percent']; ?>"></td>
                                <td class="amount-cell amount">0.00</td>
                                <td style="text-align: center;"><button type="button" class="remove-btn" onclick="removeRow(this)">X</button></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td>1</td>
                            <td><input type="text" name="description[]" required placeholder="e.g. Polishing, Casting"></td>
                            <td>
                                <select name="item_metal_type[]" class="row-metal-type calc-trigger">
                                    <option value="gold">Gold</option>
                                    <option value="silver">Silver</option>
                                </select>
                            </td>
                            <td><input type="text" name="sac_code[]" value="9988"></td>
                            <td><input type="number" step="0.001" name="quantity[]" class="qty calc-trigger" required placeholder="0.000"></td>
                            <td><input type="number" step="0.01" name="making_percent[]" class="making-pct calc-trigger" required placeholder="0.00"></td>
                            <td class="amount-cell amount">0.00</td>
                            <td style="text-align: center;"><button type="button" class="remove-btn" onclick="removeRow(this)">X</button></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <button type="button" class="add-btn" onclick="addRow()">+ Add Next Line Item</button>

                <div class="metal-ledger-box">
                    <h4 style="margin: 0 0 10px 0; color: #374151; font-size:14px;"><i class="fas fa-balance-scale" style="margin-right:5px; color:#2563eb;"></i> Gold Pure-Metal Accounting Ledger</h4>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <label style="margin:0;">Customer Metal Deposit Credit (Gms):</label>
                            <input type="number" step="0.001" id="gold_credit" name="gold_credit" value="<?php echo $edit_mode ? $edit_bill['gold_credit'] : '0.000'; ?>" class="calc-trigger" style="width:120px; padding:5px;">
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; border-top: 1px solid #e2e8f0; padding-top:6px;">
                            <span>Total Built Ornaments Weight:</span>
                            <span id="lbl_total_built_weight" style="font-weight:600;">0.000 g</span>
                        </div>
                        <div id="row_gold_returned" style="display:none; justify-content:space-between; align-items:center; color: #16a34a;">
                            <span>Gold Balance Return Weight:</span>
                            <span id="lbl_gold_returned" style="font-weight:700;">0.000 g</span>
                        </div>
                        <div id="row_gold_billed" style="display:none; justify-content:space-between; align-items:center; color: #dc2626;">
                            <span>Gold Billed Metal Weight:</span>
                            <span id="lbl_gold_billed" style="font-weight:700;">0.000 g</span>
                        </div>
                    </div>
                </div>

                <div class="flex-summary">
                    <div class="payment-settlement">
                        <div class="section-title" style="margin-top: 0;">Payment Settlement Breakdown</div>
                        <div class="row">
                            <div class="field">
                                <label>Cash Received (₹)</label>
                                <input type="number" step="0.01" name="cash_received" class="payment-calc" value="<?php echo $edit_mode ? $edit_bill['cash_received'] : '0.00'; ?>">
                            </div>
                            <div class="field">
                                <label>Bank / UPI Payments (₹)</label>
                                <input type="number" step="0.01" name="bank_received" class="payment-calc" value="<?php echo $edit_mode ? $edit_bill['bank_received'] : '0.00'; ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="field">
                                <label>Remaining Outstanding Balance (₹)</label>
                                <input type="number" step="0.01" name="balance_amount" id="balance_amount" readonly style="background: #f9fafb; font-weight: bold; color: #ef4444;" value="<?php echo $edit_mode ? $edit_bill['balance_amount'] : '0.00'; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="financial-summary">
                        <div class="section-title" style="margin-top: 0;">Tax Computations (Labour Job Work 5%)</div>
                        <table class="summary-table">
                            <tr>
                                <td>Service Subtotal</td>
                                <td id="subtotal_val" style="text-align: right; font-weight: 600;">0.00</td>
                            </tr>
                            <tr>
                                <td>Discount (Rs)</td>
                                <td><input type="number" step="0.01" name="discount" id="discount" value="<?php echo $edit_mode ? $edit_bill['discount'] : '0.00'; ?>" style="text-align: right; padding: 4px; width: 120px; float: right;"></td>
                            </tr>
                            <tr>
                                <td>CGST (2.5%)</td>
                                <td id="cgst_val" style="text-align: right;">0.00</td>
                            </tr>
                            <tr>
                                <td>SGST (2.5%)</td>
                                <td id="sgst_val" style="text-align: right;">0.00</td>
                            </tr>
                            <tr class="grand-total">
                                <td>Grand Net Total</td>
                                <td id="net_val" style="text-align: right;">0.00</td>
                            </tr>
                        </table>
                        
                        <input type="hidden" id="subtotal" name="subtotal" value="0">
                        <input type="hidden" id="cgst" name="cgst" value="0">
                        <input type="hidden" id="sgst" name="sgst" value="0">
                        <input type="hidden" id="net_amount" name="net_amount" value="0">
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" name="save_labour_bill" class="btn-submit">
                        <?= $edit_mode ? 'Update Labour Bill & Reprint' : 'Save Labour Bill & Print' ?>
                    </button>
                </div>
            </div>
        </form>
    </main>
</div>

<script>
function validateForm() {
    const mobile = document.getElementById('mobile').value;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    const goldCredit = parseFloat(document.getElementById('gold_credit').value) || 0;
    const gst = document.getElementById('gst_no').value;

    if (mobile !== "" && !/^[0-9]{10}$/.test(mobile)) {
        alert("Please enter a valid 10-digit mobile number.");
        return false;
    }
    if (discount > subtotal) {
        alert("Discount cannot be greater than the service subtotal.");
        return false;
    }
    if (goldCredit < 0) {
        alert("Gold Credit metal deposit value cannot be negative.");
        return false;
    }
    if (gst.trim() !== "") {
        if (!/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/.test(gst)) {
            alert("Please enter a valid GSTIN format (e.g., 24ABCDE1234F1Z5) or leave it completely blank.");
            return false;
        }
    }
    return true;
}

function calculate() {
    let subtotal = 0;
    let totalGoldWeight = 0;
    const goldBase = parseFloat(document.getElementById('base_gold_rate').value) || 0;
    const silvBase = parseFloat(document.getElementById('base_silver_rate').value) || 0;
    const goldCredit = parseFloat(document.getElementById('gold_credit').value) || 0;
    
    document.querySelectorAll('#itemsBody tr').forEach(row => {
        let metalType = row.querySelector('.row-metal-type').value;
        let qty = parseFloat(row.querySelector('.qty').value) || 0;
        let makingPct = parseFloat(row.querySelector('.making-pct').value) || 0;
        
        if(metalType === 'gold') {
            totalGoldWeight += qty;
        }
        
        let baseRatePerGram = (metalType === 'silver' ? silvBase : goldBase);
        let total = baseRatePerGram * qty * (makingPct / 100);
        
        row.querySelector('.amount').innerHTML = total.toFixed(2);
        subtotal += total;
    });

    document.getElementById('lbl_total_built_weight').innerText = totalGoldWeight.toFixed(3) + " g";
    if (goldCredit > 0 || totalGoldWeight > 0) {
        let diff = totalGoldWeight - goldCredit;
        if(diff < 0) {
            document.getElementById('row_gold_returned').style.display = 'flex';
            document.getElementById('row_gold_billed').style.display = 'none';
            document.getElementById('lbl_gold_returned').innerText = Math.abs(diff).toFixed(3) + " g";
        } else if(diff > 0) {
            document.getElementById('row_gold_returned').style.display = 'none';
            document.getElementById('row_gold_billed').style.display = 'flex';
            document.getElementById('lbl_gold_billed').innerText = diff.toFixed(3) + " g";
        } else {
            document.getElementById('row_gold_returned').style.display = 'none';
            document.getElementById('row_gold_billed').style.display = 'none';
        }
    } else {
        document.getElementById('row_gold_returned').style.display = 'none';
        document.getElementById('row_gold_billed').style.display = 'none';
    }
    
    let discount = parseFloat(document.getElementById('discount').value) || 0;
    let taxableAmount = Number(Math.max(0, subtotal - discount).toFixed(2));
    
    let cgst = Number((taxableAmount * 2.5 / 100).toFixed(2));
    let sgst = Number((taxableAmount * 2.5 / 100).toFixed(2));
    let net = Number((taxableAmount + cgst + sgst).toFixed(2));
    
    document.getElementById('subtotal_val').innerHTML = subtotal.toFixed(2);
    document.getElementById('cgst_val').innerHTML = cgst.toFixed(2);
    document.getElementById('sgst_val').innerHTML = sgst.toFixed(2);
    document.getElementById('net_val').innerHTML = net.toFixed(2);
    
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    document.getElementById('cgst').value = cgst.toFixed(2);
    document.getElementById('sgst').value = sgst.toFixed(2);
    document.getElementById('net_amount').value = net.toFixed(2);
    
    calculateSettlements(net);
}

function calculateSettlements(netAmountValue) {
    let net = netAmountValue !== undefined ? netAmountValue : (parseFloat(document.getElementById('net_amount').value) || 0);
    let cash = parseFloat(document.getElementsByName('cash_received')[0].value) || 0;
    let bank = parseFloat(document.getElementsByName('bank_received')[0].value) || 0;
    
    let balance = net - (cash + bank);
    if (balance < 0) {
        balance = 0;
    }
    document.getElementById('balance_amount').value = balance.toFixed(2);
}

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('calc-trigger') || e.target.id === 'discount') calculate();
    if (e.target.classList.contains('payment-calc')) calculateSettlements();
});

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('calc-trigger')) calculate();
});

function addRow() {
    let tbody = document.getElementById('itemsBody');
    let sr = tbody.rows.length + 1;
    let row = tbody.insertRow();
    
    row.innerHTML = `
        <td>${sr}</td>
        <td><input type="text" name="description[]" required placeholder="Description of Job Work"></td>
        <td>
            <select name="item_metal_type[]" class="row-metal-type calc-trigger" onchange="calculate()">
                <option value="gold">Gold</option>
                <option value="silver">Silver</option>
            </select>
        </td>
        <td><input type="text" name="sac_code[]" value="9988"></td>
        <td><input type="number" step="0.001" name="quantity[]" class="qty calc-trigger" required placeholder="0.000"></td>
        <td><input type="number" step="0.01" name="making_percent[]" class="making-pct calc-trigger" required placeholder="0.00"></td>
        <td class="amount-cell amount">0.00</td>
        <td style="text-align: center;"><button type="button" class="remove-btn" onclick="removeRow(this)">X</button></td>
    `;
    calculate();
}

function removeRow(btn) {
    let tbody = document.getElementById('itemsBody');
    if(tbody.rows.length > 1){
        btn.parentElement.parentElement.remove();
        Array.from(tbody.rows).forEach((row, idx) => {
            row.cells[0].innerText = idx + 1;
        });
        calculate();
    } else {
        alert("At least one labor service entry row must remain active.");
    }
}

window.addEventListener('DOMContentLoaded', () => {
    calculate();
});
</script>
</body>
</html>