<?php
include '../includes/db.php';

$software_version = "1.0.0";

$total_bills = 0;
$total_customers = 0;

$last_backup = "Never";

$res = $conn->query("
    SELECT COUNT(*) total
    FROM bills
");

if($res){
    $total_bills =
        $res->fetch_assoc()['total'];
}

$res = $conn->query("
    SELECT COUNT(*) total
    FROM customers
");

if($res){
    $total_customers =
        $res->fetch_assoc()['total'];
}

$res = $conn->query("
    SELECT last_backup
    FROM settings_backup
    WHERE id=1
");

if($res && $res->num_rows){

    $backup =
        $res->fetch_assoc();

    if(!empty($backup['last_backup'])){

        $last_backup =
            date(
                'd-M-Y h:i A',
                strtotime(
                    $backup['last_backup']
                )
            );
    }
}

$db_size = "Unknown";

$dbname = $conn->query("SELECT DATABASE() db");
$dbname = $dbname->fetch_assoc()['db'];

$size_query = $conn->query("
SELECT
ROUND(
SUM(data_length + index_length)
/ 1024 / 1024,
2
) size_mb
FROM information_schema.tables
WHERE table_schema = '$dbname'
");

if($size_query){

    $db_size =
        $size_query
        ->fetch_assoc()['size_mb']
        . " MB";
}
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Privacy Settings</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

body{
    margin:0;
    background:#f5f6fa;
    font-family:'Segoe UI';
}

.container{
    display:flex;
}

.main{
    flex:1;
    padding:25px;
}

.page-title{
    font-size:26px;
    font-weight:700;
    margin-bottom:20px;
}

.card{
    background:white;
    padding:25px;
    border-radius:12px;
    box-shadow:0 2px 8px rgba(0,0,0,.05);
}

.stats-grid{
    display:grid;
    grid-template-columns:
    repeat(auto-fit,minmax(250px,1fr));
    gap:15px;
    margin-bottom:25px;
}

.stat-card{
    background:#f8fafc;
    padding:18px;
    border-radius:10px;
    border-left:4px solid #0891b2;
}

.stat-title{
    color:#64748b;
    margin-bottom:8px;
    font-size:13px;
}

.stat-value{
    font-size:22px;
    font-weight:700;
    color:#1e293b;
}

.privacy-box{
    background:#eff6ff;
    border-left:5px solid #2563eb;
    padding:20px;
    border-radius:10px;
}

.privacy-box h3{
    margin-top:0;
}

.privacy-box ul{
    margin:0;
    padding-left:20px;
}

.privacy-box li{
    margin-bottom:10px;
}

.version-box{
    margin-top:25px;
    background:#f8fafc;
    padding:15px;
    border-radius:10px;
}
</style>

</head>

<body>

<div class="container">

<?php
$active_page='settings';
include '../includes/sidebar.php';
?>

<main class="main">

<div class="page-title">
<i class="fas fa-shield-alt"></i>
Privacy & System Information
</div>

<div class="card">

<div class="stats-grid">

<div class="stat-card">

<div class="stat-title">
Software Version
</div>

<div class="stat-value">
<?= $software_version ?>
</div>

</div>

<div class="stat-card">

<div class="stat-title">
Database Size
</div>

<div class="stat-value">
<?= $db_size ?>
</div>

</div>

<div class="stat-card">

<div class="stat-title">
Total Bills
</div>

<div class="stat-value">
<?= number_format($total_bills) ?>
</div>

</div>

<div class="stat-card">

<div class="stat-title">
Total Customers
</div>

<div class="stat-value">
<?= number_format($total_customers) ?>
</div>

</div>

<div class="stat-card">

<div class="stat-title">
Last Backup
</div>

<div class="stat-value" style="font-size:16px;">
<?= $last_backup ?>
</div>

</div>

</div>

<div class="privacy-box">

<h3>
<i class="fas fa-lock"></i>
 Privacy Statement
</h3>

<ul>

<li>
All records are stored locally
inside your system database.
</li>

<li>
No cloud storage is used.
</li>

<li>
No customer data is shared
with third parties.
</li>

<li>
Software works completely offline.
</li>

<li>
Internet connection is not required
for billing operations.
</li>

<li>
Backup files remain under your control.
</li>

<li>
Data ownership remains exclusively
with Chandrakala Jewellers.
</li>

</ul>

</div>

<div class="version-box">

<b>System Information</b>

<hr>

Database Engine:
MySQL

<br><br>

Application:
Bill Management Software

<br><br>

Environment:
XAMPP + PHP + MySQL

</div>

</div>

</main>

</div>

</body>
</html>