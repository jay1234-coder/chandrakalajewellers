<?php
/**
 * ChandraKala Jewellers — In-Store Billing (POS) Native Controller
 * Handles form submissions, transactions, and AJAX search endpoints for native Admin POS.
 */

// Only activate when billing view or billing action is requested
$is_billing_req = (isset($_GET['view']) && $_GET['view'] === 'billing')
    || (isset($_POST['view']) && $_POST['view'] === 'billing')
    || isset($_GET['billing_ajax'])
    || isset($_POST['save_sale_bill'])
    || isset($_POST['save_labour_bill'])
    || isset($_POST['delete_bill'])
    || isset($_POST['record_khata_payment']);

if (!$is_billing_req) {
    return;
}

// Ensure database tables exist
require_once dirname(__DIR__) . '/billing-software/includes/db.php';
if (isset($conn) && $conn instanceof mysqli) {
    @mysqli_query($conn, "SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
}
date_default_timezone_set('Asia/Kolkata');

// Helper URL generator
$billing_url = function (string $tab = 'sale', array $params = []): string {
    $base = '/admin/index.php?view=billing&tab=' . urlencode($tab);
    foreach ($params as $k => $v) {
        $base .= '&' . urlencode($k) . '=' . urlencode((string)$v);
    }
    return $base;
};

// ─── 1. AJAX: Customer Autocomplete Search ─────────────────────────────────────
if (isset($_GET['billing_ajax']) && $_GET['billing_ajax'] === 'customer_search') {
    header('Content-Type: application/json; charset=UTF-8');
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q === '') {
        echo json_encode([]);
        exit;
    }

    $searchTerm = '%' . $q . '%';
    $stmt = $conn->prepare("
        SELECT id, name, COALESCE(mobile, phone, '') AS mobile, COALESCE(address, '') AS address, 
               COALESCE(gst_no, '') AS gst_no, COALESCE(pan_no, '') AS pan_no
        FROM customers
        WHERE name LIKE ? OR mobile LIKE ? OR phone LIKE ?
        ORDER BY id DESC
        LIMIT 8
    ");

    if ($stmt) {
        $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $res = $stmt->get_result();
        $customers = [];
        while ($r = $res->fetch_assoc()) {
            $customers[] = [
                'id'      => (int)$r['id'],
                'name'    => $r['name'],
                'mobile'  => $r['mobile'],
                'address' => $r['address'],
                'gst_no'  => $r['gst_no'],
                'pan_no'  => $r['pan_no']
            ];
        }
        $stmt->close();
        echo json_encode($customers);
    } else {
        echo json_encode([]);
    }
    exit;
}

// ─── 2. AJAX: Customer Khata Ledger History ───────────────────────────────────
if (isset($_GET['billing_ajax']) && $_GET['billing_ajax'] === 'customer_ledger') {
    header('Content-Type: application/json; charset=UTF-8');
    $name = trim((string)($_GET['customer_name'] ?? ''));
    $mobile = trim((string)($_GET['mobile'] ?? ''));

    if ($name === '' && $mobile === '') {
        echo json_encode(['error' => 'Missing customer identifier']);
        exit;
    }

    $where = [];
    $types = '';
    $params = [];

    if ($name !== '') {
        $where[] = "customer_name = ?";
        $types .= 's';
        $params[] = $name;
    }
    if ($mobile !== '' && $mobile !== 'N/A') {
        $where[] = "(mobile = ?)";
        $types .= 's';
        $params[] = $mobile;
    }

    $sql = "SELECT id, bill_no, bill_date, bill_type, net_amount, cash_received, bank_received, old_gold_amount, balance_amount, status 
            FROM bills WHERE " . implode(' OR ', $where) . " ORDER BY bill_date DESC, id DESC LIMIT 50";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $bills = [];
        while ($r = $res->fetch_assoc()) {
            $bills[] = $r;
        }
        $stmt->close();
        echo json_encode(['success' => true, 'bills' => $bills]);
    } else {
        echo json_encode(['error' => 'Query error']);
    }
    exit;
}

