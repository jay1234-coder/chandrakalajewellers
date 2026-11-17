<?php
/**
 * Site root splash for humans; bots go straight to the real homepage (index1).
 */
require_once __DIR__ . '/includes/security.php';

$target = 'index1';
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}

$ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
$isBot = (bool) preg_match(
    '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|twitterbot|linkedinbot|whatsapp|telegrambot|discordbot|googleother|gptbot|claudebot|bytespider|semrush|ahrefs|yandex|duckduck/i',
    $ua
);

if ($isBot) {
    header('Location: https://chandrakalajewellers.in/index1' . (!empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : ''), true, 301);
    exit;
}

$canonical = 'https://chandrakalajewellers.in/index1';
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="UTF-8">
    <?php
    $seoConfig = require __DIR__ . '/config/seo-config.php';
    if (!empty($seoConfig['google_site_verification'])) {
        echo '<meta name="google-site-verification" content="' . htmlspecialchars($seoConfig['google_site_verification'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
    if (!empty($seoConfig['bing_site_verification'])) {
        echo '<meta name="msvalidate.01" content="' . htmlspecialchars($seoConfig['bing_site_verification'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
    if (!empty($seoConfig['yandex_verification'])) {
        echo '<meta name="yandex-verification" content="' . htmlspecialchars($seoConfig['yandex_verification'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
    ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="refresh" content="3;url=<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="noindex, follow">
    <meta name="theme-color" content="#b8972c">
    <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="manifest" href="manifest.webmanifest">
    <title>ChandraKala Jewellers — Welcome</title>
    <meta name="description" content="Welcome to ChandraKala Jewellers — gold, silver &amp; 925 jewellery in Khedbrahma, Gujarat.">
    <?php include __DIR__ . '/includes/gtm-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #d4af37;
            --gold-dark: #b8972c;
            --ink: #1f2937;
            --cream: #fffef8;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Poppins, system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(ellipse 80% 50% at 50% -10%, rgba(212,175,55,.22), transparent 55%),
                linear-gradient(180deg, #fffef5 0%, #faf9f6 55%, #f3efe4 100%);
            padding: 24px;
            text-align: center;
        }
        .splash {
            width: min(420px, 100%);
            animation: rise .7s cubic-bezier(.16,1,.3,1) both;
        }
        .logo-wrap {
            width: 96px;
            height: 96px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #fff;
            border: 1px solid rgba(212,175,55,.35);
            box-shadow: 0 12px 40px rgba(184,151,44,.18);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .logo-wrap img { width: 72px; height: 72px; object-fit: contain; }
        h1 {
            font-family: "Playfair Display", Georgia, serif;
            font-size: clamp(1.6rem, 5vw, 2.1rem);
            font-weight: 700;
            margin: 0 0 8px;
            letter-spacing: .01em;
        }
        .tag {
            display: inline-block;
            font-size: 10px;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: var(--gold-dark);
            font-weight: 600;
            margin-bottom: 12px;
        }
        p {
            margin: 0 auto 22px;
            max-width: 28ch;
            font-size: 14px;
            line-height: 1.55;
            color: #4b5563;
        }
        .btn {
            display: inline-block;
            padding: 13px 28px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 10px 24px rgba(184,151,44,.28);
        }
        .bar {
            margin: 28px auto 0;
            width: 140px;
            height: 3px;
            border-radius: 999px;
            background: rgba(212,175,55,.2);
            overflow: hidden;
        }
        .bar > span {
            display: block;
            height: 100%;
            width: 40%;
            background: linear-gradient(90deg, var(--gold), var(--gold-dark));
            animation: load 2.6s ease forwards;
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: none; }
        }
        @keyframes load {
            from { transform: translateX(-100%); }
            to { transform: translateX(260%); }
        }
        @media (prefers-reduced-motion: reduce) {
            .splash, .bar > span { animation: none; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/gtm-body.php'; ?>
<div class="splash">
    <div class="logo-wrap">
        <img src="images/logo.png" alt="ChandraKala Jewellers" width="72" height="72">
    </div>
    <span class="tag">Khedbrahma · Gujarat</span>
    <h1>ChandraKala Jewellers</h1>
    <p>Gold, silver &amp; 925 jewellery — entering the showroom</p>
    <a class="btn" href="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>">Enter Showroom</a>
    <div class="bar" aria-hidden="true"><span></span></div>
</div>
<script>
(function () {
  var t = <?= json_encode($target) ?>;
  setTimeout(function () { window.location.replace(t); }, 2800);
})();
</script>
</body>
</html>
