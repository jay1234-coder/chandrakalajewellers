<?php
/**
 * Database Diagnostic tool for ChandraKala Jewellers.
 * Upload to server and visit: https://chandrakalajewellers.in/db-diagnostic
 */
require_once __DIR__ . '/config/db.php';

cj_admin_noindex_headers();
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow, noarchive');

echo "--- ChandraKala Jewellers Database Diagnostics ---\n\n";

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}
echo "Connected successfully to database: " . $dbname . "\n\n";

echo "--- Table: rates ---\n";
$res1 = $conn->query("DESCRIBE rates");
if ($res1) {
    while ($row = $res1->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "  Error describing rates: " . $conn->error . "\n";
}

echo "\n--- Table: manual_rates ---\n";
$res2 = $conn->query("DESCRIBE manual_rates");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "  Error describing manual_rates: " . $conn->error . "\n";
}

echo "\n--- Table: jewellery_items ---\n";
$res_items = $conn->query("DESCRIBE jewellery_items");
if ($res_items) {
    while ($row = $res_items->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "  Error describing jewellery_items: " . $conn->error . "\n";
}

echo "\n--- Latest standard rates ---\n";
$res3 = $conn->query("SELECT * FROM rates ORDER BY updated_at DESC LIMIT 1");
if ($res3) {
    if ($row = $res3->fetch_assoc()) {
        print_r($row);
    } else {
        echo "  No standard rates found.\n";
    }
} else {
    echo "  Error fetching rates: " . $conn->error . "\n";
}

echo "\n--- Latest manual rates ---\n";
$res4 = $conn->query("SELECT * FROM manual_rates ORDER BY updated_at DESC LIMIT 1");
if ($res4) {
    if ($row = $res4->fetch_assoc()) {
        print_r($row);
    } else {
        echo "  No manual rates found.\n";
    }
} else {
    echo "  Error fetching manual_rates: " . $conn->error . "\n";
}
