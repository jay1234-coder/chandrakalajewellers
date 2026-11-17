<?php
/**
 * Optional email invoices. Off until Super Admin enables feat_email_invoices.
 * InfinityFree often blocks SMTP — PHP mail() is the fallback.
 */
require_once __DIR__ . '/settings.php';

if (!function_exists('cj_mail_from')) {
    function cj_mail_from(): string
    {
        $from = trim(cj_setting('smtp_from'));
        if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }
        $shop = trim(cj_setting('shop_email'));
        return filter_var($shop, FILTER_VALIDATE_EMAIL) ? $shop : 'chandrakalajewellers849@gmail.com';
    }
}

if (!function_exists('cj_smtp_send')) {
    function cj_smtp_send(string $to, string $subject, string $html): bool
    {
        $host = trim(cj_setting('smtp_host'));
        $user = trim(cj_setting('smtp_user'));
        $pass = (string) cj_setting('smtp_pass');
        $port = (int) cj_setting('smtp_port', '587');
        $secure = strtolower(trim(cj_setting('smtp_secure', 'tls')));
        if ($host === '' || $port <= 0) {
            return false;
        }
        $from = cj_mail_from();
        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT);
        if (!$fp) {
            error_log('cj_smtp_send connect: ' . $errstr);
            return false;
        }
        stream_set_timeout($fp, 12);
        $read = static function () use ($fp): string {
            $out = '';
            while ($line = fgets($fp, 512)) {
                $out .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $out;
        };
        $cmd = static function (string $line) use ($fp, $read): string {
            fwrite($fp, $line . "\r\n");
            return $read();
        };
        $ok = static function (string $resp, string $expect): bool {
            return str_starts_with($resp, $expect);
        };
        $banner = $read();
        if (!$ok($banner, '220')) {
            fclose($fp);
            return false;
        }
        $ehlo = $cmd('EHLO chandrakalajewellers.in');
        if ($secure === 'tls' && (str_contains($ehlo, 'STARTTLS') || str_contains($ehlo, 'starttls'))) {
            $cmd('STARTTLS');
            $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                fclose($fp);
                return false;
            }
            $cmd('EHLO chandrakalajewellers.in');
        }
        if ($user !== '' && $pass !== '') {
            $cmd('AUTH LOGIN');
            $cmd(base64_encode($user));
            $auth = $cmd(base64_encode($pass));
            if (!$ok($auth, '235')) {
                fclose($fp);
                return false;
            }
        }
        if (!$ok($cmd('MAIL FROM:<' . $from . '>'), '250')) {
            fclose($fp);
            return false;
        }
        if (!$ok($cmd('RCPT TO:<' . $to . '>'), '250')) {
            fclose($fp);
            return false;
        }
        $cmd('DATA');
        $headers = 'From: ChandraKala Jewellers <' . $from . ">\r\n";
        $headers .= 'To: <' . $to . ">\r\n";
        $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        fwrite($fp, $headers . "\r\n" . $html . "\r\n.\r\n");
        $data = $read();
        $cmd('QUIT');
        fclose($fp);
        return $ok($data, '250');
    }
}

if (!function_exists('cj_send_mail')) {
    function cj_send_mail(string $to, string $subject, string $html): bool
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (trim(cj_setting('smtp_host')) !== '') {
            if (cj_smtp_send($to, $subject, $html)) {
                return true;
            }
        }
        $from = cj_mail_from();
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $headers .= 'From: ChandraKala Jewellers <' . $from . '>' . "\r\n";
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);
    }
}

if (!function_exists('cj_invoice_html')) {
    /**
     * @param array<string,mixed> $order
     * @param list<array<string,mixed>> $items
     */
    function cj_invoice_html(array $order, array $items): string
    {
        $no = htmlspecialchars((string) ($order['order_no'] ?? ''));
        $name = htmlspecialchars((string) ($order['customer_name'] ?? ''));
        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;">'
                . htmlspecialchars((string) ($item['product_name'] ?? 'Item'))
                . '</td><td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">₹'
                . number_format((float) ($item['unit_price'] ?? 0), 0)
                . '</td></tr>';
        }
        $sub = (float) ($order['subtotal'] ?? 0);
        $gst = (float) ($order['gst_amount'] ?? 0);
        $rate = (float) ($order['gst_rate'] ?? 0);
        $grand = cj_order_payable_rupees($order);
        $gstLine = $gst > 0
            ? '<p>GST (' . htmlspecialchars((string) $rate) . '%): ₹' . number_format($gst, 2) . '</p>'
            : '';
        return '<div style="font-family:Georgia,serif;max-width:560px;margin:0 auto;color:#1f2937;">
            <h1 style="color:#b8860b;">ChandraKala Jewellers</h1>
            <p>Khedbrahma, Gujarat</p>
            <h2>Invoice ' . $no . '</h2>
            <p>Bill to: ' . $name . '</p>
            <table style="width:100%;border-collapse:collapse;">' . $rows . '</table>
            <p>Subtotal: ₹' . number_format($sub, 2) . '</p>'
            . $gstLine .
            '<p style="font-weight:700;">Total: ₹' . number_format($grand, 2) . '</p>
            <p style="font-size:12px;color:#6b7280;">Thank you for shopping with ChandraKala Jewellers.</p>
        </div>';
    }
}

if (!function_exists('cj_send_order_invoice')) {
    function cj_send_order_invoice(mysqli $conn, int $orderId): bool
    {
        if ($orderId <= 0 || !cj_feature('feat_email_invoices')) {
            return false;
        }
        if (!function_exists('cj_fetch_order_by_no')) {
            require_once __DIR__ . '/orders.php';
        }
        $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$order) {
            return false;
        }
        $to = trim((string) ($order['customer_email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $items = [];
        $it = $conn->prepare('SELECT * FROM order_items WHERE order_id = ?');
        if ($it) {
            $it->bind_param('i', $orderId);
            $it->execute();
            $res = $it->get_result();
            while ($row = $res->fetch_assoc()) {
                $items[] = $row;
            }
            $it->close();
        }
        $subject = 'Invoice ' . (string) ($order['order_no'] ?? '') . ' — ChandraKala Jewellers';
        return cj_send_mail($to, $subject, cj_invoice_html($order, $items));
    }
}
