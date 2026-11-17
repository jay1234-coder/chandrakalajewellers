<?php
/**
 * Server-side Gemini proxy — never send the API key to the browser.
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

cj_auth_bootstrap($conn);
cj_admin_noindex_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required.']);
    exit;
}

if (empty($_SESSION['loggedin']) || !in_array((string) ($_SESSION['role'] ?? ''), ['admin', 'superadmin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Authentication required.']);
    exit;
}

$sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
$postedToken = (string) ($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid request. Please refresh and try again.']);
    exit;
}

$apiKey = trim(cj_setting('gemini_api_key', ''));
if ($apiKey === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Please configure your Gemini API Key in the settings first.']);
    exit;
}

$name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 160);
$metal = mb_substr(trim((string) ($_POST['metal'] ?? '')), 0, 40);
$weight = mb_substr(trim((string) ($_POST['weight'] ?? '')), 0, 20);
if ($name === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Please enter an item name first.']);
    exit;
}

$prompt = "Generate a professional, short, and attractive description and search tags/keywords for a jewellery store item. Details: Name is '" . $name . "', Metal is '" . $metal . "', Weight is '" . $weight . "'. Return a JSON object with keys: 'description' (a beautiful 2-sentence description) and 'tags' (a comma-separated string of 10-15 highly relevant search keywords/synonyms/attributes like style, occasion, target group, type, e.g. hand-crafted, gift, wedding, chandi, sonar, etc.). Return only the raw JSON. Do not include markdown formatting, backticks, or any conversational text.";

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . rawurlencode($apiKey);
$payload = json_encode([
    'contents' => [
        ['parts' => [['text' => $prompt]]],
    ],
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($url);
if ($ch === false) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'Could not start AI request.']);
    exit;
}
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 25,
]);
$raw = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($raw === false || $code < 200 || $code >= 300) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'AI request failed. Check the Gemini API key.']);
    exit;
}

$decoded = json_decode((string) $raw, true);
$text = '';
if (is_array($decoded)) {
    $text = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
}
$text = trim($text);
$text = preg_replace('/^```json\s*/i', '', $text) ?? $text;
$text = preg_replace('/```\s*$/', '', $text) ?? $text;
$parsed = json_decode(trim($text), true);
if (!is_array($parsed)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'AI returned an unexpected response.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'description' => mb_substr(trim((string) ($parsed['description'] ?? '')), 0, 2000),
    'tags' => mb_substr(trim((string) ($parsed['tags'] ?? '')), 0, 500),
]);
