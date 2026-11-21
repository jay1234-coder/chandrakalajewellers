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
body{
    margin:0;
    background:#f5f6fa;
    font-family:'Segoe UI',sans-serif;
}

.container{
    display:flex;
}

.main{
    flex:1;
    padding:25px;
}

.page-title{
    font-size:28px;
    font-weight:700;
    color:#1e293b;
    margin-bottom:25px;
}

.settings-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
    gap:20px;
}

.setting-card{
    background:#fff;
    border-radius:12px;
    padding:25px;
    text-decoration:none;
    transition:.25s;
    box-shadow:0 2px 8px rgba(0,0,0,.05);
    border-left:5px solid #2563eb;
}

.setting-card:hover{
    transform:translateY(-4px);
    box-shadow:0 8px 20px rgba(0,0,0,.08);
}

.setting-icon{
    width:60px;
    height:60px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:24px;
    margin-bottom:15px;
    color:#fff;
}

.business{background:#2563eb;}
.printing{background:#16a34a;}
.backup{background:#ea580c;}
.data{background:#7c3aed;}
.notification{background:#dc2626;}
.privacy{background:#0891b2;}

.setting-title{
    font-size:18px;
    font-weight:700;
    color:#1e293b;
    margin-bottom:8px;
}

.setting-desc{
    font-size:14px;
    color:#64748b;
    line-height:1.5;
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