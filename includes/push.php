<?php
/**
 * Queue + send Web Push notifications (VAPID, empty body; SW fetches content).
 */
require_once __DIR__ . '/pwa-schema.php';
require_once __DIR__ . '/vapid.php';

if (!function_exists('cj_queue_push')) {
    function cj_queue_push(mysqli $conn, string $title, string $body, string $audience = 'customer', string $urlPath = 'index1', string $createdBy = 'system', ?string $imageUrl = null, ?string $scheduledAt = null, int $isConstant = 0, ?string $constantTime = null): int
    {
        cj_ensure_pwa_schema($conn);
        $stmt = $conn->prepare('INSERT INTO push_queue (title, body, url_path, image_url, audience, status, created_by, scheduled_at, is_constant, constant_time) VALUES (?,?,?,?,?, \'pending\',?,?,?,?)');
        $stmt->bind_param('sssssssis', $title, $body, $urlPath, $imageUrl, $audience, $createdBy, $scheduledAt, $isConstant, $constantTime);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }
}

if (!function_exists('cj_queue_template')) {
    function cj_queue_template(mysqli $conn, string $slug, string $createdBy = 'system', ?string $bodyOverride = null): ?int
    {
        cj_ensure_pwa_schema($conn);
        $stmt = $conn->prepare('SELECT title, body, audience, url_path FROM push_templates WHERE slug = ? AND is_active = 1 LIMIT 1');
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;
        $body = $bodyOverride !== null ? $bodyOverride : $row['body'];
        return cj_queue_push($conn, $row['title'], $body, $row['audience'], $row['url_path'] ?: 'index1', $createdBy);
    }
}

if (!function_exists('cj_push_audience_clause')) {
    function cj_push_audience_clause(string $audience): string
    {
        if ($audience === 'all') {
            return "1=1";
        }
        if ($audience === 'customer') {
            return "audience IN ('customer','all')";
        }
        if ($audience === 'admin') {
            return "audience IN ('admin','superadmin','all')";
        }
        if ($audience === 'superadmin') {
            return "audience IN ('superadmin','all')";
        }
        return "audience = '" . addslashes($audience) . "'";
    }
}

if (!function_exists('cj_send_web_push_empty')) {
    /**
     * Send an empty Web Push (triggers SW to fetch latest message).
     */
    function cj_send_web_push_empty(string $endpoint, array $vapid): bool
    {
        if ($endpoint === '' || empty($vapid['publicKey']) || empty($vapid['privateKeyPem'])) {
            return false;
        }
        $parts = parse_url($endpoint);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $audience = $parts['scheme'] . '://' . $parts['host'];
        $jwt = cj_vapid_jwt($audience, $vapid);
        if ($jwt === '') {
            return false;
        }

        $headers = [
            'TTL: 86400',
            'Urgency: normal',
            'Authorization: vapid t=' . $jwt . ', k=' . $vapid['publicKey'],
            'Content-Length: 0',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_POSTFIELDS => '',
            ]);
            curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code >= 200 && $code < 300;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => '',
                'timeout' => 12,
                'ignore_errors' => true,
            ],
        ]);
        $result = @file_get_contents($endpoint, false, $ctx);
        $statusLine = $http_response_header[0] ?? '';
        return (bool)preg_match('/\s(201|204|200)\s/', $statusLine) || $result !== false;
    }
}

if (!function_exists('cj_dispatch_push_queue')) {
    function cj_dispatch_push_queue(mysqli $conn, ?int $queueId = null): array
    {
        cj_ensure_pwa_schema($conn);

        // Auto-queue and trigger constant (recurring) daily notifications
        $today = date('Y-m-d');
        $nowTime = date('H:i:s');
        $constants = $conn->query("SELECT * FROM push_queue WHERE is_constant = 1 AND (last_constant_sent_at IS NULL OR DATE(last_constant_sent_at) < '{$today}') AND constant_time <= '{$nowTime}'");
        if ($constants) {
            while ($c = $constants->fetch_assoc()) {
                $conn->query("UPDATE push_queue SET last_constant_sent_at = NOW() WHERE id = " . (int)$c['id']);
                $title = $conn->real_escape_string($c['title']);
                $body = $conn->real_escape_string($c['body']);
                $url = $conn->real_escape_string($c['url_path']);
                $img = $c['image_url'] ? "'" . $conn->real_escape_string($c['image_url']) . "'" : "NULL";
                $aud = $conn->real_escape_string($c['audience']);
                $created_by = $conn->real_escape_string($c['created_by'] . ' (Auto)');

                $conn->query("INSERT INTO push_queue (title, body, url_path, image_url, audience, status, created_by, sent_at) VALUES ('{$title}', '{$body}', '{$url}', {$img}, '{$aud}', 'pending', '{$created_by}', NOW())");
                $newId = (int)$conn->insert_id;
                if ($newId) {
                    cj_dispatch_push_queue($conn, $newId);
                }
            }
        }

        $vapid = cj_vapid_config();
        $ok = 0;
        $fail = 0;

        if ($queueId) {
            $q = $conn->query('SELECT * FROM push_queue WHERE id = ' . (int)$queueId . ' LIMIT 1');
        } else {
            $q = $conn->query("SELECT * FROM push_queue WHERE status = 'pending' AND (scheduled_at IS NULL OR scheduled_at <= NOW()) ORDER BY id ASC LIMIT 5");
        }
        if (!$q) return ['ok' => 0, 'fail' => 0];

        while ($job = $q->fetch_assoc()) {
            $clause = cj_push_audience_clause($job['audience']);
            $subs = $conn->query("SELECT id, endpoint FROM push_subscriptions WHERE {$clause}");
            $jobOk = 0;
            $jobFail = 0;
            if ($subs) {
                while ($sub = $subs->fetch_assoc()) {
                    $sent = cj_send_web_push_empty($sub['endpoint'], $vapid);
                    if ($sent) {
                        $jobOk++;
                        $ok++;
                    } else {
                        $jobFail++;
                        $fail++;
                    }
                }
            }

            // Persist payload for SW fetch (latest pending message per audience)
            $conn->query("UPDATE push_queue SET status = 'sent', sent_at = NOW(), success_count = {$jobOk}, fail_count = {$jobFail} WHERE id = " . (int)$job['id']);
            $title = $conn->real_escape_string($job['title']);
            $body = $conn->real_escape_string($job['body']);
            $aud = $conn->real_escape_string($job['audience']);
            $conn->query("INSERT INTO push_logs (queue_id, title, body, audience, success_count, fail_count) VALUES (" . (int)$job['id'] . ", '{$title}', '{$body}', '{$aud}', {$jobOk}, {$jobFail})");
        }

        return ['ok' => $ok, 'fail' => $fail];
    }
}

if (!function_exists('cj_notify_event')) {
    /**
     * Fire template + optionally dispatch (best-effort).
     * Use $dispatchImmediately=false on admin save paths so slow push delivery
     * cannot time out the request after the DB write.
     */
    function cj_notify_event(mysqli $conn, string $slug, string $createdBy = 'system', ?string $bodyOverride = null, bool $dispatchImmediately = true): void
    {
        try {
            $id = cj_queue_template($conn, $slug, $createdBy, $bodyOverride);
            if ($id && $dispatchImmediately) {
                cj_dispatch_push_queue($conn, $id);
            }
        } catch (Throwable $e) {
            // never break admin save flows
        }
    }
}
