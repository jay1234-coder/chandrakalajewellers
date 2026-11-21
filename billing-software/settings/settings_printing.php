<?php
include '../includes/db.php';

$message = '';

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $paper_size = $_POST['paper_size'];
    $show_logo = isset($_POST['show_logo']) ? 1 : 0;
    $show_gst = isset($_POST['show_gst']) ? 1 : 0;
    $show_qr = isset($_POST['show_qr']) ? 1 : 0;
    $show_signature = isset($_POST['show_signature']) ? 1 : 0;
    $default_print_mode = $_POST['default_print_mode'];

    $stmt = $conn->prepare("
        UPDATE settings_printing
        SET
            paper_size=?,
            show_logo=?,
            show_gst=?,
            show_qr=?,
            show_signature=?,
            default_print_mode=?
        WHERE id=1
    ");

    $stmt->bind_param(
        "siiiis",
        $paper_size,
        $show_logo,
        $show_gst,
        $show_qr,
        $show_signature,
        $default_print_mode
    );

    $stmt->execute();

    $message = "Printing settings saved.";
}

$result = $conn->query("
    SELECT *
    FROM settings_printing
    WHERE id=1
");

$data = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Printing Settings</title>

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

.section-title{
    font-size:18px;
    font-weight:600;
    margin-bottom:15px;
    color:#1e293b;
}

.form-group{
    margin-bottom:18px;
}

label{
    display:block;
    margin-bottom:8px;
    font-weight:600;
}

select{
    width:100%;
    padding:10px;
    border:1px solid #ddd;
    border-radius:8px;
}

.checkbox{
    margin-bottom:12px;
}

.checkbox label{
    display:flex;
    gap:10px;
    align-items:center;
    cursor:pointer;
}

.save-btn{
    background:#16a34a;
    color:white;
    border:none;
    padding:12px 25px;
    border-radius:8px;
    font-weight:600;
    cursor:pointer;
}

.success{
    background:#dcfce7;
    color:#166534;
    padding:12px;
    border-radius:8px;
    margin-bottom:20px;
}

.preview-box{
    margin-top:20px;
    border:2px dashed #cbd5e1;
    border-radius:10px;
    padding:15px;
    background:#fafafa;
}

.preview-a5{
    width:210px;
    height:300px;
    border:1px solid #333;
    margin:auto;
    background:white;
}

.preview-a4{
    width:250px;
    height:350px;
    border:1px solid #333;
    margin:auto;
    background:white;
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
<i class="fas fa-print"></i>
Printing Settings
</div>

<div class="card">

<?php if($message): ?>
<div class="success">
<?= $message ?>
</div>
<?php endif; ?>

<form method="POST">

<div class="section-title">
Paper Settings
</div>

<div class="form-group">

<label>Paper Size</label>

<select name="paper_size">

<option value="A5"
<?= ($data['paper_size']=='A5') ? 'selected' : '' ?>>
A5 Bill
</option>

<option value="A4"
<?= ($data['paper_size']=='A4') ? 'selected' : '' ?>>
A4 Bill
</option>

</select>

</div>

<div class="section-title">
Bill Components
</div>

<div class="checkbox">
<label>
<input
type="checkbox"
name="show_logo"
<?= ($data['show_logo']) ? 'checked' : '' ?>>
Show Logo
</label>
</div>

<div class="checkbox">
<label>
<input
type="checkbox"
name="show_gst"
<?= ($data['show_gst']) ? 'checked' : '' ?>>
Show GST Number
</label>
</div>

<div class="checkbox">
<label>
<input
type="checkbox"
name="show_qr"
<?= ($data['show_qr']) ? 'checked' : '' ?>>
Show QR Code
</label>
</div>

<div class="checkbox">
<label>
<input
type="checkbox"
name="show_signature"
<?= ($data['show_signature']) ? 'checked' : '' ?>>
Show Signature
</label>
</div>

<div class="section-title">
Print Behaviour
</div>

<div class="form-group">

<label>Default Print Mode</label>

<select name="default_print_mode">

<option value="preview"
<?= ($data['default_print_mode']=='preview') ? 'selected' : '' ?>>
Preview Before Print
</option>

<option value="direct"
<?= ($data['default_print_mode']=='direct') ? 'selected' : '' ?>>
Direct Print
</option>

</select>

</div>

<button type="submit" class="save-btn">
<i class="fas fa-save"></i>
 Save Printing Settings
</button>

</form>

<div class="preview-box">

<h4>Current Format Preview</h4>

<?php if($data['paper_size']=='A5'){ ?>

<div class="preview-a5"></div>

<?php } else { ?>

<div class="preview-a4"></div>

<?php } ?>

</div>

</div>

</main>

</div>

</body>
</html>