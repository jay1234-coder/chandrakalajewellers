<?php
/**
 * Vercel Serverless Entry Point for ChandraKala Jewellers PHP Application.
 * Routes incoming web requests to the appropriate PHP script in the repository.
 */

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($rawUri, PHP_URL_PATH);
$path = trim($path, '/');

$rootDir = dirname(__DIR__);

// 1. Root / Homepage
if ($path === '' || $path === 'index' || $path === 'index.php') {
    require $rootDir . '/index.php';
    exit;
}

// 2. Admin sub-directory routes (e.g. /admin/orders.php)
if (strpos($path, 'admin/') === 0) {
    $sub = substr($path, 6);
    $target = $rootDir . '/admin/' . $sub;
    if (is_file($target)) {
        require $target;
        exit;
    }
    if (is_file($target . '.php')) {
        require $target . '.php';
        exit;
    }
}

// 3. Admin root (/admin)
if ($path === 'admin') {
    require $rootDir . '/admin.php';
    exit;
}

// 4. API endpoints inside api/
if (strpos($path, 'api/') === 0) {
    $sub = substr($path, 4);
    $target = __DIR__ . '/' . $sub;
    if (is_file($target) && realpath($target) !== __FILE__) {
        require $target;
        exit;
    }
    if (is_file($target . '.php')) {
        require $target . '.php';
        exit;
    }
}

// 5. Direct PHP files in root (e.g. gallery, product, cart, checkout)
$directFile = $rootDir . '/' . $path;
if (is_file($directFile)) {
    require $directFile;
    exit;
}
if (is_file($directFile . '.php')) {
    require $directFile . '.php';
    exit;
}

// 6. Handle case-sensitive variations (e.g., /catalogue -> Catalogue.php)
if (strtolower($path) === 'catalogue') {
    require $rootDir . '/Catalogue.php';
    exit;
}

// 7. Not found
http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($path);
