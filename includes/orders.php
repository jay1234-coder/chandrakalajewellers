<?php
/**
 * Place / expire / confirm jewellery orders with row-level stock reservation.
 */
require_once __DIR__ . '/orders-schema.php';
require_once __DIR__ . '/pricing.php';
require_once __DIR__ . '/settings.php';

if (!function_exists('cj_order_reservation_hours')) {
    function cj_order_reservation_hours(): int
    {
        return 24;
    }
}

if (!function_exists('cj_ensure_price_cache')) {
    function cj_ensure_price_cache(mysqli $conn): void
    {
        cj_ensure_orders_schema($conn);
        $need = $conn->query("SELECT COUNT(*) AS c FROM jewellery_items WHERE cached_price = 0 AND weight > 0 LIMIT 1");
        $row = $need ? $need->fetch_assoc() : null;
        $needZeros = $row && (int) ($row['c'] ?? 0) > 0;
        $formulaJson = function_exists('cj_setting') ? cj_setting('rate_formula_json', '') : '';
        $stamp = md5($formulaJson);
        $saved = function_exists('cj_setting') ? cj_setting('price_cache_stamp', '') : '';
        if ($needZeros || $saved !== $stamp) {
            cj_refresh_all_cached_prices($conn);
            if (function_exists('cj_setting_set')) {
                cj_setting_set($conn, 'price_cache_stamp', $stamp);
            }
        }
    }
}

if (!function_exists('cj_rates_bundle')) {
    /** @return array{standard:array,manual:array} */
    function cj_rates_bundle(mysqli $conn): array
    {
        require_once __DIR__ . '/pricing.php';
        $formula = function_exists('cj_rate_formula_map') ? cj_rate_formula_map() : [];
        $std = cj_get_standard_rates($conn);
        $std['rate_formula'] = $formula;
        $man = cj_get_manual_rates($conn);
        $man['rate_formula'] = $formula;
        return [
            'standard' => $std,
            'manual' => $man,
        ];
    }
}

if (!function_exists('cj_refresh_item_cached_price')) {
    function cj_refresh_item_cached_price(mysqli $conn, int $id, ?array $bundle = null): void
    {
        if ($id <= 0) {
            return;
        }
        cj_ensure_orders_schema($conn);
        $bundle = $bundle ?: cj_rates_bundle($conn);
        $stmt = $conn->prepare('SELECT metal_type, weight, use_manual_rates FROM jewellery_items WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$item) {
            return;
        }
        $rates = !empty($item['use_manual_rates']) ? $bundle['manual'] : $bundle['standard'];
        $price = round(cj_calculate_price((string) $item['metal_type'], $item['weight'], $rates), 2);
        $upd = $conn->prepare('UPDATE jewellery_items SET cached_price = ? WHERE id = ?');
        if ($upd) {
            $upd->bind_param('di', $price, $id);
            $upd->execute();
            $upd->close();
        }
    }
}

if (!function_exists('cj_refresh_all_cached_prices')) {
    function cj_refresh_all_cached_prices(mysqli $conn): void
    {
        cj_ensure_orders_schema($conn);
        $bundle = cj_rates_bundle($conn);
        $res = $conn->query('SELECT id, metal_type, weight, use_manual_rates FROM jewellery_items');
        if (!$res) {
            return;
        }
        $upd = $conn->prepare('UPDATE jewellery_items SET cached_price = ? WHERE id = ?');
        if (!$upd) {
            return;
        }
        while ($item = $res->fetch_assoc()) {
            $rates = !empty($item['use_manual_rates']) ? $bundle['manual'] : $bundle['standard'];
            $price = round(cj_calculate_price((string) $item['metal_type'], $item['weight'], $rates), 2);
            $id = (int) $item['id'];
            $upd->bind_param('di', $price, $id);
            $upd->execute();
        }
        $upd->close();
    }
}

if (!function_exists('cj_expire_reservations')) {
    function cj_expire_reservations(mysqli $conn): void
    {
        cj_ensure_orders_schema($conn);
        $res = $conn->query(
            "SELECT id FROM orders
             WHERE status = 'reserved'
               AND reserved_until IS NOT NULL
               AND reserved_until < NOW()
               AND (payment_status IS NULL OR payment_status <> 'paid')"
        );
        if (!$res) {
            return;
        }
        while ($row = $res->fetch_assoc()) {
            cj_release_order_stock($conn, (int) $row['id'], 'expired');
        }
    }
}

