<?php
date_default_timezone_set('Asia/Kolkata');

// Determine if greeting timeframe is Morning, Afternoon, or Evening
$hour = (int)date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 17) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome - Chandrakala Jewellers</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    /* Figma-Style Luxury Dark System Architecture */
    :root {
        --bg-luxury: #0b0c10;
        --accent-burgundy: #960018;
        --metallic-gold: #d4af37;
        --text-bright: #f8fafc;
        --text-muted: #94a3b8;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        background-color: var(--bg-luxury);
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        height: 100vh;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        perspective: 1000px;
    }

    /* Ambient Glow Background Canvas */
    .splash-container {
        position: relative;
        width: 100%;
        max-width: 480px;
        text-align: center;
        padding: 40px;
        z-index: 10;
    }

    .ambient-glow {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 600px;
        height: 600px;
        background: radial-gradient(circle, rgba(150, 0, 24, 0.12) 0%, transparent 70%);
        z-index: 1;
        pointer-events: none;
    }

    /* Cinematic Motion Graphics Stages */
    .brand-logo-wrapper {
        position: relative;
        width: 110px;
        height: 110px;
        margin: 0 auto 30px auto;
        opacity: 0;
        transform: scale(0.3) rotate(-15deg);
        animation: scaleUpReveal 1.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }

    .brand-logo-wrapper::after {
        content: '';
        position: absolute;
        inset: -8px;
        border-radius: 50%;
        border: 2px dashed rgba(212, 175, 55, 0.25);
        animation: subtleRotate 12s linear infinite;
    }

    .logo-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: #ffffff;
        border-radius: 50%;
        padding: 12px;
        border: 2px solid var(--metallic-gold);
        box-shadow: 0 10px 30px rgba(150, 0, 24, 0.3), 0 0 20px rgba(212, 175, 55, 0.1);
    }

    .welcome-card {
        opacity: 0;
        transform: translateY(25px);
        animation: slideUpFade 1s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        animation-delay: 0.5s;
    }

    .greeting-text {
        color: var(--metallic-gold);
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 3px;
        text-transform: uppercase;
        margin-bottom: 8px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.5);
    }

    .app-title {
        color: var(--text-bright);
        font-size: 32px;
        font-weight: 800;
        letter-spacing: -0.5px;
        margin-bottom: 12px;
        background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .user-card {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.06);
        padding: 8px 18px;
        border-radius: 30px;
        color: var(--text-muted);
        font-size: 14px;
        font-weight: 500;
        backdrop-filter: blur(10px);
        margin-bottom: 40px;
    }

    .user-card i {
        color: var(--metallic-gold);
        font-size: 12px;
    }
    
    .user-name-highlight {
        color: var(--text-bright);
        font-weight: 600;
    }

    /* Minimalist Progress Loader Engine */
    .loader-track {
        width: 180px;
        height: 3px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        margin: 0 auto;
        position: relative;
        overflow: hidden;
        opacity: 0;
        animation: fadeIn 0.5s ease forwards;
        animation-delay: 0.8s;
    }

    .loader-bar {
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        width: 0;
        background: linear-gradient(90deg, var(--accent-burgundy), var(--metallic-gold));
        border-radius: 10px;
        box-shadow: 0 0 10px var(--metallic-gold);
        animation: loadWorkspace 2.2s cubic-bezier(0.65, 0.05, 0.36, 1) forwards;
    }

    /* Keyframe Choreography Timeline */
    @keyframes scaleUpReveal {
        0% { opacity: 0; transform: scale(0.3) rotate(-15deg); }
        100% { opacity: 1; transform: scale(1) rotate(0deg); }
    }

    @keyframes slideUpFade {
        0% { opacity: 0; transform: translateY(25px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeIn {
        to { opacity: 1; }
    }

    @keyframes subtleRotate {
        to { transform: rotate(360deg); }
    }

    @keyframes loadWorkspace {
        0% { width: 0%; }
        40% { width: 55%; }
        70% { width: 85%; }
        100% { width: 100%; }
    }
</style>
</head>
<body>

<div class="ambient-glow"></div>

<div class="splash-container">
    <!-- Brand Logo Element utilizing assets pathway map -->
    <div class="brand-logo-wrapper">
        <img src="assets/logo.png" alt="Chandrakala Logo" class="logo-img">
    </div>

    <!-- Welcome Typography and Personalized Greetings Configuration Card -->
    <div class="welcome-card">
        <div class="greeting-text"><?= $greeting; ?></div>
        <h1 class="app-title">Chandrakala Jewellers</h1>
        
        <div class="user-card">
            <i class="fas fa-circle-user"></i>
            <span>Welcome, <span class="user-name-highlight">Mr. Hasmukh Soni</span></span>
        </div>
    </div>

    <!-- System Processing Progress Bar Container -->
    <div class="loader-track">
        <div class="loader-bar" id="progressBar"></div>
    </div>
</div>

<script>
    // System workspace loader dashboard routing handler
    document.getElementById('progressBar').addEventListener('animationend', function() {
        window.location.href = 'dashboard.php';
    });
</script>

</body>
</html>