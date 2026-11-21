<?php
include '../includes/db.php';

$message = '';

$total_bills = 0;
$total_customers = 0;
$total_products = 0;

$res = $conn->query("SELECT COUNT(*) total FROM bills");
if($res){
    $total_bills = $res->fetch_assoc()['total'];
}

$res = $conn->query("SELECT COUNT(*) total FROM customers");
if($res){
    $total_customers = $res->fetch_assoc()['total'];
}

$res = $conn->query("SELECT COUNT(*) total FROM products");
if($res){
    $total_products = $res->fetch_assoc()['total'];
}

if(isset($_POST['optimize_db'])){

    $tables = [];

    $result = $conn->query("SHOW TABLES");

    while($row = $result->fetch_array()){
        $tables[] = $row[0];
    }

    foreach($tables as $table){
        $conn->query("OPTIMIZE TABLE `$table`");
    }

    $message = "Database optimized successfully.";
}
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Data Management</title>

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

.section{
    margin-bottom:30px;
}

.section-title{
    font-size:18px;
    font-weight:600;
    margin-bottom:15px;
}

.stats-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:15px;
}

.stat-card{
    background:#f8fafc;
    padding:18px;
    border-radius:10px;
    border-left:4px solid #2563eb;
}

.stat-number{
    font-size:24px;
    font-weight:700;
    color:#1e293b;
}

.stat-label{
    color:#64748b;
    margin-top:5px;
}

.btn{
    display:inline-block;
    padding:12px 20px;
    color:white;
    text-decoration:none;
    border:none;
    border-radius:8px;
    font-weight:600;
    cursor:pointer;
    margin-right:10px;
    margin-bottom:10px;
}

.excel-btn{
    background:#16a34a;
}

.pdf-btn{
    background:#dc2626;
}

.optimize-btn{
    background:#2563eb;
}

.success{
    background:#dcfce7;
    color:#166534;
    padding:12px;
    border-radius:8px;
    margin-bottom:20px;
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
<i class="fas fa-file-export"></i>
Data Management
</div>

<div class="card">

<?php if($message){ ?>
<div class="success">
<?= $message ?>
</div>
<?php } ?>

<div class="section">

<div class="section-title">
Database Statistics
</div>

<div class="stats-grid">

<div class="stat-card">
<div class="stat-number">
<?= $total_bills ?>
</div>
<div class="stat-label">
Total Bills
</div>
</div>

<div class="stat-card">
<div class="stat-number">
<?= $total_customers ?>
</div>
<div class="stat-label">
Total Customers
</div>
</div>

<div class="stat-card">
<div class="stat-number">
<?= $total_products ?>
</div>
<div class="stat-label">
Total Products
</div>
</div>

</div>

</div>

<div class="section">

<div class="section-title">
Export Bills
</div>

<a
href="export_bills_excel.php"
class="btn excel-btn">

<i class="fas fa-file-excel"></i>
 Export Bills Excel

</a>

<a
href="export_bills_pdf.php"
class="btn pdf-btn">

<i class="fas fa-file-pdf"></i>
 Export Bills PDF

</a>

</div>

<div class="section">

<div class="section-title">
Export Customers
</div>

<a
href="export_customers_excel.php"
class="btn excel-btn">

<i class="fas fa-file-excel"></i>
 Export Customers Excel

</a>

<a
href="export_customers_pdf.php"
class="btn pdf-btn">

<i class="fas fa-file-pdf"></i>
 Export Customers PDF

</a>

</div>

<div class="section">

<div class="section-title">
Database Maintenance
</div>

<form method="POST">

<button
type="submit"
name="optimize_db"
class="btn optimize-btn">

<i class="fas fa-database"></i>
 Optimize Database

</button>

</form>

</div>

</div>

</main>

</div>

</body>
</html>