if (!function_exists('cj_release_order_stock')) {
    function cj_release_order_stock(mysqli $conn, int $orderId, string $newStatus = 'cancelled'): bool
    {
        if ($orderId <= 0) {
            return false;
        }
        $newStatus = in_array($newStatus, ['cancelled', 'expired'], true) ? $newStatus : 'cancelled';
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT id, status, payment_status FROM orders WHERE id = ? FOR UPDATE");
            if (!$stmt) {
                throw new RuntimeException('order lock failed');
            }
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$order || ($order['status'] ?? '') !== 'reserved') {
                $conn->rollback();
                return false;
            }
            if (($order['payment_status'] ?? '') === 'paid') {
                $conn->rollback();
                return false;
            }

            $rel = $conn->prepare(
                'UPDATE jewellery_items
                 SET is_sold_out = 0, reserved_order_id = NULL, reserved_until = NULL
                 WHERE reserved_order_id = ?'
            );
            if ($rel) {
                $rel->bind_param('i', $orderId);
                $rel->execute();
                $rel->close();
            }

            $col = $newStatus === 'expired' ? 'cancelled_at' : 'cancelled_at';
            $upd = $conn->prepare("UPDATE orders SET status = ?, {$col} = NOW(), reserved_until = NULL WHERE id = ?");
            if (!$upd) {
                throw new RuntimeException('order update failed');
            }
            $upd->bind_param('si', $newStatus, $orderId);
            $upd->execute();
            $upd->close();
            $conn->commit();
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('cj_release_order_stock: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('cj_confirm_order')) {
    function cj_confirm_order(mysqli $conn, int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT id, status, payment_status, payment_method FROM orders WHERE id = ? FOR UPDATE");
            if (!$stmt) {
                throw new RuntimeException('order lock failed');
            }
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$order || ($order['status'] ?? '') !== 'reserved') {
                $conn->rollback();
                return false;
            }

            $keep = $conn->prepare(
                'UPDATE jewellery_items
                 SET is_sold_out = 1, reserved_until = NULL
                 WHERE reserved_order_id = ?'
            );
            if ($keep) {
                $keep->bind_param('i', $orderId);
                $keep->execute();
                $keep->close();
            }

            $status = 'confirmed';
            if (($order['payment_status'] ?? '') === 'paid') {
                $upd = $conn->prepare(
                    "UPDATE orders SET status = ?, confirmed_at = NOW(), reserved_until = NULL WHERE id = ?"
                );
                $upd->bind_param('si', $status, $orderId);
            } else {
                $payStatus = 'paid';
                $payMethod = (($order['payment_method'] ?? '') === 'razorpay') ? 'cash' : (string) ($order['payment_method'] ?: 'cash');
                if ($payMethod === 'whatsapp') {
                    $payMethod = 'cash';
                }
                $upd = $conn->prepare(
                    "UPDATE orders SET status = ?, confirmed_at = NOW(), reserved_until = NULL,
                     payment_status = ?, payment_method = ?, paid_at = IFNULL(paid_at, NOW()) WHERE id = ?"
                );
                $upd->bind_param('sssi', $status, $payStatus, $payMethod, $orderId);
            }
            $upd->execute();
            $upd->close();
            $conn->commit();
            require_once __DIR__ . '/mailer.php';
            cj_send_order_invoice($conn, $orderId);
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('cj_confirm_order: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('cj_mark_order_paid')) {
    /**
     * Idempotent: record Razorpay payment and confirm stock.
     */
    function cj_mark_order_paid(
        mysqli $conn,
        int $orderId,
        string $razorpayOrderId,
        string $razorpayPaymentId,
        string $razorpaySignature = ''
    ): bool {
        if ($orderId <= 0 || $razorpayOrderId === '' || $razorpayPaymentId === '') {
            return false;
        }
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
            if (!$stmt) {
                throw new RuntimeException('order lock failed');
            }
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$order) {
                $conn->rollback();
                return false;
            }

            $status = (string) ($order['status'] ?? '');
            if (in_array($status, ['cancelled', 'expired'], true)) {
                $conn->rollback();
                return false;
            }

            if (($order['payment_status'] ?? '') === 'paid') {
                $conn->commit();
                return true;
            }

            $payStatus = 'paid';
            $payMethod = 'razorpay';
            $upd = $conn->prepare(
                "UPDATE orders
                 SET payment_status = ?, payment_method = ?, razorpay_order_id = ?, razorpay_payment_id = ?,
                     razorpay_signature = ?, paid_at = IFNULL(paid_at, NOW()),
                     status = 'confirmed', confirmed_at = IFNULL(confirmed_at, NOW()), reserved_until = NULL
                 WHERE id = ?"
            );
            if (!$upd) {
                throw new RuntimeException('paid update failed');
            }
            $upd->bind_param(
                'sssssi',
                $payStatus,
                $payMethod,
                $razorpayOrderId,
                $razorpayPaymentId,
                $razorpaySignature,
                $orderId
            );
            $upd->execute();
            $upd->close();

            $keep = $conn->prepare(
                'UPDATE jewellery_items
                 SET is_sold_out = 1, reserved_until = NULL
                 WHERE reserved_order_id = ?'
            );
            if ($keep) {
                $keep->bind_param('i', $orderId);
                $keep->execute();
                $keep->close();
            }

            $conn->commit();
            require_once __DIR__ . '/mailer.php';
            cj_send_order_invoice($conn, $orderId);
            return true;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('cj_mark_order_paid: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('cj_generate_order_no')) {
    function cj_generate_order_no(mysqli $conn): string
    {
        for ($i = 0; $i < 6; $i++) {
            $no = 'CKJ' . date('ymd') . strtoupper(bin2hex(random_bytes(2)));
            $stmt = $conn->prepare('SELECT id FROM orders WHERE order_no = ? LIMIT 1');
            if (!$stmt) {
                return $no;
            }
            $stmt->bind_param('s', $no);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$exists) {
                return $no;
            }
        }
        return 'CKJ' . date('ymdHis');
    }
}

if (!function_exists('cj_fetch_order_by_no')) {
    /** @return array{order:array,items:list<array>}|null */
    function cj_fetch_order_by_no(mysqli $conn, string $orderNo): ?array
    {
        $orderNo = trim($orderNo);
        if ($orderNo === '') {
            return null;
        }
        cj_ensure_orders_schema($conn);
        $stmt = $conn->prepare('SELECT * FROM orders WHERE order_no = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $orderNo);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$order) {
            return null;
        }
        $items = [];
        $oid = (int) $order['id'];
        $it = $conn->prepare('SELECT * FROM order_items WHERE order_id = ?');
        if ($it) {
            $it->bind_param('i', $oid);
            $it->execute();
            $res = $it->get_result();
            while ($row = $res->fetch_assoc()) {
                $items[] = $row;
            }
            $it->close();
        }
        return ['order' => $order, 'items' => $items];
    }
}

if (!function_exists('cj_fetch_order_by_razorpay_order_id')) {
    /** @return array<string,mixed>|null */
    function cj_fetch_order_by_razorpay_order_id(mysqli $conn, string $razorpayOrderId): ?array
    {
        $razorpayOrderId = trim($razorpayOrderId);
        if ($razorpayOrderId === '') {
            return null;
        }
        cj_ensure_orders_schema($conn);
        $stmt = $conn->prepare('SELECT * FROM orders WHERE razorpay_order_id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $razorpayOrderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $order ?: null;
    }
}

if (!function_exists('cj_whatsapp_order_url')) {
    /**
     * @param array<string,mixed> $order
     * @param list<array<string,mixed>> $items
     */
    function cj_whatsapp_order_url(array $order, array $items, string $shopPhone = ''): string
    {
        if ($shopPhone === '') {
            $shopPhone = function_exists('cj_shop_whatsapp_digits') ? cj_shop_whatsapp_digits() : '919427080359';
        }
        $orderNo = (string) ($order['order_no'] ?? '');
        $pay = (string) ($order['payment_status'] ?? 'unpaid');
        $status = (string) ($order['status'] ?? 'reserved');
        $message = "Hello, I would like to confirm my order.\n\n";
        $message .= "*Order:* {$orderNo}\n";
        if ($pay === 'paid') {
            $message .= "*Payment:* Paid online\n";
            $message .= "*Status:* Confirmed\n\n";
        } else {
            $message .= "*Payment:* Pay later / at shop\n";
            $message .= "*Status:* " . ($status === 'reserved' ? 'Reserved for 24 hours' : ucfirst($status)) . "\n\n";
        }
        $name = (string) ($order['customer_name'] ?? '');
        $address = (string) ($order['customer_address'] ?? '');
        $phone = (string) ($order['customer_phone'] ?? '');
        $pincode = (string) ($order['customer_pincode'] ?? '');
        if ($name !== '' || $address !== '' || $phone !== '') {
            $message .= "*Customer Details:*\n";
            if ($name !== '') {
                $message .= "Name: {$name}\n";
            }
            if ($address !== '') {
                $message .= "Address: {$address}\n";
            }
            if ($pincode !== '') {
                $message .= "PIN: {$pincode}\n";
            }
            if ($phone !== '') {
                $message .= "Phone: {$phone}\n";
            }
            $message .= "\n";
        }
        $message .= "*Items:*\n";
        $subtotal = (float) ($order['subtotal'] ?? 0);
        if ($items) {
            foreach ($items as $item) {
                $message .= "- " . ($item['product_name'] ?? 'Item') . " (₹" . number_format((float) ($item['unit_price'] ?? 0), 0) . ")\n";
            }
            $message .= "\n*Total:* ₹" . number_format(function_exists('cj_order_payable_rupees') ? cj_order_payable_rupees($order) : $subtotal, 0) . "\n";
            $gstAmt = (float) ($order['gst_amount'] ?? 0);
            if ($gstAmt > 0) {
                $message .= "*GST:* ₹" . number_format($gstAmt, 2) . "\n";
            }
        } else {
            $message .= "No products were found.\n";
        }
        return 'https://wa.me/' . $shopPhone . '?text=' . rawurlencode($message);
    }
}

if (!function_exists('cj_place_order')) {
    /**
     * Transactionally reserve unique jewellery pieces.
     *
     * @param list<int> $productIds
     * @return array{ok:bool,message:string,order_no?:string,order_id?:int,next?:string,payment_method?:string,unavailable?:list<string>}
     */
    function cj_place_order(
        mysqli $conn,
        array $productIds,
        string $name,
        string $phone,
        string $address,
        string $source = 'checkout',
        string $paymentMethod = 'whatsapp',
        string $pincode = '',
        string $email = '',
        int $customerId = 0
    ): array {
        cj_ensure_orders_schema($conn);
        cj_expire_reservations($conn);

        $ids = [];
        foreach ($productIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        if ($ids === []) {
            return ['ok' => false, 'message' => 'Your cart is empty.'];
        }
        if (count($ids) > 20) {
            return ['ok' => false, 'message' => 'Please checkout 20 or fewer pieces at a time.'];
        }

        $name = mb_substr(trim($name), 0, 120);
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        $address = mb_substr(trim($address), 0, 500);
        $pincode = preg_replace('/\D+/', '', $pincode) ?? '';
        $pincode = mb_substr($pincode, 0, 10);
        if ($name === '' || strlen($phone) < 10 || strlen($phone) > 15) {
            return ['ok' => false, 'message' => 'Please enter a valid name and 10–15 digit phone number.'];
        }
        if ($address === '') {
            return ['ok' => false, 'message' => 'Please enter a delivery address.'];
        }
        if ($pincode !== '' && strlen($pincode) !== 6) {
            return ['ok' => false, 'message' => 'Please enter a valid 6-digit PIN code, or leave it blank.'];
        }
        $email = mb_substr(trim($email), 0, 160);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please enter a valid email, or leave it blank.'];
        }
        if ($customerId <= 0 && function_exists('cj_customer_session')) {
            $sess = cj_customer_session();
            if ($sess) {
                $customerId = (int) $sess['id'];
                if ($email === '' && ($sess['email'] ?? '') !== '') {
                    $email = (string) $sess['email'];
                }
            }
        }
        if (!in_array($paymentMethod, ['razorpay', 'whatsapp', 'cash'], true)) {
            $paymentMethod = 'whatsapp';
        }
        $payStatus = $paymentMethod === 'razorpay' ? 'pending' : 'unpaid';

        $bundle = cj_rates_bundle($conn);
        $hours = cj_order_reservation_hours();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $conn->begin_transaction();
        try {
            $sql = "SELECT * FROM jewellery_items WHERE id IN ($placeholders) FOR UPDATE";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Could not lock stock.');
            }
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $res = $stmt->get_result();
            $locked = [];
            while ($row = $res->fetch_assoc()) {
                $locked[(int) $row['id']] = $row;
            }
            $stmt->close();

            $unavailable = [];
            $lines = [];
            $subtotal = 0.0;
            foreach ($ids as $id) {
                if (!isset($locked[$id])) {
                    $unavailable[] = 'Item #' . $id;
                    continue;
                }
                $row = $locked[$id];
                if (!empty($row['is_sold_out']) || !empty($row['reserved_order_id'])) {
                    $unavailable[] = (string) $row['name'];
                    continue;
                }
                $rates = !empty($row['use_manual_rates']) ? $bundle['manual'] : $bundle['standard'];
                $price = round(cj_calculate_price((string) $row['metal_type'], $row['weight'], $rates), 2);
                $subtotal += $price;
                $lines[] = [
                    'product_id' => $id,
                    'product_name' => (string) $row['name'],
                    'metal_type' => (string) $row['metal_type'],
                    'weight' => (float) $row['weight'],
                    'unit_price' => $price,
                ];
            }

            if ($unavailable !== []) {
                $conn->rollback();
                return [
                    'ok' => false,
                    'message' => 'Some pieces are no longer available: ' . implode(', ', $unavailable) . '. Please remove them and try again.',
                    'unavailable' => $unavailable,
                ];
            }
            if ($lines === []) {
                $conn->rollback();
                return ['ok' => false, 'message' => 'No available items to reserve.'];
            }

            $orderNo = cj_generate_order_no($conn);
            $until = date('Y-m-d H:i:s', time() + ($hours * 3600));
            $gst = function_exists('cj_gst_breakdown')
                ? cj_gst_breakdown($subtotal)
                : ['rate' => 0.0, 'gst_amount' => 0.0, 'grand_total' => $subtotal];
            $gstRate = (float) ($gst['rate'] ?? 0);
            $gstAmount = (float) ($gst['gst_amount'] ?? 0);
            $grandTotal = (float) ($gst['grand_total'] ?? $subtotal);
            $ins = $conn->prepare(
                'INSERT INTO orders (order_no, customer_name, customer_phone, customer_address, customer_pincode, customer_email, customer_id, status, source, subtotal, gst_rate, gst_amount, grand_total, reserved_until, payment_method, payment_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'reserved\', ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$ins) {
                throw new RuntimeException('Could not create order.');
            }
            $ins->bind_param(
                'ssssssisddddsss',
                $orderNo,
                $name,
                $phone,
                $address,
                $pincode,
                $email,
                $customerId,
                $source,
                $subtotal,
                $gstRate,
                $gstAmount,
                $grandTotal,
                $until,
                $paymentMethod,
                $payStatus
            );
            if (!$ins->execute()) {
                throw new RuntimeException('Could not save order.');
            }
            $orderId = (int) $ins->insert_id;
            $ins->close();

            $itemStmt = $conn->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, metal_type, weight, unit_price, qty)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            );
            $lockStmt = $conn->prepare(
                'UPDATE jewellery_items
                 SET is_sold_out = 1, reserved_order_id = ?, reserved_until = ?
                 WHERE id = ? AND is_sold_out = 0 AND reserved_order_id IS NULL'
            );
            if (!$itemStmt || !$lockStmt) {
                throw new RuntimeException('Could not reserve items.');
            }
            foreach ($lines as $line) {
                $itemStmt->bind_param(
                    'iissdd',
                    $orderId,
                    $line['product_id'],
                    $line['product_name'],
                    $line['metal_type'],
                    $line['weight'],
                    $line['unit_price']
                );
                $itemStmt->execute();
                $pid = $line['product_id'];
                $lockStmt->bind_param('isi', $orderId, $until, $pid);
                $lockStmt->execute();
                if ($lockStmt->affected_rows !== 1) {
                    throw new RuntimeException('Piece was taken by another customer: ' . $line['product_name']);
                }
            }
            $itemStmt->close();
            $lockStmt->close();
            $conn->commit();

            return [
                'ok' => true,
                'message' => 'Order reserved.',
                'order_no' => $orderNo,
                'order_id' => $orderId,
                'next' => $paymentMethod === 'razorpay' ? 'pay' : 'success',
                'payment_method' => $paymentMethod,
            ];
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('cj_place_order: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not reserve stock. Please try again.'];
        }
    }
}