// ─── 3. POST: Save / Update Sale Bill ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sale_bill'])) {
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        $_SESSION['billing_toast'] = ['type' => 'error', 'msg' => 'Security check failed (CSRF). Please refresh and submit again.'];
        header('Location: ' . $billing_url('sale'));
        exit;
    }

    $is_update     = isset($_POST['edit_bill_id']) && intval($_POST['edit_bill_id']) > 0;
    $edit_bill_id  = $is_update ? intval($_POST['edit_bill_id']) : null;
    $save_and_print = !empty($_POST['save_and_print']);

    $bill_no       = trim((string)($_POST['bill_no'] ?? ''));
    $bill_date     = trim((string)($_POST['bill_date'] ?? date('Y-m-d')));
    $customer_name = trim((string)($_POST['customer_name'] ?? ''));
    $mobile        = trim((string)($_POST['mobile'] ?? ''));
    $address       = trim((string)($_POST['address'] ?? ''));
    $gst_no        = trim((string)($_POST['gst_no'] ?? ''));
    $state_code    = trim((string)($_POST['state_code'] ?? '24 - Gujarat'));
    $pan_no        = trim((string)($_POST['pan_no'] ?? ''));
    $delivery_opt  = trim((string)($_POST['delivery_option'] ?? 'Hand Delivery'));

    $gold_rate     = floatval($_POST['gold_rate_global'] ?? 0);
    $silver_rate   = floatval($_POST['silver_rate_global'] ?? 0);

    $subtotal      = floatval($_POST['subtotal'] ?? 0);
    $discount      = floatval($_POST['discount'] ?? 0);
    $cgst          = floatval($_POST['cgst'] ?? 0);
    $sgst          = floatval($_POST['sgst'] ?? 0);
    $net_amount    = floatval($_POST['net_amount'] ?? 0);

    $cash_rec      = floatval($_POST['cash_received'] ?? 0);
    $bank_rec      = floatval($_POST['bank_received'] ?? 0);
    $old_gold_amt  = floatval($_POST['old_gold_amount'] ?? 0);
    $balance_amt   = floatval($_POST['balance_amount'] ?? 0);

    if ($customer_name === '') {
        $_SESSION['billing_toast'] = ['type' => 'error', 'msg' => 'Customer name is required.'];
        header('Location: ' . $billing_url('sale'));
        exit;
    }

    // Auto-generate Bill Number if not provided
    if ($bill_no === '') {
        $year = date('Y');
        $qId = $conn->query("SELECT id FROM bills ORDER BY id DESC LIMIT 1");
        $nextId = ($qId && $qId->num_rows > 0) ? ((int)$qId->fetch_assoc()['id'] + 1) : 1;
        $bill_no = $year . '-' . str_pad((string)$nextId, 3, '0', STR_PAD_LEFT);
    }

    $conn->begin_transaction();
    try {
        // Sync customer record (isolated so secondary CRM sync never blocks or aborts bill creation)
        if ($mobile !== '') {
            try {
                $custCheck = $conn->prepare("SELECT id FROM customers WHERE mobile = ? OR phone = ? LIMIT 1");
                if ($custCheck) {
                    $custCheck->bind_param('ss', $mobile, $mobile);
                    $custCheck->execute();
                    $custRes = $custCheck->get_result();
                    if ($custRes && $custRes->num_rows === 0) {
                        // In-store walk-in POS customer: generate dummy hash so NOT NULL password_hash constraint is satisfied
                        $dummyHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                        $insCust = $conn->prepare("INSERT INTO customers (name, phone, mobile, address, gst_no, pan_no, password_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        if ($insCust) {
                            $insCust->bind_param('sssssss', $customer_name, $mobile, $mobile, $address, $gst_no, $pan_no, $dummyHash);
                            $insCust->execute();
                            $insCust->close();
                        }
                    } else {
                        // Update customer profile with latest address / GST
                        $updCust = $conn->prepare("UPDATE customers SET name = ?, address = ?, gst_no = ?, pan_no = ? WHERE mobile = ? OR phone = ?");
                        if ($updCust) {
                            $updCust->bind_param('ssssss', $customer_name, $address, $gst_no, $pan_no, $mobile, $mobile);
                            $updCust->execute();
                            $updCust->close();
                        }
                    }
                    $custCheck->close();
                }
            } catch (Throwable $custErr) {
                // Secondary CRM sync error must never fail the sale invoice
                error_log("Customer CRM sync notice: " . $custErr->getMessage());
            }
        }

        $saved_bill_id = 0;
        if ($is_update) {
            $stmt = $conn->prepare("
                UPDATE bills SET
                    bill_no=?, bill_date=?, customer_name=?, mobile=?, address=?, gst_no=?, state_code=?, pan_no=?, delivery_option=?,
                    gold_rate=?, silver_rate=?, subtotal=?, discount=?, cgst=?, sgst=?, net_amount=?,
                    cash_received=?, bank_received=?, old_gold_amount=?, balance_amount=?
                WHERE id=?
            ");
            $stmt->bind_param(
                'sssssssssdddddddddddi',
                $bill_no, $bill_date, $customer_name, $mobile, $address, $gst_no, $state_code, $pan_no, $delivery_opt,
                $gold_rate, $silver_rate, $subtotal, $discount, $cgst, $sgst, $net_amount,
                $cash_rec, $bank_rec, $old_gold_amt, $balance_amt, $edit_bill_id
            );
            $stmt->execute();
            $stmt->close();

            // Clear old items
            $delItems = $conn->prepare("DELETE FROM bill_items WHERE bill_id = ?");
            $delItems->bind_param('i', $edit_bill_id);
            $delItems->execute();
            $delItems->close();

            $saved_bill_id = $edit_bill_id;
        } else {
            // Check for duplicate bill number
            $dupCheck = $conn->prepare("SELECT id FROM bills WHERE bill_no = ? LIMIT 1");
            $dupCheck->bind_param('s', $bill_no);
            $dupCheck->execute();
            if ($dupCheck->get_result()->num_rows > 0) {
                // Append suffix if duplicate
                $bill_no .= '-' . time();
            }
            $dupCheck->close();

            $stmt = $conn->prepare("
                INSERT INTO bills (
                    bill_no, bill_date, customer_name, mobile, address, gst_no, state_code, pan_no, delivery_option,
                    gold_rate, silver_rate, subtotal, discount, cgst, sgst, net_amount,
                    cash_received, bank_received, old_gold_amount, balance_amount, bill_type, status, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, 'SALE', 'ACTIVE', NOW()
                )
            ");
            $stmt->bind_param(
                'sssssssssddddddddddd',
                $bill_no, $bill_date, $customer_name, $mobile, $address, $gst_no, $state_code, $pan_no, $delivery_opt,
                $gold_rate, $silver_rate, $subtotal, $discount, $cgst, $sgst, $net_amount,
                $cash_rec, $bank_rec, $old_gold_amt, $balance_amt
            );
            $stmt->execute();
            $saved_bill_id = (int)$conn->insert_id;
            $stmt->close();
        }

        // Insert items
        if ($saved_bill_id > 0 && !empty($_POST['description']) && is_array($_POST['description'])) {
            $itemStmt = $conn->prepare("
                INSERT INTO bill_items (
                    bill_id, description, hsn_code, quantity, rate, making_percent, gold_value, item_metal_type, making_amount, total_amount
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($_POST['description'] as $k => $desc) {
                $desc = trim((string)$desc);
                if ($desc === '') continue;

                $metal      = trim((string)($_POST['item_metal_type'][$k] ?? 'Gold 22K'));
                $purity_num = floatval($_POST['purity_val'][$k] ?? 91.6);
                $hsn        = trim((string)($_POST['hsn_code'][$k] ?? '7113'));
                $qty        = floatval($_POST['quantity'][$k] ?? 0);
                $rate_val   = floatval($_POST['rate'][$k] ?? 0);
                $making_pct = floatval($_POST['making'][$k] ?? 0);

                if (stripos($metal, 'silver') !== false) {
                    $making_flat = $silver_rate * ($making_pct / 100);
                    $gold_val_dec = 0;
                } else {
                    $making_flat = $gold_rate * ($making_pct / 100);
                    $gold_val_dec = $purity_num / 100;
                }

                $total_item_amt = ($rate_val + $making_flat) * $qty;

                $itemStmt->bind_param(
                    'issddddsdd',
                    $saved_bill_id, $desc, $hsn, $qty, $rate_val, $making_pct, $gold_val_dec, $metal, $making_flat, $total_item_amt
                );
                $itemStmt->execute();
            }
            $itemStmt->close();
        }

        $conn->commit();

        if (function_exists('cj_log_activity')) {
            cj_log_activity($conn, 'billing', "Saved GST Sale Invoice #{$bill_no} for {$customer_name} (₹" . number_format($net_amount, 2) . ")");
        }

        $_SESSION['billing_toast'] = [
            'type' => 'success',
            'msg'  => "Invoice #{$bill_no} successfully saved!",
            'print_id' => $save_and_print ? $saved_bill_id : null,
            'bill_no' => $bill_no
        ];

        $redirectTab = $save_and_print ? 'sale' : 'history';
        $extraParams = $save_and_print ? ['print_id' => $saved_bill_id] : [];
        header('Location: ' . $billing_url($redirectTab, $extraParams));
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['billing_toast'] = ['type' => 'error', 'msg' => 'Failed to save bill: ' . $e->getMessage()];
        header('Location: ' . $billing_url('sale'));
        exit;
    }
}

// ─── 4. POST: Save / Update Labour Bill ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_labour_bill'])) {
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        $_SESSION['billing_toast'] = ['type' => 'error', 'msg' => 'Security check failed. Please refresh.'];
        header('Location: ' . $billing_url('labour'));
        exit;
    }

    $is_update     = isset($_POST['edit_bill_id']) && intval($_POST['edit_bill_id']) > 0;
    $edit_bill_id  = $is_update ? intval($_POST['edit_bill_id']) : null;
    $save_and_print = !empty($_POST['save_and_print']);

    $bill_no       = trim((string)($_POST['bill_no'] ?? ''));
    $bill_date     = trim((string)($_POST['bill_date'] ?? date('Y-m-d')));
    $customer_name = trim((string)($_POST['customer_name'] ?? ''));
    $mobile        = trim((string)($_POST['mobile'] ?? ''));
    $address       = trim((string)($_POST['address'] ?? ''));
    $gst_no        = trim((string)($_POST['gst_no'] ?? ''));
    $state_code    = trim((string)($_POST['state_code'] ?? '24 - Gujarat'));
    $pan_no        = trim((string)($_POST['pan_no'] ?? ''));

    $gold_rate     = floatval($_POST['gold_rate'] ?? 0);
    $silver_rate   = floatval($_POST['silver_rate'] ?? 0);
    $gold_credit   = floatval($_POST['gold_credit'] ?? 0);

    $subtotal      = floatval($_POST['subtotal'] ?? 0);
    $discount      = floatval($_POST['discount'] ?? 0);
    $cgst          = floatval($_POST['cgst'] ?? 0);
    $sgst          = floatval($_POST['sgst'] ?? 0);
    $net_amount    = floatval($_POST['net_amount'] ?? 0);

    $cash_rec      = floatval($_POST['cash_received'] ?? 0);
    $bank_rec      = floatval($_POST['bank_received'] ?? 0);
    $balance_amt   = floatval($_POST['balance_amount'] ?? 0);

    if ($customer_name === '') {
        $_SESSION['billing_toast'] = ['type' => 'error', 'msg' => 'Customer name is required.'];
        header('Location: ' . $billing_url('labour'));
        exit;
    }

    if ($bill_no === '') {
        $year = date('Y');
        $qId = $conn->query("SELECT id FROM bills WHERE bill_type = 'LABOUR' ORDER BY id DESC LIMIT 1");
        $nextId = ($qId && $qId->num_rows > 0) ? ((int)$qId->fetch_assoc()['id'] + 1) : 1;
        $bill_no = 'L-' . $year . '-' . str_pad((string)$nextId, 3, '0', STR_PAD_LEFT);
    }

    $conn->begin_transaction();
    try {
        // Sync customer record (isolated so secondary CRM sync never blocks or aborts labour bill creation)
        if ($mobile !== '') {
            try {
                $custCheck = $conn->prepare("SELECT id FROM customers WHERE mobile = ? OR phone = ? LIMIT 1");
                if ($custCheck) {
                    $custCheck->bind_param('ss', $mobile, $mobile);
                    $custCheck->execute();
                    $custRes = $custCheck->get_result();
                    if ($custRes && $custRes->num_rows === 0) {
                        $dummyHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                        $insCust = $conn->prepare("INSERT INTO customers (name, phone, mobile, address, gst_no, pan_no, password_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        if ($insCust) {
                            $insCust->bind_param('sssssss', $customer_name, $mobile, $mobile, $address, $gst_no, $pan_no, $dummyHash);
                            $insCust->execute();
                            $insCust->close();
                        }
                    } else {
                        $updCust = $conn->prepare("UPDATE customers SET name = ?, address = ?, gst_no = ?, pan_no = ? WHERE mobile = ? OR phone = ?");
                        if ($updCust) {
                            $updCust->bind_param('ssssss', $customer_name, $address, $gst_no, $pan_no, $mobile, $mobile);
                            $updCust->execute();
                            $updCust->close();
                        }
                    }
                    $custCheck->close();
                }
            } catch (Throwable $custErr) {
                error_log("Labour bill customer CRM sync notice: " . $custErr->getMessage());
            }
        }

        if ($is_update) {
            $stmt = $conn->prepare("
                UPDATE bills SET
                    bill_no=?, bill_date=?, customer_name=?, mobile=?, address=?, gst_no=?, state_code=?, pan_no=?,
                    gold_rate=?, silver_rate=?, gold_credit=?, subtotal=?, discount=?, cgst=?, sgst=?, net_amount=?,
                    cash_received=?, bank_received=?, balance_amount=?
                WHERE id=? AND bill_type='LABOUR'
            ");
            $stmt->bind_param(
                'ssssssssdddddddddddi',
                $bill_no, $bill_date, $customer_name, $mobile, $address, $gst_no, $state_code, $pan_no,
                $gold_rate, $silver_rate, $gold_credit, $subtotal, $discount, $cgst, $sgst, $net_amount,
                $cash_rec, $bank_rec, $balance_amt, $edit_bill_id
            );
            $stmt->execute();
            $stmt->close();

            $delItems = $conn->prepare("DELETE FROM bill_items WHERE bill_id = ?");
            $delItems->bind_param('i', $edit_bill_id);
            $delItems->execute();
            $delItems->close();

            $saved_id = $edit_bill_id;
        } else {
            $stmt = $conn->prepare("
                INSERT INTO bills (
                    bill_no, bill_date, customer_name, mobile, address, gst_no, state_code, pan_no,
                    gold_rate, silver_rate, gold_credit, subtotal, discount, cgst, sgst, net_amount,
                    cash_received, bank_received, balance_amount, bill_type, status, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, 'LABOUR', 'ACTIVE', NOW()
                )
            ");
            $stmt->bind_param(
                'ssssssssddddddddddd',
                $bill_no, $bill_date, $customer_name, $mobile, $address, $gst_no, $state_code, $pan_no,
                $gold_rate, $silver_rate, $gold_credit, $subtotal, $discount, $cgst, $sgst, $net_amount,
                $cash_rec, $bank_rec, $balance_amt
            );
            $stmt->execute();
            $saved_id = (int)$conn->insert_id;
            $stmt->close();
        }

        // Insert labour line items
        if ($saved_id > 0 && !empty($_POST['description']) && is_array($_POST['description'])) {
            $itemStmt = $conn->prepare("
                INSERT INTO bill_items (bill_id, description, quantity, rate, total_amount) VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($_POST['description'] as $k => $desc) {
                $desc = trim((string)$desc);
                if ($desc === '') continue;
                $qty = floatval($_POST['quantity'][$k] ?? 1);
                $rate_val = floatval($_POST['rate'][$k] ?? 0);
                $total_val = floatval($_POST['item_total'][$k] ?? ($qty * $rate_val));

                $itemStmt->bind_param('isddd', $saved_id, $desc, $qty, $rate_val, $total_val);
                $itemStmt->execute();
            }
            $itemStmt->close();
        }

        $conn->commit();

        if (function_exists('cj_log_activity')) {
            cj_log_activity($conn, 'billing', "Saved Labour Invoice #{$bill_no} for {$customer_name} (₹" . number_format($net_amount, 2) . ")");
        }

        $_SESSION['billing_toast'] = [
            'type' => 'success',
            'msg'  => "Labour Invoice #{$bill_no} saved successfully!",
            'print_labour_id' => $save_and_print ? $saved_id : null,
            'bill_no' => $bill_no
        ];

        $extraParams = $save_and_print ? ['print_labour_id' => $saved_id] : [];
        header('Location: ' . $billing_url('history', $extraParams));
        exit;

    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['billing_toast'] = ['type' => 'error', 'msg' => 'Failed to save labour invoice: ' . $e->getMessage()];
        header('Location: ' . $billing_url('labour'));
        exit;
    }
}

// ─── 5. POST: Purge / Delete Invoice ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bill'])) {
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        $_SESSION['billing_toast'] = ['type' => 'error', 'msg' => 'Security check failed. Please refresh.'];
        header('Location: ' . $billing_url('history'));
        exit;
    }

    $del_id = intval($_POST['delete_id'] ?? 0);
    if ($del_id > 0) {
        $conn->begin_transaction();
        try {
            $delItems = $conn->prepare("DELETE FROM bill_items WHERE bill_id = ?");
            $delItems->bind_param('i', $del_id);
            $delItems->execute();
            $delItems->close();

            $delBill = $conn->prepare("DELETE FROM bills WHERE id = ?");
            $delBill->bind_param('i', $del_id);
            $delBill->execute();
            $delBill->close();

            $conn->commit();

            if (function_exists('cj_log_activity')) {
                cj_log_activity($conn, 'billing', "Purged invoice ID #{$del_id}");
            }

            $_SESSION['billing_toast'] = ['type' => 'success', 'msg' => "Invoice record #{$del_id} deleted permanently."];
        } catch (Throwable $e) {
            $conn->rollback();
            $_SESSION['billing_toast'] = ['type' => 'error', 'msg' => "Could not delete invoice: " . $e->getMessage()];
        }
    }
    header('Location: ' . $billing_url('history'));
    exit;
}

// ─── 6. POST: Record Khata Settlement Payment ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_khata_payment'])) {
    if (!function_exists('cj_validate_csrf') || !cj_validate_csrf()) {
        $_SESSION['billing_toast'] = ['type' => 'error', 'msg' => 'Security check failed.'];
        header('Location: ' . $billing_url('customers'));
        exit;
    }

    $bill_id = intval($_POST['settle_bill_id'] ?? 0);
    $pay_amount = floatval($_POST['pay_amount'] ?? 0);
    $pay_mode = trim((string)($_POST['pay_mode'] ?? 'CASH'));

    if ($bill_id > 0 && $pay_amount > 0) {
        $stmt = $conn->prepare("SELECT net_amount, cash_received, bank_received, balance_amount FROM bills WHERE id = ?");
        $stmt->bind_param('i', $bill_id);
        $stmt->execute();
        $b = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($b) {
            $new_balance = max(0, floatval($b['balance_amount']) - $pay_amount);
            if ($pay_mode === 'BANK_UPI') {
                $new_bank = floatval($b['bank_received']) + $pay_amount;
                $upd = $conn->prepare("UPDATE bills SET bank_received = ?, balance_amount = ? WHERE id = ?");
                $upd->bind_param('ddi', $new_bank, $new_balance, $bill_id);
            } else {
                $new_cash = floatval($b['cash_received']) + $pay_amount;
                $upd = $conn->prepare("UPDATE bills SET cash_received = ?, balance_amount = ? WHERE id = ?");
                $upd->bind_param('ddi', $new_cash, $new_balance, $bill_id);
            }
            $upd->execute();
            $upd->close();

            $_SESSION['billing_toast'] = ['type' => 'success', 'msg' => "Payment of ₹" . number_format($pay_amount, 2) . " recorded. New balance: ₹" . number_format($new_balance, 2)];
        }
    }
    header('Location: ' . $billing_url('history'));
    exit;
}
?>
