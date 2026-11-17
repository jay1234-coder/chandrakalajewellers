<?php
/**
 * Vercel Serverless Entry Point for ChandraKala Jewellers PHP Application.
 * Routes incoming web requests to the appropriate PHP script in the repository.
 */

// Enable error reporting to avoid silent 500 crashes
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Ensure session directory exists in writable /tmp on AWS Lambda
if (!is_dir('/tmp/sessions')) {
    @mkdir('/tmp/sessions', 0777, true);
}
@ini_set('session.save_path', '/tmp/sessions');

// Register shutdown function to capture any fatal errors and display them cleanly
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo "<!DOCTYPE html><html><head><title>500 Application Error</title>";
        echo "<style>body{font-family:system-ui,-apple-system,sans-serif;padding:30px;background:#111;color:#eee;}";
        echo ".card{background:#222;border:1px solid #444;border-radius:8px;padding:24px;max-width:800px;margin:0 auto;}";
        echo "h2{color:#f87171;margin-top:0;}pre{background:#333;padding:12px;border-radius:6px;overflow-x:auto;}";
        echo "</style></head><body><div class='card'>";
        echo "<h2>Application Execution Error</h2>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($error['file']) . " (Line: " . $error['line'] . ")</p>";
        echo "</div></body></html>";
    }
});

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($rawUri, PHP_URL_PATH);
$path = trim($path, '/');

// Clean duplicate /admin/admin/...
while (strpos($path, 'admin/admin/') === 0) {
    $path = substr($path, 6);
}

$rootDir = dirname(__DIR__);

// Handle static files if routed here
if ($path !== '') {
    // Cloudinary redirects for media on serverless runtime
    if (strpos($path, 'uploads/') === 0) {
        require_once $rootDir . '/includes/cloudinary.php';
        $fn = substr($path, 8);
        $cldUrl = cj_cloudinary_url($fn, 1200, 'uploads');
        header('Location: ' . $cldUrl, true, 302);
        exit;
    }
    if (strpos($path, 'catalogue/') === 0) {
        require_once $rootDir . '/includes/cloudinary.php';
        $fn = substr($path, 10);
        $cldUrl = cj_cloudinary_url($fn, 1200, 'catalogue');
        header('Location: ' . $cldUrl, true, 302);
        exit;
    }

    $staticFile = $rootDir . '/' . $path;
    if (is_file($staticFile)) {
        $ext = strtolower(pathinfo($staticFile, PATHINFO_EXTENSION));
        $mimes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'webmanifest' => 'application/manifest+json',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'ico' => 'image/x-icon',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'xml' => 'application/xml',
        ];
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
            header('Content-Length: ' . filesize($staticFile));
            readfile($staticFile);
            exit;
        }
    }
}

// Guarantee default HTML content type and inline disposition for all dynamic PHP scripts
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline');

// Helper to safely execute a target PHP script with its own directory as working directory
function cj_dispatch_script(string $file): void {
    chdir(dirname($file));
    require $file;
    exit;
}

// 1. Root / Homepage
if ($path === '' || $path === 'index' || $path === 'index.php') {
    cj_dispatch_script($rootDir . '/index.php');
}

// 2. Admin root routes (/admin, /admin.php)
if ($path === 'admin' || $path === 'admin.php') {
    cj_dispatch_script($rootDir . '/admin/index.php');
}

// 3. Admin sub-directory routes (e.g. /admin/orders.php, /admin/index.php, /admin/customers)
if (strpos($path, 'admin/') === 0) {
    $sub = substr($path, 6);
    if ($sub === '' || $sub === 'index' || $sub === 'index.php' || $sub === 'admin.php') {
        cj_dispatch_script($rootDir . '/admin/index.php');
    }
    // Sub-aliases inside admin
    if ($sub === 'admin-orders' || $sub === 'admin-orders.php') {
        $sub = 'orders.php';
    }
    if ($sub === 'admin-customers' || $sub === 'admin-customers.php') {
        $sub = 'customers.php';
    }
    if ($sub === 'catalogue') {
        $sub = 'delete_c.php';
    }
    $target = $rootDir . '/admin/' . $sub;
    if (is_file($target)) {
        cj_dispatch_script($target);
    }
    if (is_file($target . '.php')) {
        cj_dispatch_script($target . '.php');
    }
}

// 4. Admin aliases requested at root level (e.g. /orders, /customers, /admin-orders, /admin-customers)
$adminAliases = [
    'orders' => 'orders.php',
    'orders.php' => 'orders.php',
    'admin-orders' => 'orders.php',
    'admin-orders.php' => 'orders.php',
    'customers' => 'customers.php',
    'customers.php' => 'customers.php',
    'admin-customers' => 'customers.php',
    'admin-customers.php' => 'customers.php',
    'superadmin' => 'superadmin.php',
    'superadmin.php' => 'superadmin.php',
    'delete_c' => 'delete_c.php',
    'delete_c.php' => 'delete_c.php',
    'manual_rate' => 'manual_rate.php',
    'manual_rate.php' => 'manual_rate.php',
    'manual_rate_update' => 'manual_rate_update.php',
    'manual_rate_update.php' => 'manual_rate_update.php',
    'notifications' => 'notifications.php',
    'notifications.php' => 'notifications.php',
];
if (isset($adminAliases[$path])) {
    $target = $rootDir . '/admin/' . $adminAliases[$path];
    if (is_file($target)) {
        cj_dispatch_script($target);
    }
}

// 4. API endpoints inside api/
if (strpos($path, 'api/') === 0) {
    $sub = substr($path, 4);
    $target = __DIR__ . '/' . $sub;
    if (is_file($target) && realpath($target) !== __FILE__) {
        cj_dispatch_script($target);
    }
    if (is_file($target . '.php')) {
        cj_dispatch_script($target . '.php');
    }
}

// 5. Direct PHP files in root (e.g. gallery, product, cart, checkout)
$directFile = $rootDir . '/' . $path;
if (is_file($directFile)) {
    cj_dispatch_script($directFile);
}
if (is_file($directFile . '.php')) {
    cj_dispatch_script($directFile . '.php');
}

// 6. Case-insensitive aliases (e.g., /catalogue -> Catalogue.php)
if (strtolower($path) === 'catalogue') {
    cj_dispatch_script($rootDir . '/Catalogue.php');
}

// 7. Not found
http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($path);
