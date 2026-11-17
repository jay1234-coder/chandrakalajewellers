<?php
/**
 * Razorpay helpers (curl, no Composer SDK) for InfinityFree-friendly hosts.
 */
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/settings.php';

if (!function_exists('cj_razorpay_key_id')) {
    function cj_razorpay_key_id(): string
    {
        return trim(cj_setting('razorpay_key_id'));
    }
}

if (!function_exists('cj_razorpay_key_secret')) {
    function cj_razorpay_key_secret(): string
    {
        return trim(cj_setting('razorpay_key_secret'));
    }
}

if (!function_exists('cj_razorpay_keys_ready')) {
    function cj_razorpay_keys_ready(): bool
    {
        $id = cj_razorpay_key_id();
        $secret = cj_razorpay_key_secret();
        return $id !== '' && $secret !== '' && str_starts_with($id, 'rzp_') && function_exists('curl_init');
    }
}

if (!function_exists('cj_razorpay_configured')) {
    function cj_razorpay_configured(): bool
    {
        if (function_exists('cj_feature') && !cj_feature('feat_online_pay')) {
            return false;
        }
        return cj_razorpay_keys_ready();
    }
}

if (!function_exists('cj_razorpay_amount_paise')) {
    function cj_razorpay_amount_paise(float $rupees): int
    {
        return (int) round($rupees * 100);
    }
}

if (!function_exists('cj_normalize_payment_method')) {
    function cj_normalize_payment_method(string $method): string
    {
        $method = strtolower(trim($method));
        if ($method === 'razorpay' && cj_razorpay_configured()) {
            return 'razorpay';
        }
        if ($method === 'cash') {
            return 'cash';
        }
        return 'whatsapp';
    }
}

if (!function_exists('cj_razorpay_api')) {
    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    function cj_razorpay_api(string $method, string $path, ?array $body = null): array
    {
        if (!cj_razorpay_keys_ready()) {
            throw new RuntimeException('Online payment is not configured.');
        }
        $path = '/' . ltrim($path, '/');
        $ch = curl_init('https://api.razorpay.com/v1' . $path);
        if ($ch === false) {
            throw new RuntimeException('Could not start payment request.');
        }
        $headers = ['Accept: application/json'];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => cj_razorpay_key_id() . ':' . cj_razorpay_key_secret(),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Payment gateway unreachable. Please try WhatsApp checkout.');
        }
        $decoded = json_decode((string) $raw, true);
        if ($code < 200 || $code >= 300) {
            $msg = is_array($decoded) ? (string) ($decoded['error']['description'] ?? 'Payment gateway error.') : 'Payment gateway error.';
            throw new RuntimeException($msg);
        }
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('cj_razorpay_verify_checkout_signature')) {
    function cj_razorpay_verify_checkout_signature(string $orderId, string $paymentId, string $signature): bool
    {
        if ($orderId === '' || $paymentId === '' || $signature === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, cj_razorpay_key_secret());
        return hash_equals($expected, $signature);
    }
}

if (!function_exists('cj_razorpay_verify_webhook_signature')) {
    function cj_razorpay_verify_webhook_signature(string $rawBody, string $signature): bool
    {
        $secret = trim(cj_setting('razorpay_webhook_secret'));
        if ($secret === '' || $signature === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }
}

if (!function_exists('cj_ensure_razorpay_order')) {
    /**
     * Create (or reuse) a Razorpay order for a reserved shop order.
     *
     * @param array<string,mixed> $order
     * @return array{razorpay_order_id:string,amount:int,currency:string,key_id:string}
     */
    function cj_ensure_razorpay_order(mysqli $conn, array $order): array
    {
        $rupees = function_exists('cj_order_payable_rupees')
            ? cj_order_payable_rupees($order)
            : (float) ($order['subtotal'] ?? 0);
        $paise = cj_razorpay_amount_paise($rupees);
        if ($paise < 100) {
            throw new RuntimeException('Order amount is too small for online payment.');
        }
        $existing = trim((string) ($order['razorpay_order_id'] ?? ''));
        if ($existing !== '') {
            return [
                'razorpay_order_id' => $existing,
                'amount' => $paise,
                'currency' => 'INR',
                'key_id' => cj_razorpay_key_id(),
            ];
        }

        $created = cj_razorpay_api('POST', '/orders', [
            'amount' => $paise,
            'currency' => 'INR',
            'receipt' => mb_substr((string) ($order['order_no'] ?? ''), 0, 40),
            'payment_capture' => 1,
            'notes' => [
                'order_no' => (string) ($order['order_no'] ?? ''),
                'shop_order_id' => (string) ($order['id'] ?? ''),
            ],
        ]);
        $rzpId = trim((string) ($created['id'] ?? ''));
        if ($rzpId === '') {
            throw new RuntimeException('Could not start online payment.');
        }

        $oid = (int) ($order['id'] ?? 0);
        $pending = 'pending';
        $method = 'razorpay';
        $stmt = $conn->prepare('UPDATE orders SET razorpay_order_id = ?, payment_status = ?, payment_method = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('sssi', $rzpId, $pending, $method, $oid);
            $stmt->execute();
            $stmt->close();
        }

        return [
            'razorpay_order_id' => $rzpId,
            'amount' => $paise,
            'currency' => 'INR',
            'key_id' => cj_razorpay_key_id(),
        ];
    }
}

if (!function_exists('cj_razorpay_refund_payment')) {
    /**
     * Full refund of a captured Razorpay payment.
     *
     * @return array<string,mixed>
     */
    function cj_razorpay_refund_payment(string $paymentId, int $paise = 0, string $notes = ''): array
    {
        $paymentId = trim($paymentId);
        if ($paymentId === '') {
            throw new RuntimeException('Missing payment id.');
        }
        $body = ['speed' => 'normal'];
        if ($paise > 0) {
            $body['amount'] = $paise;
        }
        if ($notes !== '') {
            $body['notes'] = ['reason' => mb_substr($notes, 0, 200)];
        }
        return cj_razorpay_api('POST', '/payments/' . rawurlencode($paymentId) . '/refund', $body);
    }
}
