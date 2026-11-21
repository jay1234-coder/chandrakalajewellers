<?php
include '../includes/db.php';

// ─── 1. Auto Bill Number Generation ──────────────────────────────────────────
$year = date('Y');
$result = mysqli_query($conn, "SELECT id FROM bills ORDER BY id DESC LIMIT 1");
if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $next = $row['id'] + 1;
} else {
    $next = 1;
}
$bill_no = $year . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);

// ─── 2. Handle Edit Mode ─────────────────────────────────────────────────────
$edit_mode = false;
$edit_bill = null;
$edit_items = [];
if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $edit_id = intval($_GET['edit']);
    $edit_res = mysqli_query($conn, "SELECT * FROM bills WHERE id = $edit_id AND status != 'CANCELLED'");
    if (mysqli_num_rows($edit_res) > 0) {
        $edit_mode = true;
        $edit_bill = mysqli_fetch_assoc($edit_res);
        $items_res = mysqli_query($conn, "SELECT * FROM bill_items WHERE bill_id = $edit_id");
        while ($r = mysqli_fetch_assoc($items_res)) $edit_items[] = $r;
        $bill_no = $edit_bill['bill_no'];
    }
}

// ─── 3. AJAX: Customer Search ─────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'customer_search') {
    $q = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
    $res = mysqli_query($conn, "SELECT id, name, mobile, address, gst_no, pan_no FROM customers WHERE name LIKE '%$q%' OR mobile LIKE '%$q%' LIMIT 8");
    $customers = [];
    while ($r = mysqli_fetch_assoc($res)) $customers[] = $r;
    header('Content-Type: application/json');
    echo json_encode($customers);
    exit;
}