if (!function_exists('cj_order_can_refund')) {
    /** @param array<string,mixed> $order */
    function cj_order_can_refund(array $order): bool
    {
        $pay = (string) ($order['payment_status'] ?? '');
        $status = (string) ($order['status'] ?? '');
        if ($pay === 'refunded' || $status === 'refunded' || !empty($order['refunded_at'])) {
            return false;
        }
        return $pay === 'paid' || $status === 'confirmed';
    }
}

if (!function_exists('cj_admin_can_refund')) {
    function cj_admin_can_refund(): bool
    {
        if (function_exists('cj_feature') && !cj_feature('feat_refunds')) {
            return false;
        }
        $role = (string) ($_SESSION['role'] ?? '');
        if ($role === 'superadmin') {
            return true;
        }
        return $role === 'admin' && function_exists('cj_feature') && cj_feature('perm_admin_refunds');
    }
}

if (!function_exists('cj_restock_order_products')) {
    function cj_restock_order_products(mysqli $conn, int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }
        $ids = [];
        $it = $conn->prepare('SELECT product_id FROM order_items WHERE order_id = ?');
        if ($it) {
            $it->bind_param('i', $orderId);
            $it->execute();
            $res = $it->get_result();
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int) $row['product_id'];
            }
            $it->close();
        }
        $rel = $conn->prepare(
            'UPDATE jewellery_items
             SET is_sold_out = 0, reserved_order_id = NULL, reserved_until = NULL
             WHERE id = ?'
        );
        if (!$rel) {
            return;
        }
        foreach ($ids as $pid) {
            if ($pid <= 0) {
                continue;
            }
            $rel->bind_param('i', $pid);
            $rel->execute();
        }
        $rel->close();
    }
}

