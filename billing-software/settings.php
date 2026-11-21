<?php
include 'includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings - Chandrakala Jewellers</title>

<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
.page-title {
    font-size: 26px;
    font-weight: 700;
    color: var(--cj-text-main);
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.page-title i {
    color: var(--cj-gold);
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 22px;
}

.setting-card {
    background: #fff;
    border-radius: var(--cj-radius);
    padding: 26px;
    text-decoration: none;
    transition: all 0.25s ease;
    box-shadow: var(--cj-shadow);
    border: 1px solid var(--cj-border);
    border-top: 3px solid var(--cj-gold);
    display: flex;
    flex-direction: column;
}

.setting-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--cj-shadow-lg);
    border-color: var(--cj-gold-border);
}

.setting-icon {
    width: 54px;
    height: 54px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    margin-bottom: 16px;
    color: #fff;
}

.business { background: linear-gradient(135deg, #b45309 0%, #d97706 100%); box-shadow: 0 4px 12px rgba(180, 83, 9, 0.25); }
.printing { background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); box-shadow: 0 4px 12px rgba(15, 118, 110, 0.25); }
.backup { background: linear-gradient(135deg, #c2410c 0%, #f97316 100%); box-shadow: 0 4px 12px rgba(194, 65, 12, 0.25); }
.data { background: linear-gradient(135deg, #6d28d9 0%, #8b5cf6 100%); box-shadow: 0 4px 12px rgba(109, 40, 217, 0.25); }
.notification { background: linear-gradient(135deg, #960018 0%, #dc2626 100%); box-shadow: 0 4px 12px rgba(150, 0, 24, 0.25); }
.privacy { background: linear-gradient(135deg, #0369a1 0%, #0284c7 100%); box-shadow: 0 4px 12px rgba(3, 105, 161, 0.25); }

.setting-title {
    font-size: 17px;
    font-weight: 700;
    color: var(--cj-text-main);
    margin-bottom: 8px;
    font-family: 'Playfair Display', Georgia, serif;
}

.setting-desc {
    font-size: 13px;
    color: var(--cj-text-muted);
    line-height: 1.5;
}
</style>
</head>
<body>

<div class="container">

<?php
$active_page = 'settings';
include 'includes/sidebar.php';
?>

<main class="main">

<div class="page-title">
    <i class="fas fa-cog"></i>
    Settings Center
</div>

<div class="settings-grid">

    <a href="settings/settings_business.php" class="setting-card">
        <div class="setting-icon business">
            <i class="fas fa-store"></i>
        </div>

        <div class="setting-title">
            Business Settings
        </div>

        <div class="setting-desc">
            Shop information, GST details, logo,
            address and invoice footer settings.
        </div>
    </a>

    <a href="settings/settings_printing.php" class="setting-card">
        <div class="setting-icon printing">
            <i class="fas fa-print"></i>
        </div>

        <div class="setting-title">
            Printing Settings
        </div>

        <div class="setting-desc">
            A4 / A5 bill printing,
            QR visibility and print preferences.
        </div>
    </a>

    <a href="settings/settings_backup.php" class="setting-card">
        <div class="setting-icon backup">
            <i class="fas fa-database"></i>
        </div>

        <div class="setting-title">
            Backup & Restore
        </div>

        <div class="setting-desc">
            Create database backups and restore
            previous backups when needed.
        </div>
    </a>

    <a href="settings/settings_data.php" class="setting-card">
        <div class="setting-icon data">
            <i class="fas fa-file-export"></i>
        </div>

        <div class="setting-title">
            Data Management
        </div>

        <div class="setting-desc">
            Export bills, customers and
            optimize database records.
        </div>
    </a>

    <a href="settings/settings_notifications.php" class="setting-card">
        <div class="setting-icon notification">
            <i class="fas fa-bell"></i>
        </div>

        <div class="setting-title">
            Notification Settings
        </div>

        <div class="setting-desc">
            Backup reminders and pending
            payment alerts.
        </div>
    </a>

    <a href="settings/settings_privacy.php" class="setting-card">
        <div class="setting-icon privacy">
            <i class="fas fa-shield-alt"></i>
        </div>

        <div class="setting-title">
            Privacy Settings
        </div>

        <div class="setting-desc">
            Local storage information,
            database size and privacy details.
        </div>
    </a>

</div>

</main>

</div>

</body>
</html>