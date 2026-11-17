<?php
/**
 * Optional welcome splash (not the site root).
 * Root "/" now 301-redirects to index1 for Bing/Google SEO.
 */
require_once __DIR__ . '/includes/security.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="content-language" content="en-IN">
    <meta name="language" content="English">
    <title>Welcome | ChandraKala Jewellers — Gold &amp; Silver in Khedbrahma</title>
    <meta name="description" content="Welcome to ChandraKala Jewellers — family-owned gold &amp; silver jewellery store in Khedbrahma, Gujarat. Enter our showroom for 916 gold, silver &amp; live rates.">
    <meta name="robots" content="noindex, follow">
    <link rel="canonical" href="https://chandrakalajewellers.in/index1">
    <link rel="stylesheet" href="css/tailwind.min.css?v=9">
    <link rel="stylesheet" href="css/style.css?v=9">
    <link rel="stylesheet" href="css/admin-theme.css?v=9">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/includes/theme-boot.php'; ?>
    <style>
        body { font-family: Poppins, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(180deg, #fffef5, #faf9f6); padding: 24px; text-align: center; }
        h1 { font-family: 'Playfair Display', serif; font-size: 2rem; margin: 12px 0; }
        .card { max-width: 420px; background: #fff; padding: 32px 24px; border-radius: 20px; border: 1px solid rgba(212,175,55,.25); }
        a.btn { display: inline-block; margin-top: 20px; padding: 12px 28px; background: linear-gradient(135deg,#d4af37,#b8860b);
            color: #fff; text-decoration: none; border-radius: 999px; font-weight: 600; font-size: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <img src="images/logo.png" alt="ChandraKala Jewellers" width="80" height="80">
        <h1>ChandraKala Jewellers</h1>
        <p>Gold &amp; silver jewellery — Khedbrahma, Gujarat</p>
        <a class="btn" href="index1">Enter Showroom</a>
    </div>
</body>
</html>