if (!function_exists('cj_refund_order')) {
    /**
     * Razorpay refund when paid online; always restock unique pieces.
     *
     * @return array{ok:bool,message:string}
     */
    function cj_refund_order(mysqli $conn, int $orderId, string $reason = ''): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'message' => 'Invalid order.'];
        }
        if (!cj_admin_can_refund()) {
            return ['ok' => false, 'message' => 'Refunds are disabled or not allowed for this login.'];
        }
        $reason = mb_substr(trim($reason), 0, 255);
        require_once __DIR__ . '/payments.php';

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            if (!$stmt) {
                throw new RuntimeException('Could not lock order.');
            }
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$order) {
                $conn->rollback();
                return ['ok' => false, 'message' => 'Order not found.'];
            }
            if (!cj_order_can_refund($order)) {
                $conn->rollback();
                return ['ok' => false, 'message' => 'This order cannot be refunded.'];
            }

            $refundId = '';
            $payId = trim((string) ($order['razorpay_payment_id'] ?? ''));
            if ($payId !== '') {
                $paise = function_exists('cj_razorpay_amount_paise')
                    ? cj_razorpay_amount_paise(cj_order_payable_rupees($order))
                    : 0;
                try {
                    $ref = cj_razorpay_refund_payment($payId, $paise, $reason);
                    $refundId = trim((string) ($ref['id'] ?? ''));
                } catch (Throwable $e) {
                    $conn->rollback();
                    return ['ok' => false, 'message' => 'Razorpay refund failed: ' . $e->getMessage()];
                }
            }

            cj_restock_order_products($conn, $orderId);
            $status = 'refunded';
            $payStatus = 'refunded';
            $upd = $conn->prepare(
                "UPDATE orders
                 SET status = ?, payment_status = ?, refunded_at = NOW(), refund_id = ?, refund_reason = ?, reserved_until = NULL
                 WHERE id = ?"
            );
            if (!$upd) {
                throw new RuntimeException('Could not save refund.');
            }
            $upd->bind_param('ssssi', $status, $payStatus, $refundId, $reason, $orderId);
            $upd->execute();
            $upd->close();
            $conn->commit();
            return ['ok' => true, 'message' => $payId !== '' ? 'Refund sent to Razorpay and stock released.' : 'Order refunded and piece returned to the shop.'];
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('cj_refund_order: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not refund this order.'];
        }
    }
}