// ─── 4. Handle Form Submission (Save / Update) ───────────────────────────────
if (isset($_POST['save_bill'])) {
    $is_update = isset($_POST['edit_bill_id']) && intval($_POST['edit_bill_id']) > 0;
    $edit_bill_id = $is_update ? intval($_POST['edit_bill_id']) : null;

    $bill_no_post      = mysqli_real_escape_string($conn, $_POST['bill_no']);
    $bill_date         = mysqli_real_escape_string($conn, $_POST['bill_date']);
    $customer_name     = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $mobile            = mysqli_real_escape_string($conn, $_POST['mobile']);
    $address           = mysqli_real_escape_string($conn, $_POST['address']);
    $gst_no            = mysqli_real_escape_string($conn, $_POST['gst_no']);
    $state_code        = mysqli_real_escape_string($conn, $_POST['state_code']);
    $pan_no            = mysqli_real_escape_string($conn, $_POST['pan_no']);
    $delivery_option   = mysqli_real_escape_string($conn, $_POST['delivery_option']);
    
    $gold_rate_global  = floatval($_POST['gold_rate_global'] ?? 0);
    $silver_rate_global = floatval($_POST['silver_rate_global'] ?? 0);

    $subtotal          = floatval($_POST['subtotal']);
    $discount          = floatval($_POST['discount']);
    $cgst              = floatval($_POST['cgst']);
    $sgst              = floatval($_POST['sgst']);
    $net_amount        = floatval($_POST['net_amount']);

    $cash_received     = floatval($_POST['cash_received']);
    $bank_received     = floatval($_POST['bank_received']);
    $old_gold_amount   = floatval($_POST['old_gold_amount']);
    $balance_amount    = floatval($_POST['balance_amount']);

    mysqli_begin_transaction($conn);

    try {
        // Isolated Customer CRM Sync: never fail or abort bill creation on CRM constraint
        if (!empty($mobile)) {
            try {
                $cust_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM customers WHERE mobile='$mobile' OR phone='$mobile' LIMIT 1"));
                if (!$cust_check) {
                    $dummyHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                    $escaped_hash = mysqli_real_escape_string($conn, $dummyHash);
                    @mysqli_query($conn, "INSERT INTO customers (name, phone, mobile, address, gst_no, pan_no, password_hash, created_at) VALUES ('$customer_name', '$mobile', '$mobile', '$address', '$gst_no', '$pan_no', '$escaped_hash', NOW())");
                } else {
                    @mysqli_query($conn, "UPDATE customers SET name='$customer_name', address='$address', gst_no='$gst_no', pan_no='$pan_no' WHERE mobile='$mobile' OR phone='$mobile'");
                }
            } catch (Throwable $custEx) {
                error_log("Customer sync notice: " . $custEx->getMessage());
            }
        }

        if ($is_update) {
            $sql = "UPDATE bills SET
                bill_no='$bill_no_post', bill_date='$bill_date', customer_name='$customer_name', mobile='$mobile',
                address='$address', gst_no='$gst_no', state_code='$state_code', pan_no='$pan_no', delivery_option='$delivery_option',
                gold_rate='$gold_rate_global', silver_rate='$silver_rate_global', subtotal='$subtotal', discount='$discount', cgst='$cgst', sgst='$sgst',
                net_amount='$net_amount', cash_received='$cash_received', bank_received='$bank_received',
                old_gold_amount='$old_gold_amount', balance_amount='$balance_amount'
                WHERE id=$edit_bill_id";
            
            if (!mysqli_query($conn, $sql)) {
                throw new Exception(mysqli_error($conn));
            }

            if (!mysqli_query($conn, "DELETE FROM bill_items WHERE bill_id=$edit_bill_id")) {
                throw new Exception(mysqli_error($conn));
            }
            $saved_bill_id = $edit_bill_id;
        } else {
            $bill_check = mysqli_query($conn, "SELECT id FROM bills WHERE bill_no='$bill_no_post'");
            if (mysqli_num_rows($bill_check) > 0) {
                throw new Exception("Duplicate Bill Number Detected.");
            }

            $sql = "INSERT INTO bills (
                bill_no, bill_date, customer_name, mobile, address, gst_no, state_code, pan_no, delivery_option,
                gold_rate, silver_rate, subtotal, discount, cgst, sgst, net_amount,
                cash_received, bank_received, old_gold_amount, balance_amount, bill_type, status
            ) VALUES (
                '$bill_no_post','$bill_date','$customer_name','$mobile','$address','$gst_no','$state_code','$pan_no','$delivery_option',
                '$gold_rate_global','$silver_rate_global','$subtotal','$discount','$cgst','$sgst','$net_amount',
                '$cash_received','$bank_received','$old_gold_amount','$balance_amount','SALE','ACTIVE'
            )";
            
            if (mysqli_query($conn, $sql)) {
                $saved_bill_id = (int)mysqli_insert_id($conn);
                if ($saved_bill_id <= 0) {
                    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM bills WHERE bill_no='$bill_no_post' ORDER BY id DESC LIMIT 1"));
                    if ($r) $saved_bill_id = (int)$r['id'];
                }
            } else {
                throw new Exception("Could not save initial bill structure: " . mysqli_error($conn));
            }
        }

        if ($saved_bill_id > 0) {
            foreach ($_POST['description'] as $key => $description) {
                $desc        = mysqli_real_escape_string($conn, $description);
                $metal_type  = mysqli_real_escape_string($conn, $_POST['item_metal_type'][$key]);
                $purity_num  = floatval($_POST['purity_val'][$key]);
                $hsn         = mysqli_real_escape_string($conn, $_POST['hsn_code'][$key]);
                $qty         = floatval($_POST['quantity'][$key]);
                $rate_saved  = floatval($_POST['rate'][$key]);
                $making_pct  = floatval($_POST['making'][$key]);

                if (strpos(strtolower($metal_type), 'silver') !== false) {
                    $making_flat = $silver_rate_global * ($making_pct / 100);
                    $purity_num = 0;
                } else {
                    $making_flat = $gold_rate_global * ($making_pct / 100);
                }

                // FIXED FORMULA: (Rate + Making Charge per gram) * Quantity
                $total_amount = number_format((($rate_saved + $making_flat) * $qty), 2, '.', '');

                $insert_item = mysqli_query($conn, "
                    INSERT INTO bill_items (
                        bill_id, description, hsn_code, quantity, rate, making_percent, gold_value, item_metal_type, making_amount, total_amount
                    ) VALUES (
                        '$saved_bill_id', '$desc', '$hsn', '$qty', '$rate_saved', '$making_pct', " . ($purity_num / 100) . ", '$metal_type', '$making_flat', '$total_amount'
                    )
                ");

                if (!$insert_item) {
                    throw new Exception(mysqli_error($conn));
                }
            }

            mysqli_commit($conn);

            echo "<script>
                alert('" . ($is_update ? 'Bill Updated Successfully!' : 'Bill Saved Successfully!') . "');
                window.location.href = 'print_bill.php?id=$saved_bill_id';
            </script>";
            exit;
        } else {
            throw new Exception("Invalid billing reference ID.");
        }

    } catch (Exception $e) {
        mysqli_rollback($conn);
        die("Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $edit_mode ? 'Edit Bill' : 'Create Sale Bill' ?> - Chandrakala Jewellers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; margin: 0; padding: 0; }
        .container { display: flex; }
        .main { flex: 1; padding: 25px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .page-header h1 { margin: 0; color: #111827; font-size: 22px; }
        .badge-edit { background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; border: 1px solid #fcd34d; }
        .bill-box { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.05); }
        .section-title { font-size: 14px; font-weight: 700; color: #374151; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin: 24px 0 14px 0; text-transform: uppercase; }
        .row { display: flex; gap: 16px; margin-bottom: 14px; flex-wrap: wrap; }
        .field { flex: 1; min-width: 180px; display: flex; flex-direction: column; }
        label { margin-bottom: 5px; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; }
        input, select { padding: 10px 12px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; background: #fff; }
        input:focus, select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .customer-wrapper { position: relative; }
        #customer-suggestions { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1.5px solid #3b82f6; border-top: none; border-radius: 0 0 8px 8px; z-index: 100; max-height: 220px; overflow-y: auto; }
        .suggestion-item { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f3f4f6; }
        .suggestion-item:hover { background: #eff6ff; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px 8px; border: 1px solid #e5e7eb; text-align: left; font-size: 13px; }
        th { background: #f8fafc; font-weight: 700; color: #374151; font-size: 12px; text-transform: uppercase; }
        .amount-cell { font-weight: 700; text-align: right; background: #fafafa; color: #111827; }
        table input, table select { width: 100%; box-sizing: border-box; border: 1px solid #e0e0e0; padding: 6px 8px; border-radius: 5px; }
        .add-btn { background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; margin-top: 12px; }
        .remove-btn { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; padding: 5px 10px; border-radius: 5px; cursor: pointer; font-weight: 700; }
        .flex-summary { display: flex; justify-content: space-between; margin-top: 28px; gap: 30px; flex-wrap: wrap; }
        .payment-settlement { flex: 1; min-width: 300px; }
        .financial-summary { min-width: 340px; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td { padding: 9px 12px; border: none; font-size: 14px; }
        .summary-table tr { border-bottom: 1px solid #f0f0f0; }
        .summary-table tr.grand-total { border-top: 2px solid #111827; border-bottom: 2px solid #111827; font-size: 16px; font-weight: 700; background: #f9fafb; }
        .actions { margin-top: 28px; border-top: 1px solid #e5e7eb; padding-top: 20px; display: flex; gap: 12px; justify-content: flex-end; }
        .btn-submit { background: #2563eb; color: white; border: none; padding: 12px 32px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 15px; }
        .btn-cancel-action { background: #f1f5f9; color: #374151; border: 1.5px solid #d1d5db; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <?php $active_page = 'sale_bill'; include '../includes/sidebar.php'; ?>
    <main class="main">
        <div class="page-header">
            <h1><i class="fas fa-file-invoice" style="color:#2563eb; margin-right:8px;"></i><?= $edit_mode ? 'Edit Tax Invoice' : 'New Tax Invoice' ?></h1>
            <?php if ($edit_mode): ?>
                <span class="badge-edit"><i class="fas fa-edit"></i> Editing Bill #<?= $edit_bill['bill_no'] ?></span>
            <?php endif; ?>
        </div>

        <form method="POST" id="billForm">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="edit_bill_id" value="<?= $edit_bill['id'] ?>">
            <?php endif; ?>

            <div class="bill-box">
                <div class="row">
                    <div class="field">
                        <label>Bill Invoice No</label>
                        <input type="text" name="bill_no" value="<?= htmlspecialchars($bill_no) ?>" style="border-color: #3b82f6; font-weight: bold;">
                    </div>
                    <div class="field">
                        <label>Date</label>
                        <input type="date" name="bill_date" value="<?= $edit_mode ? $edit_bill['bill_date'] : date('Y-m-d') ?>" required>
                    </div>
                    <div class="field">
                        <label>Gold Rate (₹ / Gram)</label>
                        <input type="number" step="0.01" id="gold_rate_global" name="gold_rate_global" value="<?= $edit_mode ? $edit_bill['gold_rate'] : '10000.00' ?>" class="calc-trigger-global" style="font-weight: bold; border-color: #2563eb;">
                    </div>
                    <div class="field">
                        <label>Silver Rate (₹ / Gram)</label>
                        <input type="number" step="0.01" id="silver_rate_global" name="silver_rate_global" value="<?= $edit_mode && isset($edit_bill['silver_rate']) ? $edit_bill['silver_rate'] : '100.00' ?>" class="calc-trigger-global" style="font-weight: bold; border-color: #10b981;">
                    </div>
                    <div class="field">
                        <label>Delivery Option</label>
                        <select name="delivery_option" style="font-weight:600; border-color:#3b82f6;">
                            <option value="At Shop" <?= ($edit_mode && $edit_bill['delivery_option'] === 'At Shop') ? 'selected' : '' ?>>At Shop</option>
                            <option value="At Mcx platform" <?= ($edit_mode && $edit_bill['delivery_option'] === 'At Mcx platform') ? 'selected' : '' ?>>At Mcx platform</option>
                        </select>
                    </div>
                </div>

                <div class="section-title"><i class="fas fa-user" style="margin-right:6px;"></i>Customer / Buyer Details</div>
                <div class="row">
                    <div class="field customer-wrapper" style="flex:2;">
                        <label>Customer Name</label>
                        <input type="text" name="customer_name" id="customer_name" value="<?= $edit_mode ? htmlspecialchars($edit_bill['customer_name']) : '' ?>" placeholder="Search client name..." required autocomplete="off" oninput="searchCustomers(this.value)">
                        <div id="customer-suggestions"></div>
                    </div>
                    <div class="field">
                        <label>Mobile Number</label>
                        <input type="text" name="mobile" id="mobile" value="<?= $edit_mode ? htmlspecialchars($edit_bill['mobile']) : '' ?>" placeholder="10-digit mobile" oninput="searchCustomers(this.value)">
                    </div>
                </div>
                <div class="row">
                    <div class="field" style="flex:2;">
                        <label>Address</label>
                        <input type="text" name="address" id="address" value="<?= $edit_mode ? htmlspecialchars($edit_bill['address']) : '' ?>" placeholder="Complete Address">
                    </div>
                </div>
                <div class="row">
                    <div class="field">
                        <label>GSTIN OR AADHAAR NO.</label>
                        <input type="text" name="gst_no" id="gst_no" value="<?= $edit_mode ? htmlspecialchars($edit_bill['gst_no']) : '' ?>">
                    </div>
                    <div class="field">
                        <label>State Code</label>
                        <input type="text" name="state_code" id="state_code" value="<?= $edit_mode ? htmlspecialchars($edit_bill['state_code']) : '24' ?>">
                    </div>
                    <div class="field">
                        <label>PAN No</label>
                        <input type="text" name="pan_no" id="pan_no" value="<?= $edit_mode ? htmlspecialchars($edit_bill['pan_no']) : '' ?>">
                    </div>
                </div>

                <div class="section-title"><i class="fas fa-list" style="margin-right:6px;"></i>Description of Items</div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:4%;">Sr</th>
                            <th style="width:14%;">Type of Good</th>
                            <th style="width:20%;">Description of Good</th>
                            <th style="width:10%;">Purity (%)</th>
                            <th style="width:10%;">HSNCODE</th>
                            <th style="width:10%;">Weight (Gms)</th>
                            <th style="width:12%;">Rate / 1g (Editable)</th>
                            <th style="width:8%;">Making %</th>
                            <th style="width:10%;">Amount (₹)</th>
                            <th style="width:4%;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <?php if ($edit_mode && !empty($edit_items)): ?>
                            <?php foreach ($edit_items as $idx => $ei): ?>
                            <?php 
                                $item_metal = !empty($ei['item_metal_type']) ? trim(strtolower($ei['item_metal_type'])) : trim(strtolower($ei['making_amount']));
                                $is_sil = (strpos($item_metal, 'silver') !== false); 
                            ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <select name="item_metal_type[]" class="row-metal-type" onchange="handleMetalChange(this)">
                                        <option value="gold" <?= ($item_metal === 'gold') ? 'selected' : '' ?>>Gold Ornaments</option>
                                        <option value="silver" <?= ($item_metal === 'silver') ? 'selected' : '' ?>>Silver Ornaments</option>
                                        <option value="Gold bullion" <?= ($item_metal === 'gold bullion') ? 'selected' : '' ?>>Gold Bullion</option>
                                        <option value="Silver bullion" <?= ($item_metal === 'silver bullion') ? 'selected' : '' ?>>Silver Bullion</option>
                                    </select>
                                </td>
                                <td><input type="text" name="description[]" value="<?= htmlspecialchars($ei['description']) ?>" required></td>
                                <td><input type="number" step="0.01" name="purity_val[]" class="purity-input calc-trigger" value="<?= $is_sil ? '' : floatval($ei['gold_value']) * 100 ?>" <?= $is_sil ? 'readonly style="background:#f3f4f6; border-color:#e5e7eb;"' : '' ?> placeholder="-"></td>
                                <td><input type="text" name="hsn_code[]" value="<?= htmlspecialchars($ei['hsn_code']) ?>" style="text-align: center; font-weight:600;"></td>
                                <td><input type="number" step="0.001" name="quantity[]" class="qty calc-trigger" value="<?= $ei['quantity'] ?>" required></td>
                                <td><input type="number" step="0.01" name="rate[]" class="rate-input manual-rate-trigger" data-edited="true" value="<?= number_format($ei['rate'], 2, '.', '') ?>" style="text-align:right; font-weight:600; border-color:#2563eb;"></td>
                                <td><input type="number" step="0.01" name="making[]" class="making calc-trigger" value="<?= $ei['making_percent'] ?>"></td>
                                <td class="amount-cell amount">0.00</td>
                                <td style="text-align:center;"><button type="button" class="remove-btn" onclick="removeRow(this)">✕</button></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td>1</td>
                            <td>
                                <select name="item_metal_type[]" class="row-metal-type" onchange="handleMetalChange(this)">
                                    <option value="gold">Gold Ornaments</option>
                                    <option value="silver">Silver Ornaments</option>
                                    <option value="Gold bullion">Gold Bullion</option>
                                    <option value="Silver bullion">Silver Bullion</option>
                                </select>
                            </td>
                            <td><input type="text" name="description[]" required placeholder="Item description"></td>
                            <td><input type="number" step="0.01" name="purity_val[]" class="purity-input calc-trigger" value="92" placeholder="e.g. 92"></td>
                            <td><input type="text" name="hsn_code[]" value="7113" style="text-align: center; font-weight:600;"></td>
                            <td><input type="number" step="0.001" name="quantity[]" class="qty calc-trigger" required placeholder="0.000"></td>
                            <td><input type="number" step="0.01" name="rate[]" class="rate-input manual-rate-trigger" value="0.00" style="text-align:right; font-weight:600; border-color:#2563eb;"></td>
                            <td><input type="number" step="0.01" name="making[]" class="making calc-trigger" value="16.00"></td>
                            <td class="amount-cell amount">0.00</td>
                            <td style="text-align:center;"><button type="button" class="remove-btn" onclick="removeRow(this)">✕</button></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="button" class="add-btn" onclick="addRow()"><i class="fas fa-plus"></i> Add Next Item</button>

                <div class="flex-summary">
                    <div class="payment-settlement">
                        <div class="section-title" style="margin-top:0;">Payment Breakdown</div>
                        <div class="row">
                            <div class="field">
                                <label>Cash Received (₹)</label>
                                <input type="number" step="0.01" name="cash_received" class="payment-calc" value="<?= $edit_mode ? $edit_bill['cash_received'] : '0.00' ?>">
                            </div>
                            <div class="field">
                                <label>Bank / UPI (₹)</label>
                                <input type="number" step="0.01" name="bank_received" class="payment-calc" value="<?= $edit_mode ? $edit_bill['bank_received'] : '0.00' ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="field">
                                <label>Old Metal Exchanged (₹)</label>
                                <input type="number" step="0.01" name="old_gold_amount" class="payment-calc" value="<?= $edit_mode ? $edit_bill['old_gold_amount'] : '0.00' ?>">
                            </div>
                            <div class="field">
                                <label>Remaining Balance (₹)</label>
                                <input type="number" step="0.01" name="balance_amount" id="balance_amount" readonly style="font-weight:700; color:#dc2626; background:#fef2f2;" value="<?= $edit_mode ? $edit_bill['balance_amount'] : '0.00' ?>">
                            </div>
                        </div>
                    </div>

                    <div class="financial-summary">
                        <div class="section-title" style="margin-top:0;">Tax Computations</div>
                        <table class="summary-table">
                            <tr><td>Subtotal</td><td id="subtotal_val" style="text-align:right; font-weight:600;">0.00</td></tr>
                            <tr>
                                <td>Discount</td>
                                <td><input type="number" step="0.01" name="discount" id="discount" value="<?= $edit_mode ? $edit_bill['discount'] : '0.00' ?>" style="text-align:right; width:130px; float:right;"></td>
                            </tr>
                            <tr><td>CGST (1.5%)</td><td id="cgst_val" style="text-align:right;">0.00</td></tr>
                            <tr><td>SGST (1.5%)</td><td id="sgst_val" style="text-align:right;">0.00</td></tr>
                            <tr class="grand-total">
                                <td>Grand Total</td>
                                <td id="net_val" style="text-align:right; color:#2563eb;">0.00</td>
                            </tr>
                        </table>
                        <input type="hidden" id="subtotal" name="subtotal" value="0">
                        <input type="hidden" id="cgst" name="cgst" value="0">
                        <input type="hidden" id="sgst" name="sgst" value="0">
                        <input type="hidden" id="net_amount" name="net_amount" value="0">
                    </div>
                </div>

                <div class="actions">
                    <a href="bill_history.php" class="btn-cancel-action"><i class="fas fa-times"></i> Cancel</a>
                    <button type="submit" name="save_bill" class="btn-submit"><i class="fas fa-save"></i> <?= $edit_mode ? 'Update Bill & Reprint' : 'Save Bill & Generate Invoice' ?></button>
                </div>
            </div>
        </form>
    </main>
</div>

<script>
function searchCustomers(q) {
    if (q.length < 2) { hideSuggestions(); return; }
    fetch(`sale_bill.php?ajax=customer_search&q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(customers => {
            const box = document.getElementById('customer-suggestions');
            if (!customers.length) { box.innerHTML = ''; return; }
            box.innerHTML = customers.map(c => `
                <div class="suggestion-item" onclick="fillCustomer(${JSON.stringify(c).replace(/"/g, '&quot;')})">
                    <strong>${c.name}</strong><small>${c.mobile}</small>
                </div>`).join('');
        });
}
function fillCustomer(c) {
    document.getElementById('customer_name').value = c.name;
    document.getElementById('mobile').value = c.mobile;
    document.getElementById('address').value = c.address || '';
    document.getElementById('gst_no').value = c.gst_no || '';
    document.getElementById('pan_no').value = c.pan_no || '';
    hideSuggestions();
}
function hideSuggestions() { document.getElementById('customer-suggestions').innerHTML = ''; }
document.addEventListener('click', e => { if (!e.target.closest('.customer-wrapper')) hideSuggestions(); });

function handleMetalChange(sel) {
    const row = sel.closest('tr');
    const purityInp = row.querySelector('.purity-input');
    const makingInp = row.querySelector('.making');
    const rateInp = row.querySelector('.rate-input');
    
    rateInp.removeAttribute('data-edited');

    if (sel.value.toLowerCase().includes('silver')) {
        purityInp.readOnly = true;
        purityInp.style.background = "#f3f4f6";
        purityInp.style.borderColor = "#e5e7eb";
        purityInp.value = "";
        purityInp.placeholder = "-";
        makingInp.value = "10.00";
    } else {
        purityInp.readOnly = false;
        purityInp.style.background = "#fff";
        purityInp.style.borderColor = "#d1d5db";
        purityInp.value = "92";
        purityInp.placeholder = "e.g. 92";
        makingInp.value = "16.00";
    }
    calculate(true);
}

function calculate(autoFillRates = false) {
    let subtotal = 0;

    const goldRateGlobal = parseFloat(document.getElementById('gold_rate_global').value) || 0;
    const silverRateGlobal = parseFloat(document.getElementById('silver_rate_global').value) || 0;

    document.querySelectorAll('#itemsBody tr').forEach(row => {
        const metalType = row.querySelector('.row-metal-type').value.toLowerCase();
        const purityPct = parseFloat(row.querySelector('.purity-input').value) || 0;
        const qty       = parseFloat(row.querySelector('.qty').value) || 0;
        const makingPct = parseFloat(row.querySelector('.making').value) || 0;
        const rateInp   = row.querySelector('.rate-input');

        const hasBeenEdited = rateInp.getAttribute('data-edited') === 'true';
        let total = 0;

        if (metalType.includes('silver')) {
            if (autoFillRates && (!hasBeenEdited || parseFloat(rateInp.value) === 0)) {
                rateInp.value = (silverRateGlobal / 1.03).toFixed(2);
            }

            let activeRate = parseFloat(rateInp.value) || 0;
            let makingFlat = silverRateGlobal * (makingPct / 100);
            
            // FIXED FORMULA: (Rate + Making) * Weight
            total = (activeRate + makingFlat) * qty;

        } else {
            if (autoFillRates && (!hasBeenEdited || parseFloat(rateInp.value) === 0)) {
                let ratePerGram = (goldRateGlobal * (purityPct / 100));
                rateInp.value = (ratePerGram / 1.03).toFixed(2);
            }

            let activeRate = parseFloat(rateInp.value) || 0;
            let makingFlat = goldRateGlobal * (makingPct / 100);
            
            // FIXED FORMULA: (Rate + Making) * Weight
            total = (activeRate + makingFlat) * qty;
        }

        total = Number(total.toFixed(2));
        row.querySelector('.amount').textContent = total.toFixed(2);
        subtotal += total;
    });

    subtotal = Number(subtotal.toFixed(2));
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const taxable = Number((subtotal - discount).toFixed(2));

    const cgst = Number((taxable * 1.5 / 100).toFixed(2));
    const sgst = Number((taxable * 1.5 / 100).toFixed(2));
    const net = Number((taxable + cgst + sgst).toFixed(2));

    document.getElementById('subtotal_val').textContent = subtotal.toFixed(2);
    document.getElementById('cgst_val').textContent = cgst.toFixed(2);
    document.getElementById('sgst_val').textContent = sgst.toFixed(2);
    document.getElementById('net_val').textContent = net.toFixed(2);

    document.getElementById('subtotal').value = subtotal.toFixed(2);
    document.getElementById('cgst').value = cgst.toFixed(2);
    document.getElementById('sgst').value = sgst.toFixed(2);
    document.getElementById('net_amount').value = net.toFixed(2);

    calculateSettlements(net);
}

function calculateSettlements(net) {
    if (net === undefined) net = parseFloat(document.getElementById('net_amount').value) || 0;

    const cash = parseFloat(document.querySelector('[name=cash_received]').value) || 0;
    const bank = parseFloat(document.querySelector('[name=bank_received]').value) || 0;
    const old  = parseFloat(document.querySelector('[name=old_gold_amount]').value) || 0;

    let balance = net - (cash + bank + old);
    if (balance < 0) balance = 0;

    document.getElementById('balance_amount').value = balance.toFixed(2);
}

function addRow() {
    const tbody = document.getElementById('itemsBody');
    const sr = tbody.rows.length + 1;

    const row = tbody.insertRow();
    row.innerHTML = `
        <td>${sr}</td>
        <td>
            <select name="item_metal_type[]" class="row-metal-type" onchange="handleMetalChange(this)">
                <option value="gold">Gold Ornaments</option>
                <option value="silver">Silver Ornaments</option>
                <option value="Gold bullion">Gold Bullion</option>
                <option value="Silver bullion">Silver Bullion</option>
            </select>
        </td>
        <td><input type="text" name="description[]" required placeholder="Item Details"></td>
        <td><input type="number" step="0.01" name="purity_val[]" class="purity-input calc-trigger" value="92"></td>
        <td><input type="text" name="hsn_code[]" value="7113" style="text-align: center; font-weight:600;"></td>
        <td><input type="number" step="0.001" name="quantity[]" class="qty calc-trigger" required></td>
        <td><input type="number" step="0.01" name="rate[]" class="rate-input manual-rate-trigger" value="0.00" style="text-align:right; font-weight:600; border-color:#2563eb;"></td>
        <td><input type="number" step="0.01" name="making[]" class="making calc-trigger" value="16.00"></td>
        <td class="amount-cell amount">0.00</td>
        <td style="text-align:center;"><button type="button" class="remove-btn" onclick="removeRow(this)">✕</button></td>
    `;
    calculate(true);
}

function removeRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.rows.length > 1) {
        btn.closest('tr').remove();
        Array.from(tbody.rows).forEach((row, idx) => { row.cells[0].textContent = idx + 1; });
        calculate(false);
    }
}

document.addEventListener('input', e => {
    if (e.target.classList.contains('manual-rate-trigger')) {
        e.target.setAttribute('data-edited', 'true');
        calculate(false);
    }
    if (e.target.classList.contains('calc-trigger') || e.target.id === 'discount') {
        calculate(true);
    }
    if (e.target.classList.contains('payment-calc')) {
        calculateSettlements();
    }
});

document.getElementById('gold_rate_global').addEventListener('input', () => {
    document.querySelectorAll('.rate-input').forEach(inp => inp.removeAttribute('data-edited'));
    calculate(true);
});
document.getElementById('silver_rate_global').addEventListener('input', () => {
    document.querySelectorAll('.rate-input').forEach(inp => inp.removeAttribute('data-edited'));
    calculate(true);
});

document.addEventListener('change', e => { if (e.target.classList.contains('calc-trigger')) calculate(true); });
window.addEventListener('DOMContentLoaded', () => { calculate(true); });
</script>
</body>
</html>